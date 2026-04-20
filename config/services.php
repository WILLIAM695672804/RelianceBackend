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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'momo' => [
        'base_url' => env('MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
        'api_key' => env('MOMO_API_KEY'),
        'api_user' => env('MOMO_API_USER'),
        'subscription_key' => env('MOMO_SUBSCRIPTION_KEY'),
        'environment' => env('MOMO_ENVIRONMENT', 'sandbox'),
    ],

    'orange_money' => [
        'base_url' => env('OM_BASE_URL'),
        'merchant_key' => env('OM_MERCHANT_KEY'),
        'api_key' => env('OM_API_KEY'),
    ],

];
