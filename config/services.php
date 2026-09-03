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

    /*
     * SES admite credenciales propias porque el disco s3 puede apuntar a otro
     * proveedor (DigitalOcean Spaces, por ejemplo) con claves distintas. Si no
     * se definen las AWS_SES_*, se usan las compartidas.
     */
    'ses' => [
        'key' => env('AWS_SES_KEY', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('AWS_SES_SECRET', env('AWS_SECRET_ACCESS_KEY')),
        'region' => env('AWS_SES_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
