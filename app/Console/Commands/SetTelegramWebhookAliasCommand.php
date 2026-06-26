<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Console\Command;

class SetTelegramWebhookAliasCommand extends Command
{
    protected $signature = 'telegram:set-webhook {url : Full webhook URL or base domain}';

    protected $description = 'Set Telegram webhook using TELEGRAM_WEBHOOK_SECRET.';

    public function handle(TelegramBotClient $telegram): int
    {
        $url = rtrim((string) $this->argument('url'), '/');
        if (! str_ends_with($url, '/api/telegram/webhook')) {
            $url .= '/api/telegram/webhook';
        }

        $response = $telegram->setWebhook($url, (string) config('telegram.webhook_secret', ''));
        $this->line(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
