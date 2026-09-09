<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screen_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_message_id')->constrained('screen_messages')->onDelete(fk_on_delete('cascade'));
            $table->unsignedBigInteger('user_id');
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete(fk_on_delete('restrict'));
            $table->unique(['screen_message_id', 'user_id'], 'screen_message_recipients_unique');
            $table->index(['user_id', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_message_recipients');
    }
};
