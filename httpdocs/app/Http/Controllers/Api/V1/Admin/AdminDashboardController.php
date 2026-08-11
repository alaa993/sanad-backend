<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $r)
    {
        $payload = Cache::remember('admin:dashboard', 20, function () {
            $count = function ($table) {
                try {
                    return DB::table($table)->count();
                } catch (\Throwable $e) {
                    return 0;
                }
            };

            $today = 0;
            $sessionsWeek = 0;
            $specPending = 0;
            $orgPending = 0;

            try {
                $today = DB::table('appointments')
                    ->whereDate('starts_at', now()->toDateString())
                    ->count();
            } catch (\Throwable $e) {
            }

            try {
                $sessionsWeek = DB::table('appointments')
                    ->where('starts_at', '>=', now()->subDays(7))
                    ->count();
            } catch (\Throwable $e) {
            }

            try {
                $specPending = DB::table('specialist_profiles')
                    ->where('status', 'pending')
                    ->count();
            } catch (\Throwable $e) {
            }

            try {
                $orgPending = DB::table('organizations')
                    ->where('status', 'pending')
                    ->count();
            } catch (\Throwable $e) {
            }

            $libraryCount = $count('library_articles');
            $alerts = [];
            if ($specPending > 0) {
                $alerts[] = [
                    'id' => 'pending_specialists',
                    'title' => 'Specialists awaiting approval',
                    'message' => "{$specPending} specialist profiles need review",
                    'level' => 'warning',
                ];
            }
            if ($orgPending > 0) {
                $alerts[] = [
                    'id' => 'pending_orgs',
                    'title' => 'Organizations awaiting approval',
                    'message' => "{$orgPending} organizations need review",
                    'level' => 'info',
                ];
            }

            return [
                'counters' => [
                    'users' => $count('users'),
                    'specialists' => $count('specialist_profiles'),
                    'organizations' => $count('organizations'),
                    'appointments' => $count('appointments'),
                    'appointments_today' => $today,
                    'posts' => $libraryCount,
                    'sessions_week' => $sessionsWeek,
                    'specialists_pending' => $specPending,
                    'organizations_pending' => $orgPending,
                ],
                'quick_actions' => [
                    ['id' => 'approve_specialists', 'label' => 'Specialists'],
                    ['id' => 'approve_orgs', 'label' => 'Organizations'],
                    ['id' => 'sessions', 'label' => 'Sessions'],
                    ['id' => 'users', 'label' => 'Users'],
                    ['id' => 'library', 'label' => 'Library'],
                    ['id' => 'community', 'label' => 'Community'],
                    ['id' => 'wallet', 'label' => 'Wallet'],
                    ['id' => 'reports', 'label' => 'Reports'],
                    ['id' => 'vent', 'label' => 'Vent moderation'],
                    ['id' => 'daily_tips', 'label' => 'Daily tips'],
                    ['id' => 'settings', 'label' => 'Settings'],
                ],
                'alerts' => $alerts,
            ];
        });

        return response()->json($payload);
    }
}
