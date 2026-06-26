<?php

namespace App\Services\Telegram;

use App\Models\TelegramChat;
use App\Models\TelegramPoll;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramQuizPollService
{
    public function __construct(
        protected TelegramBotClient $client,
        protected WordSelectionService $words,
        protected QuizOptionBuilder $options,
    ) {
    }

    public function sendForChat(TelegramChat $chat, bool $ignoreTimeRange = false, bool $force = false): ?TelegramPoll
    {
        if (! $chat->is_active) {
            return null;
        }

        $settings = $chat->ensureSettings();
        if ($settings->is_paused) {
            return null;
        }

        $now = CarbonImmutable::now($settings->timezone);
        [$dayStart, $dayEnd] = [$now->startOfDay()->utc(), $now->endOfDay()->utc()];

        if (! $ignoreTimeRange && ! $this->isInTimeRange($now, $settings->start_time, $settings->end_time)) {
            return null;
        }

        $sentToday = TelegramPoll::where('chat_id', $chat->chat_id)
            ->whereIn('status', ['sent', 'dry_run', 'closed'])
            ->whereBetween('sent_at', [$dayStart, $dayEnd])
            ->count();

        if (! $force && $sentToday >= $settings->polls_per_day) {
            Log::info('Telegram quiz daily limit reached.', ['chat_id' => $chat->chat_id, 'limit' => $settings->polls_per_day]);
            return null;
        }

        if (! $force && ! $ignoreTimeRange && ! $this->isDue($chat, $now, $sentToday)) {
            return null;
        }

        $word = $this->words->select($chat, $dayStart, $dayEnd);
        if (! $word) {
            return null;
        }

        $quiz = $this->options->build($word, $settings->direction);
        $payload = [
            'chat_id' => (string) $chat->chat_id,
            'question' => $quiz['question'],
            'options' => $quiz['options'],
            'type' => 'quiz',
            'is_anonymous' => false,
            'correct_option_id' => $quiz['correct_option_ids'][0],
            'explanation' => $quiz['explanation'],
            'open_period' => $settings->poll_open_period,
        ];

        try {
            $response = $this->client->sendPoll($payload);
            $result = $response['result'] ?? [];

            $poll = TelegramPoll::create([
                'telegram_poll_id' => data_get($result, 'poll.id'),
                'telegram_message_id' => data_get($result, 'message_id'),
                'chat_id' => (string) $chat->chat_id,
                'word_id' => $word->id,
                'question' => $quiz['question'],
                'options' => $quiz['options'],
                'correct_option_ids' => $quiz['correct_option_ids'],
                'status' => $this->client->isDryRun() ? 'dry_run' : 'sent',
                'direction' => $quiz['direction'],
                'level' => $word->level,
                'open_period' => $settings->poll_open_period,
                'sent_at' => CarbonImmutable::now(),
                'is_dry_run' => $this->client->isDryRun(),
            ]);

            Log::info('Telegram quiz poll sent.', ['chat_id' => $chat->chat_id, 'telegram_poll_id' => $poll->telegram_poll_id]);

            return $poll;
        } catch (Throwable $exception) {
            Log::error('Telegram quiz poll failed.', ['chat_id' => $chat->chat_id, 'error' => $exception->getMessage()]);

            return TelegramPoll::create([
                'chat_id' => (string) $chat->chat_id,
                'word_id' => $word->id,
                'question' => $quiz['question'],
                'options' => $quiz['options'],
                'correct_option_ids' => $quiz['correct_option_ids'],
                'status' => 'failed',
                'direction' => $quiz['direction'],
                'level' => $word->level,
                'open_period' => $settings->poll_open_period,
                'error_message' => $exception->getMessage(),
                'sent_at' => CarbonImmutable::now(),
                'is_dry_run' => false,
            ]);
        }
    }

    protected function isDue(TelegramChat $chat, CarbonImmutable $now, int $sentToday): bool
    {
        $settings = $chat->ensureSettings();
        if ($sentToday === 0) {
            return true;
        }

        $lastSentAt = TelegramPoll::where('chat_id', $chat->chat_id)
            ->whereIn('status', ['sent', 'dry_run', 'closed'])
            ->latest('sent_at')
            ->value('sent_at');
        if (! $lastSentAt) {
            return true;
        }

        $start = CarbonImmutable::parse($now->toDateString().' '.$settings->start_time, $settings->timezone);
        $end = CarbonImmutable::parse($now->toDateString().' '.$settings->end_time, $settings->timezone);
        $minutes = max(1, $start->diffInMinutes($end));
        $interval = max(1, (int) floor($minutes / max(1, $settings->polls_per_day)));

        return CarbonImmutable::parse($lastSentAt)->diffInMinutes(CarbonImmutable::now()) >= $interval;
    }

    protected function isInTimeRange(CarbonImmutable $now, string $startTime, string $endTime): bool
    {
        $start = CarbonImmutable::parse($now->toDateString().' '.$startTime, $now->timezone);
        $end = CarbonImmutable::parse($now->toDateString().' '.$endTime, $now->timezone);

        return $now->betweenIncluded($start, $end);
    }
}
