<?php
namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\OrganizationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrganizationSessionsController extends Controller
{
    public function index(Request $r)
    {
        $orgId = OrganizationResolver::resolveOrgIdFromRequest($r);
        if (!$orgId) {
            return response()->json(['data' => []]);
        }
        $cacheKey = "org:sessions:{$orgId}";
        $payload = Cache::remember($cacheKey, 20, function () use ($orgId) {
            $specIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('role', 'specialist')
                ->pluck('user_id');
            $q = Appointment::whereIn('specialist_id', $specIds)->orderBy('starts_at');
            return ['data' => $q->limit(500)->get()];
        });
        return response()->json($payload);
    }
}
