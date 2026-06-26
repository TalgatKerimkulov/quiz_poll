<?php

namespace App\Services\Telegram;

use App\Models\Word;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WordManagementService
{
    public const LEVELS = ['A1', 'A2', 'B1', 'B2'];

    public function add(string $wordEn, string $translationRu, ?string $level = null, ?int $userId = null, ?string $username = null): Word
    {
        $wordEn = mb_strtolower(trim($wordEn));
        $translationRu = trim($translationRu);
        $level = $level ? mb_strtoupper(trim($level)) : null;

        if ($wordEn === '' || $translationRu === '') {
            throw new InvalidArgumentException('English word and Russian translation are required.');
        }
        if ($level !== null && ! in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException('Invalid level.');
        }

        return Word::firstOrCreate(
            ['word_en' => $wordEn, 'translation_ru' => $translationRu],
            [
                'level' => $level,
                'is_active' => true,
                'created_by_user_id' => $userId,
                'created_by_username' => $username,
            ],
        );
    }

    public function disable(string $wordEn): int
    {
        return Word::where('word_en', mb_strtolower(trim($wordEn)))->update(['is_active' => false]);
    }

    public function find(string $query): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        return Word::where('word_en', 'like', "%{$query}%")
            ->orWhere('translation_ru', 'like', "%{$query}%")
            ->orderBy('word_en')
            ->limit(10)
            ->get();
    }
}
