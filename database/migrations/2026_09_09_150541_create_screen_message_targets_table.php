<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screen_message_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_message_id')->constrained('screen_messages')->onDelete(fk_on_delete('cascade'));
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->unique(['screen_message_id', 'target_type', 'target_id'], 'screen_message_targets_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_message_targets');
    }
};
