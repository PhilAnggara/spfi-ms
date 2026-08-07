<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyIncrementalImporter;
use App\Services\Reconcile\ReconcileDeltaAuditor;
use Illuminate\Console\Command;
use Throwable;

class ReconcileImsToNewCommand extends Command
{
    protected $signature = 'reconcile:ims-to-new
                            {--report : Audit only (default when --apply is omitted)}
                            {--apply : Import IMS-only / conflict docs into spfi_ms}
                            {--since= : Cutoff date (default from config/reconcile.php)}
                            {--only= : Comma-separated datasets (supplier,product,prs,canvassing,po,rr,sws,ts,dr,stock)}
                            {--conflict= : skip|import-as-alias|prefer-ims (default from config)}
                            {--no-stock : Skip StockService posting for imported RR/TS}
                            {--force-stock : Allow negative balances when posting stock (default true on --apply)}';

    protected $description = 'Incremental reconcile: audit/import IMS (legacy_sqlsrv_1) deltas into spfi_ms since a cutoff date';

    public function handle(
        ReconcileDeltaAuditor $auditor,
        LegacyIncrementalImporter $importer,
    ): int {
        $since = (string) ($this->option('since') ?: config('reconcile.default_since'));
        $only = $this->parseOnly();
        $apply = (bool) $this->option('apply');
        $conflict = (string) ($this->option('conflict') ?: config('reconcile.conflict', 'import-as-alias'));

        if (! in_array($conflict, ['skip', 'import-as-alias', 'prefer-ims'], true)) {
            $this->error('Invalid --conflict. Use skip, import-as-alias, or prefer-ims.');

            return self::FAILURE;
        }

        $this->info('IMS → spfi_ms reconcile');
        $this->line("  since: {$since}");
        $this->line('  mode: '.($apply ? 'APPLY' : 'REPORT'));
        $this->line('  conflict: '.$conflict);
        if ($only !== null) {
            $this->line('  only: '.implode(',', $only));
        }
        $this->newLine();

        try {
            if (! $apply) {
                $audit = $auditor->audit($since, $only, writeCsv: true);
                $this->renderAuditSummary($audit);
                if ($audit['report_dir']) {
                    $this->info('CSV reports: '.$audit['report_dir']);
                }

                return self::SUCCESS;
            }

            if (! config('reconcile.freeze_writes')) {
                $this->warn('RECONCILE_FREEZE_WRITES is false. Consider enabling it during apply to avoid race writes.');
            }

            $result = $importer->apply(
                since: $since,
                only: $only,
                conflict: $conflict,
                applyStock: ! (bool) $this->option('no-stock'),
                // Historical IMS docs may temporarily go negative vs current SPFI balances.
                forceStock: true,
            );

            $this->renderAuditSummary($result['audit']);
            $this->newLine();
            $this->info('Import results:');
            foreach ($result['imported'] as $dataset => $count) {
                $skipped = $result['skipped'][$dataset] ?? 0;
                $this->line(sprintf('  %-12s imported=%-5d skipped=%-5d', $dataset, $count, $skipped));
            }
            $this->line('  conflicts_aliased: '.$result['conflicts_aliased']);
            $this->line('  stock_posted: '.$result['stock_posted']);
            $this->line('  stock_failed: '.$result['stock_failed']);

            if (($result['audit']['report_dir'] ?? null)) {
                $this->info('CSV reports: '.$result['audit']['report_dir']);
            }

            return $result['stock_failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return list<string>|null
     */
    private function parseOnly(): ?array
    {
        $raw = trim((string) $this->option('only'));
        if ($raw === '') {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * @param  array<string, mixed>  $audit
     */
    private function renderAuditSummary(array $audit): void
    {
        $rows = [];
        foreach ($audit['datasets'] ?? [] as $dataset => $payload) {
            $rows[] = [
                $dataset,
                count($payload['ims_only'] ?? []),
                count($payload['new_only'] ?? []),
                count($payload['content_mismatches'] ?? []),
                $payload['match_count'] ?? 0,
            ];
        }

        $this->table(
            ['Dataset', 'IMS-only', 'New-only', 'Content mismatch', 'Match'],
            $rows
        );

        $stockCount = count($audit['stock_mismatches'] ?? []);
        $this->line("Stock mismatches: {$stockCount}");
    }
}
