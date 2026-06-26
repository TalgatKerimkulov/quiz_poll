<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_polls', function (Blueprint $table): void {
            $table->string('status')->default('sent')->index()->after('correct_option_ids');
            $table->string('direction')->nullable()->after('status');
            $table->string('level')->nullable()->after('direction');
            $table->unsignedInteger('open_period')->default(1800)->after('level');
            $table->text('error_message')->nullable()->after('open_period');
            $table->timestamp('closed_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_polls', function (Blueprint $table): void {
            $table->dropColumn([
                'status',
                'direction',
                'level',
                'open_period',
                'error_message',
                'closed_at',
            ]);
        });
    }
};
