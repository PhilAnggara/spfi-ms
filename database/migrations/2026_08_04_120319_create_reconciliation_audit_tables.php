<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('dataset', 64);
            $table->string('legacy_key', 128);
            $table->string('action', 32);
            $table->unsignedBigInteger('new_id')->nullable();
            $table->string('spfi_number', 128)->nullable();
            $table->string('status', 32)->default('imported');
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['dataset', 'legacy_key']);
            $table->index(['created_at']);
        });

        Schema::create('reconciliation_number_maps', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 32);
            $table->string('ims_number', 128);
            $table->string('spfi_number', 128);
            $table->string('existing_spfi_number', 128)->nullable();
            $table->string('resolution', 32)->default('import_as_alias');
            $table->string('ims_fingerprint', 64)->nullable();
            $table->string('spfi_fingerprint', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['document_type', 'ims_number', 'spfi_number'], 'recon_num_map_unique');
            $table->index(['document_type', 'ims_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_number_maps');
        Schema::dropIfExists('reconciliation_import_logs');
    }
};
