<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrsStatusSyncSeeder extends Seeder
{
    /**
     * Sync PRS status from seeded PRS item relationships.
     * Priority: PO_CREATED > CANVASSING > REQUESTED.
     */
    public function run(): void
    {
        DB::table('prs')
            ->whereNotIn('status', ['ON_HOLD', 'REVISED', 'REJECTED'])
            ->update(['status' => 'REQUESTED']);

        DB::table('prs')
            ->whereNotIn('status', ['ON_HOLD', 'REVISED', 'REJECTED'])
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('prs_items')
                    ->whereColumn('prs_items.prs_id', 'prs.id')
                    ->whereNotNull('prs_items.canvasser_id');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('prs_items')
                    ->whereColumn('prs_items.prs_id', 'prs.id')
                    ->whereNotNull('prs_items.purchase_order_id');
            })
            ->update(['status' => 'CANVASSING']);

        DB::table('prs')
            ->whereNotIn('status', ['ON_HOLD', 'REVISED', 'REJECTED'])
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('prs_items')
                    ->whereColumn('prs_items.prs_id', 'prs.id')
                    ->whereNotNull('prs_items.purchase_order_id');
            })
            ->update(['status' => 'PO_CREATED']);

        $summary = [
            'REQUESTED' => DB::table('prs')->where('status', 'REQUESTED')->count(),
            'CANVASSING' => DB::table('prs')->where('status', 'CANVASSING')->count(),
            'PO_CREATED' => DB::table('prs')->where('status', 'PO_CREATED')->count(),
        ];

        $this->command?->info(sprintf(
            '✓ [prs_status_sync] REQUESTED: %d, CANVASSING: %d, PO_CREATED: %d',
            $summary['REQUESTED'],
            $summary['CANVASSING'],
            $summary['PO_CREATED']
        ));
    }
}
