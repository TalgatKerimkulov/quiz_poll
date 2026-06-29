<?php

namespace Tests\Feature;

use App\Jobs\TranslateWordsJob;
use App\Models\Word;
use App\Services\Translation\Contracts\TranslationProvider;
use App\Services\Translation\OpenAiTranslationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MultilingualWordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_semicolon_csv_idempotently_with_translations_and_examples(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'words-');
        file_put_contents($path, implode("\n", [
            'Base Word;Translate (uzbek);Part of Speech;Sentence (English);Translation (Uzbek);Level;Topic;Details;;',
            'boot;etik;noun;He wears boots.;U etik kiyadi.;A1;clothes;Details;;',
            'abroad;;;;;A1;travel;Details;;',
            ';;;;;;;;;',
        ]));

        try {
            $this->artisan('words:import', ['path' => $path])->assertSuccessful();
            $this->artisan('words:import', ['path' => $path])->assertSuccessful();
        } finally {
            unlink($path);
        }

        $this->assertDatabaseCount('words', 2);
        $this->assertDatabaseCount('word_translations', 1);
        $this->assertDatabaseCount('word_examples', 1);
        $this->assertDatabaseHas('words', ['term' => 'boot', 'locale' => 'en', 'level' => 'A1', 'topic' => 'clothes']);
        $this->assertDatabaseHas('word_translations', ['locale' => 'uz', 'text' => 'etik', 'status' => 'imported']);
    }

    public function test_openai_provider_requests_structured_batch_and_parses_result(): void
    {
        config([
            'ai.openai.api_key' => 'test-key',
            'ai.openai.model' => 'gpt-4o-mini',
        ]);
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode(['items' => [[
                            'word_id' => 1,
                            'translation' => 'яблоко',
                            'confidence' => 0.99,
                            'notes' => '',
                        ]]], JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
            ]),
        ]);

        $word = Word::create(['id' => 1, 'term' => 'apple', 'locale' => 'en', 'source' => 'test']);
        $result = app(OpenAiTranslationProvider::class)->translate(collect([$word->load(['translations', 'examples'])]), 'ru');

        $this->assertSame('яблоко', $result[$word->id]['translation']);
        $this->assertSame(0.99, $result[$word->id]['confidence']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'gpt-4o-mini'
            && data_get($request->data(), 'text.format.type') === 'json_schema');
    }

    public function test_translate_command_batches_missing_translations_on_queue(): void
    {
        Queue::fake();
        foreach (['apple', 'book', 'water'] as $term) {
            Word::create(['term' => $term, 'locale' => 'en', 'source' => 'test']);
        }

        $this->artisan('words:translate', ['--target' => 'ru', '--limit' => 3, '--batch' => 2])->assertSuccessful();

        Queue::assertPushed(TranslateWordsJob::class, 2);
    }

    public function test_translation_job_persists_machine_translation_audit_data(): void
    {
        $word = Word::create(['term' => 'apple', 'locale' => 'en', 'source' => 'test']);
        $this->app->instance(TranslationProvider::class, new class implements TranslationProvider
        {
            public function translate(Collection $words, string $targetLocale): array
            {
                return [$words->first()->id => [
                    'translation' => 'яблоко',
                    'confidence' => 0.98,
                    'notes' => null,
                ]];
            }

            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'fake-model';
            }
        });

        dispatch_sync(new TranslateWordsJob([$word->id], 'ru'));

        $this->assertDatabaseHas('word_translations', [
            'word_id' => $word->id,
            'locale' => 'ru',
            'text' => 'яблоко',
            'status' => 'machine',
            'provider' => 'fake',
            'model' => 'fake-model',
        ]);
    }
}
