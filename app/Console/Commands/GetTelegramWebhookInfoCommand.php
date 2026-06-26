<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Console\Command;

class GetTelegramWebhookInfoCommand extends Command
{
    protected $signature = 'telegram:webhook:info';

    protected $description = 'Show Telegram bot webhook info.';

    public function handle(TelegramBotClient $telegram): int
    {
        $this->line(json_encode($telegram->getWebhookInfo(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
