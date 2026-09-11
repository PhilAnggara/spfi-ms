<?php

use App\Models\UserActivityLog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Remove noisy historical chat unread-messages poll visits.
     */
    public function up(): void
    {
        // Avoid JSON path operators (unsupported on this SQL Server setup).
        UserActivityLog::query()
            ->where('action', UserActivityLog::ACTION_ACTIVE)
            ->where(function ($query): void {
                $query->where('meta', 'like', '%chat.unread-messages%')
                    ->orWhere('meta', 'like', '%/chat/unread-messages%')
                    ->orWhere('meta', 'like', '%Chat%Unread Messages%');
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
