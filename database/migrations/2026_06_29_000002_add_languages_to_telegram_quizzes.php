<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_chat_settings', function (Blueprint $table): void {
            $table->string('source_locale', 10)->default('en')->after('level');
            $table->string('target_locale', 10)->default('ru')->after('source_locale');
        });

        Schema::table('telegram_polls', function (Blueprint $table): void {
            $table->string('source_locale', 10)->nullable()->after('direction');
            $table->string('target_locale', 10)->nullable()->after('source_locale');
        });

        DB::table('telegram_chat_settings')->where('direction', 'en_ru')->update(['direction' => 'forward']);
        DB::table('telegram_chat_settings')->where('direction', 'ru_en')->update(['direction' => 'reverse']);
        DB::table('telegram_polls')->where('direction', 'en_ru')->update(['direction' => 'forward', 'source_locale' => 'en', 'target_locale' => 'ru']);
        DB::table('telegram_polls')->where('direction', 'ru_en')->update(['direction' => 'reverse', 'source_locale' => 'en', 'target_locale' => 'ru']);

        Schema::table('telegram_chat_settings', function (Blueprint $table): void {
            $table->string('direction')->default('forward')->change();
        });
    }

    public function down(): void
    {
        DB::table('telegram_chat_settings')->where('direction', 'forward')->update(['direction' => 'en_ru']);
        DB::table('telegram_chat_settings')->where('direction', 'reverse')->update(['direction' => 'ru_en']);

        Schema::table('telegram_chat_settings', function (Blueprint $table): void {
            $table->string('direction')->default('en_ru')->change();
        });

        Schema::table('telegram_polls', function (Blueprint $table): void {
            $table->dropColumn(['source_locale', 'target_locale']);
        });
        Schema::table('telegram_chat_settings', function (Blueprint $table): void {
            $table->dropColumn(['source_locale', 'target_locale']);
        });
    }
};
