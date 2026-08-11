<?php
namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\OrganizationResolver;
use App\Support\Privacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrganizationSpecialistsController extends Controller {
  public function index(Request $r){
    $orgId = OrganizationResolver::resolveOrgIdFromRequest($r);
    if(!$orgId){ return response()->json(['data'=>[]]); }
    $cacheKey = "org:specs:list:{$orgId}";
    $payload = Cache::remember($cacheKey, 30, function () use ($orgId) {
    $specialistIds = DB::table('organization_user')
      ->where('organization_id',$orgId)
      ->pluck('user_id');

    $stats = $this->loadStats($specialistIds);

    $specs = DB::table('users')
      ->whereIn('id',$specialistIds)
      ->select('id','name','email')
      ->orderBy('name')
      ->get()
      ->map(function($row) use ($stats){
        $stat = $stats[$row->id] ?? null;
        return [
          'id'=>$row->id,
          'name'=>$row->name,
          'role'=>'specialist',
          'email'=>$row->email,
          'sessions_count'=>$stat['sessions_count'] ?? 0,
          'commitment_rate'=>$stat['commitment_rate'] ?? 0,
          'avg_rating'=>0,
          'next_session_at'=>$stat['next_session_at'] ?? null,
        ];
      });
      return ['data'=>$specs];
    });
    return response()->json($payload);
  }

  public function show(Request $request, $id){
    $orgId = OrganizationResolver::resolveOrgIdFromRequest($request);
    if(!$orgId){ return response()->json(['message'=>'organization_not_found'],404); }

    $isMember = DB::table('organization_user')
      ->where('organization_id',$orgId)
      ->where('user_id',$id)
      ->exists();
    if(!$isMember){ return response()->json(['message'=>'not_found'],404); }

    $cacheKey = "org:specs:show:{$orgId}:{$id}";
    $payload = Cache::remember($cacheKey, 30, function () use ($id, $orgId) {
        $specialist = DB::table('users')
          ->where('id',$id)
          ->select('id','name','email','phone')
          ->first();

        $stats = $this->loadStats(collect([$id]));
        $detailStats = $stats[$id] ?? ['sessions_count'=>0,'commitment_rate'=>0,'next_session_at'=>null];

        $sessions = Appointment::where('specialist_id',$id)
          ->orderByDesc('starts_at')
          ->limit(8)
          ->get(['id','patient_id','status','starts_at','ends_at'])
          ->map(function($row){
            return [
              'id' => $row->id,
              'status' => $row->status,
              'starts_at' => $row->starts_at,
              'ends_at' => $row->ends_at,
              'patient_code' => Privacy::patientCode((int) $row->patient_id),
            ];
          });

        $beneficiaries = DB::table('organization_beneficiaries as ob')
          ->join('users as u','u.id','=','ob.patient_id')
          ->where('ob.organization_id',$orgId)
          ->where('ob.assigned_specialist_id',$id)
          ->select('u.id','u.name','ob.risk_level')
          ->get()
          ->map(function($row){
            $stub = Privacy::patientStub($row->id, $row->name ?? null);
            return [
              'id' => $row->id,
              'code' => $stub['code'],
              'name' => $stub['name'],
              'risk_level' => $row->risk_level,
            ];
          });

        return [
          'data'=>[
            'specialist'=>$specialist,
            'stats'=>$detailStats,
            'sessions'=>$sessions,
            'beneficiaries'=>$beneficiaries,
          ]
        ];
    });

    return response()->json($payload);
  }

  private function loadStats($specialistIds): array {
    if($specialistIds->isEmpty()){ return []; }
    return Appointment::selectRaw('specialist_id,
        COUNT(*) as sessions_count,
        SUM(CASE WHEN status="completed" THEN 1 ELSE 0 END) as completed_sessions,
        SUM(CASE WHEN status="canceled" THEN 1 ELSE 0 END) as cancelled_sessions,
        MAX(CASE WHEN starts_at >= NOW() THEN starts_at ELSE NULL END) as next_session_at')
      ->whereIn('specialist_id',$specialistIds)
      ->groupBy('specialist_id')
      ->get()
      ->mapWithKeys(function($row){
        $completed = (int) $row->completed_sessions;
        $cancelled = (int) $row->cancelled_sessions;
        $total = max($completed + $cancelled, 1);
        return [$row->specialist_id => [
          'sessions_count' => (int) $row->sessions_count,
          'commitment_rate' => round(($completed / $total) * 100, 1),
          'next_session_at' => $row->next_session_at,
        ]];
      })
      ->toArray();
  }
}
