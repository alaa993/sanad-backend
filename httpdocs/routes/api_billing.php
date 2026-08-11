
<?php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function(){
  // Plans
  Route::get('/api/v1/billing/plans', [\App\Http\Controllers\Api\V1\Billing\PlansController::class, 'index']);
  // Subscriptions
  Route::post('/api/v1/billing/subscribe', [\App\Http\Controllers\Api\V1\Billing\SubscriptionsController::class, 'subscribe']);
  Route::post('/api/v1/billing/cancel', [\App\Http\Controllers\Api\V1\Billing\SubscriptionsController::class, 'cancel']);
  // Wallet
  Route::get('/api/v1/wallet/me', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'me']);
  Route::post('/api/v1/wallet/topup/intent', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'createIntent']);
  Route::post('/api/v1/wallet/apply-coupon', [\App\Http\Controllers\Api\V1\Billing\WalletController::class, 'applyCoupon']);
  // Checkout for sessions
  Route::post('/api/v1/sessions/{id}/confirm-payment', [\App\Http\Controllers\Api\V1\Billing\CheckoutController::class, 'confirmSessionPayment']);
  // Invoices & Transactions
  Route::get('/api/v1/billing/invoices', [\App\Http\Controllers\Api\V1\Billing\InvoicesController::class, 'index']);
  Route::get('/api/v1/billing/transactions', [\App\Http\Controllers\Api\V1\Billing\TransactionsController::class, 'index']);
});

// Stripe webhook (no auth)
Route::post('/api/webhooks/stripe', [\App\Http\Controllers\Api\V1\Billing\StripeWebhookController::class, 'handle']);
Route::post('/api/v1/ios/verify-receipt', [\App\Http\Controllers\Api\V1\Billing\IosReceiptController::class, 'verify'])->middleware('auth:sanctum');
