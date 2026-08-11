<?php

namespace App\Http\Controllers\Api\V1\Patient;

use App\Http\Controllers\Controller;
use App\Models\PatientIntake;
use App\Services\TriageService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PatientIntakeController extends Controller
{
    public function show(Request $request)
    {
        $intake = PatientIntake::with('recommendedSpecialist:id,name')
            ->firstWhere('user_id', $request->user()->id);

        if (!$intake) {
            $intake = new PatientIntake(['user_id' => $request->user()->id]);
        }

        return response()->json($intake);
    }

    public function save(Request $request)
    {
        $payload = $this->preparePayload($request);

        $validator = Validator::make($payload, [
            'full_name' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:5|max:120',
            'occupation' => 'nullable|string|max:255',
            'issue_duration' => 'nullable|string|max:50',
            'severity_level' => ['nullable', 'string', Rule::in(['mild', 'moderate', 'severe'])],
            'impact_level' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'preferred_session_mode' => ['nullable', 'string', Rule::in(['video', 'voice', 'chat', 'text'])],
            'symptoms' => 'nullable|array',
            'symptoms.*' => 'string|max:120',
            'primary_issue' => 'nullable|string|max:500',
            'benefit_score' => 'nullable|integer|min:0|max:100',
            'previous_consult' => 'nullable|boolean',
            'consult_notes' => 'nullable|string',
            'risk_flags' => 'nullable|array',
            'risk_flags.*' => 'string|max:120',
            'notes' => 'nullable|string',
            'triage_category' => 'nullable|string|max:120',
            'triage_recommendation' => 'nullable|array',
            'recommended_specialist_id' => [
                'nullable',
                Rule::exists('specialist_profiles', 'user_id'),
            ],
            'recommended_specialist_notes' => 'nullable|string',
            'initial_session_id' => [
                'nullable',
                Rule::exists('therapy_sessions', 'id'),
            ],
        ]);

        $data = $validator->validate();

        if (empty($data['full_name'])) {
            $data['full_name'] = $request->user()->name;
        }

        $intake = PatientIntake::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        if (Schema::hasColumn($intake->getTable(), 'onboarding_step')) {
            $intake->onboarding_step = 'intake_done';
        }

        $intake = app(TriageService::class)->evaluateAndApply($intake);
        $intake->save();

        \Illuminate\Support\Facades\Cache::forget("dash:{$request->user()->id}:patient");

        return response()->json([
            'saved' => true,
            'intake' => $intake->fresh('recommendedSpecialist')
        ], 201);
    }

    private function preparePayload(Request $request): array
    {
        $payload = $request->all();

        if (isset($payload['form']) && is_array($payload['form'])) {
            $payload = array_merge($payload, $payload['form']);
        }

        $map = [
            'fullName' => 'full_name',
            'age' => 'age',
            'occupation' => 'occupation',
            'duration' => 'issue_duration',
            'severityLevel' => 'severity_level',
            'impactLevel' => 'impact_level',
            'sessionMode' => 'preferred_session_mode',
            'primaryIssue' => 'primary_issue',
            'benefitScore' => 'benefit_score',
            'hadConsultation' => 'previous_consult',
            'consultationNotes' => 'consult_notes',
            'notes' => 'notes',
            'triageTags' => 'risk_flags',
            'riskFlags' => 'risk_flags',
        ];

        foreach ($map as $from => $to) {
            if (array_key_exists($from, $payload) && !array_key_exists($to, $payload)) {
                $payload[$to] = $payload[$from];
            }
        }

        if (array_key_exists('age', $payload)) {
            $age = $payload['age'];
            if ($age === '' || $age === null) {
                $payload['age'] = null;
            } elseif (is_string($age) && is_numeric($age)) {
                $payload['age'] = (int) $age;
            }
        }

        $symptoms = collect($payload['symptoms'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->values()
            ->all();
        $riskFlags = collect($payload['risk_flags'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->values()
            ->all();
        $payload['symptoms'] = $symptoms;
        $payload['risk_flags'] = array_values(array_unique(array_merge($symptoms, $riskFlags)));

        if (isset($payload['triage_specialist']) || isset($payload['triage_reason'])) {
            $payload['triage_recommendation'] = array_filter([
                'specialist' => $payload['triage_specialist'] ?? null,
                'reason' => $payload['triage_reason'] ?? null,
            ]);
        }

        return Arr::except($payload, ['form', 'triage_specialist', 'triage_reason']);
    }
}
