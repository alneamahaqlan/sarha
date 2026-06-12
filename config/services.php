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

    // Platform-specific service credentials
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    // AI assistant + article generation.
    // The active provider is picked by SystemSetting('ai_provider') (default: gemini).
    // Env values below are only used as fallback when the DB rows are empty.
    'ai' => [
        'default' => env('AI_DEFAULT_PROVIDER', 'gemini'),
    ],

    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'openai' => [
        'key'   => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],

    'unifonic' => [
        'app_sid'   => env('UNIFONIC_APP_SID'),
        'sender_id' => env('UNIFONIC_SENDER_ID'),
    ],

    // WhatsApp OTP gateway (Wappi — https://wappi.pro). Per-number credentials
    // (profile_id + token) live in the whatsapp_senders table, managed from the
    // admin panel. Only the base URL is configurable here so the endpoint can
    // be repointed without code changes.
    'wappi' => [
        'base_url' => env('WAPPI_BASE_URL', 'https://wappi.pro'),
    ],

    'moyasar' => [
        'secret_key'  => env('MOYASAR_SECRET_KEY'),
        'publishable' => env('MOYASAR_PUBLISHABLE_KEY'),
    ],

    // VAPID identity for Web Push (Phase C). Public key is also
    // exposed to the browser via /api/v1/push/vapid-public-key so the
    // service worker can sign its subscription handshake.
    'webpush' => [
        'vapid_public_key'  => env('VAPID_PUBLIC_KEY'),
        'vapid_private_key' => env('VAPID_PRIVATE_KEY'),
        'vapid_subject'     => env('VAPID_SUBJECT', 'mailto:admin@saerha.sa'),
    ],

];
