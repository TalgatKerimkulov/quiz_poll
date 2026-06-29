<?php

namespace App\Services\Telegram;

use App\Models\Word;
use Illuminate\Support\Collection;
use RuntimeException;

class QuizOptionBuilder
{
    public function build(Word $word, string $direction, string $targetLocale): array
    {
        if ($direction === 'mixed') {
            $direction = random_int(0, 1) === 1 ? 'forward' : 'reverse';
        }

        $translation = $word->translationFor($targetLocale);
        if (! $translation) {
            throw new RuntimeException("Word has no {$targetLocale} translation.");
        }

        $correctText = $direction === 'forward' ? $translation->text : $word->term;
        $wrong = $this->wrongOptions($word, $direction, $targetLocale, $correctText);
        if ($wrong->count() < 3) {
            throw new RuntimeException('Not enough unique wrong options for quiz poll.');
        }

        $options = $wrong->take(3)->push($correctText)->shuffle()->values()->all();

        return [
            'direction' => $direction,
            'question' => $direction === 'forward'
                ? "Как переводится: {$word->term}?"
                : "Как по-английски: {$translation->text}?",
            'options' => $options,
            'correct_option_ids' => [array_search($correctText, $options, true)],
            'explanation' => "{$word->term} = {$translation->text}",
        ];
    }

    protected function wrongOptions(Word $word, string $direction, string $targetLocale, string $correctText): Collection
    {
        $query = Word::query()
            ->where('words.is_active', true)
            ->where('words.locale', $word->locale)
            ->where('words.id', '!=', $word->id)
            ->join('word_translations', function ($join) use ($targetLocale): void {
                $join->on('word_translations.word_id', '=', 'words.id')
                    ->where('word_translations.locale', $targetLocale);
            });

        $column = $direction === 'forward' ? 'word_translations.text' : 'words.term';
        $query->where($column, '!=', $correctText);

        $sameLevel = (clone $query)
            ->when($word->level, fn ($builder) => $builder->where('words.level', $word->level))
            ->inRandomOrder()
            ->pluck($column)
            ->unique()
            ->values();

        if ($sameLevel->count() >= 3) {
            return $sameLevel;
        }

        return $sameLevel->merge($query->inRandomOrder()->pluck($column))->unique()->values();
    }
}
