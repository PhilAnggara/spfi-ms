<?php

namespace App\Console\Commands;

use App\Services\Reconcile\ImsNumberAlignAuditor;
use App\Services\Reconcile\ImsNumberAligner;
use Illuminate\Console\Command;
use Throwable;

class ReconcileAlignImsNumbersCommand extends Command
{
    protected $signature = 'reconcile:align-ims-numbers
                            {--report : Audit mismatched IMS/SPFI numbers by fingerprint}
                            {--dry-run : Alias for --report}
                            {--apply : Apply IMS-canonical document numbers}
                            {--since= : Cutoff date (default from config/reconcile.php)}
                            {--only= : Comma-separated datasets (prs,po,sws,rr,ts,dr)}';

    protected $description = 'Align SPFI document numbers to IMS numbers when the business content matches since a cutoff date';

    public function handle(ImsNumberAlignAuditor $auditor, ImsNumberAligner $aligner): int
    {
        $since = (string) ($this->option('since') ?: config('reconcile.default_since'));
        $only = $this->parseOnly();
        $apply = (bool) $this->option('apply');

        $this->info('IMS canonical number alignment');
        $this->line("  since: {$since}");
        $this->line('  mode: '.($apply ? 'APPLY' : 'REPORT'));
        if ($only !== null) {
            $this->line('  only: '.implode(',', $only));
        }
        $this->newLine();

        try {
            if (! $apply) {
                $audit = $auditor->audit($since, $only, writeCsv: true);
                $this->renderSummary($audit);
                if ($audit['report_dir']) {
                    $this->info('CSV reports: '.$audit['report_dir']);
                }

                return self::SUCCESS;
            }

            $result = $aligner->apply($since, $only);
            $this->renderSummary($result['audit']);
            $this->newLine();
            $this->info('Apply results:');

            foreach ($result['applied'] as $dataset => $count) {
                $this->line(sprintf(
                    '  %-6s applied=%-5d skipped=%-5d',
                    $dataset,
                    $count,
                    $result['skipped'][$dataset] ?? 0
                ));
            }

            if (($result['audit']['report_dir'] ?? null)) {
                $this->info('CSV reports: '.$result['audit']['report_dir']);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

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
    private function renderSummary(array $audit): void
    {
        $rows = [];

        foreach ($audit['datasets'] ?? [] as $dataset => $payload) {
            $actionCounts = collect($payload['action_counts'] ?? []);
            $rows[] = [
                $dataset,
                $payload['ims_since_count'] ?? 0,
                $payload['spfi_since_count'] ?? 0,
                (int) $actionCounts->get('already_match', 0),
                (int) $actionCounts->get('rename_to_ims', 0),
                (int) $actionCounts->get('promote_alias', 0),
                (int) $actionCounts->get('retire_orphan_alias', 0),
                (int) $actionCounts->get('replace_from_ims', 0),
                (int) $actionCounts->get('import_missing', 0),
                (int) $actionCounts->get('manual_review', 0),
            ];
        }

        $this->table(
            ['Dataset', 'IMS since', 'SPFI since', 'Match', 'Rename', 'Promote alias', 'Retire orphan', 'Replace', 'Import', 'Manual'],
            $rows
        );
    }
}
