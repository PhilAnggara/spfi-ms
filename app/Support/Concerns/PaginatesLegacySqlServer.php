<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

trait PaginatesLegacySqlServer
{
    protected function paginateEloquentForCurrentConnection(
        EloquentBuilder $query,
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
                ->whereKey($ids)
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
}
