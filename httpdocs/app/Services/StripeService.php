<?php
namespace App\Services;
use Stripe\Stripe;
use Stripe\PaymentIntent;
class StripeService {
  public function __construct(){
    Stripe::setApiKey(config('stripe.secret'));
  }
  public function createIntent(int $amount, array $metadata = []){
    // amount is passed through as Stripe minor units (same as before).
    // Spendable ledger uses metadata.points (1:1 with the UI amount).
    $pi = PaymentIntent::create([
      'amount' => max(1, $amount),
      'currency' => config('stripe.currency','USD'),
      'automatic_payment_methods' => ['enabled'=>true],
      'metadata' => array_merge([
        'purpose' => 'wallet_topup',
        'points' => (string) $amount,
      ], $metadata),
    ]);
    return ['id'=>$pi->id, 'client_secret'=>$pi->client_secret];
  }
}
