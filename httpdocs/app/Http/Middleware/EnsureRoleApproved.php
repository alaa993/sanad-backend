<?php

namespace App\Http\Middleware;

use App\Support\OrganizationResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureRoleApproved
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'unauthenticated'], 401);
        }

        if ($user->role === 'specialist') {
            $allowSpecialistArea = $request->is('api/v1/specialist/*');
            $status = DB::table('specialist_profiles')
                ->where('user_id', $user->id)
                ->value('status');
            $status = $status ?? 'pending';
            if ($status !== 'approved' && !$allowSpecialistArea) {
                return response()->json(['message' => 'profile_pending', 'status' => $status], 403);
            }
        } elseif ($user->role === 'organization') {
            $allowOrgArea = $request->is('api/v1/org/*');
            $orgId = OrganizationResolver::resolveOrgId($user);
            if ($orgId) {
                $status = DB::table('organizations')->where('id', $orgId)->value('status');
                $status = $status ?? 'pending';
                if ($status !== 'approved' && !$allowOrgArea) {
                    return response()->json(['message' => 'org_pending', 'status' => $status], 403);
                }
            }
        }

        return $next($request);
    }
}
