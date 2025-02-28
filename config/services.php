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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'api_key' => env('WHATSAPP_API_KEY'),
        'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v17.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'api_url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-pro'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],
    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'plans' => [
            'starter' => [
                'monthly' => env('RAZORPAY_PLAN_STARTER_MONTHLY'),
                'quarterly' => env('RAZORPAY_PLAN_STARTER_QUARTERLY'),
                'annual' => env('RAZORPAY_PLAN_STARTER_ANNUAL'),
            ],
            'growth' => [
                'monthly' => env('RAZORPAY_PLAN_GROWTH_MONTHLY'),
                'quarterly' => env('RAZORPAY_PLAN_GROWTH_QUARTERLY'),
                'annual' => env('RAZORPAY_PLAN_GROWTH_ANNUAL'),
            ],
            'enterprise' => [
                'monthly' => env('RAZORPAY_PLAN_ENTERPRISE_MONTHLY'),
                'quarterly' => env('RAZORPAY_PLAN_ENTERPRISE_QUARTERLY'),
                'annual' => env('RAZORPAY_PLAN_ENTERPRISE_ANNUAL'),
            ],
        ],
    ],
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'prices' => [
            'starter' => [
                'monthly' => env('STRIPE_PRICE_STARTER_MONTHLY'),
                'quarterly' => env('STRIPE_PRICE_STARTER_QUARTERLY'),
                'annual' => env('STRIPE_PRICE_STARTER_ANNUAL'),
            ],
            'growth' => [
                'monthly' => env('STRIPE_PRICE_GROWTH_MONTHLY'),
                'quarterly' => env('STRIPE_PRICE_GROWTH_QUARTERLY'),
                'annual' => env('STRIPE_PRICE_GROWTH_ANNUAL'),
            ],
            'enterprise' => [
                'monthly' => env('STRIPE_PRICE_ENTERPRISE_MONTHLY'),
                'quarterly' => env('STRIPE_PRICE_ENTERPRISE_QUARTERLY'),
                'annual' => env('STRIPE_PRICE_ENTERPRISE_ANNUAL'),
            ],
        ],
    ],
    'subscriptions' => [
        'default_provider' => env('DEFAULT_PAYMENT_PROVIDER', 'stripe'),
    ],

];
