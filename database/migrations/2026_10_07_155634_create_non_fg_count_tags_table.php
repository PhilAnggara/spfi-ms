<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('non_fg_count_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->unique();
            $table->string('count_tag_number')->nullable();
            $table->date('count_tag_date')->nullable();
            $table->foreignId('item_id')
                ->nullable()
                ->constrained('items', indexName: 'non_fg_ct_item_fk')
                ->onDelete(fk_on_delete('set null'));
            $table->string('item_code');
            $table->foreignId('item_category_id')
                ->nullable()
                ->constrained('item_categories', indexName: 'non_fg_ct_category_fk')
                ->onDelete(fk_on_delete('set null'));
            $table->foreignId('unit_of_measure_id')
                ->nullable()
                ->constrained('unit_of_measures', indexName: 'non_fg_ct_uom_fk')
                ->onDelete(fk_on_delete('set null'));
            $table->string('uom_code')->nullable();
            $table->foreignId('location_id')
                ->nullable()
                ->constrained('count_tag_locations', indexName: 'non_fg_ct_location_fk')
                ->onDelete(fk_on_delete('set null'));
            $table->string('location_name')->nullable();
            $table->foreignId('section_id')
                ->nullable()
                ->constrained('count_tag_sections', indexName: 'non_fg_ct_section_fk')
                ->onDelete(fk_on_delete('set null'));
            $table->string('section_code', 10)->nullable();
            $table->unsignedInteger('row')->nullable();
            $table->unsignedInteger('col')->nullable();
            $table->unsignedInteger('level')->nullable();
            $table->string('size')->nullable();
            $table->string('condition')->nullable();
            $table->decimal('qty', 20, 5)->default(0);
            $table->date('tran_date')->nullable();
            $table->string('group_name')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', indexName: 'non_fg_ct_created_by_fk')
                ->onDelete(fk_on_delete('set null'));
            $table->string('created_by_name')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tran_date', 'non_fg_ct_tran_date_idx');
            $table->index('item_code', 'non_fg_ct_item_code_idx');
            $table->index('count_tag_date', 'non_fg_ct_count_tag_date_idx');
            $table->index(['item_code', 'tran_date'], 'non_fg_ct_item_tran_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_fg_count_tags');
    }
};
