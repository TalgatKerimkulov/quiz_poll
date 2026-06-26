<?php

namespace Tests\Feature;

use App\Models\TelegramChat;
use App\Models\TelegramPoll;
use App\Models\TelegramPollAnswer;
use App\Models\Word;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramQuizPollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TelegramBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.webhook_secret' => '',
            'telegram.dry_run' => true,
        ]);
    }

    public function test_connect_creates_telegram_chat(): void
    {
        $this->mock(TelegramBotClient::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(-1001, Mockery::on(fn (string $text): bool => str_contains($text, 'Группа подключена')))
                ->andReturn(['ok' => true]);
        });

        $this->postJson('/api/telegram/webhook', [
            'message' => $this->message('/connect'),
        ])->assertOk();

        $this->assertDatabaseHas('telegram_chats', [
            'chat_id' => '-1001',
            'title' => 'English Group',
            'type' => 'supergroup',
            'is_active' => true,
            'connected_by_user_id' => 777,
            'connected_by_username' => 'talgat',
        ]);
    }

    public function test_disconnect_disables_telegram_chat(): void
    {
        TelegramChat::create([
            'chat_id' => '-1001',
            'title' => 'English Group',
            'type' => 'supergroup',
            'is_active' => true,
        ]);

        $this->mock(TelegramBotClient::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(-1001, Mockery::on(fn (string $text): bool => str_contains($text, 'Группа отключена')))
                ->andReturn(['ok' => true]);
        });

        $this->postJson('/api/telegram/webhook', [
            'message' => $this->message('/disconnect'),
        ])->assertOk();

        $this->assertDatabaseHas('telegram_chats', [
            'chat_id' => '-1001',
            'is_active' => false,
        ]);
    }

    public function test_invalid_webhook_secret_returns_403(): void
    {
        config(['telegram.webhook_secret' => 'secret']);

        $this->postJson('/api/telegram/webhook', [
            'update_id' => 123,
            'message' => $this->message('/help'),
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'bad',
        ])->assertForbidden();
    }

    public function test_duplicate_update_is_not_processed_twice(): void
    {
        $this->mock(TelegramBotClient::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true]);
        });

        $payload = [
            'update_id' => 555,
            'message' => $this->message('/help'),
        ];

        $this->postJson('/api/telegram/webhook', $payload)->assertOk();
        $this->postJson('/api/telegram/webhook', $payload)->assertOk();
    }

    public function test_set_polls_per_day_updates_settings(): void
    {
        TelegramChat::create([
            'chat_id' => '-1001',
            'title' => 'English Group',
            'type' => 'supergroup',
            'is_active' => true,
        ]);

        $this->mock(TelegramBotClient::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true]);
        });

        $this->postJson('/api/telegram/webhook', [
            'message' => $this->message('/set_polls_per_day 15'),
        ])->assertOk();

        $this->assertDatabaseHas('telegram_chat_settings', [
            'polls_per_day' => 15,
        ]);
    }

    public function test_service_does_not_send_more_than_configured_daily_limit(): void
    {
        config(['services.telegram.polls_per_day' => 20]);

        $chat = TelegramChat::create([
            'chat_id' => '-1001',
            'type' => 'supergroup',
            'is_active' => true,
        ]);
        $word = Word::create(['word_en' => 'apple', 'translation_ru' => 'яблоко']);

        for ($i = 0; $i < 20; $i++) {
            TelegramPoll::create([
                'telegram_poll_id' => "poll-{$i}",
                'chat_id' => '-1001',
                'word_id' => $word->id,
                'question' => 'Q',
                'options' => ['a', 'b', 'c', 'd'],
                'correct_option_ids' => [0],
                'sent_at' => now(),
            ]);
        }

        $this->mock(TelegramBotClient::class, function ($mock): void {
            $mock->shouldNotReceive('sendPoll');
        });

        $this->assertNull(app(TelegramQuizPollService::class)->sendForChat($chat));
    }

    public function test_service_does_not_repeat_word_in_same_chat_day(): void
    {
        config(['services.telegram.polls_per_day' => 20]);
        $chat = TelegramChat::create([
            'chat_id' => '-1001',
            'type' => 'supergroup',
            'is_active' => true,
        ]);
        $this->seedWords();

        $this->mock(TelegramBotClient::class, function ($mock): void {
            $mock->shouldReceive('isDryRun')->andReturn(true);
            $mock->shouldReceive('sendPoll')->twice()->andReturnUsing(function (array $payload): array {
                return [
                    'ok' => true,
                    'result' => [
                        'message_id' => random_int(1, 9999),
                        'poll' => ['id' => 'poll-'.uniqid()],
                    ],
                ];
            });
        });

        $service = app(TelegramQuizPollService::class);
        $first = $service->sendForChat($chat, ignoreTimeRange: true, force: true);
        $second = $service->sendForChat($chat, ignoreTimeRange: true, force: true);

        $this->assertNotSame($first?->word_id, $second?->word_id);
    }

    public function test_poll_answer_saves_correct_answer(): void
    {
        $poll = $this->createPoll([1]);

        $this->postJson('/api/telegram/webhook', [
            'poll_answer' => [
                'poll_id' => $poll->telegram_poll_id,
                'user' => ['id' => 777, 'username' => 'talgat', 'first_name' => 'Talgat'],
                'option_ids' => [1],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('telegram_poll_answers', [
            'telegram_poll_id' => $poll->telegram_poll_id,
            'telegram_user_id' => 777,
            'is_correct' => true,
        ]);
    }

    public function test_poll_answer_saves_wrong_answer(): void
    {
        $poll = $this->createPoll([2]);

        $this->postJson('/api/telegram/webhook', [
            'poll_answer' => [
                'poll_id' => $poll->telegram_poll_id,
                'user' => ['id' => 777, 'username' => 'talgat', 'first_name' => 'Talgat'],
                'option_ids' => [1],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('telegram_poll_answers', [
            'telegram_poll_id' => $poll->telegram_poll_id,
            'telegram_user_id' => 777,
            'is_correct' => false,
        ]);
    }

    public function test_stats_returns_aggregated_group_stats(): void
    {
        $poll = $this->createPoll([0]);
        TelegramPollAnswer::create([
            'telegram_poll_id' => $poll->telegram_poll_id,
            'chat_id' => '-1001',
            'telegram_user_id' => 1,
            'option_ids' => [0],
            'is_correct' => true,
            'answered_at' => now(),
        ]);
        TelegramPollAnswer::create([
            'telegram_poll_id' => $poll->telegram_poll_id,
            'chat_id' => '-1001',
            'telegram_user_id' => 2,
            'option_ids' => [1],
            'is_correct' => false,
            'answered_at' => now(),
        ]);

        $this->mock(TelegramBotClient::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(-1001, Mockery::on(fn (string $text): bool => str_contains($text, 'Всего ответов: 2')
                    && str_contains($text, 'Правильных: 1')
                    && str_contains($text, 'Ошибок: 1')
                    && str_contains($text, 'Средняя точность: 50%')))
                ->andReturn(['ok' => true]);
        });

        $this->postJson('/api/telegram/webhook', [
            'message' => $this->message('/stats'),
        ])->assertOk();
    }

    public function test_top_returns_group_rating(): void
    {
        $poll = $this->createPoll([0]);
        TelegramPollAnswer::create([
            'telegram_poll_id' => $poll->telegram_poll_id,
            'chat_id' => '-1001',
            'telegram_user_id' => 1,
            'telegram_first_name' => 'Talgat',
            'option_ids' => [0],
            'is_correct' => true,
            'answered_at' => now(),
        ]);
        TelegramPollAnswer::create([
            'telegram_poll_id' => $poll->telegram_poll_id,
            'chat_id' => '-1001',
            'telegram_user_id' => 2,
            'telegram_first_name' => 'Aziz',
            'option_ids' => [1],
            'is_correct' => false,
            'answered_at' => now(),
        ]);

        $this->mock(TelegramBotClient::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(-1001, Mockery::on(fn (string $text): bool => str_contains($text, '1. Talgat — 1/1')
                    && str_contains($text, '2. Aziz — 0/1')))
                ->andReturn(['ok' => true]);
        });

        $this->postJson('/api/telegram/webhook', [
            'message' => $this->message('/top'),
        ])->assertOk();
    }

    protected function message(string $text): array
    {
        return [
            'message_id' => 10,
            'chat' => [
                'id' => -1001,
                'title' => 'English Group',
                'type' => 'supergroup',
            ],
            'from' => [
                'id' => 777,
                'username' => 'talgat',
                'first_name' => 'Talgat',
            ],
            'text' => $text,
        ];
    }

    protected function createPoll(array $correctOptionIds): TelegramPoll
    {
        $word = Word::create(['word_en' => 'apple', 'translation_ru' => 'яблоко']);

        return TelegramPoll::create([
            'telegram_poll_id' => 'poll-1',
            'chat_id' => '-1001',
            'word_id' => $word->id,
            'question' => 'Как переводится слово: apple?',
            'options' => ['яблоко', 'книга', 'вода', 'дом'],
            'correct_option_ids' => $correctOptionIds,
            'sent_at' => now(),
        ]);
    }

    protected function seedWords(): void
    {
        foreach ([
            ['apple', 'яблоко'],
            ['book', 'книга'],
            ['water', 'вода'],
            ['house', 'дом'],
            ['car', 'машина'],
        ] as [$wordEn, $translationRu]) {
            Word::create(['word_en' => $wordEn, 'translation_ru' => $translationRu]);
        }
    }
}
