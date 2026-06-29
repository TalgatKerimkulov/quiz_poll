<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordTranslation extends Model
{
    protected $fillable = [
        'word_id', 'locale', 'text', 'status', 'source', 'provider', 'model',
        'confidence', 'review_notes', 'reviewed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }
}
