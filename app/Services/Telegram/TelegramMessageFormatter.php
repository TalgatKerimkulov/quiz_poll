<?php

namespace App\Services\Telegram;

use App\Models\TelegramChat;

class TelegramMessageFormatter
{
    public function help(): string
    {
        return implode("\n", [
            'Команды для всех:',
            '/help - помощь',
            '/my - моя статистика за сегодня',
            '/my_week - моя статистика за неделю',
            '/stats - статистика группы за сегодня',
            '/stats_week - статистика группы за неделю',
            '/top - рейтинг за сегодня',
            '/top_week - рейтинг за неделю',
            '/findword apple - найти слово',
            '',
            'Команды администраторов:',
            '/connect - подключить группу',
            '/disconnect - отключить группу',
            '/settings - настройки группы',
            '/set_polls_per_day 15',
            '/set_time 09:00 22:00',
            '/set_timezone Asia/Tashkent',
            '/set_level A1|A2|B1|B2|all',
            '/set_direction en_ru|ru_en|mixed',
            '/pause - поставить на паузу',
            '/resume - снять с паузы',
            '/send_now - отправить quiz сейчас',
            '/addword apple яблоко A1',
            '/disableword apple',
        ]);
    }

    public function settings(TelegramChat $chat): string
    {
        $settings = $chat->ensureSettings();

        return implode("\n", [
            'Настройки группы:',
            '',
            'Статус: '.($chat->is_active ? 'активна' : 'отключена'),
            "Часовой пояс: {$settings->timezone}",
            "Опросов в день: {$settings->polls_per_day}",
            "Время: {$this->time($settings->start_time)}–{$this->time($settings->end_time)}",
            'Уровень: '.($settings->level ?: 'все'),
            "Направление: {$settings->direction}",
            'Повтор ошибок: '.($settings->repeat_mistakes_enabled ? 'включён' : 'выключен'),
            'Пауза: '.($settings->is_paused ? 'да' : 'нет'),
        ]);
    }

    public function userStats(array $stats): string
    {
        return implode("\n", [
            $stats['title'] ?? 'Твоя статистика:',
            '',
            "Ответов: {$stats['total_answers']}",
            "Правильных: {$stats['correct_answers']}",
            "Ошибок: {$stats['wrong_answers']}",
            'Точность: '.$this->percent($stats['accuracy_percent']),
            'Место в рейтинге: '.($stats['rank'] ?? '-'),
        ]);
    }

    public function chatStats(array $stats): string
    {
        return implode("\n", [
            $stats['title'] ?? 'Статистика группы:',
            '',
            "Опросов отправлено: {$stats['polls_sent']}",
            "Всего ответов: {$stats['total_answers']}",
            "Правильных: {$stats['correct_answers']}",
            "Ошибок: {$stats['wrong_answers']}",
            'Средняя точность: '.$this->percent($stats['accuracy_percent']),
            "Участников отвечало: {$stats['unique_users']}",
        ]);
    }

    public function top(array $rows): string
    {
        $lines = ['Топ:', ''];
        if ($rows === []) {
            $lines[] = 'Пока нет ответов.';
            return implode("\n", $lines);
        }

        foreach ($rows as $index => $row) {
            $place = $index + 1;
            $lines[] = "{$place}. {$row['name']} — {$row['correct_answers']}/{$row['total_answers']}, ".$this->percent($row['accuracy_percent']);
        }

        return implode("\n", $lines);
    }

    public function connected(): string
    {
        return "Группа подключена. Бот будет отправлять quiz polls по английским словам.\n\nОткройте /settings для настройки расписания. Команды: /help";
    }

    public function disconnected(): string
    {
        return 'Группа отключена. История и статистика сохранены.';
    }

    public function notAdmin(): string
    {
        return 'Эта команда доступна только администраторам группы.';
    }

    public function invalidCommand(string $example): string
    {
        return "Неверная команда. Пример: {$example}";
    }

    public function botRightsMissing(): string
    {
        return 'Недостаточно прав. Дайте боту права на отправку сообщений и опросов.';
    }

    protected function percent(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').'%';
    }

    protected function time(string $time): string
    {
        return substr($time, 0, 5);
    }
}
