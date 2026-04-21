<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy statuses that can be safely normalized to the new flow.
     *
     * @var array<int, string>
     */
    private array $mutableStatuses = [
        'SUBMITTED',
        'RESUBMITTED',
        'APPROVED',
        'DELIVERY_COMPLETE',
        'REQUESTED',
        'CANVASSING',
        'PO_CREATED',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('prs')
            ->whereIn('status', $this->mutableStatuses)
            ->update(['status' => 'REQUESTED']);

        DB::table('prs')
            ->whereIn('status', $this->mutableStatuses)
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
            ->whereIn('status', $this->mutableStatuses)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('prs_items')
                    ->whereColumn('prs_items.prs_id', 'prs.id')
                    ->whereNotNull('prs_items.purchase_order_id');
            })
            ->update(['status' => 'PO_CREATED']);

        DB::table('prs')
            ->where('status', 'REVISED')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('prs_logs')
                    ->whereColumn('prs_logs.prs_id', 'prs.id')
                    ->where('prs_logs.action', 'RESUBMIT');
            })
            ->update(['status' => 'REQUESTED']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('prs')
            ->where('status', 'REQUESTED')
            ->update(['status' => 'SUBMITTED']);

        DB::table('prs')
            ->where('status', 'CANVASSING')
            ->update(['status' => 'APPROVED']);

        DB::table('prs')
            ->where('status', 'PO_CREATED')
            ->update(['status' => 'APPROVED']);

        DB::table('prs')
            ->where('status', 'REVISED')
            ->update(['status' => 'RESUBMITTED']);
    }
};
