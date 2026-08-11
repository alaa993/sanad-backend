
<?php
return [
  'secret' => env('STRIPE_SECRET', ''),
  'public' => env('STRIPE_PUBLIC', ''),
  'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
  'currency' => env('BILLING_CURRENCY', 'USD'),
];
