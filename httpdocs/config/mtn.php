<?php

return [
    'enabled' => (bool) env('MTN_ENABLED', false),

    'sandbox' => (bool) env('MTN_SANDBOX', true),

    'api_url' => env('MTN_API_URL', 'https://sandbox.momodeveloper.mtn.com'),

    'subscription_key' => env('MTN_SUBSCRIPTION_KEY'),

    'api_user' => env('MTN_API_USER'),

    'api_key' => env('MTN_API_KEY'),

    'currency' => env('MTN_CURRENCY', 'SYP'),

    'callback_url' => env('MTN_CALLBACK_URL'),

    'ussd_short_code' => env('MTN_USSD_SHORT_CODE', '*123#'),

    'instructions_ar' => 'أكمل الدفع عبر MTN باستخدام المرجع :reference ثم أدخل رقم العملية في التطبيق.',

    'instructions_en' => 'Complete MTN payment using reference :reference, then enter the transaction ID in the app.',
];
