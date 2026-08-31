<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait PaginatesLegacySqlServer
{
    protected function paginateEloquentForCurrentConnection(
        EloquentBuilder|Relation $query,
        string $rowNumberOrderBySql,
        int $perPage = 15
    ): LengthAwarePaginator {
        if (! $this->isSqlServerConnection()) {
            return $query
                ->paginate($perPage)
                ->withQueryString();
        }

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPage = max(1, (int) $currentPage);
        $total = (clone $query)->count();
        $startRow = (($currentPage - 1) * $perPage) + 1;
        $endRow = $currentPage * $perPage;

        $model = $query->getModel();
        $keyName = $model->getKeyName();
        $qualifiedKeyName = $model->getQualifiedKeyName();

        $rankedIdsQuery = (clone $query)
            ->reorder()
            ->selectRaw("{$qualifiedKeyName} as pagination_id")
            ->selectRaw("ROW_NUMBER() OVER (ORDER BY {$rowNumberOrderBySql}) as row_num");

        $ids = DB::query()
            ->fromSub($rankedIdsQuery, 'ranked_rows')
            ->whereBetween('row_num', [$startRow, $endRow])
            ->orderBy('row_num')
            ->pluck('pagination_id')
            ->all();

        $collection = collect();

        if (! empty($ids)) {
            $itemsById = (clone $query)
                ->whereIn($qualifiedKeyName, $ids)
                ->get()
                ->keyBy($keyName);

            $collection = collect($ids)
                ->map(fn ($id) => $itemsById->get($id))
                ->filter()
                ->values();
        }

        return new LengthAwarePaginator(
            items: $collection,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            options: [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    protected function isSqlServerConnection(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }

    protected function buildDataTableOrderBySql(
        string $orderColumn,
        string $orderDirection,
        string $tieBreakerColumn
    ): string {
        $direction = strtolower($orderDirection) === 'asc' ? 'ASC' : 'DESC';

        if ($orderColumn === $tieBreakerColumn) {
            return "{$orderColumn} {$direction}";
        }

        return "{$orderColumn} {$direction}, {$tieBreakerColumn} DESC";
    }

    /**
     * @return Collection<int, mixed>
     */
    protected function sliceEloquentQueryForDataTables(
        EloquentBuilder $query,
        string $qualifiedKeyName,
        string $orderBySql,
        int $start,
        int $length
    ): Collection {
        if (! $this->isSqlServerConnection()) {
            return $query
                ->skip($start)
                ->take($length)
                ->get();
        }

        $length = max(1, $length);
        $startRow = $start + 1;
        $endRow = $start + $length;
        $keyName = $query->getModel()->getKeyName();

        $rankedIdsQuery = (clone $query)
            ->reorder()
            ->selectRaw("{$qualifiedKeyName} as pagination_id")
            ->selectRaw("ROW_NUMBER() OVER (ORDER BY {$orderBySql}) as row_num");

        $ids = DB::query()
            ->fromSub($rankedIdsQuery, 'ranked_rows')
            ->whereBetween('row_num', [$startRow, $endRow])
            ->orderBy('row_num')
            ->pluck('pagination_id')
            ->all();

        if ($ids === []) {
            return collect();
        }

        $itemsById = (clone $query)
            ->reorder()
            ->whereIn($qualifiedKeyName, $ids)
            ->get()
            ->keyBy($keyName);

        return collect($ids)
            ->map(fn ($id) => $itemsById->get($id))
            ->filter()
            ->values();
    }
}
