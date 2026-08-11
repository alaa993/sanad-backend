<?php
namespace App\Http\Controllers\Api\V1\Billing;
use App\Http\Controllers\Controller; use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
class PlansController extends Controller {
  public function index(){
    $payload = Cache::remember('billing:plans', 120, function () {
      try{
        $rows = DB::table('plans')->where('is_active',1)->select('id','slug','type','cycle','price','currency','features')->orderBy('price')->get();
      } catch(\Throwable $e){ $rows = []; }
      return ['data'=>$rows];
    });
    return response()->json($payload);
  }
}
