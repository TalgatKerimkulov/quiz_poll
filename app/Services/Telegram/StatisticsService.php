<?php

namespace App\Services\Telegram;

use App\Models\TelegramPoll;
use App\Models\TelegramPollAnswer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StatisticsService
{
    public function getUserStatsForPeriod($chatId, $userId, Carbon $from, Carbon $to): array
    {
        $answers = $this->answersForPeriod((string) $chatId, $from, $to)
            ->where('telegram_user_id', (int) $userId);

        $stats = $this->statsFromAnswers($answers);
        $top = $this->getTopForPeriod($chatId, $from, $to, 1000);
        $rank = collect($top)->search(fn (array $row): bool => (int) $row['telegram_user_id'] === (int) $userId);
        $stats['rank'] = $rank === false ? '-' : $rank + 1;

        return $stats;
    }

    public function getChatStatsForPeriod($chatId, Carbon $from, Carbon $to): array
    {
        $polls = TelegramPoll::where('chat_id', (string) $chatId)
            ->whereBetween('sent_at', [$from, $to])
            ->count();

        return array_merge($this->statsFromAnswers($this->answersForPeriod((string) $chatId, $from, $to)), [
            'polls_sent' => $polls,
        ]);
    }

    public function getTopForPeriod($chatId, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return $this->answersForPeriod((string) $chatId, $from, $to)
            ->groupBy('telegram_user_id')
            ->map(function (Collection $answers): array {
                $total = $answers->count();
                $correct = $answers->where('is_correct', true)->count();
                $first = $answers->first();
                $lastCorrect = $answers->where('is_correct', true)->max('answered_at');

                return [
                    'telegram_user_id' => $first->telegram_user_id,
                    'name' => $first->telegram_first_name ?: ($first->telegram_username ?: (string) $first->telegram_user_id),
                    'total_answers' => $total,
                    'correct_answers' => $correct,
                    'wrong_answers' => $total - $correct,
                    'accuracy_percent' => $total > 0 ? round($correct / $total * 100, 2) : 0.0,
                    'last_correct_at' => $lastCorrect,
                ];
            })
            ->sort(function (array $a, array $b): int {
                return [$b['correct_answers'], $b['accuracy_percent'], $b['total_answers'], $a['last_correct_at']]
                    <=> [$a['correct_answers'], $a['accuracy_percent'], $a['total_answers'], $b['last_correct_at']];
            })
            ->values()
            ->take($limit)
            ->all();
    }

    protected function answersForPeriod(string $chatId, Carbon $from, Carbon $to): Collection
    {
        $pollIds = TelegramPoll::where('chat_id', $chatId)
            ->whereBetween('sent_at', [$from, $to])
            ->pluck('telegram_poll_id');

        if ($pollIds->isEmpty()) {
            return collect();
        }

        return TelegramPollAnswer::whereIn('telegram_poll_id', $pollIds)->get();
    }

    protected function statsFromAnswers(Collection $answers): array
    {
        $total = $answers->count();
        $correct = $answers->where('is_correct', true)->count();

        return [
            'total_answers' => $total,
            'correct_answers' => $correct,
            'wrong_answers' => $total - $correct,
            'accuracy_percent' => $total > 0 ? round($correct / $total * 100, 2) : 0.0,
            'unique_users' => $answers->pluck('telegram_user_id')->unique()->count(),
            'polls_sent' => 0,
        ];
    }
}
