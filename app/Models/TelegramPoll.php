<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramPoll extends Model
{
    protected $fillable = [
        'telegram_poll_id',
        'telegram_message_id',
        'chat_id',
        'word_id',
        'question',
        'options',
        'correct_option_ids',
        'status',
        'direction',
        'level',
        'open_period',
        'error_message',
        'sent_at',
        'closed_at',
        'is_dry_run',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_option_ids' => 'array',
            'open_period' => 'integer',
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_dry_run' => 'boolean',
        ];
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }
}
