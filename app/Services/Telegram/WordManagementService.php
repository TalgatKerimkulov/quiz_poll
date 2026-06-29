<?php

namespace App\Services\Telegram;

use App\Models\Word;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WordManagementService
{
    public const LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    public function add(
        string $term,
        string $translation,
        ?string $level = null,
        ?int $userId = null,
        ?string $username = null,
        string $targetLocale = 'ru',
        string $sourceLocale = 'en',
    ): Word {
        $term = mb_strtolower(trim($term));
        $translation = trim($translation);
        $level = $level ? mb_strtoupper(trim($level)) : null;

        if ($term === '' || $translation === '') {
            throw new InvalidArgumentException('Term and translation are required.');
        }
        if ($level !== null && ! in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException('Invalid level.');
        }

        $word = Word::query()
            ->where('term', $term)
            ->where('locale', $sourceLocale)
            ->where('level', $level)
            ->whereHas('translations', fn ($query) => $query
                ->where('locale', $targetLocale)
                ->where('text', $translation))
            ->first();

        if (! $word) {
            $word = Word::create([
                'term' => $term,
                'locale' => $sourceLocale,
                'level' => $level,
                'source' => 'manual',
                'is_active' => true,
                'created_by_user_id' => $userId,
                'created_by_username' => $username,
            ]);
            $word->translations()->create([
                'locale' => $targetLocale,
                'text' => $translation,
                'status' => 'reviewed',
                'source' => 'manual',
                'reviewed_at' => now(),
            ]);
        }

        return $word->load('translations');
    }

    public function disable(string $term): int
    {
        return Word::where('term', mb_strtolower(trim($term)))->update(['is_active' => false]);
    }

    public function find(string $query): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        return Word::with('translations')
            ->where(fn ($builder) => $builder
                ->where('term', 'like', "%{$query}%")
                ->orWhereHas('translations', fn ($translations) => $translations->where('text', 'like', "%{$query}%")))
            ->orderBy('term')
            ->limit(10)
            ->get();
    }
}
