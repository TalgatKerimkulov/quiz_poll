<?php

namespace App\Services\Telegram;

use App\Models\Word;
use Illuminate\Support\Collection;
use RuntimeException;

class QuizOptionBuilder
{
    public function build(Word $word, string $direction): array
    {
        if ($direction === 'mixed') {
            $direction = random_int(0, 1) === 1 ? 'en_ru' : 'ru_en';
        }

        $correctText = $this->answerText($word, $direction);
        $wrong = $this->wrongOptions($word, $direction);

        if ($wrong->count() < 3) {
            throw new RuntimeException('Not enough unique wrong options for quiz poll.');
        }

        $options = $wrong->take(3)->push($correctText)->shuffle()->values()->all();

        return [
            'direction' => $direction,
            'question' => $direction === 'en_ru'
                ? "Как переводится слово: {$word->word_en}?"
                : "Как по-английски: {$word->translation_ru}?",
            'options' => $options,
            'correct_option_ids' => [array_search($correctText, $options, true)],
            'explanation' => "{$word->word_en} = {$word->translation_ru}",
        ];
    }

    protected function wrongOptions(Word $word, string $direction): Collection
    {
        $column = $direction === 'en_ru' ? 'translation_ru' : 'word_en';
        $correctText = $this->answerText($word, $direction);

        $query = Word::where('is_active', true)
            ->where('id', '!=', $word->id)
            ->where($column, '!=', $correctText);

        $sameLevel = (clone $query)
            ->when($word->level, fn ($q) => $q->where('level', $word->level))
            ->inRandomOrder()
            ->pluck($column)
            ->unique()
            ->values();

        if ($sameLevel->count() >= 3) {
            return $sameLevel;
        }

        return $sameLevel
            ->merge($query->inRandomOrder()->pluck($column))
            ->unique()
            ->values();
    }

    protected function answerText(Word $word, string $direction): string
    {
        return $direction === 'en_ru' ? $word->translation_ru : $word->word_en;
    }
}
