<?php
namespace App\Http\Controllers\Api\V1\Billing;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class StripeWebhookController extends Controller {
  public function handle(Request $r){
    $secret = config('stripe.webhook_secret');
    $sig = $r->header('Stripe-Signature');
    $payload = $r->getContent();
    try {
      $event = Webhook::constructEvent($payload, $sig, $secret);
      Log::info('stripe_event', ['type'=>$event->type]);

      if ($event->type === 'payment_intent.succeeded') {
        $this->creditWalletFromPaymentIntent($event->data->object);
      }

      return response()->json(['ok'=>true]);
    } catch(\Throwable $e) {
      Log::error('stripe_webhook_error', ['err'=>$e->getMessage()]);
      return response()->json(['ok'=>false], 400);
    }
  }

  private function creditWalletFromPaymentIntent($pi): void
  {
    $meta = is_object($pi->metadata ?? null) ? (array) $pi->metadata->toArray() : (array) ($pi->metadata ?? []);
    if (($meta['purpose'] ?? '') !== 'wallet_topup') {
      return;
    }
    $userId = (int) ($meta['user_id'] ?? 0);
    $points = (int) ($meta['points'] ?? 0);
    if ($userId <= 0 || $points <= 0) {
      Log::warning('stripe_topup_missing_meta', ['meta' => $meta, 'pi' => $pi->id ?? null]);
      return;
    }

    $piId = (string) ($pi->id ?? '');
    $already = DB::table('transactions')
      ->where('owner_type', 'user')
      ->where('owner_id', $userId)
      ->where('type', 'point_credit')
      ->where('meta', 'like', '%"payment_intent":"'.$piId.'"%')
      ->exists();
    if ($already) {
      return;
    }

    DB::transaction(function () use ($userId, $points, $piId) {
      $wallet = DB::table('wallets')->where(['owner_type'=>'user','owner_id'=>$userId])->lockForUpdate()->first();
      if (!$wallet) {
        DB::table('wallets')->insert([
          'owner_type'=>'user','owner_id'=>$userId,'balance'=>0,'points'=>0,
          'created_at'=>now(),'updated_at'=>now(),
        ]);
        $wallet = DB::table('wallets')->where(['owner_type'=>'user','owner_id'=>$userId])->lockForUpdate()->first();
      }
      DB::table('wallets')->where('id', $wallet->id)->update([
        'points' => DB::raw('points + '.$points),
        'updated_at' => now(),
      ]);
      DB::table('transactions')->insert([
        'owner_type' => 'user',
        'owner_id'   => $userId,
        'type'       => 'point_credit',
        'amount'     => 0,
        'points'     => $points,
        'currency'   => 'PTS',
        'meta'       => json_encode(['source'=>'stripe','payment_intent'=>$piId]),
        'status'     => 'succeeded',
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    });

    Cache::forget("billing:wallet:{$userId}");
    Cache::forget("billing:tx:{$userId}");
  }
}
