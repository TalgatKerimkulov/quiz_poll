<?php

namespace App\Console\Commands;

use App\Services\Telegram\WordManagementService;
use Illuminate\Console\Command;
use Throwable;

class ImportWordsCommand extends Command
{
    protected $signature = 'words:import {path : CSV path}';

    protected $description = 'Import words from CSV.';

    public function handle(WordManagementService $words): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $imported = $skipped = $failed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            if (! $data) {
                $failed++;
                continue;
            }

            try {
                $before = \App\Models\Word::where('word_en', mb_strtolower(trim($data['word_en'] ?? '')))
                    ->where('translation_ru', trim($data['translation_ru'] ?? ''))
                    ->exists();
                $word = $words->add($data['word_en'] ?? '', $data['translation_ru'] ?? '', $data['level'] ?? null);
                $word->update([
                    'part_of_speech' => $data['part_of_speech'] ?? null,
                    'example_en' => $data['example_en'] ?? null,
                    'example_ru' => $data['example_ru'] ?? null,
                ]);
                $before ? $skipped++ : $imported++;
            } catch (Throwable) {
                $failed++;
            }
        }
        fclose($handle);

        $this->info("Imported: {$imported}; skipped: {$skipped}; failed: {$failed}");

        return self::SUCCESS;
    }
}
