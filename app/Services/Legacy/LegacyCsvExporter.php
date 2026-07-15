<?php

namespace App\Services\Legacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LegacyCsvExporter
{
    public function __construct(
        private readonly LegacyImportDatasetRegistry $registry,
    ) {}

    /**
     * @param  list<string>  $datasets
     * @return list<array{
     *     dataset: string,
     *     connection: string,
     *     table: string,
     *     rows: int,
     *     bytes: int,
     *     path: string,
     *     success: bool,
     *     message: string|null
     * }>
     */
    public function export(array $datasets, int $defaultChunkSize = 2000, bool $dryRun = false): array
    {
        $results = [];

        foreach ($datasets as $dataset) {
            $results[] = $this->exportDataset($dataset, $defaultChunkSize, $dryRun);
        }

        return $results;
    }

    /**
     * @return array{
     *     dataset: string,
     *     connection: string,
     *     table: string,
     *     rows: int,
     *     bytes: int,
     *     path: string,
     *     success: bool,
     *     message: string|null
     * }
     */
    public function exportDataset(string $dataset, int $defaultChunkSize = 2000, bool $dryRun = false): array
    {
        $connection = $this->registry->resolveConnection($dataset);
        $table = $this->registry->resolveTable($dataset);
        $path = $this->registry->resolveCsvAbsolutePath($dataset);
        $orderBy = $this->registry->resolveOrderBy($dataset);
        $chunkSize = $this->registry->resolveChunkSize(
            $dataset,
            $defaultChunkSize > 0 ? $defaultChunkSize : (int) config('legacy_import.export.default_chunk_size', 2000),
        );
        $delimiter = $this->registry->resolveDelimiter();

        $result = [
            'dataset' => $dataset,
            'connection' => $connection,
            'table' => $table,
            'rows' => 0,
            'bytes' => 0,
            'path' => $path,
            'success' => false,
            'message' => null,
        ];

        try {
            DB::connection($connection)->getPdo();

            $rowCount = (int) DB::connection($connection)->table($table)->count();
            $result['rows'] = $rowCount;

            if ($dryRun) {
                $result['success'] = true;

                return $result;
            }

            $columns = $this->resolveColumns($connection, $table);

            if ($columns === []) {
                throw new \RuntimeException("No columns found for table [{$table}].");
            }

            File::ensureDirectoryExists(dirname($path));

            $handle = fopen($path, 'w');
            if ($handle === false) {
                throw new \RuntimeException("Unable to open CSV file for writing: {$path}");
            }

            try {
                fputcsv($handle, $columns, $delimiter);

                $chunkColumn = $this->resolveChunkColumn($orderBy, $columns);

                DB::connection($connection)
                    ->table($table)
                    ->orderBy($chunkColumn)
                    ->chunkById($chunkSize, function ($rows) use ($handle, $columns, $delimiter): void {
                        foreach ($rows as $row) {
                            $values = [];
                            foreach ($columns as $column) {
                                $values[] = $this->normalizeCellValue($this->readColumnValue($row, $column));
                            }

                            fputcsv($handle, $values, $delimiter);
                        }
                    }, $chunkColumn);
            } finally {
                fclose($handle);
            }

            if (! is_file($path) || filesize($path) === 0) {
                throw new \RuntimeException("CSV file was not written: {$path}");
            }

            $result['bytes'] = is_file($path) ? (int) filesize($path) : 0;
            $result['success'] = true;
        } catch (Throwable $exception) {
            if (is_file($path) && ! $dryRun) {
                @unlink($path);
            }

            $result['message'] = $exception->getMessage();
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function resolveColumns(string $connection, string $table): array
    {
        $driver = DB::connection($connection)->getDriverName();

        if ($driver === 'sqlsrv') {
            $rows = DB::connection($connection)->select(
                'SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = ?
                 ORDER BY ORDINAL_POSITION',
                [$table],
            );

            $columns = array_map(
                static fn ($row): string => (string) $row->COLUMN_NAME,
                $rows,
            );

            if ($columns !== []) {
                return $columns;
            }
        }

        if (Schema::connection($connection)->hasTable($table)) {
            $columns = Schema::connection($connection)->getColumnListing($table);

            if ($columns !== []) {
                return $columns;
            }
        }

        $firstRow = DB::connection($connection)->table($table)->first();

        if ($firstRow === null) {
            return [];
        }

        return array_keys((array) $firstRow);
    }

    /**
     * Resolve a chunk/order column that actually exists on the table.
     * Matches configured order_by case-insensitively, then falls back to id variants,
     * then the first column (common for legacy tables keyed by *_code).
     *
     * @param  list<string>  $columns
     */
    private function resolveChunkColumn(string $configuredOrderBy, array $columns): string
    {
        if ($columns === []) {
            throw new \RuntimeException('Cannot resolve chunk column without table columns.');
        }

        foreach ($columns as $column) {
            if (strcasecmp($column, $configuredOrderBy) === 0) {
                return $column;
            }
        }

        foreach (['id', 'Id', 'ID'] as $candidate) {
            foreach ($columns as $column) {
                if (strcasecmp($column, $candidate) === 0) {
                    return $column;
                }
            }
        }

        return $columns[0];
    }

    private function normalizeCellValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function readColumnValue(object $row, string $column): mixed
    {
        $attributes = (array) $row;
        $columnLower = strtolower($column);

        foreach ($attributes as $key => $value) {
            $normalizedKey = strtolower((string) preg_replace('/^\x00\*\x00/', '', (string) $key));

            if ($normalizedKey === $columnLower) {
                return $value;
            }
        }

        return $row->{$column} ?? null;
    }
}
