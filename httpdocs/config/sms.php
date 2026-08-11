<?php

return [
    'driver' => env('SMS_DRIVER', 'log'),

    'from' => env('SMS_FROM', 'Sanad'),

    'http' => [
        'url' => env('SMS_HTTP_URL'),
        'method' => env('SMS_HTTP_METHOD', 'POST'),
        'phone_key' => env('SMS_HTTP_PHONE_KEY', 'phone'),
        'message_key' => env('SMS_HTTP_MESSAGE_KEY', 'message'),
        'headers' => array_filter([
            'Authorization' => env('SMS_HTTP_AUTH') ? 'Bearer ' . env('SMS_HTTP_AUTH') : null,
            'Accept' => 'application/json',
        ]),
    ],

    'otp' => [
        'ttl_minutes' => (int) env('SMS_OTP_TTL', 10),
        'length' => 6,
        'message_ar' => 'رمز التحقق في سند: :code',
        'message_en' => 'Your Sanad verification code: :code',
    ],
];
