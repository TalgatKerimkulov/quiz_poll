<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TelegramChat extends Model
{
    protected $fillable = [
        'chat_id',
        'title',
        'type',
        'is_active',
        'inactive_reason',
        'connected_by_user_id',
        'connected_by_username',
        'connected_at',
        'bot_status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'connected_at' => 'datetime',
            'bot_status_updated_at' => 'datetime',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(TelegramChatSetting::class);
    }

    public function ensureSettings(): TelegramChatSetting
    {
        return $this->settings()->firstOrCreate([], TelegramChatSetting::defaults());
    }
}
