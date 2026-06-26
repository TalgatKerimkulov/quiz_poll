<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramUserWordStat extends Model
{
    protected $fillable = [
        'chat_id',
        'telegram_user_id',
        'word_id',
        'correct_count',
        'wrong_count',
        'last_answered_at',
        'last_wrong_at',
    ];

    protected function casts(): array
    {
        return [
            'correct_count' => 'integer',
            'wrong_count' => 'integer',
            'last_answered_at' => 'datetime',
            'last_wrong_at' => 'datetime',
        ];
    }
}
