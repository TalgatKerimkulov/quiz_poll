<?php

namespace App\Services\Translation;

use App\Models\Word;
use App\Services\Translation\Contracts\TranslationProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaTranslationProvider implements TranslationProvider
{
    public function translate(Collection $words, string $targetLocale): array
    {
        if ($words->isEmpty()) {
            return [];
        }

        $options = ['temperature' => 0];
        // Cap CPU threads so inference does not starve the host (0 = let Ollama use all cores).
        $threads = (int) config('ai.ollama.num_thread', 0);
        if ($threads > 0) {
            $options['num_thread'] = $threads;
        }

        $response = $this->client()->post('/api/chat', [
            'model' => $this->model(),
            'stream' => false,
            'options' => $options,
            'format' => $this->schema(),
            'messages' => [
                ['role' => 'system', 'content' => $this->instructions($targetLocale)],
                ['role' => 'user', 'content' => json_encode($words->map(fn (Word $word): array => [
                    'word_id' => $word->id,
                    'term' => $word->term,
                    'source_locale' => $word->locale,
                    'level' => $word->level,
                    'part_of_speech' => $word->part_of_speech,
                    'guideword' => $word->guideword,
                    'topic' => $word->topic,
                    'examples' => $word->examples->map(fn ($example): array => [
                        'locale' => $example->locale,
                        'text' => $example->text,
                    ])->all(),
                ])->values()->all(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            ],
        ])->throw();

        $text = $response->json('message.content');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Ollama returned no translation payload.');
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
            $confidence = isset($item['confidence']) ? (float) $item['confidence'] : null;
            $notes = trim((string) ($item['notes'] ?? '')) ?: null;

            // A Russian translation must be Cyrillic. Stray Latin letters mean the small model
            // echoed English or mixed scripts (e.g. "miss", "мrs.", "пainted") — force the
            // confidence to 0 and flag it so it surfaces first during manual review.
            if (preg_match('/[A-Za-z]/', $translation) === 1) {
                $confidence = 0.0;
                $notes = trim('Содержит латиницу — требуется проверка. '.($notes ?? ''));
            }

            $translations[$wordId] = [
                'translation' => $translation,
                'confidence' => $confidence,
                'notes' => $notes,
            ];
        }

        // A small local model occasionally drops or malforms items. Rather than failing the
        // whole batch, return the valid subset; un-returned words keep no ru translation and are
        // picked up by a later words:translate run (which filters on whereDoesntHave ru).
        if ($translations === []) {
            throw new RuntimeException('Ollama returned no usable translations for this batch.');
        }

        return $translations;
    }

    public function name(): string
    {
        return 'ollama';
    }

    public function model(): string
    {
        return (string) config('ai.ollama.model', 'qwen2.5:3b');
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('ai.ollama.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('ai.ollama.timeout', 300))
            ->retry(2, 1000);
    }

    /**
     * JSON schema Ollama enforces on the model output.
     *
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return [
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
                    ],
                ],
            ],
            'required' => ['items'],
        ];
    }

    protected function instructions(string $targetLocale): string
    {
        return <<<PROMPT
You are a professional English->Russian lexicographer. Translate every input entry into Russian.

HARD RULES:
- Output Russian ONLY, written in Cyrillic letters. Never output Uzbek, English, Latin
  transliteration, or any other language. If unsure, give the best Russian dictionary equivalent.
- Translate the WHOLE entry, including multi-word phrases, as a natural Russian equivalent
  (e.g. "happy birthday" -> "с днём рождения", "it is raining" -> "идёт дождь"). Do not translate
  word-by-word and do not invent unrelated words.
- Use natural lowercase dictionary forms. Disambiguate the sense with part_of_speech, guideword,
  topic and the English examples.
- confidence is 0..1 and must honestly reflect doubt: use < 0.7 when the sense is ambiguous or you
  are guessing. notes is an empty string when fine; otherwise briefly explain the ambiguity in Russian.
- Never omit an item, never change word_id, keep each translation within 100 characters.

Reply with JSON only.
PROMPT;
    }
}
