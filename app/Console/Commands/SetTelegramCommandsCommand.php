<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Console\Command;

class SetTelegramCommandsCommand extends Command
{
    protected $signature = 'telegram:set-commands';

    protected $description = 'Register Telegram bot commands.';

    public function handle(TelegramBotClient $telegram): int
    {
        $commands = [
            ['command' => 'help', 'description' => 'помощь'],
            ['command' => 'connect', 'description' => 'подключить группу'],
            ['command' => 'disconnect', 'description' => 'отключить группу'],
            ['command' => 'settings', 'description' => 'настройки группы'],
            ['command' => 'my', 'description' => 'моя статистика за сегодня'],
            ['command' => 'my_week', 'description' => 'моя статистика за неделю'],
            ['command' => 'stats', 'description' => 'статистика группы за сегодня'],
            ['command' => 'stats_week', 'description' => 'статистика группы за неделю'],
            ['command' => 'top', 'description' => 'рейтинг за сегодня'],
            ['command' => 'top_week', 'description' => 'рейтинг за неделю'],
            ['command' => 'send_now', 'description' => 'отправить quiz сейчас'],
            ['command' => 'pause', 'description' => 'поставить группу на паузу'],
            ['command' => 'resume', 'description' => 'снять группу с паузы'],
        ];

        $this->line(json_encode($telegram->setMyCommands($commands), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
