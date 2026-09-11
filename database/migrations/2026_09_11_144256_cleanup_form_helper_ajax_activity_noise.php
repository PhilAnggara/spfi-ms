<?php

use App\Models\UserActivityLog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Remove noisy historical form-helper / chart AJAX "visit" rows.
     */
    public function up(): void
    {
        // Avoid JSON path operators (unsupported on this SQL Server setup).
        UserActivityLog::query()
            ->where('action', UserActivityLog::ACTION_ACTIVE)
            ->where(function ($query): void {
                $query->where('meta', 'like', '%sws-by-number%')
                    ->orWhere('meta', 'like', '%po-by-number%')
                    ->orWhere('meta', 'like', '%capex-lines%')
                    ->orWhere('meta', 'like', '%items/search%')
                    ->orWhere('meta', 'like', '%items.search%')
                    ->orWhere('meta', 'like', '%account-lookup%')
                    ->orWhere('meta', 'like', '%check-code%')
                    ->orWhere('meta', 'like', '%open-prs-heatmap%')
                    ->orWhere('meta', 'like', '%preview-sample%')
                    ->orWhere('meta', 'like', '%screen-messages/%/live%')
                    ->orWhere('meta', 'like', '%screen-messages.live%')
                    ->orWhere('meta', 'like', '%Sws By Number%')
                    ->orWhere('meta', 'like', '%Po By Number%')
                    ->orWhere('meta', 'like', '%Capex Lines%')
                    ->orWhere('meta', 'like', '%Account Lookup%')
                    ->orWhere('meta', 'like', '%Check Code%')
                    ->orWhere('meta', 'like', '%Open Prs Heatmap%')
                    ->orWhere('meta', 'like', '%Preview Sample%');
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
