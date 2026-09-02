<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'docs/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL', 'https://app.yourdomain.com'),
        env('POS_CLIENT_URL', 'https://pos.yourdomain.com'),
        'http://localhost',
        'http://localhost:80',
        'http://localhost:8000',
        'http://localhost:8080',
        'http://localhost:8001',
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:5173',
        'http://127.0.0.1',
        'http://127.0.0.1:80',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:8080',
        'http://api.smartpos.test',
        'https://api.smartpos.test',
        'https://smartpos-api.servicefixit.me',
    ])),

    'allowed_origins_patterns' => [
        '#^https?://(api|admin|pos|app)\.smartpos\.test(:[0-9]+)?$#',
        '#^https?://.*\.ngrok(-free)?\.app$#',
        '#^https?://.*\.ngrok\.io$#',
        '#^https?://localhost:(80|8000|8080|8001|8002|8003|3000|3001|5173)$#',
        '#^https?://127\.0\.0\.1:(80|8000|8080|8001|8002|8003|3000|3001|5173)$#',
    ],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-User-Uuid', 'Accept', 'Origin'],

    'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
