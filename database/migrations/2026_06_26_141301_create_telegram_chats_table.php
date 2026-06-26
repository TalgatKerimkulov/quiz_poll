<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_chats', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id')->unique();
            $table->string('title')->nullable();
            $table->string('type');
            $table->boolean('is_active')->default(true)->index();
            $table->bigInteger('connected_by_user_id')->nullable();
            $table->string('connected_by_username')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_chats');
    }
};
