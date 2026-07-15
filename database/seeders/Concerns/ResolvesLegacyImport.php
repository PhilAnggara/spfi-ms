<?php

namespace Database\Seeders\Concerns;

use App\Services\Legacy\LegacyImportDatasetRegistry;
use Illuminate\Support\Facades\DB;
use Throwable;

trait ResolvesLegacyImport
{
    // Menentukan apakah mode seeding diset ke sumber legacy DB.
    protected function isLegacySource(): bool
    {
        return strtolower((string) config('legacy_import.source', 'local')) === 'legacy';
    }

    // Menentukan apakah gagal legacy boleh otomatis fallback ke local seeder.
    protected function shouldFallbackToLocal(): bool
    {
        return (bool) config('legacy_import.fallback_to_local', true);
    }

    // Backward-compat alias agar seeder lama yang memanggil shouldFallbackToCsv() tetap jalan.
    protected function shouldFallbackToCsv(): bool
    {
        return $this->shouldFallbackToLocal();
    }

    protected function legacyImportRegistry(): LegacyImportDatasetRegistry
    {
        return app(LegacyImportDatasetRegistry::class);
    }

    // Mengambil path CSV per dataset dari konfigurasi.
    protected function csvPathFor(string $dataset): string
    {
        return $this->legacyImportRegistry()->resolveCsvAbsolutePath($dataset);
    }

    // Mengambil seluruh baris dari legacy DB sesuai mapping dataset.
    protected function getLegacyRows(string $dataset): array
    {
        $registry = $this->legacyImportRegistry();
        $connection = $registry->resolveConnection($dataset);
        $table = $registry->resolveTable($dataset);

        $rows = DB::connection($connection)->table($table)->get();

        return $rows->map(static fn ($row) => (array) $row)->all();
    }

    // Mengambil baris dari legacy DB dengan chunking untuk dataset besar.
    protected function getLegacyRowsChunked(string $dataset, int $chunkSize = 500, ?callable $callback = null): void
    {
        $registry = $this->legacyImportRegistry();
        $connection = $registry->resolveConnection($dataset);
        $table = $registry->resolveTable($dataset);
        $orderBy = $registry->resolveOrderBy($dataset);

        DB::connection($connection)->table($table)->orderBy($orderBy)->chunkById($chunkSize, function ($rows) use ($callback) {
            $callback?->__invoke($rows->map(static fn ($row) => (array) $row)->all());
        }, $orderBy);
    }

    // Menampilkan sumber import yang dipakai untuk dataset saat ini.
    protected function logImportSource(string $dataset, string $source): void
    {
        $this->command?->info("ℹ [{$dataset}] source: {$source}");
    }

    protected function resolveRows(string $dataset, ?callable $onWarning = null): array
    {
        if (! $this->isLegacySource()) {
            return [];
        }

        try {
            return $this->getLegacyRows($dataset);
        } catch (Throwable $e) {
            if (! $this->shouldFallbackToLocal()) {
                throw $e;
            }

            $onWarning?->__invoke("Legacy source failed for [{$dataset}], fallback to local seeder. {$e->getMessage()}");

            return [];
        }
    }
}
