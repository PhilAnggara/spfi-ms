<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screen_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete(fk_on_delete('restrict'));
            $table->string('title');
            $table->text('body');
            $table->string('display_mode', 32);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('audience_type', 32);
            $table->boolean('allow_reply')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->unsignedBigInteger('deactivated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'created_at']);
            $table->index('user_id');
            $table->index('deactivated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_messages');
    }
};
