<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PrsCanvassingItemSeeder extends Seeder
{
    use ResolvesLegacyImport;

    public function run(): void
    {
        $legacyRows = $this->resolveRows('assign_canv_prc', fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && !empty($legacyRows)) {
            $this->seedRows($legacyRows, 'legacy');
            return;
        }

        $this->seedFromCsv();
    }

    protected function seedRows(array $rows, string $source): void
    {
        $this->logImportSource('assign_canv_prc', $source);
        $this->command?->info('ℹ [assign_canv_prc] rows loaded: ' . count($rows));

        // Build lookup: prs_number -> prs_id
        $prsIdByNumber = DB::table('prs')->pluck('id', 'prs_number')->all();

        // Build lookup: item code -> item_id
        $itemIdByCode = DB::table('items')->pluck('id', 'code')->all();

        // Build lookup: (prs_id:item_id) -> prs_item_id
        $prsItemLookup = [];
        DB::table('prs_items')->select('id', 'prs_id', 'item_id')->get()
            ->each(function ($row) use (&$prsItemLookup) {
                $prsItemLookup["{$row->prs_id}:{$row->item_id}"] = $row->id;
            });

        // Build lookup: supplier code -> supplier_id
        $supplierIdByCode = DB::table('suppliers')->pluck('id', 'code')->all();

        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $prsNumber  = trim((string) ($row['prsnumber'] ?? ''));
            $productCode = trim((string) ($row['productcode'] ?? ''));
            $supplierCode = trim((string) ($row['supplier_code'] ?? ''));

            if ($prsNumber === '' || $productCode === '' || $supplierCode === '') {
                $this->warn("assign_canv_prc skipped: missing prsnumber, productcode, or supplier_code");
                $skipped++;
                continue;
            }

            $prsId = $prsIdByNumber[$prsNumber] ?? null;
            if ($prsId === null) {
                $this->warn("assign_canv_prc skipped: prsnumber '{$prsNumber}' not found in prs table");
                $skipped++;
                continue;
            }

            $itemId = $itemIdByCode[$productCode] ?? null;
            if ($itemId === null) {
                $this->warn("assign_canv_prc skipped: productcode '{$productCode}' not found in items table (prsnumber: {$prsNumber})");
                $skipped++;
                continue;
            }

            $prsItemId = $prsItemLookup["{$prsId}:{$itemId}"] ?? null;
            if ($prsItemId === null) {
                $this->warn("assign_canv_prc skipped: no prs_item for prs_id={$prsId}, item_id={$itemId} (prsnumber={$prsNumber}, productcode={$productCode})");
                $skipped++;
                continue;
            }

            $supplierId = $supplierIdByCode[$supplierCode] ?? null;
            if ($supplierId === null) {
                $this->warn("assign_canv_prc skipped: supplier_code '{$supplierCode}' not found in suppliers table (prsnumber={$prsNumber})");
                $skipped++;
                continue;
            }

            $isSelected = strtoupper(trim((string) ($row['is_selected'] ?? 'N'))) === 'Y';
            $isActive   = strtoupper(trim((string) ($row['is_active'] ?? 'Y'))) === 'Y';

            $createdDate = $this->parseDate($row['created_date'] ?? null);
            $updatedDate = $this->parseDate($row['updated_date'] ?? null);
            $deletedAt   = $isActive ? null : ($updatedDate ?? now());

            $topRaw = strtoupper(trim((string) ($row['top'] ?? '')));
            $termOfPaymentType = match (true) {
                str_contains($topRaw, 'CASH')   => 'cash',
                str_contains($topRaw, 'CREDIT') => 'credit',
                default                         => null,
            };

            $unitPriceRaw = $row['unit_price'] ?? null;
            $unitPrice = is_numeric($unitPriceRaw) ? (float) $unitPriceRaw : 0.0;

            $meta = json_encode(array_merge(['wp' => $this->nullableString($row['wp'] ?? null)], $row));

            DB::table('prs_canvassing_items')->updateOrInsert(
                [
                    'prs_item_id' => $prsItemId,
                    'supplier_id' => $supplierId,
                ],
                [
                    'prs_id'               => $prsId,
                    'prs_item_id'          => $prsItemId,
                    'supplier_id'          => $supplierId,
                    'is_selected'          => $isSelected,
                    'unit_price'           => $unitPrice,
                    'lead_time_days'       => null,
                    'term_of_payment_type' => $termOfPaymentType,
                    'term_of_payment'      => $this->nullableString($row['top_desc'] ?? null),
                    'term_of_delivery'     => $this->nullableString($row['tod'] ?? null),
                    'notes'                => null,
                    'canvased_by'          => null,
                    'meta'                 => $meta,
                    'created_at'           => $createdDate?->toDateTimeString() ?? now(),
                    'updated_at'           => $updatedDate?->toDateTimeString() ?? now(),
                    'deleted_at'           => $deletedAt?->toDateTimeString(),
                ]
            );

            $inserted++;
        }

        $this->syncSelectedCanvassingItems();

        $this->command?->info("✓ [assign_canv_prc] Inserted/Updated: {$inserted}, Skipped: {$skipped}");
    }

    /**
     * Sync prs_items.selected_canvassing_item_id from is_selected=true active canvassing rows.
     */
    protected function syncSelectedCanvassingItems(): void
    {
        $selectedItems = DB::table('prs_canvassing_items')
            ->whereNull('deleted_at')
            ->where('is_selected', true)
            ->select('id', 'prs_item_id')
            ->get();

        $count = 0;
        foreach ($selectedItems as $item) {
            DB::table('prs_items')
                ->where('id', $item->prs_item_id)
                ->update(['selected_canvassing_item_id' => $item->id]);
            $count++;
        }

        $this->command?->info("✓ [assign_canv_prc] Synced selected_canvassing_item_id for {$count} prs_items");
    }

    protected function seedFromCsv(): void
    {
        $csvPath = $this->csvPathFor('assign_canv_prc');

        if (!File::exists($csvPath)) {
            $this->command?->warn("assign_canv_prc.csv not found at: {$csvPath}");
            return;
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->command?->warn("Failed to open assign_canv_prc.csv at: {$csvPath}");
            return;
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            $this->command?->warn("assign_canv_prc.csv is empty");
            return;
        }

        $rows = [];
        $skippedInvalidColumns = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) !== count($header)) {
                $skippedInvalidColumns++;
                continue;
            }

            $data = array_combine($header, $row);
            if ($data === false) {
                $skippedInvalidColumns++;
                continue;
            }

            $rows[] = $data;
        }

        fclose($handle);

        if ($skippedInvalidColumns > 0) {
            $this->command?->warn("assign_canv_prc CSV skipped rows with invalid columns: {$skippedInvalidColumns}");
        }

        if (empty($rows)) {
            $this->command?->warn('No CSV assign_canv_prc rows loaded.');
            return;
        }

        $this->seedRows($rows, 'csv');
    }

    // ─── helpers ────────────────────────────────────────────────────────

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || strtoupper(trim((string) $value)) === 'NULL') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);
        return ($str === '' || strtoupper($str) === 'NULL') ? null : $str;
    }

    private function warn(string $message): void
    {
        $this->command?->warn("⚠ {$message}");
        Log::warning("[PrsCanvassingItemSeeder] {$message}");
    }
}
