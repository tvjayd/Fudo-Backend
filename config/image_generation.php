<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Generation Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for generating meal images using AI image generation APIs.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | The image generation service provider to use.
    | Options: 'openai', 'stability', 'replicate', 'huggingface'
    |
    */

    'provider' => env('IMAGE_GENERATION_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration (DALL-E)
    |--------------------------------------------------------------------------
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_IMAGE_MODEL', 'dall-e-3'),
        'size' => env('OPENAI_IMAGE_SIZE', '1024x1024'),
        'quality' => env('OPENAI_IMAGE_QUALITY', 'standard'), // standard or hd
        'style' => env('OPENAI_IMAGE_STYLE', 'natural'), // vivid or natural
    ],

    /*
    |--------------------------------------------------------------------------
    | Stability AI Configuration (Stable Diffusion)
    |--------------------------------------------------------------------------
    */

    'stability' => [
        'api_key' => env('STABILITY_API_KEY'),
        'model' => env('STABILITY_MODEL', 'stable-diffusion-xl-1024-v1-0'),
        'width' => env('STABILITY_WIDTH', 1024),
        'height' => env('STABILITY_HEIGHT', 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Replicate Configuration
    |--------------------------------------------------------------------------
    */

    'replicate' => [
        'api_key' => env('REPLICATE_API_KEY'),
        'model' => env('REPLICATE_MODEL', 'stability-ai/sdxl:39ed52f2a78e934b3ba6e2a89f5b1c712de7dfea535525255b1aa35c5565e08b'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hugging Face Configuration
    |--------------------------------------------------------------------------
    */

    'huggingface' => [
        'api_key' => env('HUGGINGFACE_API_KEY'),
        'model' => env('HUGGINGFACE_MODEL', 'stabilityai/stable-diffusion-xl-base-1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Storage
    |--------------------------------------------------------------------------
    |
    | Whether to download and store images locally or use remote URLs.
    |
    */

    'store_locally' => env('IMAGE_STORE_LOCALLY', false),
    'storage_path' => env('IMAGE_STORAGE_PATH', 'public/meal-images'),

    /*
    |--------------------------------------------------------------------------
    | Fallback
    |--------------------------------------------------------------------------
    |
    | Use placeholder images if generation fails.
    |
    */

    'use_placeholder_fallback' => env('IMAGE_USE_PLACEHOLDER_FALLBACK', true),
];

