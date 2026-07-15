<?php

namespace App\Services\Legacy;

use RuntimeException;

class LegacyImportDatasetRegistry
{
    /**
     * @return list<string>
     */
    public function datasetKeys(): array
    {
        return array_keys($this->datasets());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function datasets(): array
    {
        return config('legacy_import.datasets', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function datasetConfig(string $dataset): array
    {
        $config = $this->datasets()[$dataset] ?? null;

        if (! is_array($config)) {
            throw new RuntimeException("Dataset [{$dataset}] is not configured.");
        }

        return $config;
    }

    public function resolveConnection(string $dataset): string
    {
        $config = $this->datasetConfig($dataset);
        $connection = (string) ($config['connection'] ?? '');

        if ($connection !== '') {
            return $connection;
        }

        return (string) config('legacy_import.default_connection', 'legacy_sqlsrv_1');
    }

    public function resolveTable(string $dataset): string
    {
        $table = (string) ($this->datasetConfig($dataset)['table'] ?? '');

        if ($table === '') {
            throw new RuntimeException("Legacy table for dataset [{$dataset}] is not configured.");
        }

        return $table;
    }

    public function resolveCsvRelativePath(string $dataset): string
    {
        $relativePath = (string) ($this->datasetConfig($dataset)['csv_path'] ?? '');

        if ($relativePath === '') {
            throw new RuntimeException("CSV path for dataset [{$dataset}] is not configured.");
        }

        return $relativePath;
    }

    public function resolveCsvAbsolutePath(string $dataset): string
    {
        return public_path($this->resolveCsvRelativePath($dataset));
    }

    public function resolveOrderBy(string $dataset): string
    {
        $orderBy = (string) ($this->datasetConfig($dataset)['order_by'] ?? '');

        if ($orderBy !== '') {
            return $orderBy;
        }

        return (string) config('legacy_import.export.default_order_by', 'id');
    }

    public function resolveChunkSize(string $dataset, int $default): int
    {
        $chunkSize = $this->datasetConfig($dataset)['chunk_size'] ?? null;

        if (is_numeric($chunkSize) && (int) $chunkSize > 0) {
            return (int) $chunkSize;
        }

        return $default;
    }

    public function resolveDelimiter(): string
    {
        return (string) config('legacy_import.export.delimiter', ';');
    }

    public function isExportEnabled(string $dataset): bool
    {
        $export = $this->datasetConfig($dataset)['export'] ?? true;

        return $export !== false;
    }

    /**
     * @return list<string>
     */
    public function exportableDatasetKeys(): array
    {
        return array_values(array_filter(
            $this->datasetKeys(),
            fn (string $dataset): bool => $this->isExportEnabled($dataset),
        ));
    }
}
