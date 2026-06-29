<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordExample extends Model
{
    protected $fillable = [
        'word_id', 'locale', 'text', 'translation_locale', 'translation_text', 'source',
    ];

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }
}
