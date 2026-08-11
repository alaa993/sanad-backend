<?php

namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\OrganizationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrganizationBillingController extends Controller
{
    public function overview(Request $request)
    {
        $orgId = OrganizationResolver::resolveOrgIdFromRequest($request);
        if (!$orgId) {
            return response()->json(['message' => 'organization_not_found'], 404);
        }
        $cacheKey = "org:billing:overview:{$orgId}";

        $payload = Cache::remember($cacheKey, 30, function () use ($orgId) {
            $organization = DB::table('organizations')->where('id', $orgId)->first();
            $meta = $organization && $organization->about ? json_decode($organization->about, true) : [];

            $planName = data_get($meta, 'plan.name', 'Standard');
            $planStatus = data_get($meta, 'plan.status', 'active');
            $seatLimit = (int) data_get($meta, 'plan.seat_limit', 50);
            $sessionLimit = (int) data_get($meta, 'plan.session_limit', $seatLimit * 4);
            $renewsAt = data_get($meta, 'plan.renews_at', now()->copy()->addMonth()->toDateString());

            $seatUsed = DB::table('organization_beneficiaries')
                ->where('organization_id', $orgId)
                ->count();

            $specIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->pluck('user_id');

            $sessionsUsed = Appointment::query()
                ->whereIn('specialist_id', $specIds)
                ->whereYear('starts_at', now()->year)
                ->count();

            // Session payouts credit owner_type=user with appointments.organization_id
            // (User id of the org account). Billing must read that wallet, not a separate
            // owner_type=organization row that never receives payouts.
            $managerIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->whereIn('role', ['manager', 'owner', 'admin'])
                ->pluck('user_id');
            if ($managerIds->isEmpty()) {
                $managerIds = DB::table('organization_user')
                    ->where('organization_id', $orgId)
                    ->orderBy('id')
                    ->limit(1)
                    ->pluck('user_id');
            }

            $points = 0;
            $balance = 0;
            if ($managerIds->isNotEmpty()) {
                $rows = DB::table('wallets')
                    ->where('owner_type', 'user')
                    ->whereIn('owner_id', $managerIds)
                    ->get();
                foreach ($rows as $row) {
                    $points += (int) ($row->points ?? 0);
                    $balance += (int) ($row->balance ?? 0);
                }
            }

            $orgWallet = DB::table('wallets')
                ->where('owner_type', 'organization')
                ->where('owner_id', $orgId)
                ->first();
            if ($orgWallet) {
                $points += (int) ($orgWallet->points ?? 0);
                $balance += (int) ($orgWallet->balance ?? 0);
            }

            $invoices = DB::table('invoices')
                ->where('owner_type', 'organization')
                ->where('owner_id', $orgId)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            return [
                'plan' => [
                    'name' => $planName,
                    'status' => $planStatus,
                    'renews_at' => $renewsAt,
                ],
                'seats' => [
                    'limit' => $seatLimit,
                    'used' => $seatUsed,
                ],
                'sessions' => [
                    'limit' => $sessionLimit,
                    'used' => $sessionsUsed,
                ],
                'wallet' => [
                    'balance' => $balance,
                    'points' => $points,
                    'currency' => 'PTS',
                ],
                'invoices' => $invoices,
            ];
        });

        return response()->json($payload);
    }
}
