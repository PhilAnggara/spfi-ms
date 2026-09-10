<?php

use App\Models\UserActivityLog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Remove noisy historical activity rows (pending inbox polls + typing spam).
     */
    public function up(): void
    {
        UserActivityLog::query()
            ->where('action', UserActivityLog::ACTION_TYPING)
            ->delete();

        // Avoid JSON path operators (unsupported on this SQL Server setup).
        // Match stored meta text for the pending inbox poll route/path/page.
        UserActivityLog::query()
            ->where('action', UserActivityLog::ACTION_ACTIVE)
            ->where(function ($query): void {
                $query->where('meta', 'like', '%screen-messages.inbox.pending%')
                    ->orWhere('meta', 'like', '%/screen-messages/inbox/pending%')
                    ->orWhere('meta', 'like', '%Screen Messages%Pending%');
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data cleanup.
    }
};
