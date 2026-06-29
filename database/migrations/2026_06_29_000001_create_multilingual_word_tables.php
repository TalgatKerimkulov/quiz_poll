<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('words', function (Blueprint $table): void {
            $table->string('locale', 10)->default('en')->after('word_en')->index();
            $table->string('guideword')->nullable()->after('part_of_speech');
            $table->string('topic')->nullable()->after('guideword')->index();
            $table->string('source')->default('manual')->after('topic')->index();
            $table->string('source_key', 64)->nullable()->after('source');
            $table->unique(['source', 'source_key']);
        });

        Schema::create('word_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10)->index();
            $table->string('text', 500);
            $table->string('status')->default('imported')->index();
            $table->string('source')->default('manual');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['word_id', 'locale']);
        });

        Schema::create('word_examples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('text');
            $table->string('translation_locale', 10)->nullable();
            $table->text('translation_text')->nullable();
            $table->string('source')->default('manual');
            $table->timestamps();
        });

        DB::table('words')->orderBy('id')->each(function (object $word): void {
            if (trim((string) $word->translation_ru) !== '') {
                DB::table('word_translations')->insert([
                    'word_id' => $word->id,
                    'locale' => 'ru',
                    'text' => trim((string) $word->translation_ru),
                    'status' => 'reviewed',
                    'source' => 'legacy',
                    'reviewed_at' => now(),
                    'created_at' => $word->created_at,
                    'updated_at' => $word->updated_at,
                ]);
            }

            if (trim((string) $word->example_en) !== '') {
                DB::table('word_examples')->insert([
                    'word_id' => $word->id,
                    'locale' => 'en',
                    'text' => trim((string) $word->example_en),
                    'translation_locale' => trim((string) $word->example_ru) !== '' ? 'ru' : null,
                    'translation_text' => trim((string) $word->example_ru) ?: null,
                    'source' => 'legacy',
                    'created_at' => $word->created_at,
                    'updated_at' => $word->updated_at,
                ]);
            }
        });

        Schema::table('words', function (Blueprint $table): void {
            $table->dropUnique(['word_en', 'translation_ru']);
            $table->dropColumn(['translation_ru', 'example_en', 'example_ru']);
            $table->renameColumn('word_en', 'term');
        });
    }

    public function down(): void
    {
        Schema::table('words', function (Blueprint $table): void {
            $table->renameColumn('term', 'word_en');
            $table->string('translation_ru')->default('')->after('word_en');
            $table->text('example_en')->nullable();
            $table->text('example_ru')->nullable();
        });

        DB::table('word_translations')->where('locale', 'ru')->orderBy('id')->each(function (object $translation): void {
            DB::table('words')->where('id', $translation->word_id)->update(['translation_ru' => $translation->text]);
        });

        Schema::dropIfExists('word_examples');
        Schema::dropIfExists('word_translations');

        Schema::table('words', function (Blueprint $table): void {
            $table->dropUnique(['source', 'source_key']);
            $table->dropIndex(['locale']);
            $table->dropIndex(['topic']);
            $table->dropIndex(['source']);
        });

        Schema::table('words', function (Blueprint $table): void {
            $table->dropColumn(['locale', 'guideword', 'topic', 'source', 'source_key']);
            $table->unique(['word_en', 'translation_ru']);
        });
    }
};
