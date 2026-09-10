<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screen_messages', function (Blueprint $table) {
            $table->string('theme', 32)->default('default')->after('allow_reply');
        });
    }

    public function down(): void
    {
        Schema::table('screen_messages', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
