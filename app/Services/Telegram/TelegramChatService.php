<?php

namespace App\Services\Telegram;

use App\Models\TelegramChat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramChatService
{
    public function __construct(protected TelegramBotClient $client)
    {
    }

    public function connect(array $message): TelegramChat
    {
        $chat = $message['chat'] ?? [];
        $from = $message['from'] ?? [];

        $telegramChat = TelegramChat::updateOrCreate(
            ['chat_id' => (string) ($chat['id'] ?? '')],
            [
                'title' => $chat['title'] ?? null,
                'type' => $chat['type'] ?? 'private',
                'is_active' => true,
                'inactive_reason' => null,
                'connected_by_user_id' => $from['id'] ?? null,
                'connected_by_username' => $from['username'] ?? null,
                'connected_at' => CarbonImmutable::now(),
            ],
        );
        $telegramChat->ensureSettings();

        Log::info('Telegram group connected.', ['chat_id' => $telegramChat->chat_id]);

        return $telegramChat;
    }

    public function disconnect(array $message): ?TelegramChat
    {
        $chat = TelegramChat::where('chat_id', (string) data_get($message, 'chat.id', ''))->first();
        if (! $chat) {
            return null;
        }

        $chat->update(['is_active' => false, 'inactive_reason' => 'disconnect']);
        Log::info('Telegram group disconnected.', ['chat_id' => $chat->chat_id]);

        return $chat;
    }

    public function isAdmin(int|string $chatId, int|string|null $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        if ((bool) config('telegram.dry_run', true)) {
            return true;
        }

        try {
            $member = data_get($this->client->getChatMember($chatId, $userId), 'result', []);
            return in_array($member['status'] ?? '', ['creator', 'administrator'], true);
        } catch (Throwable $exception) {
            Log::warning('Telegram admin check failed.', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function botCanOperate(int|string $chatId): bool
    {
        if ((bool) config('telegram.dry_run', true)) {
            return true;
        }

        try {
            $botId = data_get($this->client->getMe(), 'result.id');
            $member = data_get($this->client->getChatMember($chatId, $botId), 'result', []);

            if (($member['status'] ?? '') === 'left') {
                return false;
            }

            return ($member['can_send_messages'] ?? true) && ($member['can_send_polls'] ?? true);
        } catch (Throwable $exception) {
            Log::warning('Telegram bot rights check failed.', [
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function handleMyChatMember(array $update): void
    {
        $chat = $update['chat'] ?? [];
        $newStatus = data_get($update, 'new_chat_member.status');
        $chatId = (string) ($chat['id'] ?? '');
        if ($chatId === '') {
            return;
        }

        $telegramChat = TelegramChat::firstOrCreate(
            ['chat_id' => $chatId],
            ['title' => $chat['title'] ?? null, 'type' => $chat['type'] ?? 'group', 'is_active' => false],
        );

        if (in_array($newStatus, ['left', 'kicked'], true)) {
            $telegramChat->update([
                'is_active' => false,
                'inactive_reason' => 'bot_removed',
                'bot_status_updated_at' => CarbonImmutable::now(),
            ]);
            Log::info('Telegram bot removed from group.', ['chat_id' => $chatId]);
            return;
        }

        if (in_array($newStatus, ['member', 'administrator'], true)) {
            $telegramChat->update([
                'bot_status_updated_at' => CarbonImmutable::now(),
            ]);
            Log::info('Telegram bot added to group.', ['chat_id' => $chatId]);
        }
    }
}
