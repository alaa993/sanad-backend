<?php

namespace App\Http\Controllers\Api\V1\Specialist;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PatientIntake;
use App\Models\PatientTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SpecialistPatientsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $statuses = [
            'accepted',
            'confirmed',
            'in_progress',
            'started',
            'scheduled',
            'upcoming',
            'completed',
        ];

        $patients = DB::table('appointments as a')
            ->join('users as u', 'u.id', '=', 'a.patient_id')
            ->where('a.specialist_id', $user->id)
            ->whereIn('a.status', $statuses)
            ->distinct()
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.avatar']);

        return response()->json(['data' => $patients]);
    }

    private function ensureAccess(Request $request, int $patientId): void
    {
        $user = $request->user();
        $isAllowed = Appointment::where('specialist_id', $user->id)
            ->where('patient_id', $patientId)
            ->exists();

        abort_unless($isAllowed, 403, 'Patient not linked to specialist');
    }

    public function intake(Request $request, int $patientId)
    {
        $this->ensureAccess($request, $patientId);

        $cacheKey = "spec:intake:{$patientId}";
        $payload = Cache::remember($cacheKey, 30, function () use ($patientId) {
            $intake = PatientIntake::where('user_id', $patientId)->first();
            return $intake ?? new PatientIntake([
                'user_id' => $patientId,
            ]);
        });

        return response()->json($payload);
    }

    public function updateIntake(Request $request, int $patientId)
    {
        $this->ensureAccess($request, $patientId);
        $data = $request->validate([
            'risk_flags' => 'nullable|array',
            'risk_flags.*' => 'string|max:120',
            'triageTags' => 'nullable|array',
            'triageTags.*' => 'string|max:120',
            'triage_category' => 'nullable|string|max:120',
            'triage_reason' => 'nullable|string|max:500',
        ]);

        $riskFlags = $data['risk_flags'] ?? $data['triageTags'] ?? null;
        $intake = PatientIntake::firstOrCreate(['user_id' => $patientId]);
        if ($riskFlags !== null) {
            $intake->risk_flags = $riskFlags;
        }
        if (array_key_exists('triage_category', $data)) {
            $intake->triage_category = $data['triage_category'];
        }
        if (array_key_exists('triage_reason', $data)) {
            $intake->triage_recommendation = array_filter([
                'reason' => $data['triage_reason'],
            ]);
        }
        $intake->save();
        Cache::forget("spec:intake:{$patientId}");
        return response()->json($intake->fresh('recommendedSpecialist'));
    }

    public function tasks(Request $request, int $patientId)
    {
        $this->ensureAccess($request, $patientId);

        $cacheKey = "spec:tasks:{$patientId}";
        $tasks = Cache::remember($cacheKey, 20, function () use ($patientId) {
            return PatientTask::where('user_id', $patientId)
                ->orderByRaw('COALESCE(due_at, created_at)')
                ->limit(200)
                ->get();
        });

        return response()->json($tasks);
    }

    public function sessions(Request $request, int $patientId)
    {
        $this->ensureAccess($request, $patientId);

        $sessions = Appointment::query()
            ->where('specialist_id', $request->user()->id)
            ->where('patient_id', $patientId)
            ->whereIn('status', ['completed', 'accepted', 'confirmed', 'in_progress', 'started'])
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get([
                'id', 'status', 'starts_at', 'ends_at', 'closed_at',
                'specialist_notes', 'rating', 'type', 'transferred_at', 'transfer_reason',
            ]);

        return response()->json(['data' => $sessions]);
    }

    public function acknowledgePhysicianReferral(Request $request, int $patientId)
    {
        $this->ensureAccess($request, $patientId);
        $intake = PatientIntake::firstOrCreate(['user_id' => $patientId]);
        $intake->external_physician_acknowledged_at = now();
        if (!$intake->external_physician_notes) {
            $intake->external_physician_notes = $request->input('notes');
        }
        $intake->save();
        Cache::forget("spec:intake:{$patientId}");
        return response()->json(['acknowledged' => true]);
    }

    public function recommendExternalPhysician(Request $request, int $patientId)
    {
        $this->ensureAccess($request, $patientId);
        $data = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);
        $intake = PatientIntake::firstOrCreate(['user_id' => $patientId]);
        $intake->external_physician_recommended = true;
        $intake->external_physician_notes = $data['notes'] ?? __('External physician consultation recommended.');
        $intake->save();
        Cache::forget("spec:intake:{$patientId}");
        return response()->json($intake);
    }

    public function applyTaskTemplates(Request $request, int $patientId)
    {
        $this->ensureAccess($request, $patientId);
        $data = $request->validate([
            'template_ids' => 'required|array|min:1',
            'template_ids.*' => 'string|max:60',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
        ]);
        $templates = collect(config('sanad.task_templates', []))->keyBy('id');
        $created = [];
        foreach ($data['template_ids'] as $tid) {
            $tpl = $templates->get($tid);
            if (!$tpl) {
                continue;
            }
            $created[] = PatientTask::create([
                'user_id' => $patientId,
                'appointment_id' => $data['appointment_id'] ?? null,
                'title' => $tpl['title_ar'] ?? $tpl['title_en'] ?? $tid,
                'description' => $tpl['description_ar'] ?? null,
                'status' => 'pending',
                'due_at' => now()->addDays(7),
                'meta' => ['template_id' => $tid],
            ]);
        }
        Cache::forget("spec:tasks:{$patientId}");
        Cache::forget("patient:tasks:{$patientId}");
        return response()->json(['data' => $created], 201);
    }
}
