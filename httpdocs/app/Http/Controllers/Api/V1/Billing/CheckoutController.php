<?php
namespace App\Http\Controllers\Api\V1\Billing;
use App\Http\Controllers\Controller; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
class CheckoutController extends Controller {
  public function confirmSessionPayment($id, Request $r){
    $u = $r->user();
    $method = $r->input('method','wallet'); // wallet|points|card|mtn
    $coupon = $r->input('coupon');
    $price = (int) config('sanad.session_price_wallet', 100);
    if ($method === 'points') {
      $price = (int) config('sanad.session_price_points', $price);
    }
    try{
      // apply coupon if exists
      if($coupon){
        $c = DB::table('coupons')->where('code',$coupon)->first();
        if($c){ if($c->percent_off){ $price = (int)round($price*(100-$c->percent_off)/100); } elseif($c->amount_off){ $price = max(0,$price - $c->amount_off); } }
      }
      if($method==='points'){
        $w = DB::table('wallets')->where(['owner_type'=>'user','owner_id'=>$u->id])->first();
        if(!$w || $w->points < $price) return response()->json(['ok'=>false,'msg'=>'insufficient_points'],402);
        DB::table('wallets')->where('id',$w->id)->update(['points'=>$w->points - $price]);
      } elseif($method==='wallet'){
        $w = DB::table('wallets')->where(['owner_type'=>'user','owner_id'=>$u->id])->first();
        if(!$w || $w->balance < $price) return response()->json(['ok'=>false,'msg'=>'insufficient_balance'],402);
        DB::table('wallets')->where('id',$w->id)->update(['balance'=>$w->balance - $price]);
      } elseif($method==='mtn'){
        $externalRef = $r->input('transaction_id') ?: $r->input('reference');
        if(!$externalRef) return response()->json(['ok'=>false,'msg'=>'mtn_reference_required'],422);
      } else {
        // card: assume client completed PaymentSheet -> mark as paid (client confirmation)
      }
      // mark appointment/session as confirmed/paid (if you have such fields)
      try { DB::table('appointments')->where('id',$id)->update(['status'=>'confirmed']); } catch(\Throwable $e){}
      $hasTx = DB::table('transactions')
          ->where(['owner_type'=>'user','owner_id'=>$u->id,'type'=>'charge'])
          ->where('meta->appointment_id', $id)
          ->exists();
      if (!$hasTx) {
        DB::table('transactions')->insert([
          'owner_type'=>'user',
          'owner_id'=>$u->id,
          'type'=>'charge',
          'amount'=>$price,
          'currency'=>config('stripe.currency'),
          'meta'=>json_encode(['appointment_id'=>$id,'method'=>$method]),
          'status'=>'succeeded',
          'created_at'=>now(),
          'updated_at'=>now(),
        ]);
      }
      Cache::forget("billing:wallet:{$u->id}");
      Cache::forget("billing:tx:{$u->id}");
      return response()->json(['ok'=>true,'paid'=>$price]);
    }catch(\Throwable $e){ return response()->json(['ok'=>false],500); }
  }
}
