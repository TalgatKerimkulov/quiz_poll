<?php

namespace App\Services\Telegram;

use App\Models\TelegramChat;
use App\Models\TelegramPoll;
use App\Models\TelegramUserWordStat;
use App\Models\Word;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WordSelectionService
{
    public function select(TelegramChat $chat, CarbonImmutable $from, CarbonImmutable $to): ?Word
    {
        $settings = $chat->ensureSettings();
        $base = Word::where('is_active', true)
            ->when($settings->level, fn ($query) => $query->where('level', $settings->level));

        $askedToday = TelegramPoll::where('chat_id', $chat->chat_id)
            ->whereBetween('sent_at', [$from, $to])
            ->pluck('word_id');

        $available = (clone $base)->whereNotIn('id', $askedToday)->get();
        if ($available->isEmpty()) {
            $available = $base->get();
        }

        $available = $available->filter(fn (Word $word): bool => $this->hasEnoughOptions($word, $settings->direction));
        if ($available->isEmpty()) {
            return null;
        }

        return $this->weightedRandom($available, (string) $chat->chat_id, (bool) $settings->repeat_mistakes_enabled);
    }

    protected function weightedRandom(Collection $words, string $chatId, bool $repeatMistakes): ?Word
    {
        $weights = [];
        $total = 0;

        foreach ($words as $word) {
            $weight = 10;
            if ($repeatMistakes) {
                $wrong = TelegramUserWordStat::where('chat_id', $chatId)
                    ->where('word_id', $word->id)
                    ->sum('wrong_count');
                $weight += min(40, (int) $wrong * 5);
            }

            $recent = TelegramPoll::where('chat_id', $chatId)
                ->where('word_id', $word->id)
                ->latest('sent_at')
                ->value('sent_at');
            if ($recent) {
                $daysAgo = CarbonImmutable::parse($recent)->diffInDays(CarbonImmutable::now());
                $weight += min(20, $daysAgo * 2);
            } else {
                $weight += 15;
            }

            $weights[$word->id] = max(1, $weight);
            $total += $weights[$word->id];
        }

        $pick = random_int(1, max(1, $total));
        foreach ($words as $word) {
            $pick -= $weights[$word->id];
            if ($pick <= 0) {
                return $word;
            }
        }

        return $words->first();
    }

    protected function hasEnoughOptions(Word $word, string $direction): bool
    {
        $column = $direction === 'ru_en' ? 'word_en' : 'translation_ru';

        return Word::where('is_active', true)
            ->where('id', '!=', $word->id)
            ->where($column, '!=', $word->{$column})
            ->distinct()
            ->count($column) >= 3;
    }
}
