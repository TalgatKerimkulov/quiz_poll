<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('words', function (Blueprint $table): void {
            $table->string('part_of_speech')->nullable()->after('level');
            $table->text('example_en')->nullable()->after('part_of_speech');
            $table->text('example_ru')->nullable()->after('example_en');
            $table->bigInteger('created_by_user_id')->nullable()->after('is_active');
            $table->string('created_by_username')->nullable()->after('created_by_user_id');
            $table->index('word_en');
        });
    }

    public function down(): void
    {
        Schema::table('words', function (Blueprint $table): void {
            $table->dropIndex(['word_en']);
            $table->dropColumn([
                'part_of_speech',
                'example_en',
                'example_ru',
                'created_by_user_id',
                'created_by_username',
            ]);
        });
    }
};
