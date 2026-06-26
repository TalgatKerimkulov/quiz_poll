<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_polls', function (Blueprint $table): void {
            $table->id();
            $table->string('telegram_poll_id')->nullable()->index();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->string('chat_id')->index();
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->json('options');
            $table->json('correct_option_ids');
            $table->timestamp('sent_at')->index();
            $table->boolean('is_dry_run')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_polls');
    }
};
