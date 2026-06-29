<?php

namespace App\Jobs;

use App\Models\Word;
use App\Services\Translation\Contracts\TranslationProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TranslateWordsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<int, int> $wordIds */
    public function __construct(
        public array $wordIds,
        public string $targetLocale = 'ru',
        public bool $overwrite = false,
    ) {
        // Isolate AI translation on its own queue so only the host worker
        // (which can reach the local Ollama) processes these jobs.
        $this->onQueue('translation');
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(TranslationProvider $provider): void
    {
        $words = Word::query()
            ->with(['translations', 'examples'])
            ->whereIn('id', $this->wordIds)
            ->when(! $this->overwrite, fn ($query) => $query->whereDoesntHave(
                'translations',
                fn ($translation) => $translation->where('locale', $this->targetLocale),
            ))
            ->get();

        $results = $provider->translate($words, $this->targetLocale);

        foreach ($words as $word) {
            // The provider may legitimately skip words it could not translate this run;
            // those stay untranslated and are retried by a later words:translate invocation.
            $result = $results[$word->id] ?? null;
            if ($result === null) {
                continue;
            }
            $word->translations()->updateOrCreate(
                ['locale' => $this->targetLocale],
                [
                    'text' => $result['translation'],
                    'status' => 'machine',
                    'source' => 'ai',
                    'provider' => $provider->name(),
                    'model' => $provider->model(),
                    'confidence' => $result['confidence'],
                    'review_notes' => $result['notes'],
                    'reviewed_at' => null,
                ],
            );
        }
    }
}
