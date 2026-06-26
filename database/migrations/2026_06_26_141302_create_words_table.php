<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('words', function (Blueprint $table): void {
            $table->id();
            $table->string('word_en');
            $table->string('translation_ru');
            $table->string('level')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['word_en', 'translation_ru']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
