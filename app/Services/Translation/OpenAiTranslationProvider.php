<?php

namespace App\Services\Translation;

use App\Models\Word;
use App\Services\Translation\Contracts\TranslationProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiTranslationProvider implements TranslationProvider
{
    public function translate(Collection $words, string $targetLocale): array
    {
        if ($words->isEmpty()) {
            return [];
        }

        $response = $this->client()->post('/responses', [
            'model' => $this->model(),
            'instructions' => $this->instructions($targetLocale),
            'input' => json_encode($words->map(fn (Word $word): array => [
                'word_id' => $word->id,
                'term' => $word->term,
                'source_locale' => $word->locale,
                'level' => $word->level,
                'part_of_speech' => $word->part_of_speech,
                'guideword' => $word->guideword,
                'topic' => $word->topic,
                'known_translations' => $word->translations->mapWithKeys(
                    fn ($translation): array => [$translation->locale => $translation->text],
                )->all(),
                'examples' => $word->examples->map(fn ($example): array => [
                    'locale' => $example->locale,
                    'text' => $example->text,
                ])->all(),
            ])->values()->all(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'word_translations',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'word_id' => ['type' => 'integer'],
                                        'translation' => ['type' => 'string'],
                                        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                                        'notes' => ['type' => 'string'],
                                    ],
                                    'required' => ['word_id', 'translation', 'confidence', 'notes'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['items'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ])->throw();

        $text = collect($response->json('output', []))
            ->flatMap(fn (array $output): array => $output['content'] ?? [])
            ->firstWhere('type', 'output_text')['text'] ?? null;
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('OpenAI returned no translation payload.');
        }

        $payload = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        $requested = $words->pluck('id')->flip();
        $translations = [];

        foreach ($payload['items'] ?? [] as $item) {
            $wordId = (int) ($item['word_id'] ?? 0);
            $translation = trim((string) ($item['translation'] ?? ''));
            if (! $requested->has($wordId) || $translation === '' || mb_strlen($translation) > 100) {
                continue;
            }
            $translations[$wordId] = [
                'translation' => $translation,
                'confidence' => isset($item['confidence']) ? (float) $item['confidence'] : null,
                'notes' => trim((string) ($item['notes'] ?? '')) ?: null,
            ];
        }

        if (count($translations) !== $words->count()) {
            throw new RuntimeException('OpenAI did not return every requested translation.');
        }

        return $translations;
    }

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('ai.openai.model', 'gpt-4o-mini');
    }

    protected function client(): PendingRequest
    {
        $apiKey = (string) config('ai.openai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('ai.openai.base_url'), '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('ai.openai.timeout', 60))
            ->retry(2, 500);
    }

    protected function instructions(string $targetLocale): string
    {
        return <<<PROMPT
You are a lexicographer translating source vocabulary into {$targetLocale}.
Return one concise dictionary translation for every input word. Use the guideword, part of speech,
topic, known translations and examples to preserve the intended sense. For Russian, use natural
lowercase dictionary forms. Self-review every translation before returning it. Confidence is from
0 to 1. Notes must be an empty string when no warning is needed; otherwise briefly explain ambiguity.
Never omit an item, never change word_id, and keep each translation within 100 characters.
PROMPT;
    }
}
