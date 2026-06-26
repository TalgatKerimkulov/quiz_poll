<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table): void {
            $table->string('inactive_reason')->nullable()->after('is_active');
            $table->timestamp('bot_status_updated_at')->nullable()->after('connected_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table): void {
            $table->dropColumn(['inactive_reason', 'bot_status_updated_at']);
        });
    }
};
