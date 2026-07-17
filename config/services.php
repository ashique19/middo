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

    'mimsms' => [
        'api_key'       => env('MIMSMS_API_KEY'),
        'user_name'     => env('MIMSMS_USERNAME'),
        'sender_name'   => env('MIMSMS_SENDER_NAME'),
        'base_url'      => env('MIMSMS_BASE_URL', 'https://api.mimsms.com/api/V2/SMS'),
    ],

    /*
    | Firebase Cloud Messaging (HTTP v1).
    | Download a service account JSON from Firebase Console → Project settings
    | → Service accounts → Generate new private key. Point FIREBASE_CREDENTIALS
    | at that file path. Leave unset / missing to skip sending pushes.
    */
    'fcm' => [
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

];
