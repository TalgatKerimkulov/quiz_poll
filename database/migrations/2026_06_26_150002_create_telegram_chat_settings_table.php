<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_chat_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telegram_chat_id')->constrained()->cascadeOnDelete();
            $table->string('timezone')->default('Asia/Tashkent');
            $table->unsignedSmallInteger('polls_per_day')->default(20);
            $table->time('start_time')->default('09:00');
            $table->time('end_time')->default('23:00');
            $table->unsignedInteger('poll_open_period')->default(1800);
            $table->string('level')->nullable()->index();
            $table->string('direction')->default('en_ru');
            $table->boolean('repeat_mistakes_enabled')->default(true);
            $table->json('weekdays')->nullable();
            $table->boolean('is_paused')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_chat_settings');
    }
};
