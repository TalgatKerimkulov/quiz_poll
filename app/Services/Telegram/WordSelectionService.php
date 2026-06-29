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
            ->where('locale', $settings->source_locale)
            ->whereHas('translations', fn ($query) => $query->where('locale', $settings->target_locale))
            ->with('translations')
            ->when($settings->level, fn ($query) => $query->where('level', $settings->level));

        $askedToday = TelegramPoll::where('chat_id', $chat->chat_id)
            ->whereBetween('sent_at', [$from, $to])
            ->pluck('word_id');

        $available = (clone $base)->whereNotIn('id', $askedToday)->get();
        if ($available->isEmpty()) {
            $available = $base->get();
        }

        $available = $available->filter(fn (Word $word): bool => $this->hasEnoughOptions(
            $word,
            $settings->direction,
            $settings->target_locale,
        ));
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

    protected function hasEnoughOptions(Word $word, string $direction, string $targetLocale): bool
    {
        $query = Word::query()
            ->where('words.is_active', true)
            ->where('words.locale', $word->locale)
            ->where('words.id', '!=', $word->id)
            ->join('word_translations', function ($join) use ($targetLocale): void {
                $join->on('word_translations.word_id', '=', 'words.id')
                    ->where('word_translations.locale', $targetLocale);
            });

        $forwardReady = (clone $query)->distinct()->count('word_translations.text') >= 3;
        $reverseReady = (clone $query)->distinct()->count('words.term') >= 3;

        return match ($direction) {
            'forward' => $forwardReady,
            'reverse' => $reverseReady,
            default => $forwardReady && $reverseReady,
        };
    }
}
