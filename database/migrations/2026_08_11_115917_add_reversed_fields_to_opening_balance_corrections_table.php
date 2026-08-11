<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opening_balance_corrections', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('meta');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')
                ->constrained('users')
                ->onDelete(fk_on_delete('set null'));
        });
    }

    public function down(): void
    {
        Schema::table('opening_balance_corrections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn('reversed_at');
        });
    }
};
