<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Console\Command;

class DeleteTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook:delete {--drop-pending-updates : Drop pending Telegram updates}';

    protected $description = 'Delete Telegram bot webhook.';

    public function handle(TelegramBotClient $telegram): int
    {
        $response = $telegram->deleteWebhook((bool) $this->option('drop-pending-updates'));

        $this->info('Telegram webhook deleted.');
        $this->line('Response: '.json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
