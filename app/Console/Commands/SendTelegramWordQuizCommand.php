<?php

namespace App\Console\Commands;

use App\Models\TelegramChat;
use App\Services\Telegram\TelegramQuizPollService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTelegramWordQuizCommand extends Command
{
    protected $signature = 'telegram:send-word-quiz';

    protected $description = 'Send English word quiz polls to active Telegram groups.';

    public function handle(TelegramQuizPollService $polls): int
    {
        TelegramChat::where('is_active', true)
            ->whereIn('type', ['group', 'supergroup'])
            ->each(function (TelegramChat $chat) use ($polls): void {
                try {
                    $poll = $polls->sendForChat($chat);

                    if ($poll && $poll->status !== 'failed') {
                        $this->info("Sent quiz poll to chat {$chat->chat_id}.");
                        Log::info('Telegram quiz poll sent.', [
                            'chat_id' => $chat->chat_id,
                            'telegram_poll_id' => $poll->telegram_poll_id,
                            'word_id' => $poll->word_id,
                        ]);
                    } elseif ($poll && $poll->status === 'failed') {
                        $this->warn("Failed quiz poll attempt saved for chat {$chat->chat_id}.");
                    } else {
                        $this->line("Skipped chat {$chat->chat_id}.");
                    }
                } catch (Throwable $exception) {
                    $this->error("Failed chat {$chat->chat_id}: {$exception->getMessage()}");
                    Log::error('Telegram quiz poll send failed.', [
                        'chat_id' => $chat->chat_id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

        return self::SUCCESS;
    }
}
