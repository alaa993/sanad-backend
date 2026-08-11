<?php

namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\Privacy;
use App\Support\OrganizationResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrganizationReportsController extends Controller
{
    public function summary(Request $request)
    {
        $orgId = OrganizationResolver::resolveOrgIdFromRequest($request);
        if (!$orgId) {
            return response()->json(['message' => 'organization_not_found'], 404);
        }

        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        $cacheKey = "org:reports:{$orgId}:" . $from->toDateString() . ':' . $to->toDateString();

        $payload = Cache::remember($cacheKey, 30, function () use ($orgId, $from, $to) {
            $specIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->pluck('user_id');

            $appointments = Appointment::query()
                ->whereIn('specialist_id', $specIds);

            $beneficiaries = DB::table('organization_beneficiaries')
                ->where('organization_id', $orgId);

            $totalBeneficiaries = (clone $beneficiaries)->count();
            $activeBeneficiaries = (clone $beneficiaries)->where('status', 'active')->count();
            $highRisk = (clone $beneficiaries)->where('risk_level', 'high')->count();

            $sessionsCompleted = (clone $appointments)
                ->whereBetween('starts_at', [$from, $to])
                ->where('status', 'completed')
                ->count();

            $sessionsUpcoming = (clone $appointments)
                ->whereBetween('starts_at', [now(), now()->addDays(7)])
                ->whereIn('status', ['pending', 'accepted'])
                ->count();

            $sessionsCancelled = (clone $appointments)
                ->whereBetween('starts_at', [$from, $to])
                ->where('status', 'canceled')
                ->count();

            $engagementRate = $totalBeneficiaries > 0
                ? round(($sessionsCompleted / max($totalBeneficiaries, 1)) * 100, 1)
                : 0;

            $topBeneficiaries = DB::table('organization_beneficiaries as ob')
                ->join('users as u', 'u.id', '=', 'ob.patient_id')
                ->where('ob.organization_id', $orgId)
                ->selectRaw("ob.id, ob.patient_id, u.name, ob.risk_level, ob.primary_issue, ob.last_session_at")
                ->orderByRaw("CASE ob.risk_level WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
                ->orderByDesc('ob.updated_at')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    $stub = Privacy::patientStub($row->patient_id, $row->name ?? null);
                    return [
                        'id'             => $row->id,
                        'patient_id'     => $row->patient_id,
                        'code'           => $stub['code'],
                        'name'           => $stub['name'],
                        'risk_level'     => $row->risk_level,
                        'primary_issue'  => $row->primary_issue,
                        'last_session_at'=> $row->last_session_at,
                    ];
                });

            return [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'metrics' => [
                    'beneficiaries_total' => $totalBeneficiaries,
                    'beneficiaries_active' => $activeBeneficiaries,
                    'high_risk_cases' => $highRisk,
                    'sessions_completed' => $sessionsCompleted,
                    'sessions_cancelled' => $sessionsCancelled,
                    'sessions_upcoming_week' => $sessionsUpcoming,
                    'engagement_rate' => $engagementRate,
                ],
                'top_beneficiaries' => $topBeneficiaries,
            ];
        });

        return response()->json($payload);
    }
}
