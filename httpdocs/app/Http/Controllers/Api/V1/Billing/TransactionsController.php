<?php
namespace App\Http\Controllers\Api\V1\Billing;
use App\Http\Controllers\Controller; use Illuminate\Support\Facades\DB; use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
class TransactionsController extends Controller {
  public function index(Request $r){
    $u=$r->user();
    $cacheKey = "billing:tx:{$u->id}";
    $payload = Cache::remember($cacheKey, 20, function () use ($u) {
      try{ $rows=DB::table('transactions')->where(['owner_type'=>'user','owner_id'=>$u->id])->orderByDesc('id')->limit(200)->get(); }catch(\Throwable $e){ $rows=[]; }
      return ['data'=>$rows];
    });
    return response()->json($payload);
  }
}
