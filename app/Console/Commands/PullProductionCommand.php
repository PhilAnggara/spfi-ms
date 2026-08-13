<?php

namespace App\Console\Commands;

use App\Services\ProductionDatabaseSync;
use Illuminate\Console\Command;
use Throwable;

class PullProductionCommand extends Command
{
    protected $signature = 'db:pull-production
                            {--force : Skip the confirmation prompt}
                            {--chunk=1000 : Number of rows to copy per batch}
                            {--dry-run : List tables and row counts without writing}';

    protected $description = 'Full-replace the current development database with data from production SQL Server';

    public function handle(ProductionDatabaseSync $sync): int
    {
        $chunkSize = (int) $this->option('chunk');

        if ($chunkSize < 1) {
            $this->error('Chunk size must be at least 1.');

            return self::FAILURE;
        }

        try {
            $preview = $sync->preview();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Production → development full replace');
        $this->line("  Source: {$preview['source']['name']} {$preview['source']['driver']} {$preview['source']['host']} / {$preview['source']['database']}");
        $this->line("  Target: {$preview['target']['name']} {$preview['target']['driver']} {$preview['target']['host']} / {$preview['target']['database']}");
        $this->line('  Tables: '.count($preview['tables']));
        $this->newLine();

        $this->table(
            ['Table', 'Production rows', 'Target rows'],
            collect($preview['tables'])->map(fn (array $table): array => [
                $table['table'],
                $table['source_rows'],
                $table['target_rows'],
            ])->all()
        );

        if ((bool) $this->option('dry-run')) {
            $this->comment('Dry run only. No data was written.');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force') && ! $this->confirm(
            'This will ERASE all data in the target development database and replace it with production data. Continue?',
            false
        )) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->sync($chunkSize, function (string $table, int $rows): void {
                $this->line("  Copied {$table}: {$rows} rows");
            });
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $totalRows = collect($result['tables'])->sum('rows');
        $this->newLine();
        $this->info('Pull complete: '.count($result['tables']).' tables, '.$totalRows.' rows.');

        return self::SUCCESS;
    }
}
