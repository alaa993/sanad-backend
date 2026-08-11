<?php

namespace App\Http\Controllers\Api\V1\Patient;

use App\Http\Controllers\Controller;
use App\Models\PatientIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PreSessionSurveyController extends Controller
{
    public function show(Request $request)
    {
        $intake = PatientIntake::firstWhere('user_id', $request->user()->id);
        return response()->json([
            'questions' => config('sanad.pre_session_questions', []),
            'completed' => $intake && $intake->pre_session_completed_at,
            'answers' => $intake?->pre_session_survey,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'answers' => 'required|array',
        ]);

        $intake = PatientIntake::firstOrCreate(['user_id' => $request->user()->id]);
        $intake->pre_session_survey = $data['answers'];
        $intake->pre_session_completed_at = now();
        if (!$intake->onboarding_step || $intake->onboarding_step === 'intake') {
            $intake->onboarding_step = 'pre_session_done';
        }
        $intake->save();
        Cache::forget("dash:{$request->user()->id}:patient");

        return response()->json(['saved' => true, 'completed_at' => optional($intake->pre_session_completed_at)->toIso8601String()]);
    }
}
