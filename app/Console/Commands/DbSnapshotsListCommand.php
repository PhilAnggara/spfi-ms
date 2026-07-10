<?php

namespace App\Console\Commands;

use App\Services\DatabaseSnapshot;
use Illuminate\Console\Command;

class DbSnapshotsListCommand extends Command
{
    protected $signature = 'db:snapshots';

    protected $description = 'List available dev database snapshots';

    public function handle(DatabaseSnapshot $databaseSnapshot): int
    {
        $target = $databaseSnapshot->currentTarget();
        $snapshots = $databaseSnapshot->list();

        $this->info('Current dev database target:');
        $this->line("  Connection: {$target['name']} | Driver: {$target['driver']} | Database: {$target['database']}");
        $this->newLine();

        if ($snapshots === []) {
            $this->comment('No snapshots found. Run php artisan db:snapshot after seeding.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Driver', 'Database', 'File', 'Backup Path', 'Created', 'Size (MB)'],
            array_map(static function (array $snapshot): array {
                return [
                    $snapshot['name'] ?? '-',
                    $snapshot['driver'] ?? '-',
                    $snapshot['database'] ?? '-',
                    $snapshot['file'] ?? '-',
                    $snapshot['backup_path'] ?? '-',
                    $snapshot['created_at'] ?? '-',
                    isset($snapshot['size_bytes']) && $snapshot['size_bytes'] !== null
                        ? number_format(((int) $snapshot['size_bytes']) / 1024 / 1024, 2)
                        : '-',
                ];
            }, $snapshots)
        );

        return self::SUCCESS;
    }
}
