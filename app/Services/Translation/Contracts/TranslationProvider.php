<?php

namespace App\Services\Translation\Contracts;

use Illuminate\Support\Collection;

interface TranslationProvider
{
    /**
     * @return array<int, array{translation: string, confidence: float|null, notes: string|null}>
     */
    public function translate(Collection $words, string $targetLocale): array;

    public function name(): string;

    public function model(): string;
}
