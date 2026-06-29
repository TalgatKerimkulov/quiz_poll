<?php

namespace App\Providers;

use App\Services\Translation\Contracts\TranslationProvider;
use App\Services\Translation\OllamaTranslationProvider;
use App\Services\Translation\OpenAiTranslationProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TranslationProvider::class, function (Application $app): TranslationProvider {
            return match (config('ai.translation.driver')) {
                'openai' => $app->make(OpenAiTranslationProvider::class),
                'ollama' => $app->make(OllamaTranslationProvider::class),
                default => throw new \RuntimeException('Unsupported AI translation driver: '.config('ai.translation.driver')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
