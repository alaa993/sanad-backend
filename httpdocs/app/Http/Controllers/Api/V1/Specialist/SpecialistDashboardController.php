<?php
namespace App\Http\Controllers\Api\V1\Specialist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use Illuminate\Support\Facades\Cache;

class SpecialistDashboardController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        $cacheKey = "spec:dash:{$u->id}";
        $payload = Cache::remember($cacheKey, 20, function () use ($u) {
            $today = now()->startOfDay();
            $tomorrow = now()->addDay()->startOfDay();
            $upcoming = Appointment::where('specialist_id', $u->id)
                ->where('starts_at', '>=', $today)
                ->whereIn('status', ['pending', 'accepted'])
                ->count();
            $todayCount = Appointment::where('specialist_id', $u->id)
                ->whereBetween('starts_at', [$today, $tomorrow])
                ->count();
            $pending = Appointment::where('specialist_id', $u->id)
                ->where('status', 'pending')
                ->count();

            return [
                'counters' => [
                    'upcoming' => $upcoming,
                    'today' => $todayCount,
                    'pending' => $pending,
                ],
                'shortcuts' => [
                    ['id' => 'sessions', 'title' => 'جلساتي', 'route' => 'sessions'],
                    ['id' => 'patients', 'title' => 'مرضاي', 'route' => 'patients'],
                    ['id' => 'community', 'title' => 'المجتمع', 'route' => 'community'],
                    ['id' => 'library', 'title' => 'المكتبة', 'route' => 'library'],
                    ['id' => 'chat', 'title' => 'المحادثات', 'route' => 'chat'],
                ],
            ];
        });

        return response()->json($payload);
    }
}
