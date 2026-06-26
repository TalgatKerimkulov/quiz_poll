<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Console\Command;

class DeleteTelegramWebhookAliasCommand extends Command
{
    protected $signature = 'telegram:delete-webhook {--drop-pending-updates}';

    protected $description = 'Delete Telegram webhook.';

    public function handle(TelegramBotClient $telegram): int
    {
        $response = $telegram->deleteWebhook((bool) $this->option('drop-pending-updates'));
        $this->line(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
