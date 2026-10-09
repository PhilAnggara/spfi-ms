<?php

namespace App\Console\Commands;

use App\Services\CountTag\NonFgCountTagLegacyImporter;
use Illuminate\Console\Command;
use Throwable;

class CountTagImportNonFgCommand extends Command
{
    protected $signature = 'count-tag:import-non-fg {--chunk=500 : Number of legacy rows to process per batch}';

    protected $description = 'One-time import of Non-FG count tags from legacy counttag database (read-only)';

    public function handle(NonFgCountTagLegacyImporter $importer): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        $this->info('Importing Non-FG count tags from legacy (read-only)...');
        $this->line(sprintf('Chunk size: %d', $chunkSize));
        $this->newLine();

        $startedAt = microtime(true);

        try {
            $result = $importer->import(
                $chunkSize,
                function (int $lastId, int $batchCount): void {
                    $this->line(sprintf('Processed through legacy Id=%d (%d rows in batch)', $lastId, $batchCount));
                },
            );
        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $locations = $result['locations'];
        $sections = $result['sections'];
        $tags = $result['tags'];

        $this->info('Locations');
        $this->line(sprintf('  created=%d  updated=%d', $locations['created'], $locations['updated']));
        $this->newLine();

        $this->info('Sections');
        $this->line(sprintf('  created=%d  updated=%d', $sections['created'], $sections['updated']));
        $this->newLine();

        $this->info('Count tags');
        $this->line(sprintf('  created=%d  updated=%d', $tags['created'], $tags['updated']));
        $this->line(sprintf(
            '  unmatched_items=%d  unmatched_categories=%d  unmatched_locations=%d',
            $tags['unmatched_items'],
            $tags['unmatched_categories'],
            $tags['unmatched_locations'],
        ));
        $this->newLine();

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("Import completed in {$elapsed}s.");

        return self::SUCCESS;
    }
}
