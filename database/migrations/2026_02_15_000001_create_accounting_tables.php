<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Grouping (dari tbl_Acct_SubGroup)
        Schema::create('groupings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('desc');
            $table->string('major', 2)->nullable();
            $table->integer('grp')->default(0);
            $table->integer('tab')->default(0);
            $table->boolean('other')->default(false);
            $table->boolean('selection')->default(false);
            $table->timestamps();
        });

        // Tabel Accounting Group Codes
        Schema::create('accounting_group_codes', function (Blueprint $table) {
            $table->id();
            $table->string('group_code', 10)->unique();
            $table->string('group_desc');
            $table->timestamps();
        });

        // Tabel Accounting Codes
        Schema::create('accounting_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('desc');
            $table->timestamps();
        });

        // Tabel Pivot: BS Grouping (many-to-many relationship)
        Schema::create('bs_groupings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_code_id')->constrained('accounting_group_codes')->onDelete(fk_on_delete('cascade'));
            $table->foreignId('accounting_code_id')->constrained('accounting_codes')->onDelete(fk_on_delete('cascade'));
            $table->foreignId('grouping_id')->nullable()->constrained('groupings')->onDelete(fk_on_delete('set null'));
            $table->string('major', 2)->nullable();
            $table->timestamps();

            $table->unique(['group_code_id', 'accounting_code_id'], 'unique_mapping');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_groupings');
        Schema::dropIfExists('accounting_codes');
        Schema::dropIfExists('accounting_group_codes');
        Schema::dropIfExists('groupings');
    }
};
