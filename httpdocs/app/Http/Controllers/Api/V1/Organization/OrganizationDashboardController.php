<?php
namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\OrganizationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrganizationDashboardController extends Controller
{
    public function index(Request $r)
    {
        $orgId = OrganizationResolver::resolveOrgIdFromRequest($r);
        if (!$orgId) {
            return response()->json(['message' => 'organization_not_found'], 404);
        }

        $cacheKey = "org:dash:{$orgId}";
        $payload = Cache::remember($cacheKey, 20, function () use ($orgId) {
            $specIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('role', 'specialist')
                ->pluck('user_id');

            $beneficiaries = DB::table('organization_beneficiaries')
                ->where('organization_id', $orgId);

            $beneficiaryCount = (clone $beneficiaries)->count();
            $highRisk = (clone $beneficiaries)->where('risk_level', 'high')->count();
            $specialistsActive = $specIds->count();

            $sessionsQuery = Appointment::whereIn('specialist_id', $specIds);
            $sessionsTotal = (clone $sessionsQuery)->count();
            $upcoming = (clone $sessionsQuery)->where('starts_at', '>=', now())->count();
            $upcoming48h = (clone $sessionsQuery)
                ->whereBetween('starts_at', [now(), now()->addHours(48)])
                ->whereIn('status', ['pending', 'accepted'])
                ->count();
            $pending = (clone $sessionsQuery)->where('status', 'pending')->count();

            $alerts = [];
            if ($highRisk > 0) {
                $alerts[] = [
                    'id' => 'high_risk',
                    'title' => 'High risk beneficiaries',
                    'level' => 'warning',
                    'message' => "{$highRisk} beneficiaries need attention",
                ];
            }
            if ($pending > 0) {
                $alerts[] = [
                    'id' => 'pending_sessions',
                    'title' => 'Pending sessions',
                    'level' => 'info',
                    'message' => "{$pending} sessions awaiting confirmation",
                ];
            }

            return [
                'org_id' => $orgId,
                'counters' => [
                    'beneficiaries' => $beneficiaryCount,
                    'sessions_total' => $sessionsTotal,
                    'upcoming_48h' => $upcoming48h,
                    'specialists_active' => $specialistsActive,
                    'high_risk_cases' => $highRisk,
                    'upcoming' => $upcoming,
                    'pending' => $pending,
                ],
                'quick_actions' => [
                    ['id' => 'beneficiaries', 'label' => 'Beneficiaries'],
                    ['id' => 'group_session', 'label' => 'Group sessions'],
                    ['id' => 'reports', 'label' => 'Reports'],
                    ['id' => 'community_room', 'label' => 'Support room'],
                ],
                'alerts' => $alerts,
            ];
        });

        return response()->json($payload);
    }

    public function resubmit(Request $r)
    {
        if ($r->user()->role !== 'organization') {
            abort(403);
        }
        $orgId = OrganizationResolver::resolveOrgIdFromRequest($r);
        if (!$orgId) {
            return response()->json(['message' => 'organization_not_found'], 404);
        }
        DB::table('organizations')->where('id', $orgId)->update([
            'status' => 'pending',
            'review_notes' => null,
            'updated_at' => now(),
        ]);
        OrganizationResolver::clearUserCaches($r->user()->id, $orgId);
        Cache::forget("org:dash:{$orgId}");
        return response()->json(['ok' => true, 'status' => 'pending']);
    }

    public function supportRoom(Request $r, \App\Services\OrgSupportRoomService $rooms)
    {
        $orgId = OrganizationResolver::resolveOrgIdFromRequest($r);
        if (!$orgId) {
            return response()->json(['message' => 'organization_not_found'], 404);
        }
        $community = $rooms->resolveForUser($r->user()->id);
        $name = $community->name;
        if (is_array($name)) {
            $name = $name[app()->getLocale()] ?? $name['ar'] ?? reset($name);
        }
        return response()->json([
            'community_id' => $community->id,
            'slug' => $community->slug,
            'name' => $name,
            'visibility' => $community->visibility,
        ]);
    }
}
