<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('count_tag_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')
                ->constrained('count_tag_locations', indexName: 'ct_section_location_fk')
                ->onDelete(fk_on_delete('cascade'));
            $table->string('code', 10);
            $table->unsignedInteger('max_row')->default(0);
            $table->unsignedInteger('max_column')->default(0);
            $table->unsignedBigInteger('legacy_id')->unique();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'code'], 'ct_section_location_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('count_tag_sections');
    }
};
