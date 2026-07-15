<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyCsvExporter;
use App\Services\Legacy\LegacyImportDatasetRegistry;
use Illuminate\Console\Command;
use RuntimeException;

class LegacyExportCsvCommand extends Command
{
    protected $signature = 'legacy:export-csv
                            {--only= : Comma-separated dataset keys to export}
                            {--exclude= : Comma-separated dataset keys to skip}
                            {--list : List configured datasets}
                            {--dry-run : Count rows without writing files}
                            {--chunk=2000 : Default chunk size for reading legacy tables}';

    protected $description = 'Export legacy SQL Server tables to CSV fallback files in public/csv';

    public function handle(
        LegacyImportDatasetRegistry $registry,
        LegacyCsvExporter $exporter,
    ): int {
        if ($this->option('list')) {
            return $this->listDatasets($registry);
        }

        try {
            $datasets = $this->resolveTargetDatasets($registry);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($datasets === []) {
            $this->warn('No datasets selected for export.');

            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Dry run — counting legacy rows...' : 'Exporting legacy data to CSV...');
        $this->newLine();

        $startedAt = microtime(true);
        $results = $exporter->export($datasets, $chunkSize, $dryRun);
        $successCount = 0;
        $totalBytes = 0;

        foreach ($results as $result) {
            $status = $result['success'] ? 'OK' : 'FAIL';
            $rows = number_format($result['rows']);
            $size = $this->formatBytes($result['bytes']);

            $this->line(sprintf(
                '  %-22s %-18s %-22s %10s rows %10s  %s',
                $result['dataset'],
                $result['connection'],
                $result['table'],
                $rows,
                $dryRun ? '-' : $size,
                $status,
            ));

            if (! $result['success'] && $result['message'] !== null) {
                $this->error('    '.$result['message']);
            }

            if ($result['success']) {
                $successCount++;
                $totalBytes += $result['bytes'];
            }
        }

        $elapsed = $this->formatDuration(microtime(true) - $startedAt);
        $this->newLine();

        if ($dryRun) {
            $this->info("Counted {$successCount}/".count($results)." datasets in {$elapsed}");
        } else {
            $this->info(sprintf(
                'Exported %d/%d datasets (%s) in %s',
                $successCount,
                count($results),
                $this->formatBytes($totalBytes),
                $elapsed,
            ));
        }

        return $successCount === count($results) ? self::SUCCESS : self::FAILURE;
    }

    private function listDatasets(LegacyImportDatasetRegistry $registry): int
    {
        $this->info('Configured legacy datasets:');
        $this->newLine();

        foreach ($registry->datasetKeys() as $dataset) {
            $exportEnabled = $registry->isExportEnabled($dataset) ? 'yes' : 'no';

            $this->line(sprintf(
                '  %-22s %-18s %-26s export=%s',
                $dataset,
                $registry->resolveConnection($dataset),
                $registry->resolveTable($dataset),
                $exportEnabled,
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveTargetDatasets(LegacyImportDatasetRegistry $registry): array
    {
        $only = $this->parseCsvOption('only');
        $exclude = array_flip($this->parseCsvOption('exclude'));

        if ($only !== []) {
            $datasets = [];
            foreach ($only as $dataset) {
                if (! in_array($dataset, $registry->datasetKeys(), true)) {
                    throw new RuntimeException("Unknown dataset [{$dataset}]. Use --list to see configured datasets.");
                }

                $datasets[] = $dataset;
            }

            return $datasets;
        }

        return array_values(array_filter(
            $registry->exportableDatasetKeys(),
            fn (string $dataset): bool => ! array_key_exists($dataset, $exclude),
        ));
    }

    /**
     * @return list<string>
     */
    private function parseCsvOption(string $name): array
    {
        $value = trim((string) $this->option($name));

        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $value),
        )));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 0).' KB';
        }

        return number_format($bytes / 1024 / 1024, 1).' MB';
    }

    private function formatDuration(float $seconds): string
    {
        $totalSeconds = (int) round($seconds);
        $minutes = intdiv($totalSeconds, 60);
        $remainingSeconds = $totalSeconds % 60;

        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $remainingSeconds);
        }

        return sprintf('%ds', $remainingSeconds);
    }
}
