<?php

namespace App\Console\Commands;

use App\Services\Legacy\EmployeeLegacySyncService;
use Illuminate\Console\Command;

class EmployeesSyncFromLegacyCommand extends Command
{
    protected $signature = 'employees:sync-from-legacy
                            {--dry-run : Preview sync counts without writing to the database}
                            {--skip-soft-delete : Upsert only; do not soft-delete missing legacy employees}';

    protected $description = 'Safely sync employee departments and employees from legacy (or CSV fallback) without wiping photos or unrelated data';

    public function handle(EmployeeLegacySyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $skipSoftDelete = (bool) $this->option('skip-soft-delete');

        $this->info($dryRun
            ? 'Dry run — previewing employee sync from legacy/CSV...'
            : 'Syncing employee departments and employees from legacy/CSV...');
        $this->newLine();

        $startedAt = microtime(true);

        $result = $syncService->sync([
            'dry_run' => $dryRun,
            'skip_soft_delete' => $skipSoftDelete,
            'on_warning' => fn (string $message) => $this->warn($message),
        ]);

        $departments = $result['departments'];
        $employees = $result['employees'];

        $this->line(sprintf('Source: %s', $result['source']));
        $this->newLine();

        $this->info('Departments');
        $this->line(sprintf(
            '  created=%d  updated=%d  skipped=%d',
            $departments['created'],
            $departments['updated'],
            $departments['skipped'],
        ));
        $this->newLine();

        $this->info('Employees');
        $this->line(sprintf(
            '  created=%d  updated=%d  merged_manual=%d',
            $employees['created'],
            $employees['updated'],
            $employees['merged_manual'],
        ));
        $this->line(sprintf(
            '  soft_deleted=%d  restored=%d  skipped=%d',
            $employees['soft_deleted'],
            $employees['restored'],
            $employees['skipped'],
        ));
        $this->line(sprintf(
            '  photos_preserved=%d  photos_relinked=%d',
            $employees['photos_preserved'],
            $employees['photos_relinked'],
        ));
        $this->newLine();

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info($dryRun
            ? "Dry run completed in {$elapsed}s (no database writes)."
            : "Employee sync completed in {$elapsed}s.");

        return self::SUCCESS;
    }
}
