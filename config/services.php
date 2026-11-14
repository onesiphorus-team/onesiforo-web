<?php

declare(strict_types=1);

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

    'telegram-bot-api' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),

        /*
         * Where the box activity alerts go (box on/off, meetings, playback). When the chat is
         * not set, they go to the Telegram chats of the caregivers of the box (each chat once).
         * The topic id targets a topic ("thread") of a group with topics, e.g. "support".
         */
        'support_chat_id' => env('TELEGRAM_SUPPORT_CHAT_ID'),
        'support_topic_id' => env('TELEGRAM_SUPPORT_TOPIC_ID'),

        /*
         * "Collega Telegram" in the profile: off by default. Turning it on needs the bot username
         * and a webhook secret, then `php artisan telegram:set-webhook` (which replaces any webhook
         * or getUpdates consumer the bot already has).
         */
        'linking_enabled' => (bool) env('TELEGRAM_LINKING_ENABLED', false),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

];
