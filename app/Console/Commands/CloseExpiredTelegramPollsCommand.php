<?php

namespace App\Console\Commands;

use App\Models\TelegramPoll;
use App\Services\Telegram\TelegramBotClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CloseExpiredTelegramPollsCommand extends Command
{
    protected $signature = 'telegram:close-expired-polls';

    protected $description = 'Close expired Telegram quiz polls.';

    public function handle(TelegramBotClient $telegram): int
    {
        TelegramPoll::whereIn('status', ['sent', 'dry_run'])
            ->whereNotNull('telegram_message_id')
            ->whereNull('closed_at')
            ->where('sent_at', '<=', CarbonImmutable::now()->subSeconds(1))
            ->each(function (TelegramPoll $poll) use ($telegram): void {
                if ($poll->sent_at->copy()->addSeconds($poll->open_period)->isFuture()) {
                    return;
                }
                try {
                    if (! $poll->is_dry_run) {
                        $telegram->stopPoll($poll->chat_id, $poll->telegram_message_id);
                    }
                    $poll->update(['status' => 'closed', 'closed_at' => now()]);
                    $this->info("Closed poll {$poll->id}.");
                } catch (Throwable $exception) {
                    Log::warning('Telegram stopPoll failed.', ['poll_id' => $poll->id, 'error' => $exception->getMessage()]);
                }
            });

        return self::SUCCESS;
    }
}
