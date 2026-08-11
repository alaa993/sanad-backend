<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AdminOrganizationsController extends Controller {
    public function index(){
        $payload = Cache::remember('admin:orgs:list', 30, function () {
            try{ $rows=DB::table('organizations')->select('id','name','status')->orderBy('id','desc')->limit(200)->get(); }
            catch(\Throwable $e){ $rows=[]; }
            return ['data'=>$rows];
        });
        return response()->json($payload);
    }
    public function show($id){
        $cacheKey = "admin:orgs:show:{$id}";
        $payload = Cache::remember($cacheKey, 30, function () use ($id) {
            $org = DB::table('organizations')->where('id',$id)->first();
            if(!$org){ return null; }
            $members = DB::table('organization_user')->where('organization_id',$id)->count();
            $specIds = DB::table('organization_user')->where('organization_id',$id)->pluck('user_id');
            $specialists = DB::table('organization_user')->where('organization_id',$id)->where('role','specialist')->count();
            $beneficiaries = DB::table('organization_beneficiaries')->where('organization_id',$id)->count();
            $sessions = $specIds->isEmpty() ? 0 : Appointment::whereIn('specialist_id',$specIds)->count();
            $upcoming = $specIds->isEmpty() ? 0 : Appointment::whereIn('specialist_id',$specIds)->where('starts_at','>=',now())->count();
            return ['data'=>[
                'organization'=>$org,
                'stats'=>[
                    'members'=>$members,
                    'specialists'=>$specialists,
                    'beneficiaries'=>$beneficiaries,
                    'sessions_total'=>$sessions,
                    'upcoming'=>$upcoming,
                ],
            ]];
        });
        if (!$payload) { return response()->json(['message'=>'not_found'],404); }
        return response()->json($payload);
    }
    public function approve($id){ return $this->setStatus($id,'approved', null); }
    public function reject(Request $request, $id){
        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);
        return $this->setStatus($id,'rejected', $data['reason'] ?? null);
    }
    private function setStatus($id,$status, ?string $reason = null){
        try{
            if(!DB::table('organizations')->where('id',$id)->exists())
                return response()->json(['ok'=>false,'msg'=>'not_found'],404);
            $payload = ['status'=>$status, 'updated_at'=>now()];
            if ($status === 'rejected' && $reason !== null) {
                $payload['review_notes'] = $reason;
            }
            if ($status === 'approved') {
                $payload['review_notes'] = null;
            }
            DB::table('organizations')->where('id',$id)->update($payload);
            Cache::forget('admin:orgs:list');
            Cache::forget("admin:orgs:show:{$id}");
            Cache::forget('admin:dashboard');
            return response()->json(['ok'=>true,'status'=>$status,'reason'=>$payload['review_notes'] ?? null]);
        }catch(\Throwable $e){ return response()->json(['ok'=>false],500); }
    }
}
