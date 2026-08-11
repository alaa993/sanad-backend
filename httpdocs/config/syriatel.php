<?php

return [
    'enabled' => (bool) env('SYRIATEL_ENABLED', true),

    'sandbox' => (bool) env('SYRIATEL_SANDBOX', true),

    'currency' => env('SYRIATEL_CURRENCY', 'SYP'),

    'ussd_short_code' => env('SYRIATEL_USSD_SHORT_CODE', '*150#'),

    'merchant_code' => env('SYRIATEL_MERCHANT_CODE', ''),

    'api_url' => env('SYRIATEL_API_URL', ''),

    'api_key' => env('SYRIATEL_API_KEY'),

    'instructions_ar' => 'ادفع عبر سيريتيل كاش باستخدام المرجع :reference ثم أدخل رقم العملية في التطبيق لإضافة النقاط.',

    'instructions_en' => 'Pay via Syriatel Cash using reference :reference, then enter the transaction ID in the app to credit points.',
];
