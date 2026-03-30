<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('term_of_payment_type')->nullable()->after('contact_person');
            $table->string('term_of_payment')->nullable()->after('term_of_payment_type');
            $table->string('term_of_delivery')->nullable()->after('term_of_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'term_of_payment_type',
                'term_of_payment',
                'term_of_delivery',
            ]);
        });
    }
};
