<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Llama Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to an offline Llama 3 model server.
    | This assumes you're running Ollama or a compatible API server locally.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL of your local Llama API server.
    | Default: Ollama running on localhost:11434
    |
    */

    'base_url' => env('LLAMA_BASE_URL', 'http://localhost:11434'),

    /*
    |--------------------------------------------------------------------------
    | Model Name
    |--------------------------------------------------------------------------
    |
    | The name of the Llama model to use.
    | Common options: llama3, llama3:8b, llama3:70b, llama3.1, etc.
    |
    */

    'model' => env('LLAMA_MODEL', 'llama3'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout in seconds for API requests.
    | Increase this for larger models or slower hardware.
    |
    */

    'timeout' => env('LLAMA_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Default Generation Options
    |--------------------------------------------------------------------------
    |
    | Default parameters for text generation.
    |
    */

    'temperature' => env('LLAMA_TEMPERATURE', 0.7),
    'top_p' => env('LLAMA_TOP_P', 0.9),
    'max_tokens' => env('LLAMA_MAX_TOKENS', 2048),

    /*
    |--------------------------------------------------------------------------
    | Health Recommendations
    |--------------------------------------------------------------------------
    |
    | Configuration for health/fitness recommendation generation.
    |
    */

    'health_recommendations' => [
        'enabled' => env('LLAMA_HEALTH_ENABLED', true),
        'temperature' => 0.8,
        'max_tokens' => 1500,
    ],
];

