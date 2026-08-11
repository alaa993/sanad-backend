<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\SpecialistProfile;
use App\Models\SpecialistDocument;
use Illuminate\Support\Facades\Cache;

class AdminSpecialistsController extends Controller
{
    public function index()
    {
        $payload = Cache::remember('admin:specs:list', 30, function () {
            try {
                $rows = DB::table('specialist_profiles')
                    ->join('users', 'users.id', '=', 'specialist_profiles.user_id')
                    ->select(
                        'users.id',
                        'users.name',
                        'specialist_profiles.specialty',
                        'specialist_profiles.years_exp',
                        'specialist_profiles.accepting_new',
                        'specialist_profiles.status'
                    )
                    ->orderBy('users.id', 'desc')
                    ->limit(200)
                    ->get();
            } catch (\Throwable $e) {
                $rows = [];
            }
            return ['data' => $rows];
        });
        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:6',
            'phone'         => 'nullable|string|max:20|unique:users,phone',
            'specialty'     => 'nullable|string|max:255',
            'languages'     => 'nullable|array',
            'languages.*'   => 'string|max:20',
            'years_exp'     => 'nullable|integer|min:0|max:80',
            'accepting_new' => 'nullable|boolean',
            'rate_cents'    => 'nullable|integer|min:0',
            'currency'      => 'nullable|string|size:3',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'phone'    => $data['phone'] ?? null,
            'role'     => 'specialist',
        ]);
        $user->assignRole('specialist');

        $profile = SpecialistProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'specialty'     => $data['specialty'] ?? null,
                'languages'     => $data['languages'] ?? [],
                'years_exp'     => $data['years_exp'] ?? 0,
                'accepting_new' => $data['accepting_new'] ?? true,
                'rate_cents'    => $data['rate_cents'] ?? 0,
                'currency'      => strtoupper($data['currency'] ?? 'USD'),
                'status'        => 'pending',
            ]
        );
        Cache::forget('admin:specs:list');

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role'  => $user->role,
            ],
            'profile' => $profile,
        ], 201);
    }

    public function approve($id)
    {
        return $this->setStatus($id, 'approved', null);
    }

    public function reject(Request $request, $id)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);
        return $this->setStatus($id, 'rejected', $data['reason'] ?? null);
    }

    private function setStatus($id, $status, ?string $reason = null)
    {
        $profile = SpecialistProfile::where('user_id', $id)->first();
        if (!$profile) {
            return response()->json(['ok' => false, 'msg' => 'not_found'], 404);
        }
        $profile->status = $status;
        if ($status === 'approved') {
            $profile->verification_notes = null;
        } elseif ($status === 'rejected' && $reason !== null) {
            $profile->verification_notes = $reason;
        }
        $profile->save();
        Cache::forget('admin:specs:list');
        Cache::forget('admin:dashboard');
        return response()->json([
            'ok' => true,
            'status' => $status,
            'reason' => $profile->verification_notes,
        ]);
    }

    public function documents($id)
    {
        $cacheKey = "admin:specs:docs:{$id}";
        $payload = Cache::remember($cacheKey, 30, function () use ($id) {
            $profile = SpecialistProfile::where('user_id', $id)->first();
            if (!$profile) {
                return null;
            }
            $docs = SpecialistDocument::where('user_id', $id)->orderByDesc('created_at')->get();
            return [
                'status' => $profile->status,
                'verification_notes' => $profile->verification_notes,
                'documents' => $docs,
            ];
        });
        if (!$payload) {
            return response()->json(['ok' => false, 'msg' => 'not_found'], 404);
        }
        return response()->json($payload);
    }

    public function review(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|string|in:pending,under_review,approved,rejected',
            'notes' => 'nullable|string',
            'verified_documents' => 'nullable|array',
            'verified_documents.*' => 'integer',
        ]);

        $profile = SpecialistProfile::where('user_id', $id)->first();
        if (!$profile) {
            return response()->json(['ok' => false, 'msg' => 'not_found'], 404);
        }

        $profile->status = $data['status'];
        $profile->verification_notes = $data['notes'] ?? null;
        $profile->save();

        if (!empty($data['verified_documents'])) {
            SpecialistDocument::where('user_id', $id)
                ->whereIn('id', $data['verified_documents'])
                ->update(['verified_at' => now()]);
        }
        Cache::forget('admin:specs:list');
        Cache::forget("admin:specs:docs:{$id}");

        return response()->json([
            'ok' => true,
            'status' => $profile->status,
            'verification_notes' => $profile->verification_notes,
        ]);
    }
}
