<?php
namespace App\Http\Controllers\Api\V1\Billing;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\OrganizationResolver;

class InvoicesController extends Controller {
  public function index(Request $request){
    $user = $request->user();
    $rows = collect();
    try {
      $rows = DB::table('invoices')
        ->where(function ($q) use ($user) {
          $q->where(function ($q2) use ($user) {
            $q2->where('owner_type', 'user')->where('owner_id', $user->id);
          });
          if (($user->role ?? '') === 'organization') {
            $orgId = OrganizationResolver::resolveOrgId($user);
            if ($orgId) {
              $q->orWhere(function ($q2) use ($orgId) {
                $q2->where('owner_type', 'organization')->where('owner_id', $orgId);
              });
            }
          }
        })
        ->orderByDesc('id')
        ->limit(100)
        ->get();
    } catch (\Throwable $e) {
      $rows = collect();
    }
    return response()->json(['data' => $rows]);
  }
}
