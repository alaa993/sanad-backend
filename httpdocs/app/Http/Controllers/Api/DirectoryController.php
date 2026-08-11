<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DirectoryController extends Controller
{
    public function specialists(Request $r) {
        $q = $r->get('search');
        $specialty = $r->get('specialty');
        $language = $r->get('language');
        $minRating = $r->get('min_rating');
        $page = (int) ($r->get('page') ?? 1);
        $cacheKey = "dir:specialists:" . md5(json_encode($r->only(['search','specialty','language','min_rating','page'])));
        $payload = Cache::remember($cacheKey, 60, function () use ($q, $specialty, $language, $minRating) {
            $items = DB::table('users as u')
                ->leftJoin('specialist_profiles as sp', 'sp.user_id', '=', 'u.id')
                ->leftJoin('appointments as a', 'a.specialist_id', '=', 'u.id')
                ->where('u.role', 'specialist')
                ->when($q, fn($x)=>$x->where('u.name','like',"%{$q}%"))
                ->when($specialty, fn($x) => $x->where('sp.specialty', 'like', "%{$specialty}%"))
                ->when($language, function ($x) use ($language) {
                    $x->where(function ($q2) use ($language) {
                        $q2->where('sp.languages', 'like', '%"'.$language.'"%')
                            ->orWhere('sp.languages', 'like', '%'.$language.'%');
                    });
                })
                ->groupBy('u.id','u.name','u.avatar','sp.specialty','sp.years_exp','sp.bio','sp.languages')
                ->orderBy('u.name')
                ->select(
                    'u.id','u.name','u.avatar',
                    'sp.specialty','sp.years_exp','sp.bio','sp.languages',
                    DB::raw('GROUP_CONCAT(DISTINCT a.type) as session_types'),
                    DB::raw('AVG(a.rating) as avg_rating')
                )
                ->paginate(20);

            $items->getCollection()->transform(function ($row) use ($minRating) {
                $row->session_types = $row->session_types
                    ? array_values(array_filter(array_unique(explode(',', $row->session_types))))
                    : [];
                $row->bio = $row->bio ? json_decode($row->bio, true) : null;
                $row->languages = $row->languages ? json_decode($row->languages, true) : [];
                $row->rating = $row->avg_rating ? round($row->avg_rating, 1) : null;
                unset($row->avg_rating);
                return $row;
            });

            if ($minRating !== null && $minRating !== '') {
                $min = (float) $minRating;
                $filtered = $items->getCollection()->filter(fn ($row) => ($row->rating ?? 0) >= $min)->values();
                $items->setCollection($filtered);
            }

            return $items->toArray();
        });

        return response()->json($payload);
    }

    public function show($id)
    {
        $cacheKey = "dir:specialist:{$id}";
        $payload = Cache::remember($cacheKey, 60, function () use ($id) {
            $row = DB::table('users as u')
                ->leftJoin('specialist_profiles as sp', 'sp.user_id', '=', 'u.id')
                ->leftJoin('appointments as a', 'a.specialist_id', '=', 'u.id')
                ->where('u.role', 'specialist')
                ->where('u.id', $id)
                ->groupBy('u.id','u.name','u.avatar','sp.specialty','sp.years_exp','sp.bio','sp.languages','sp.accepting_new')
                ->select(
                    'u.id','u.name','u.avatar',
                    'sp.specialty','sp.years_exp','sp.bio','sp.languages','sp.accepting_new',
                    DB::raw('GROUP_CONCAT(DISTINCT a.type) as session_types'),
                    DB::raw('AVG(a.rating) as avg_rating')
                )
                ->first();

            if (!$row) return null;

            $row->session_types = $row->session_types
                ? array_values(array_filter(array_unique(explode(',', $row->session_types))))
                : [];
            $row->bio = $row->bio ? json_decode($row->bio, true) : null;
            $row->languages = $row->languages ? json_decode($row->languages, true) : [];
            $row->rating = $row->avg_rating ? round($row->avg_rating, 1) : null;
            unset($row->avg_rating);

            $reviews = [];
            try {
                $reviews = DB::table('session_ratings as sr')
                    ->join('appointments as ap', 'ap.id', '=', 'sr.appointment_id')
                    ->where('ap.specialist_id', $id)
                    ->orderByDesc('sr.id')
                    ->limit(20)
                    ->get(['sr.score as rating', 'sr.comment', 'sr.created_at']);
            } catch (\Throwable $e) {
                $reviews = [];
            }

            return ['data' => $row, 'reviews' => $reviews];
        });

        if (!$payload) abort(404);
        return response()->json($payload);
    }

    public function organizations(Request $r) {
        $q = $r->get('search');
        $page = (int) ($r->get('page') ?? 1);
        $cacheKey = "dir:orgs:q:" . md5((string) $q) . ":p:{$page}";
        $payload = Cache::remember($cacheKey, 60, function () use ($q) {
            $items = User::query()
                ->select('id','name','avatar')
                ->where('role', 'organization')
                ->when($q, fn($x)=>$x->where('name','like',"%{$q}%"))
                ->orderBy('name')
                ->paginate(20);
            return $items->toArray();
        });
        return response()->json($payload);
    }
}
