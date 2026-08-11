<?php
namespace App\Http\Controllers\Api\V1\Billing;
use App\Http\Controllers\Controller; use Illuminate\Http\Request;
use App\Services\IosReceiptVerifier;
class IosReceiptController extends Controller {
  public function verify(Request $r){
    $receipt = $r->input('receipt');
    if(!$receipt) return response()->json(['ok'=>false,'msg'=>'missing_receipt'],422);
    try{
      $ok = app(IosReceiptVerifier::class)->verify($receipt);
      return response()->json(['ok'=>$ok]);
    }catch(\Throwable $e){ return response()->json(['ok'=>false],500); }
  }
}
