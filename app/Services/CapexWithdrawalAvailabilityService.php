<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CapexWithdrawalAvailabilityService
{
    /**
     * @return array<int, float>
     */
    public function withdrawnQuantitiesByReceivingReportItem(array $receivingReportItemIds, ?int $excludeStoreWithdrawalId = null): array
    {
        if (empty($receivingReportItemIds)) {
            return [];
        }

        return DB::table('store_withdrawal_items as swi')
            ->join('store_withdrawals as sw', 'sw.id', '=', 'swi.store_withdrawal_id')
            ->whereIn('swi.receiving_report_item_id', $receivingReportItemIds)
            ->where('sw.type', 'capex')
            ->whereNull('swi.deleted_at')
            ->whereNull('sw.deleted_at')
            ->when($excludeStoreWithdrawalId !== null, function ($query) use ($excludeStoreWithdrawalId) {
                $query->where('sw.id', '!=', $excludeStoreWithdrawalId);
            })
            ->selectRaw('swi.receiving_report_item_id, SUM(swi.quantity) as withdrawn_qty')
            ->groupBy('swi.receiving_report_item_id')
            ->pluck('withdrawn_qty', 'swi.receiving_report_item_id')
            ->map(fn ($qty) => round((float) $qty, 5))
            ->all();
    }

    public function paginateAvailableLines(int $departmentId, string $search = '', int $page = 1, int $perPage = 36): LengthAwarePaginator
    {
        $page = max(1, $page);
        $search = trim($search);
        $searchLike = '%'.mb_strtolower($search).'%';

        $withdrawnSubquery = DB::table('store_withdrawal_items as swi')
            ->join('store_withdrawals as sw', 'sw.id', '=', 'swi.store_withdrawal_id')
            ->where('sw.type', 'capex')
            ->whereNull('swi.deleted_at')
            ->whereNull('sw.deleted_at')
            ->whereNotNull('swi.receiving_report_item_id')
            ->selectRaw('swi.receiving_report_item_id, SUM(swi.quantity) as withdrawn_qty')
            ->groupBy('swi.receiving_report_item_id');

        $baseQuery = DB::table('receiving_report_items as rri')
            ->join('receiving_reports as rr', 'rr.id', '=', 'rri.receiving_report_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->join('prs_items as pi', 'pi.id', '=', 'poi.prs_item_id')
            ->join('prs', 'prs.id', '=', 'pi.prs_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->leftJoinSub($withdrawnSubquery, 'withdrawn', function ($join) {
                $join->on('withdrawn.receiving_report_item_id', '=', 'rri.id');
            })
            ->whereNull('rri.deleted_at')
            ->whereNull('rr.deleted_at')
            ->whereNull('po.deleted_at')
            ->whereNull('pi.deleted_at')
            ->whereNull('prs.deleted_at')
            ->where('prs.is_capex', true)
            ->where('prs.department_id', $departmentId)
            ->whereRaw('(rri.qty_good - COALESCE(withdrawn.withdrawn_qty, 0)) > 0')
            ->when($search !== '', function ($query) use ($searchLike) {
                $query->where(function ($whereQuery) use ($searchLike) {
                    $whereQuery
                        ->whereRaw('LOWER(prs.prs_number) LIKE ?', [$searchLike])
                        ->orWhereRaw('LOWER(po.po_number) LIKE ?', [$searchLike])
                        ->orWhereRaw('LOWER(rr.rr_number) LIKE ?', [$searchLike])
                        ->orWhereRaw('LOWER(i.code) LIKE ?', [$searchLike])
                        ->orWhereRaw('LOWER(i.name) LIKE ?', [$searchLike]);
                });
            })
            ->select([
                'rri.id as receiving_report_item_id',
                'rri.qty_good',
                'rr.id as receiving_report_id',
                'rr.rr_number',
                'po.id as purchase_order_id',
                'po.po_number',
                'prs.id as prs_id',
                'prs.prs_number',
                'pi.id as prs_item_id',
                'poi.id as purchase_order_item_id',
                'i.id as item_id',
                'i.code as item_code',
                'i.name as item_name',
                'u.name as unit_name',
            ])
            ->selectRaw('COALESCE(withdrawn.withdrawn_qty, 0) as withdrawn_qty')
            ->selectRaw('(rri.qty_good - COALESCE(withdrawn.withdrawn_qty, 0)) as qty_remaining')
            ->orderByDesc('rr.received_date')
            ->orderByDesc('rri.id');

        $total = (clone $baseQuery)->count();
        $rows = (clone $baseQuery)
            ->forPage($page, $perPage)
            ->get();

        return new LengthAwarePaginator(
            items: $this->transformAvailableLines($rows),
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    /**
     * @param  array<int, array{receiving_report_item_id: int, quantity: float}>  $requestedLines
     * @return array{valid: bool, message: string}
     */
    public function validateRequestedLines(array $requestedLines, ?int $excludeStoreWithdrawalId = null): array
    {
        if (empty($requestedLines)) {
            return [
                'valid' => false,
                'message' => 'Add at least one valid CAPEX item before submitting.',
            ];
        }

        $receivingReportItemIds = collect($requestedLines)
            ->pluck('receiving_report_item_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (count($receivingReportItemIds) !== count($requestedLines)) {
            return [
                'valid' => false,
                'message' => 'Each CAPEX line must reference a valid receiving report item.',
            ];
        }

        $lines = $this->loadLinesByReceivingReportItemIds($receivingReportItemIds);
        if ($lines->count() !== count($receivingReportItemIds)) {
            return [
                'valid' => false,
                'message' => 'Some selected CAPEX lines are no longer available.',
            ];
        }

        $withdrawnMap = $this->withdrawnQuantitiesByReceivingReportItem($receivingReportItemIds, $excludeStoreWithdrawalId);
        $departmentId = (int) $lines->first()->department_id;

        foreach ($requestedLines as $row) {
            $receivingReportItemId = (int) $row['receiving_report_item_id'];
            $quantity = round((float) $row['quantity'], 5);
            $line = $lines->get($receivingReportItemId);

            if (! $line) {
                return [
                    'valid' => false,
                    'message' => 'Some selected CAPEX lines are no longer available.',
                ];
            }

            if ((int) $line->department_id !== $departmentId) {
                return [
                    'valid' => false,
                    'message' => 'All CAPEX lines must belong to the same charged department.',
                ];
            }

            if (! (bool) $line->is_capex) {
                return [
                    'valid' => false,
                    'message' => 'Only CAPEX PRS lines can be withdrawn in CAPEX mode.',
                ];
            }

            $qtyGood = round((float) $line->qty_good, 5);
            $withdrawn = round((float) ($withdrawnMap[$receivingReportItemId] ?? 0), 5);
            $remaining = max(0, round($qtyGood - $withdrawn, 5));

            if ($quantity <= 0) {
                return [
                    'valid' => false,
                    'message' => 'CAPEX withdraw quantity must be greater than zero.',
                ];
            }

            if ($quantity > $remaining) {
                return [
                    'valid' => false,
                    'message' => "Quantity for {$line->item_code} exceeds remaining RR balance ({$remaining}).",
                ];
            }
        }

        return [
            'valid' => true,
            'message' => '',
        ];
    }

    /**
     * @param  array<int, int>  $receivingReportItemIds
     */
    public function loadLinesByReceivingReportItemIds(array $receivingReportItemIds): Collection
    {
        if (empty($receivingReportItemIds)) {
            return collect();
        }

        return DB::table('receiving_report_items as rri')
            ->join('receiving_reports as rr', 'rr.id', '=', 'rri.receiving_report_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->join('prs_items as pi', 'pi.id', '=', 'poi.prs_item_id')
            ->join('prs', 'prs.id', '=', 'pi.prs_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->whereIn('rri.id', $receivingReportItemIds)
            ->whereNull('rri.deleted_at')
            ->whereNull('rr.deleted_at')
            ->whereNull('po.deleted_at')
            ->whereNull('pi.deleted_at')
            ->whereNull('prs.deleted_at')
            ->select([
                'rri.id as receiving_report_item_id',
                'rri.qty_good',
                'rr.rr_number',
                'po.po_number',
                'prs.prs_number',
                'prs.department_id',
                'prs.is_capex',
                'pi.id as prs_item_id',
                'poi.id as purchase_order_item_id',
                'i.id as item_id',
                'i.code as item_code',
                'i.name as item_name',
                'u.name as unit_name',
            ])
            ->get()
            ->keyBy('receiving_report_item_id');
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function transformAvailableLines(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            $qtyGood = round((float) $row->qty_good, 5);
            $withdrawn = round((float) $row->withdrawn_qty, 5);
            $remaining = round((float) $row->qty_remaining, 5);

            return [
                'receiving_report_item_id' => (int) $row->receiving_report_item_id,
                'item_id' => (int) $row->item_id,
                'name' => (string) $row->item_name,
                'code' => (string) $row->item_code,
                'unit' => (string) ($row->unit_name ?? 'PCS'),
                'prs_number' => (string) $row->prs_number,
                'po_number' => (string) $row->po_number,
                'rr_number' => (string) $row->rr_number,
                'qty_received' => $qtyGood,
                'qty_withdrawn' => $withdrawn,
                'qty_remaining' => $remaining,
                'stock_on_hand' => $remaining,
                'is_capex' => true,
            ];
        })->values();
    }
}
