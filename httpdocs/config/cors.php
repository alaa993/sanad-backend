<?php

$origins = array_values(array_filter(array_unique(array_map(
    static fn ($origin) => rtrim(trim((string) $origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('APP_URL', '*')))
))));

if ($origins === []) {
    $origins = ['*'];
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
