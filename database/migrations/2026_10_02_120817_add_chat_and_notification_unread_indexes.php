<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Portable composite indexes for notification/chat unread hot paths.
     * Avoids driver-specific filtered indexes / index hints.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! $this->hasIndex('notifications', 'notifications_notifiable_read_at_index')) {
                $table->index(
                    ['notifiable_type', 'notifiable_id', 'read_at'],
                    'notifications_notifiable_read_at_index'
                );
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            if (! $this->hasIndex('messages', 'messages_conversation_user_created_index')) {
                $table->index(
                    ['conversation_id', 'user_id', 'created_at'],
                    'messages_conversation_user_created_index'
                );
            }

            if (! $this->hasIndex('messages', 'messages_conversation_persona_created_index')) {
                $table->index(
                    ['conversation_id', 'persona', 'created_at'],
                    'messages_conversation_persona_created_index'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if ($this->hasIndex('messages', 'messages_conversation_persona_created_index')) {
                $table->dropIndex('messages_conversation_persona_created_index');
            }

            if ($this->hasIndex('messages', 'messages_conversation_user_created_index')) {
                $table->dropIndex('messages_conversation_user_created_index');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if ($this->hasIndex('notifications', 'notifications_notifiable_read_at_index')) {
                $table->dropIndex('notifications_notifiable_read_at_index');
            }
        });
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
            $result = \Illuminate\Support\Facades\DB::selectOne(
                'SELECT TOP 1 1 AS [found]
                 FROM sys.indexes i
                 INNER JOIN sys.tables t ON i.object_id = t.object_id
                 INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
                 WHERE t.name = ?
                   AND i.name = ?
                   AND s.name = SCHEMA_NAME()',
                [$tableName, $indexName]
            );

            return $result !== null;
        }

        return Schema::hasIndex($tableName, $indexName);
    }
};
