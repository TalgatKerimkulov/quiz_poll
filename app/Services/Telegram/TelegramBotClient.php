<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramBotClient
{
    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        return $this->post('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $options));
    }

    public function sendPoll(array $payload): array
    {
        return $this->post('sendPoll', $payload);
    }

    public function stopPoll(int|string $chatId, int|string $messageId): array
    {
        return $this->post('stopPoll', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function getChatMember(int|string $chatId, int|string $userId): array
    {
        return $this->postTelegram('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    public function getMe(): array
    {
        return $this->postTelegram('getMe', []);
    }

    public function setWebhook(string $url, ?string $secret = null): array
    {
        $payload = [
            'url' => $url,
            'allowed_updates' => ['message', 'poll_answer', 'poll', 'my_chat_member'],
        ];

        if ($secret !== null && $secret !== '') {
            $payload['secret_token'] = $secret;
        }

        return $this->postTelegram('setWebhook', $payload);
    }

    public function getWebhookInfo(): array
    {
        return $this->postTelegram('getWebhookInfo', []);
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        return $this->postTelegram('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates,
        ]);
    }

    public function setMyCommands(array $commands): array
    {
        return $this->postTelegram('setMyCommands', [
            'commands' => $commands,
        ]);
    }

    protected function post(string $method, array $payload): array
    {
        if ($this->isDryRun()) {
            Log::info('Telegram dry-run request.', [
                'method' => $method,
                'payload' => $payload,
            ]);

            return $this->fakeResponse($method, $payload);
        }

        return $this->postTelegram($method, $payload);
    }

    protected function postTelegram(string $method, array $payload): array
    {
        $token = $this->token();
        if ($token === '') {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        $response = Http::timeout((int) config('telegram.http.timeout', 10))
            ->retry(
                (int) config('telegram.http.retry_times', 2),
                (int) config('telegram.http.retry_sleep_ms', 500),
                throw: false,
            )
            ->post("https://api.telegram.org/bot{$token}/{$method}", $payload);

        $body = $response->json();
        if (! $response->successful() || ! ($body['ok'] ?? false)) {
            Log::warning('Telegram API request failed.', [
                'method' => $method,
                'status' => $response->status(),
                'response' => $body ?? $response->body(),
            ]);

            $description = is_array($body) ? ($body['description'] ?? 'Unknown Telegram API error.') : 'Unknown Telegram API error.';
            throw new RuntimeException("Telegram API {$method} failed: {$description}");
        }

        return $body;
    }

    protected function fakeResponse(string $method, array $payload): array
    {
        return match ($method) {
            'sendPoll' => [
                'ok' => true,
                'result' => [
                    'message_id' => random_int(100000, 999999),
                    'chat' => ['id' => $payload['chat_id'] ?? null],
                    'poll' => [
                        'id' => 'dry-run-'.uniqid('', true),
                        'question' => $payload['question'] ?? '',
                        'options' => $payload['options'] ?? [],
                    ],
                ],
            ],
            'sendMessage' => ['ok' => true, 'result' => ['message_id' => random_int(100000, 999999)]],
            'stopPoll' => ['ok' => true, 'result' => ['id' => 'dry-run-stopped']],
            default => ['ok' => true, 'result' => true],
        };
    }

    protected function token(): string
    {
        return (string) config('telegram.bot_token', config('services.telegram.bot_token', ''));
    }

    public function isDryRun(): bool
    {
        return (bool) config('telegram.dry_run', config('services.telegram.dry_run', true));
    }
}
