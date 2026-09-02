<?php

return [

    'api_path' => 'api/v1',

    'api_domain' => null,

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Server
    |--------------------------------------------------------------------------
    |
    | Force HTTPS because production is behind Nginx/OpenResty.
    | This prevents Scramble from generating:
    |
    | http://smartpos-api.servicefixit.me/api/v1
    |
    */

    'servers' => env('APP_ENV') === 'production'
        ? [
            'Production' => env('SCRAMBLE_SERVER_PROD', 'https://smartpos-api.servicefixit.me/api/v1'),
        ]
        : [
            'Local' => rtrim(env('APP_URL', 'http://api.smartpos.test'), '/') . '/api/v1',
            'Local (Gateway)' => 'http://localhost:8000/api/v1',
            'Production' => env('SCRAMBLE_SERVER_PROD', 'https://smartpos-api.servicefixit.me/api/v1'),
        ],

    'info' => [
        'version' => '1.0.0',

        'description' => '
# SmartPOS Identity

Identity and Access Management API for SmartPOS.

## Process Documentation & Architecture Reports

- 📖 **[IDENTITY_SERVICE_PROCESS_REPORT.md](https://smartpos-api.servicefixit.me/api/v1/IDENTITY_SERVICE_PROCESS_REPORT.md)**

## Features

- JWT Authentication
- Refresh Tokens
- Users
- Roles
- Permissions
- POS PIN
- Devices
- Sessions
- OTP
- Login Attempts
        ',
    ],

    'ui' => [
        'title' => 'SmartPOS Identity',
    ],

    'security_strategy' =>
        \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
];