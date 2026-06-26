<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Console\Command;

class TelegramBotInfoCommand extends Command
{
    protected $signature = 'telegram:bot-info';

    protected $description = 'Show Telegram bot info from getMe.';

    public function handle(TelegramBotClient $telegram): int
    {
        $this->line(json_encode($telegram->getMe(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
