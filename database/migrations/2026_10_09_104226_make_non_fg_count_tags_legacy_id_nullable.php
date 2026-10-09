<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_fg_count_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('non_fg_count_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable(false)->change();
        });
    }
};
