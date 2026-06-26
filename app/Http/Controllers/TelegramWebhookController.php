<?php

namespace App\Http\Controllers;

use App\Models\TelegramChat;
use App\Models\TelegramUpdate;
use App\Services\Telegram\StatisticsService;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramChatService;
use App\Services\Telegram\TelegramMessageFormatter;
use App\Services\Telegram\TelegramPollAnswerService;
use App\Services\Telegram\TelegramQuizPollService;
use App\Services\Telegram\WordManagementService;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramBotClient $client,
        protected TelegramChatService $chats,
        protected TelegramPollAnswerService $answers,
        protected StatisticsService $stats,
        protected TelegramMessageFormatter $formatter,
        protected TelegramQuizPollService $polls,
        protected WordManagementService $words,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('telegram.webhook_secret', config('services.telegram.webhook_secret', ''));
        if ($secret !== '' && ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();
        if (! $this->markUpdate($update)) {
            Log::info('Duplicate Telegram update skipped.', ['update_id' => $update['update_id'] ?? null]);
            return response()->json(['ok' => true]);
        }

        try {
            if (isset($update['poll_answer'])) {
                $this->answers->save($update['poll_answer']);
            } elseif (isset($update['my_chat_member'])) {
                $this->chats->handleMyChatMember($update['my_chat_member']);
            } elseif (isset($update['message'])) {
                $this->handleMessage($update['message']);
            }

            TelegramUpdate::where('update_id', $update['update_id'] ?? 0)->update(['processed_at' => now()]);
        } catch (Throwable $exception) {
            Log::error('Telegram webhook processing failed.', ['error' => $exception->getMessage()]);
            throw $exception;
        }

        return response()->json(['ok' => true]);
    }

    protected function handleMessage(array $message): void
    {
        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            return;
        }

        [$command, $args] = $this->parseCommand($text);
        match ($command) {
            '/connect' => $this->admin($message, fn () => $this->connect($message)),
            '/disconnect' => $this->admin($message, fn () => $this->disconnect($message)),
            '/settings' => $this->admin($message, fn () => $this->settings($message)),
            '/set_polls_per_day' => $this->admin($message, fn () => $this->setPollsPerDay($message, $args)),
            '/set_time' => $this->admin($message, fn () => $this->setTime($message, $args)),
            '/set_timezone' => $this->admin($message, fn () => $this->setTimezone($message, $args)),
            '/set_level' => $this->admin($message, fn () => $this->setLevel($message, $args)),
            '/set_direction' => $this->admin($message, fn () => $this->setDirection($message, $args)),
            '/pause' => $this->admin($message, fn () => $this->pause($message, true)),
            '/resume' => $this->admin($message, fn () => $this->pause($message, false)),
            '/send_now' => $this->admin($message, fn () => $this->sendNow($message)),
            '/addword' => $this->admin($message, fn () => $this->addWord($message, $args)),
            '/disableword' => $this->admin($message, fn () => $this->disableWord($message, $args)),
            '/help' => $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->help()),
            '/my' => $this->my($message, false),
            '/my_week' => $this->my($message, true),
            '/stats' => $this->chatStats($message, false),
            '/stats_week' => $this->chatStats($message, true),
            '/top' => $this->top($message, false),
            '/top_week' => $this->top($message, true),
            '/findword' => $this->findWord($message, $args),
            default => null,
        };
    }

    protected function connect(array $message): void
    {
        $chatId = data_get($message, 'chat.id');
        if (data_get($message, 'chat.type') === 'private') {
            $this->client->sendMessage($chatId, 'Добавьте бота в группу как администратора и выполните /connect в группе.');
            return;
        }
        if (! $this->chats->botCanOperate($chatId)) {
            $this->client->sendMessage($chatId, $this->formatter->botRightsMissing());
            return;
        }

        $this->chats->connect($message);
        $this->client->sendMessage($chatId, $this->formatter->connected());
    }

    protected function disconnect(array $message): void
    {
        $this->chats->disconnect($message);
        $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->disconnected());
    }

    protected function settings(array $message): void
    {
        $chat = $this->chat($message);
        $this->client->sendMessage(data_get($message, 'chat.id'), $chat ? $this->formatter->settings($chat) : 'Группа не подключена.');
    }

    protected function setPollsPerDay(array $message, array $args): void
    {
        $value = (int) ($args[0] ?? 0);
        if ($value < 1 || $value > 50) {
            $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->invalidCommand('/set_polls_per_day 15'));
            return;
        }
        $this->chat($message)?->ensureSettings()->update(['polls_per_day' => $value]);
        $this->client->sendMessage(data_get($message, 'chat.id'), "Опросов в день: {$value}");
    }

    protected function setTime(array $message, array $args): void
    {
        if (! $this->validTime($args[0] ?? '') || ! $this->validTime($args[1] ?? '')) {
            $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->invalidCommand('/set_time 09:00 22:00'));
            return;
        }
        $this->chat($message)?->ensureSettings()->update(['start_time' => $args[0], 'end_time' => $args[1]]);
        $this->client->sendMessage(data_get($message, 'chat.id'), "Время обновлено: {$args[0]}–{$args[1]}");
    }

    protected function setTimezone(array $message, array $args): void
    {
        $timezone = $args[0] ?? '';
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->invalidCommand('/set_timezone Asia/Tashkent'));
            return;
        }
        $this->chat($message)?->ensureSettings()->update(['timezone' => $timezone]);
        $this->client->sendMessage(data_get($message, 'chat.id'), "Часовой пояс: {$timezone}");
    }

    protected function setLevel(array $message, array $args): void
    {
        $level = strtoupper($args[0] ?? '');
        if (! in_array($level, ['A1', 'A2', 'B1', 'B2', 'ALL'], true)) {
            $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->invalidCommand('/set_level A1'));
            return;
        }
        $this->chat($message)?->ensureSettings()->update(['level' => $level === 'ALL' ? null : $level]);
        $this->client->sendMessage(data_get($message, 'chat.id'), 'Уровень: '.($level === 'ALL' ? 'все' : $level));
    }

    protected function setDirection(array $message, array $args): void
    {
        $direction = $args[0] ?? '';
        if (! in_array($direction, ['en_ru', 'ru_en', 'mixed'], true)) {
            $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->invalidCommand('/set_direction en_ru'));
            return;
        }
        $this->chat($message)?->ensureSettings()->update(['direction' => $direction]);
        $this->client->sendMessage(data_get($message, 'chat.id'), "Направление: {$direction}");
    }

    protected function pause(array $message, bool $paused): void
    {
        $this->chat($message)?->ensureSettings()->update(['is_paused' => $paused]);
        $this->client->sendMessage(data_get($message, 'chat.id'), $paused ? 'Группа поставлена на паузу.' : 'Пауза снята.');
    }

    protected function sendNow(array $message): void
    {
        $chat = $this->chat($message);
        $poll = $chat ? $this->polls->sendForChat($chat, ignoreTimeRange: true) : null;
        $this->client->sendMessage(data_get($message, 'chat.id'), $poll ? 'Quiz poll отправлен.' : 'Сейчас poll не отправлен: проверьте активность, паузу, лимит или словарь.');
    }

    protected function addWord(array $message, array $args): void
    {
        if (count($args) < 2) {
            $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->invalidCommand('/addword apple яблоко A1'));
            return;
        }
        try {
            $word = $this->words->add($args[0], $args[1], $args[2] ?? null, data_get($message, 'from.id'), data_get($message, 'from.username'));
            $this->client->sendMessage(data_get($message, 'chat.id'), "Слово добавлено: {$word->word_en} — {$word->translation_ru}, уровень ".($word->level ?: 'all').'.');
        } catch (InvalidArgumentException) {
            $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->invalidCommand('/addword apple яблоко A1'));
        }
    }

    protected function disableWord(array $message, array $args): void
    {
        $count = $this->words->disable($args[0] ?? '');
        $this->client->sendMessage(data_get($message, 'chat.id'), $count > 0 ? 'Слово отключено.' : 'Слово не найдено.');
    }

    protected function findWord(array $message, array $args): void
    {
        $words = $this->words->find($args[0] ?? '');
        $text = $words->isEmpty()
            ? 'Слова не найдены.'
            : $words->map(fn ($word) => "{$word->word_en} — {$word->translation_ru} ({$word->level})")->implode("\n");
        $this->client->sendMessage(data_get($message, 'chat.id'), $text);
    }

    protected function my(array $message, bool $week): void
    {
        [$from, $to] = $this->period($message, $week);
        $stats = $this->stats->getUserStatsForPeriod(data_get($message, 'chat.id'), data_get($message, 'from.id'), $from, $to);
        $stats['title'] = $week ? 'Твоя статистика за 7 дней:' : 'Твоя статистика за сегодня:';
        $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->userStats($stats));
    }

    protected function chatStats(array $message, bool $week): void
    {
        [$from, $to] = $this->period($message, $week);
        $stats = $this->stats->getChatStatsForPeriod(data_get($message, 'chat.id'), $from, $to);
        $stats['title'] = $week ? 'Статистика группы за 7 дней:' : 'Статистика группы за сегодня:';
        $this->client->sendMessage(data_get($message, 'chat.id'), $this->formatter->chatStats($stats));
    }

    protected function top(array $message, bool $week): void
    {
        [$from, $to] = $this->period($message, $week);
        $rows = $this->stats->getTopForPeriod(data_get($message, 'chat.id'), $from, $to);
        $text = str_replace('Топ:', $week ? 'Топ за 7 дней:' : 'Топ за сегодня:', $this->formatter->top($rows));
        $this->client->sendMessage(data_get($message, 'chat.id'), $text);
    }

    protected function admin(array $message, callable $callback): void
    {
        $chatId = data_get($message, 'chat.id');
        if (! $this->chats->isAdmin($chatId, data_get($message, 'from.id'))) {
            Log::info('Telegram command denied: not admin.', ['chat_id' => $chatId, 'user_id' => data_get($message, 'from.id')]);
            $this->client->sendMessage($chatId, $this->formatter->notAdmin());
            return;
        }
        $callback();
    }

    protected function chat(array $message): ?TelegramChat
    {
        return TelegramChat::where('chat_id', (string) data_get($message, 'chat.id'))->first();
    }

    protected function period(array $message, bool $week): array
    {
        $timezone = $this->chat($message)?->ensureSettings()->timezone ?: config('telegram.defaults.timezone', 'Asia/Tashkent');
        $to = Carbon::now($timezone)->endOfDay()->utc();
        $from = $week ? Carbon::now($timezone)->subDays(6)->startOfDay()->utc() : Carbon::now($timezone)->startOfDay()->utc();

        return [$from, $to];
    }

    protected function parseCommand(string $text): array
    {
        $parts = preg_split('/\s+/', trim($text)) ?: [];
        $command = strtolower(array_shift($parts) ?: '');
        $command = preg_replace('/@.+$/', '', $command) ?: $command;

        return [$command, $parts];
    }

    protected function validTime(string $time): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }

    protected function markUpdate(array $update): bool
    {
        if (! isset($update['update_id'])) {
            return true;
        }

        $type = collect(['message', 'poll_answer', 'poll', 'my_chat_member'])->first(fn ($key) => isset($update[$key])) ?: 'unknown';
        $created = TelegramUpdate::firstOrCreate(['update_id' => $update['update_id']], ['type' => $type]);

        return $created->wasRecentlyCreated;
    }
}
