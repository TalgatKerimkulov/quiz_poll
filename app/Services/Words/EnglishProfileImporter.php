<?php

namespace App\Services\Words;

use App\Models\Word;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnglishProfileImporter
{
    /** @return array{files:int,rows:int,words:int,translations:int,examples:int,skipped:int} */
    public function import(string $path, bool $dryRun = false, bool $includeAll = false): array
    {
        $files = $this->files($path, $includeAll);
        $totals = ['files' => count($files), 'rows' => 0, 'words' => 0, 'translations' => 0, 'examples' => 0, 'skipped' => 0];

        foreach ($files as $file) {
            $result = $dryRun
                ? $this->importFile($file, true)
                : DB::transaction(fn (): array => $this->importFile($file, false));

            foreach (array_keys($result) as $key) {
                $totals[$key] += $result[$key];
            }
        }

        return $totals;
    }

    /** @return array<int, string> */
    protected function files(string $path, bool $includeAll): array
    {
        if (is_file($path)) {
            return [$path];
        }
        if (! is_dir($path)) {
            throw new RuntimeException("CSV path does not exist: {$path}");
        }

        $files = glob(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.csv') ?: [];
        if (! $includeAll) {
            $files = array_values(array_filter($files, fn (string $file): bool => ! str_contains(basename($file), '(All-')));
        }
        sort($files);

        if ($files === []) {
            throw new RuntimeException("No CSV files found in: {$path}");
        }

        return $files;
    }

    /** @return array{rows:int,words:int,translations:int,examples:int,skipped:int} */
    protected function importFile(string $file, bool $dryRun): array
    {
        $handle = fopen($file, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open CSV file: {$file}");
        }

        try {
            $header = fgetcsv($handle, null, ';', '"', '');
            if (! is_array($header)) {
                throw new RuntimeException("CSV header is missing: {$file}");
            }
            $columns = [];
            foreach ($header as $index => $name) {
                $columns[$this->clean((string) $name)] = $index;
            }
            foreach (['Base Word', 'Translate (uzbek)', 'Level'] as $required) {
                if (! array_key_exists($required, $columns)) {
                    throw new RuntimeException("Required column '{$required}' is missing in {$file}");
                }
            }

            $result = ['rows' => 0, 'words' => 0, 'translations' => 0, 'examples' => 0, 'skipped' => 0];
            while (($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
                $result['rows']++;
                $term = $this->value($row, $columns, 'Base Word');
                if ($term === '') {
                    $result['skipped']++;

                    continue;
                }

                $translation = $this->value($row, $columns, 'Translate (uzbek)');
                $level = strtoupper($this->value($row, $columns, 'Level')) ?: null;
                $partOfSpeech = $this->value($row, $columns, 'Part of Speech') ?: null;
                $guideword = $this->value($row, $columns, 'Guideword') ?: null;
                $topic = $this->value($row, $columns, 'Topic') ?: null;
                $example = $this->value($row, $columns, 'Sentence (English)');
                $exampleTranslation = $this->value($row, $columns, 'Translation (Uzbek)');
                $sourceKey = hash('sha256', json_encode([
                    mb_strtolower($term), $level, $partOfSpeech, $guideword, $topic,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                $result['words']++;
                $result['translations'] += $translation !== '' ? 1 : 0;
                $result['examples'] += $example !== '' ? 1 : 0;
                if ($dryRun) {
                    continue;
                }

                $word = Word::updateOrCreate(
                    ['source' => 'english_profile', 'source_key' => $sourceKey],
                    [
                        'term' => mb_strtolower($term),
                        'locale' => 'en',
                        'level' => $level,
                        'part_of_speech' => $partOfSpeech,
                        'guideword' => $guideword,
                        'topic' => $topic,
                        'is_active' => true,
                    ],
                );

                if ($translation !== '') {
                    $word->translations()->updateOrCreate(
                        ['locale' => 'uz'],
                        ['text' => $translation, 'status' => 'imported', 'source' => 'english_profile'],
                    );
                }
                if ($example !== '') {
                    $word->examples()->updateOrCreate(
                        ['locale' => 'en', 'text' => $example],
                        [
                            'translation_locale' => $exampleTranslation !== '' ? 'uz' : null,
                            'translation_text' => $exampleTranslation ?: null,
                            'source' => 'english_profile',
                        ],
                    );
                }
            }

            return $result;
        } finally {
            fclose($handle);
        }
    }

    protected function value(array $row, array $columns, string $column): string
    {
        if (! array_key_exists($column, $columns)) {
            return '';
        }

        return $this->clean((string) ($row[$columns[$column]] ?? ''));
    }

    protected function clean(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        $value = str_replace(["\u{00A0}", "\u{2018}", "\u{2019}"], [' ', "\u{02BB}", "\u{02BB}"], $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
