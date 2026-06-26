<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    protected $fillable = [
        'word_en',
        'translation_ru',
        'level',
        'part_of_speech',
        'example_en',
        'example_ru',
        'is_active',
        'created_by_user_id',
        'created_by_username',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
