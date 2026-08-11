<?php
namespace App\Http\Controllers\Api\V1\Billing;
use App\Http\Controllers\Controller; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
use App\Services\StripeService;
use Illuminate\Support\Facades\Cache;
class SubscriptionsController extends Controller {
  public function subscribe(Request $r){
    $uid = $r->user()->id;
    $plan_id = (int)$r->input('plan_id');
    try{
      $plan = DB::table('plans')->where('id',$plan_id)->first();
      if(!$plan) return response()->json(['ok'=>false,'msg'=>'plan_not_found'],404);
      // create pending subscription
      $sid = DB::table('subscriptions')->insertGetId([
        'user_id'=>$uid,'plan_id'=>$plan_id,'status'=>'active','period_start'=>now(),'period_end'=>now()->addMonth(),'external_ref'=>null,'created_at'=>now(),'updated_at'=>now()
      ]);
      Cache::forget("billing:sub:{$uid}");
      return response()->json(['ok'=>true,'subscription_id'=>$sid]);
    }catch(\Throwable $e){ return response()->json(['ok'=>false,'msg'=>'err'],500); }
  }
  public function cancel(Request $r){
    $uid = $r->user()->id;
    try{
      $sub = DB::table('subscriptions')->where('user_id',$uid)->orderByDesc('id')->first();
      if(!$sub) return response()->json(['ok'=>false,'msg'=>'no_subscription'],404);
      DB::table('subscriptions')->where('id',$sub->id)->update(['status'=>'canceled','updated_at'=>now()]);
      Cache::forget("billing:sub:{$uid}");
      return response()->json(['ok'=>true]);
    }catch(\Throwable $e){ return response()->json(['ok'=>false],500); }
  }
}
