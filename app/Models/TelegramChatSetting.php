<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramChatSetting extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'timezone',
        'polls_per_day',
        'start_time',
        'end_time',
        'poll_open_period',
        'level',
        'direction',
        'repeat_mistakes_enabled',
        'weekdays',
        'is_paused',
    ];

    protected function casts(): array
    {
        return [
            'polls_per_day' => 'integer',
            'poll_open_period' => 'integer',
            'repeat_mistakes_enabled' => 'boolean',
            'weekdays' => 'array',
            'is_paused' => 'boolean',
        ];
    }

    public static function defaults(): array
    {
        return [
            'timezone' => (string) config('telegram.defaults.timezone', 'Asia/Tashkent'),
            'polls_per_day' => (int) config('telegram.defaults.polls_per_day', 20),
            'start_time' => (string) config('telegram.defaults.start_time', '09:00'),
            'end_time' => (string) config('telegram.defaults.end_time', '23:00'),
            'poll_open_period' => (int) config('telegram.defaults.poll_open_period', 1800),
            'level' => config('telegram.defaults.level'),
            'direction' => (string) config('telegram.defaults.direction', 'en_ru'),
            'repeat_mistakes_enabled' => (bool) config('telegram.defaults.repeat_mistakes_enabled', true),
            'is_paused' => false,
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class, 'telegram_chat_id');
    }
}
