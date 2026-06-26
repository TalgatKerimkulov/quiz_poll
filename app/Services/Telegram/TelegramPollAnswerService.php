<?php

namespace App\Services\Telegram;

use App\Models\TelegramPoll;
use App\Models\TelegramPollAnswer;
use App\Models\TelegramUserWordStat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TelegramPollAnswerService
{
    public function save(array $pollAnswer): ?TelegramPollAnswer
    {
        $pollId = (string) ($pollAnswer['poll_id'] ?? '');
        if ($pollId === '') {
            return null;
        }

        $poll = TelegramPoll::where('telegram_poll_id', $pollId)->first();
        if (! $poll) {
            return null;
        }

        $optionIds = array_values($pollAnswer['option_ids'] ?? []);
        $isCorrect = collect($optionIds)->sort()->values()->toArray()
            === collect($poll->correct_option_ids)->sort()->values()->toArray();

        $user = $pollAnswer['user'] ?? [];

        $existing = TelegramPollAnswer::where('telegram_poll_id', $pollId)
            ->where('telegram_user_id', $user['id'] ?? 0)
            ->first();

        $answer = TelegramPollAnswer::updateOrCreate(
            [
                'telegram_poll_id' => $pollId,
                'telegram_user_id' => $user['id'] ?? 0,
            ],
            [
                'chat_id' => $poll->chat_id,
                'telegram_username' => $user['username'] ?? null,
                'telegram_first_name' => $user['first_name'] ?? null,
                'option_ids' => $optionIds,
                'is_correct' => $isCorrect,
                'answered_at' => CarbonImmutable::now(),
            ],
        );

        if (! $existing) {
            $this->updateWordStats($poll, (int) ($user['id'] ?? 0), $isCorrect);
        }

        return $answer;
    }

    public function todayStatsForChat(int|string $chatId): array
    {
        $answers = $this->todayAnswersForChat((string) $chatId);
        $total = $answers->count();
        $correct = $answers->where('is_correct', true)->count();
        $wrong = $total - $correct;

        return [
            'total' => $total,
            'correct' => $correct,
            'wrong' => $wrong,
            'accuracy' => $total > 0 ? round(($correct / $total) * 100, 2) : 0.0,
        ];
    }

    public function todayTopForChat(int|string $chatId): Collection
    {
        return $this->todayAnswersForChat((string) $chatId)
            ->groupBy('telegram_user_id')
            ->map(function (Collection $answers): array {
                $total = $answers->count();
                $correct = $answers->where('is_correct', true)->count();
                $first = $answers->first();

                return [
                    'name' => $first->telegram_first_name ?: ($first->telegram_username ?: (string) $first->telegram_user_id),
                    'correct' => $correct,
                    'total' => $total,
                    'accuracy' => $total > 0 ? $correct / $total : 0,
                ];
            })
            ->sortBy([
                ['correct', 'desc'],
                ['accuracy', 'desc'],
                ['total', 'desc'],
            ])
            ->values()
            ->take(10);
    }

    protected function todayAnswersForChat(string $chatId): Collection
    {
        $pollIds = TelegramPoll::where('chat_id', $chatId)
            ->whereBetween('sent_at', [CarbonImmutable::now()->startOfDay(), CarbonImmutable::now()->endOfDay()])
            ->whereNotNull('telegram_poll_id')
            ->pluck('telegram_poll_id');

        if ($pollIds->isEmpty()) {
            return collect();
        }

        return TelegramPollAnswer::whereIn('telegram_poll_id', $pollIds)->get();
    }

    protected function updateWordStats(TelegramPoll $poll, int $userId, bool $isCorrect): void
    {
        if ($userId === 0) {
            return;
        }

        $stat = TelegramUserWordStat::firstOrCreate(
            [
                'chat_id' => (string) $poll->chat_id,
                'telegram_user_id' => $userId,
                'word_id' => $poll->word_id,
            ],
            [
                'correct_count' => 0,
                'wrong_count' => 0,
            ],
        );

        $stat->increment($isCorrect ? 'correct_count' : 'wrong_count');
        $stat->forceFill([
            'last_answered_at' => CarbonImmutable::now(),
            'last_wrong_at' => $isCorrect ? $stat->last_wrong_at : CarbonImmutable::now(),
        ])->save();
    }
}
