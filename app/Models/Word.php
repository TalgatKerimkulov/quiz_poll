<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Word extends Model
{
    protected $fillable = [
        'term',
        'locale',
        'level',
        'part_of_speech',
        'guideword',
        'topic',
        'source',
        'source_key',
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

    public function translations(): HasMany
    {
        return $this->hasMany(WordTranslation::class);
    }

    public function examples(): HasMany
    {
        return $this->hasMany(WordExample::class);
    }

    public function translation(string $locale): HasOne
    {
        return $this->hasOne(WordTranslation::class)->where('locale', $locale);
    }

    public function translationFor(string $locale): ?WordTranslation
    {
        return $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $locale)
            : $this->translations()->where('locale', $locale)->first();
    }
}
