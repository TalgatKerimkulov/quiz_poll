<?php

namespace App\Console\Commands;

use App\Services\Words\EnglishProfileImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportWordsCommand extends Command
{
    protected $signature = 'words:import
        {path=public : English Profile CSV file or directory}
        {--dry-run : Parse and validate without writing}
        {--include-all : Include the aggregate All file when importing a directory}';

    protected $description = 'Import multilingual words from English Profile semicolon CSV files.';

    public function handle(EnglishProfileImporter $importer): int
    {
        try {
            $result = $importer->import(
                (string) $this->argument('path'),
                (bool) $this->option('dry-run'),
                (bool) $this->option('include-all'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Files', 'Rows', 'Words', 'Translations', 'Examples', 'Skipped'],
            [[
                $result['files'], $result['rows'], $result['words'],
                $result['translations'], $result['examples'], $result['skipped'],
            ]],
        );
        if ($this->option('dry-run')) {
            $this->comment('Dry run: database was not changed.');
        }

        return self::SUCCESS;
    }
}
