<?php

namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Models\OrganizationBeneficiary;
use App\Models\Appointment;
use App\Models\PatientIntake;
use App\Support\OrganizationResolver;
use App\Support\Privacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class OrganizationBeneficiariesController extends Controller
{
    public function index(Request $request)
    {
        $orgId = OrganizationResolver::resolveOrgIdFromRequest($request);
        if (!$orgId) {
            return response()->json(['message' => 'organization_not_found'], 404);
        }

        $cacheKey = "org:beneficiaries:list:{$orgId}";
        $rows = Cache::remember($cacheKey, 30, function () use ($orgId) {
            return DB::table('organization_beneficiaries as ob')
                ->join('users as u', 'u.id', '=', 'ob.patient_id')
                ->leftJoin('users as sp', 'sp.id', '=', 'ob.assigned_specialist_id')
                ->where('ob.organization_id', $orgId)
                ->orderByDesc('ob.created_at')
                ->selectRaw('ob.id, ob.patient_id, u.name, ob.status, ob.risk_level, ob.primary_issue,
                    sp.name as specialist_name, ob.last_session_at, ob.updated_at')
                ->get()
                ->map(function ($row) {
                    $stub = Privacy::patientStub($row->patient_id, $row->name ?? null);
                    return [
                        'id'              => $row->id,
                        'patient_id'      => $row->patient_id,
                        'code'            => $stub['code'],
                        'name'            => $stub['name'],
                        'status'          => $row->status,
                        'risk_level'      => $row->risk_level,
                        'primary_issue'   => $row->primary_issue,
                        'specialist_name' => $row->specialist_name,
                        'last_session_at' => $row->last_session_at,
                        'updated_at'      => $row->updated_at,
                    ];
                });
        });

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $orgId = OrganizationResolver::resolveOrgIdFromRequest($request);
        if (!$orgId) {
            return response()->json(['message' => 'organization_not_found'], 404);
        }

        $data = $request->validate([
            'patient_id' => 'nullable|exists:users,id',
            'name' => 'required_without:patient_id|string|max:191',
            'email' => 'nullable|email|max:191',
            'phone' => 'nullable|string|max:50',
            'risk_level' => 'nullable|in:low,medium,high',
            'primary_issue' => 'nullable|string|max:191',
            'notes' => 'nullable|string',
        ]);

        $patientId = $data['patient_id'] ?? null;
        if (!$patientId) {
            $existing = null;
            if (!empty($data['email'])) {
                $existing = DB::table('users')->where('email', $data['email'])->first();
            }
            if ($existing) {
                $patientId = $existing->id;
            } else {
                $email = $data['email'] ?? ('beneficiary+' . Str::uuid() . '@sanad.local');
                $patientId = DB::table('users')->insertGetId([
                    'name' => $data['name'],
                    'email' => $email,
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'patient',
                    'phone' => $data['phone'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $beneficiary = OrganizationBeneficiary::updateOrCreate(
            ['organization_id' => $orgId, 'patient_id' => $patientId],
            [
                'status' => 'active',
                'risk_level' => $data['risk_level'] ?? null,
                'primary_issue' => $data['primary_issue'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]
        );
        Cache::forget("org:beneficiaries:list:{$orgId}");

        return response()->json(['data' => $beneficiary], 201);
    }

    public function show(Request $request, $id)
    {
        $orgId = OrganizationResolver::resolveOrgIdFromRequest($request);
        if (!$orgId) {
            return response()->json(['message' => 'organization_not_found'], 404);
        }

        $cacheKey = "org:beneficiaries:show:{$orgId}:{$id}";
        $payload = Cache::remember($cacheKey, 30, function () use ($orgId, $id) {
            $beneficiary = OrganizationBeneficiary::where('organization_id', $orgId)
                ->where('id', $id)
                ->first();

            if (!$beneficiary) {
                return null;
            }

            $patient = DB::table('users')->where('id', $beneficiary->patient_id)
                ->select('id', 'name')
                ->first();

            $patientStub = $patient ? Privacy::patientStub($patient->id, $patient->name) : null;

            $specialist = null;
            if ($beneficiary->assigned_specialist_id) {
                $specialist = DB::table('users')->where('id', $beneficiary->assigned_specialist_id)
                    ->select('id', 'name', 'email')
                    ->first();
            }

            $intake = PatientIntake::where('user_id', $beneficiary->patient_id)
                ->latest('updated_at')->first();

            $sessions = Appointment::where('patient_id', $beneficiary->patient_id)
                ->orderByDesc('starts_at')
                ->limit(10)
                ->get(['id', 'specialist_id', 'status', 'starts_at', 'ends_at'])
                ->map(function ($s) use ($beneficiary) {
                    return [
                        'id'           => $s->id,
                        'status'       => $s->status,
                        'starts_at'    => $s->starts_at,
                        'ends_at'      => $s->ends_at,
                        'patient_code' => Privacy::patientCode($beneficiary->patient_id),
                    ];
                });

            return [
                'data' => [
                    'beneficiary' => array_merge(
                        $beneficiary->toArray(),
                        ['code' => $patientStub['code'] ?? null]
                    ),
                    'patient' => $patientStub ? array_merge($patientStub, ['email' => null, 'phone' => null]) : null,
                    'assigned_specialist' => $specialist,
                    'intake' => Privacy::sanitizeIntake($intake),
                    'sessions' => $sessions,
                ]
            ];
        });

        if (!$payload) {
            return response()->json(['message' => 'not_found'], 404);
        }

        return response()->json($payload);
    }

    public function assignSpecialist(Request $request, $id)
    {
        $orgId = OrganizationResolver::resolveOrgIdFromRequest($request);
        if (!$orgId) {
            return response()->json(['message' => 'organization_not_found'], 404);
        }

        $beneficiary = OrganizationBeneficiary::where('organization_id', $orgId)
            ->where('id', $id)->first();
        if (!$beneficiary) {
            return response()->json(['message' => 'not_found'], 404);
        }

        $data = $request->validate([
            'specialist_id' => 'required|integer',
        ]);

        $isMember = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('user_id', $data['specialist_id'])
            ->exists();
        if (!$isMember) {
            return response()->json(['message' => 'specialist_not_in_org'], 422);
        }

        $beneficiary->assigned_specialist_id = $data['specialist_id'];
        $beneficiary->save();
        Cache::forget("org:beneficiaries:list:{$orgId}");
        Cache::forget("org:beneficiaries:show:{$orgId}:{$id}");

        return response()->json(['ok' => true]);
    }
}
