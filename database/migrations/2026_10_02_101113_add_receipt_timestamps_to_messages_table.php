<?php

use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('messages', 'delivered_at')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->timestamp('delivered_at')->nullable()->after('attachment_height');
                $table->timestamp('read_at')->nullable()->after('delivered_at');
                $table->index(['conversation_id', 'delivered_at']);
                $table->index(['conversation_id', 'read_at']);
            });
        }

        ConversationParticipant::query()
            ->whereNotNull('last_delivered_at')
            ->orderBy('id')
            ->each(function (ConversationParticipant $participant): void {
                Message::query()
                    ->where('conversation_id', $participant->conversation_id)
                    ->where('user_id', '!=', $participant->user_id)
                    ->whereNull('delivered_at')
                    ->where('created_at', '<=', $participant->last_delivered_at)
                    ->update(['delivered_at' => $participant->last_delivered_at]);
            });

        ConversationParticipant::query()
            ->whereNotNull('last_read_at')
            ->orderBy('id')
            ->each(function (ConversationParticipant $participant): void {
                Message::query()
                    ->where('conversation_id', $participant->conversation_id)
                    ->where('user_id', '!=', $participant->user_id)
                    ->whereNull('read_at')
                    ->where('created_at', '<=', $participant->last_read_at)
                    ->update(['read_at' => $participant->last_read_at]);

                Message::query()
                    ->where('conversation_id', $participant->conversation_id)
                    ->where('user_id', '!=', $participant->user_id)
                    ->whereNull('delivered_at')
                    ->where('created_at', '<=', $participant->last_read_at)
                    ->update(['delivered_at' => $participant->last_read_at]);
            });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'delivered_at']);
            $table->dropIndex(['conversation_id', 'read_at']);
            $table->dropColumn(['delivered_at', 'read_at']);
        });
    }
};
