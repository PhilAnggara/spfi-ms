<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Soft-deleted SA rows still held the original sa_number, blocking unique reuse.
        DB::table('stock_adjustments')
            ->whereNotNull('deleted_at')
            ->where('sa_number', 'not like', 'DELETED-%')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($row): void {
                DB::table('stock_adjustments')
                    ->where('id', $row->id)
                    ->update(['sa_number' => 'DELETED-'.$row->id]);
            });
    }

    public function down(): void
    {
        // Irreversible data repair — original numbers are not restored.
    }
};
