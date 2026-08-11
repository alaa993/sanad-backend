<?php

return [
    'url' => rtrim(env('REALTIME_SOCKET_URL', env('APP_URL')), '/'),
    'path' => env('REALTIME_SOCKET_PATH', '/socket/'),
    'token' => env('REALTIME_SOCKET_TOKEN', env('APP_KEY')),
    'internal_url' => rtrim((string) env('REALTIME_INTERNAL_URL', 'http://127.0.0.1:3000'), '/'),
];
