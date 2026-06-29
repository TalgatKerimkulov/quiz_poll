<?php

return [
    'translation' => [
        'driver' => env('AI_TRANSLATION_DRIVER', 'openai'),
        'batch_size' => (int) env('AI_TRANSLATION_BATCH_SIZE', 20),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_TRANSLATION_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_TRANSLATION_MODEL', 'qwen2.5:3b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 300),
        'num_thread' => (int) env('OLLAMA_NUM_THREAD', 0),
    ],
];
