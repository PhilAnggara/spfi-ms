<?php

namespace App\Console\Commands;

use App\Services\DatabaseSnapshot;
use Illuminate\Console\Command;

class DbSnapshotCommand extends Command
{
    protected $signature = 'db:snapshot {--name= : Optional snapshot name}';

    protected $description = 'Create a snapshot of the dev database (mysql or sqlsrv only)';

    public function handle(DatabaseSnapshot $databaseSnapshot): int
    {
        $target = $databaseSnapshot->currentTarget();

        $this->info('Creating snapshot for dev database:');
        $this->line("  Connection: {$target['name']}");
        $this->line("  Driver: {$target['driver']}");
        $this->line("  Database: {$target['database']}");

        try {
            $manifest = $databaseSnapshot->create($this->option('name'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $sizeMb = number_format(((int) $manifest['size_bytes']) / 1024 / 1024, 2);

        $this->info("Snapshot created: {$manifest['file']} ({$sizeMb} MB)");

        return self::SUCCESS;
    }
}
