<?php
namespace App\Http\Controllers\Api\V1\Billing;
use App\Http\Controllers\Controller; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
use App\Services\StripeService;
use App\Services\MtnPaymentService;
use App\Services\SyriatelCashService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
/**
 * Patient wallet: balance/points, Stripe intent, coupons, MTN/Syriatel Cash top-up, payment-method presets.
 * Sessions spend points; leftover cash balance is migrated into points on read when needed.
 */
class WalletController extends Controller {
  /** Ensure a wallets row exists for the owner (lazy create with zero balances). */
  private function walletRow($owner_type,$owner_id){
    $w = DB::table('wallets')->where(['owner_type'=>$owner_type,'owner_id'=>$owner_id])->first();
    if(!$w){
      DB::table('wallets')->insert([
        'owner_type'=>$owner_type,
        'owner_id'=>$owner_id,
        'balance'=>0,
        'points'=>0,
        'created_at'=>now(),
        'updated_at'=>now(),
      ]);
      $w = DB::table('wallets')->where(['owner_type'=>$owner_type,'owner_id'=>$owner_id])->first();
    }
    return $w;
  }

  /** Move leftover cash balance into spendable points (sessions debit points). */
  private function migrateBalanceToPoints(int $userId): void
  {
    DB::transaction(function () use ($userId) {
      $wallet = DB::table('wallets')
        ->where(['owner_type' => 'user', 'owner_id' => $userId])
        ->lockForUpdate()
        ->first();
      if (!$wallet) {
        return;
      }
      $bal = (int) ($wallet->balance ?? 0);
      if ($bal <= 0) {
        return;
      }
      DB::table('wallets')->where('id', $wallet->id)->update([
        'points' => DB::raw('points + '.$bal),
        'balance' => 0,
        'updated_at' => now(),
      ]);
      DB::table('transactions')->insert([
        'owner_type' => 'user',
        'owner_id'   => $userId,
        'type'       => 'balance_to_points',
        'amount'     => 0,
        'points'     => $bal,
        'currency'   => 'PTS',
        'meta'       => json_encode(['source' => 'auto_migrate']),
        'status'     => 'succeeded',
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    });
    Cache::forget("billing:wallet:{$userId}");
    Cache::forget("billing:tx:{$userId}");
  }

  public function me(Request $r){
    $u = $r->user();
    $this->migrateBalanceToPoints((int) $u->id);
    $cacheKey = "billing:wallet:{$u->id}";
    $payload = Cache::remember($cacheKey, 20, function () use ($u) {
      $w = $this->walletRow('user',$u->id);
      $tx = DB::table('transactions')->where(['owner_type'=>'user','owner_id'=>$u->id])->orderByDesc('id')->limit(50)->get();
      return ['balance'=>$w->balance ?? 0,'points'=>$w->points ?? 0,'transactions'=>$tx];
    });
    return response()->json($payload);
  }
  public function createIntent(Request $r){
    $u = $r->user();
    $amount = (int)$r->input('amount',0);
    if($amount<=0) return response()->json(['ok'=>false,'msg'=>'invalid_amount'],422);
    try{
      $pi = app(StripeService::class)->createIntent($amount, [
        'user_id' => (string) $u->id,
        'purpose' => 'wallet_topup',
        'points' => (string) $amount,
      ]);
      return response()->json(['ok'=>true,'client_secret'=>$pi['client_secret'] ?? null, 'payment_intent_id'=>$pi['id'] ?? null]);
    }catch(\Throwable $e){
      // Never credit wallet on Stripe failure — that was free top-up.
      return response()->json([
        'ok' => false,
        'msg' => 'stripe_unavailable',
        'error' => config('app.debug') ? $e->getMessage() : null,
      ], 503);
    }
  }
  public function applyCoupon(Request $r){
    $code = trim((string)$r->input('code',''));
    if($code==='') return response()->json(['ok'=>false,'msg'=>'invalid_code'],422);
    try{
      $user = $r->user();
      $updatedPoints = null;
      DB::transaction(function() use ($code,$user,&$updatedPoints){
        $coupon = DB::table('coupons')->where('code',$code)->lockForUpdate()->first();
        if(!$coupon){
          throw new \RuntimeException('not_found');
        }
        if($coupon->used_by_id){
          throw new \RuntimeException('already_used');
        }
        if($coupon->expires_at && Carbon::parse($coupon->expires_at)->isPast()){
          throw new \RuntimeException('expired');
        }
        $points = (int)($coupon->points ?? 0);
        if ($points <= 0 && isset($coupon->amount_off)) {
          $points = (int) $coupon->amount_off;
        }
        if($points <= 0){
          throw new \RuntimeException('invalid_points');
        }

        $wallet = $this->walletRow('user',$user->id);
        DB::table('wallets')->where('id',$wallet->id)->update([
          'points' => DB::raw('points + '.$points),
          'updated_at' => now(),
        ]);
        DB::table('transactions')->insert([
          'owner_type' => 'user',
          'owner_id'   => $user->id,
          'type'       => 'point_credit',
          'amount'     => 0,
          'points'     => $points,
          'currency'   => 'PTS',
          'meta'       => json_encode(['code'=>$coupon->code]),
          'status'     => 'succeeded',
          'created_at' => now(),
          'updated_at' => now(),
        ]);
        DB::table('coupons')->where('id',$coupon->id)->update([
          'used_by_type' => 'user',
          'used_by_id'   => $user->id,
          'used_at'      => now(),
        ]);
        $updated = DB::table('wallets')->where('id',$wallet->id)->first();
        $updatedPoints = (int) ($updated->points ?? 0);
      });

      Cache::forget("billing:wallet:{$user->id}");
      Cache::forget("billing:tx:{$user->id}");
      return response()->json(['ok'=>true,'points'=>$updatedPoints]);
    }catch(\RuntimeException $e){
      $map = [
        'not_found' => 404,
        'already_used' => 409,
        'expired' => 410,
        'invalid_points' => 422,
      ];
      $msg = $e->getMessage();
      return response()->json(['ok'=>false,'msg'=>$msg], $map[$msg] ?? 422);
    }catch(\Throwable $e){
      return response()->json(['ok'=>false],500);
    }
  }

  /** Start MTN Cash top-up: creates a pending payment_requests row and returns reference for the user to pay. */
  public function mtnInit(Request $r, MtnPaymentService $mtn){
    $amount = (int) $r->input('amount', 0);
    if ($amount <= 0) {
      return response()->json(['ok' => false, 'msg' => 'invalid_amount'], 422);
    }
    if (!$mtn->isEnabled()) {
      return response()->json(['ok' => false, 'msg' => 'mtn_disabled'], 503);
    }
    try {
      $payload = $mtn->createTopupRequest($r->user()->id, $amount, $r->input('phone'));
      return response()->json(['ok' => true] + $payload);
    } catch (\Throwable $e) {
      return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
    }
  }

  /** Confirm MTN payment then credit wallet points; invalidates billing caches. */
  public function mtnConfirm(Request $r, MtnPaymentService $mtn){
    $data = $r->validate([
      'reference' => 'required|string|max:80',
      'transaction_id' => 'required|string|max:120',
    ]);
    try {
      $result = $mtn->confirmTopup($r->user()->id, $data['reference'], $data['transaction_id']);
      Cache::forget("billing:wallet:{$r->user()->id}");
      Cache::forget("billing:tx:{$r->user()->id}");
      return response()->json($result);
    } catch (\RuntimeException $e) {
      return response()->json(['ok' => false, 'msg' => $e->getMessage()], 422);
    } catch (\Throwable $e) {
      return response()->json(['ok' => false], 500);
    }
  }

  /** Start Syriatel Cash top-up (same flow as MTN: pending request + user-facing reference). */
  public function syriatelInit(Request $r, SyriatelCashService $syriatel){
    $amount = (int) $r->input('amount', 0);
    if ($amount <= 0) {
      return response()->json(['ok' => false, 'msg' => 'invalid_amount'], 422);
    }
    if (!$syriatel->isEnabled()) {
      return response()->json(['ok' => false, 'msg' => 'syriatel_disabled'], 503);
    }
    try {
      $payload = $syriatel->createTopupRequest($r->user()->id, $amount, $r->input('phone'));
      return response()->json(['ok' => true] + $payload);
    } catch (\Throwable $e) {
      return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
    }
  }

  /** Confirm Syriatel Cash payment then credit wallet points; invalidates billing caches. */
  public function syriatelConfirm(Request $r, SyriatelCashService $syriatel){
    $data = $r->validate([
      'reference' => 'required|string|max:80',
      'transaction_id' => 'required|string|max:120',
    ]);
    try {
      $result = $syriatel->confirmTopup($r->user()->id, $data['reference'], $data['transaction_id']);
      Cache::forget("billing:wallet:{$r->user()->id}");
      Cache::forget("billing:tx:{$r->user()->id}");
      return response()->json($result);
    } catch (\RuntimeException $e) {
      return response()->json(['ok' => false, 'msg' => $e->getMessage()], 422);
    } catch (\Throwable $e) {
      return response()->json(['ok' => false], 500);
    }
  }

  public function paymentMethods(){
    return response()->json([
      'methods' => config('sanad.payment_methods', ['wallet', 'points', 'syriatel', 'mtn', 'coupon']),
      'mtn_enabled' => app(MtnPaymentService::class)->isEnabled(),
      'syriatel_enabled' => app(SyriatelCashService::class)->isEnabled(),
      'topup_presets' => array_values(array_map('intval', config('sanad.topup_presets', [50, 100, 300]))),
      'session_price_points' => (int) config('sanad.session_price_points', 100),
      'session_price_wallet' => (int) config('sanad.session_price_wallet', 100),
    ]);
  }
}
