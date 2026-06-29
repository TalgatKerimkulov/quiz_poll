<?php

namespace App\Console\Commands;

use App\Jobs\TranslateWordsJob;
use App\Models\Word;
use Illuminate\Console\Command;

class TranslateWordsCommand extends Command
{
    protected $signature = 'words:translate
        {--target=ru : Target locale}
        {--source=en : Source locale}
        {--level= : Optional CEFR level}
        {--limit=100 : Maximum words to schedule}
        {--batch= : Words per AI request}
        {--sync : Execute now instead of using the queue}
        {--overwrite : Replace existing target translations}';

    protected $description = 'Translate missing word translations with the configured AI provider.';

    public function handle(): int
    {
        $target = strtolower(trim((string) $this->option('target')));
        $source = strtolower(trim((string) $this->option('source')));
        $limit = max(1, (int) $this->option('limit'));
        $batch = max(1, min(100, (int) ($this->option('batch') ?: config('ai.translation.batch_size', 20))));
        $overwrite = (bool) $this->option('overwrite');

        if (! in_array($target, config('telegram.locales', []), true) || ! in_array($source, config('telegram.locales', []), true)) {
            $this->error('Unsupported source or target locale.');

            return self::FAILURE;
        }
        if ($target === $source) {
            $this->error('Source and target locales must differ.');

            return self::FAILURE;
        }

        $query = Word::query()
            ->where('locale', $source)
            ->when($this->option('level'), fn ($query, $level) => $query->where('level', strtoupper((string) $level)))
            ->when(! $overwrite, fn ($query) => $query->whereDoesntHave(
                'translations',
                fn ($translation) => $translation->where('locale', $target),
            ))
            ->orderBy('id')
            ->limit($limit);

        $ids = $query->pluck('id');
        if ($ids->isEmpty()) {
            $this->info('No words require translation.');

            return self::SUCCESS;
        }

        foreach ($ids->chunk($batch) as $chunk) {
            $job = new TranslateWordsJob($chunk->values()->all(), $target, $overwrite);
            (bool) $this->option('sync') ? dispatch_sync($job) : dispatch($job);
        }

        $mode = $this->option('sync') ? 'translated' : 'queued';
        $this->info("Words {$mode}: {$ids->count()}; batches: {$ids->chunk($batch)->count()}.");

        return self::SUCCESS;
    }
}
