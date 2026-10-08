<?php

return [

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'effort' => env('ANTHROPIC_EFFORT', 'medium'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 4000),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    'google' => [
        // Path to the service-account JSON key, relative to the project root or absolute.
        'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_JSON', 'storage/app/private/google-service-account.json'),
    ],

    'knowledge' => [
        'chunk_size' => 1500,
        'chunk_overlap' => 200,
        'max_context_chunks' => 8,
        'max_file_bytes' => 15 * 1024 * 1024,
    ],

];
