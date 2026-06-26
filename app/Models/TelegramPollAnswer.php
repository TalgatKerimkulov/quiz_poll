<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramPollAnswer extends Model
{
    protected $fillable = [
        'telegram_poll_id',
        'chat_id',
        'telegram_user_id',
        'telegram_username',
        'telegram_first_name',
        'option_ids',
        'is_correct',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'option_ids' => 'array',
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }
}
