<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'dry_run' => filter_var(env('TELEGRAM_DRY_RUN', true), FILTER_VALIDATE_BOOLEAN),

    'defaults' => [
        'timezone' => env('TELEGRAM_DEFAULT_TIMEZONE', 'Asia/Tashkent'),
        'polls_per_day' => (int) env('TELEGRAM_POLLS_PER_DAY', 20),
        'start_time' => env('TELEGRAM_POLL_START_TIME', '09:00'),
        'end_time' => env('TELEGRAM_POLL_END_TIME', '23:00'),
        'poll_open_period' => (int) env('TELEGRAM_POLL_OPEN_PERIOD', 1800),
        'level' => env('TELEGRAM_DEFAULT_LEVEL'),
        'source_locale' => env('TELEGRAM_SOURCE_LOCALE', 'en'),
        'target_locale' => env('TELEGRAM_TARGET_LOCALE', 'ru'),
        'direction' => env('TELEGRAM_DEFAULT_DIRECTION', 'forward'),
        'repeat_mistakes_enabled' => filter_var(env('TELEGRAM_REPEAT_MISTAKES_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'http' => [
        'timeout' => (int) env('TELEGRAM_HTTP_TIMEOUT', 10),
        'retry_times' => (int) env('TELEGRAM_HTTP_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('TELEGRAM_HTTP_RETRY_SLEEP_MS', 500),
    ],

    'locales' => ['en', 'ru', 'uz'],
];
