<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:8000')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'X-API-Key', 'X-Authorization', 'X-Install-Id', 'X-License-Domain', 'Authorization'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];
