<?php

declare(strict_types=1);

return [

    /*
    | CORS is locked to the storefront origin(s). The storefront authenticates
    | with bearer tokens (no cross-site cookies), so credentials are disabled.
    | Add production origins via the FRONTEND_URL env var.
    */

    'paths' => ['api/*', 'sitemap.xml', 'robots.txt', 'feed/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL'),
        'http://localhost:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3000',
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
