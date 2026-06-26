<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_poll_answers', function (Blueprint $table): void {
            $table->id();
            $table->string('telegram_poll_id')->index();
            $table->string('chat_id')->nullable();
            $table->bigInteger('telegram_user_id')->index();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->json('option_ids');
            $table->boolean('is_correct');
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['telegram_poll_id', 'telegram_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_poll_answers');
    }
};
