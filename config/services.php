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

    'oidc' => [
        'base_url' => env('AUTH_OIDC_ISSUER'),
        'client_id' => env('AUTH_OIDC_CLIENT_ID'),
        'client_secret' => env('AUTH_OIDC_CLIENT_SECRET'),
        'redirect' => env('AUTH_OIDC_REDIRECT_URI'),
        'verify_jwt' => true,
    ],

    // AI recipe import and recipe images (OpenAI). Without a key the AI features are hidden.
    // One key for all users; who may use it: users.ai_enabled (verified users only).
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL') ?: 'gpt-6-luna',
        // Only for reasoning models; empty to omit the parameter.
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
        'image_model' => env('OPENAI_IMAGE_MODEL') ?: 'gpt-image-2',
        'image_quality' => env('OPENAI_IMAGE_QUALITY', 'medium'),
        'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 150),
        // AI requests (recipe or image) per user and hour.
        'hourly_limit' => (int) env('OPENAI_HOURLY_LIMIT', 20),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
