<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Console\Command;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook:set
        {url : Public HTTPS base URL or full webhook URL}
        {--secret= : Secret token for X-Telegram-Bot-Api-Secret-Token}
        {--full-url : Treat url argument as the full webhook URL}';

    protected $description = 'Set Telegram bot webhook URL.';

    public function handle(TelegramBotClient $telegram): int
    {
        $url = rtrim((string) $this->argument('url'), '/');
        $webhookUrl = $this->option('full-url')
            ? $url
            : "{$url}/api/telegram/webhook";

        $secret = (string) ($this->option('secret') ?: config('services.telegram.webhook_secret', ''));
        $response = $telegram->setWebhook($webhookUrl, $secret);

        $this->info('Telegram webhook set.');
        $this->line('URL: '.$webhookUrl);
        $this->line('Response: '.json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
