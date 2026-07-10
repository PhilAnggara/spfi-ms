<?php

namespace App\Console\Commands;

use App\Services\DatabaseSnapshot;
use Illuminate\Console\Command;

class DbRestoreCommand extends Command
{
    protected $signature = 'db:restore {name? : Snapshot name or file to restore}';

    protected $description = 'Restore the latest dev database snapshot (mysql or sqlsrv only)';

    public function handle(DatabaseSnapshot $databaseSnapshot): int
    {
        $target = $databaseSnapshot->currentTarget();

        $this->warn('Restoring dev database snapshot:');
        $this->line("  Connection: {$target['name']}");
        $this->line("  Driver: {$target['driver']}");
        $this->line("  Database: {$target['database']}");

        if (! $this->confirm('This will overwrite the current dev database. Continue?', true)) {
            $this->comment('Restore cancelled.');

            return self::SUCCESS;
        }

        try {
            $manifest = $databaseSnapshot->restore($this->argument('name'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Restored snapshot: {$manifest['file']}");

        return self::SUCCESS;
    }
}
