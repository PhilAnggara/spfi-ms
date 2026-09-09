<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screen_message_recipients', function (Blueprint $table) {
            $table->timestamp('overlay_expires_at')->nullable()->after('dismissed_at');
            $table->index('overlay_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('screen_message_recipients', function (Blueprint $table) {
            $table->dropIndex(['overlay_expires_at']);
            $table->dropColumn('overlay_expires_at');
        });
    }
};
