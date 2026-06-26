<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_user_word_stats', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id')->index();
            $table->bigInteger('telegram_user_id')->index();
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->timestamp('last_answered_at')->nullable();
            $table->timestamp('last_wrong_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_id', 'telegram_user_id', 'word_id'], 'tg_user_word_stats_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_user_word_stats');
    }
};
