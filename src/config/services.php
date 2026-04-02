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
    | ERP (MVPController integration)
    |--------------------------------------------------------------------------
    |
    | Wraps endpoints described in `erp/MVPController.md` (e.g. /admin/mvp/OCRExtract).
    | Many deployments host these endpoints under the same host as AUTH_BASE_URL,
    | so ERP_BASE_URL defaults to AUTH_BASE_URL.
    |
    */
    'erp' => [
        'base_url' => env('ERP_BASE_URL', env('AUTH_BASE_URL', 'https://testbackerp.teljoy.io')),
        'timeout' => env('ERP_TIMEOUT', 30),
        // For development only. In production, keep SSL verification enabled.
        'verify_ssl' => env('ERP_VERIFY_SSL', true),
    ],

];
