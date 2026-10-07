<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // Separate from APP_ENV on purpose: verifying AwsTranslateTranslator
    // against the real Amazon Translate API (via IAM Identity Center/STS
    // temporary credentials) is something done *from* a local environment,
    // so it cannot be tied to APP_ENV=local meaning "always fake". Testing
    // always forces 'fake' via phpunit.xml, regardless of this env var.
    'translate' => [
        'driver' => env('TRANSLATOR_DRIVER', 'fake'),
        'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Phase 6: days after a Provider's completion report before an
    // unconfirmed Job is auto-completed (FR-38).
    'auto_confirm_days' => env('AUTO_CONFIRM_DAYS', 3),

];
