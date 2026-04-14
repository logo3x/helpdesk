<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Provider (Chatbot / RAG)
    |--------------------------------------------------------------------------
    |
    | "openrouter" — uses OpenRouter API (free tier available)
    | "anthropic"  — direct Anthropic Claude API
    |
    | Switch provider by changing LLM_PROVIDER and the corresponding key.
    |
    */
    'llm' => [
        'provider' => env('LLM_PROVIDER', 'openrouter'),
        'model' => env('LLM_MODEL', 'meta-llama/llama-3.1-8b-instruct:free'),
        'api_key' => env('LLM_API_KEY', ''),
        'embedding_model' => env('LLM_EMBEDDING_MODEL', 'nomic-ai/nomic-embed-text-v1.5'),
    ],

];
