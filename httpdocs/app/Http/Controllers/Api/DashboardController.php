<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PatientIntake;
use App\Models\VentPost;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role ?? 'patient';
        $cacheKey = "dash:{$user->id}:{$role}";

        $payload = Cache::remember($cacheKey, 30, function () use ($user, $role) {
            $upcomingQuery = Appointment::query();
            if ($role === 'specialist') {
                $upcomingQuery->where('specialist_id', $user->id);
            } elseif ($role === 'organization') {
                $upcomingQuery->where('organization_id', $user->id);
            } else {
                $upcomingQuery->where('patient_id', $user->id);
            }
            $now = now();
            $upcomingCount = (clone $upcomingQuery)
                ->whereIn('status', ['pending','accepted','confirmed','in_progress','started','scheduled','upcoming'])
                ->where('starts_at', '>=', $now)
                ->count();

            $nextSession = (clone $upcomingQuery)
                ->select('id','type','status','starts_at','scheduled_at','ends_at','join_url','specialist_id','organization_id')
                ->with(['specialist:id,name,avatar', 'organization:id,name'])
                ->whereIn('status', ['pending','accepted','confirmed','scheduled','upcoming','in_progress','started'])
                ->orderBy('starts_at')
                ->first();

            $canJoin = $nextSession && $nextSession->starts_at
                ? Carbon::parse($nextSession->starts_at)->isBetween($now->copy()->subMinutes(10), $now->copy()->addMinutes(20))
                : false;

            $nextSessionPayload = $nextSession ? [
                'id'                => $nextSession->id,
                'type'              => $nextSession->type,
                'status'            => $nextSession->status,
                'scheduled_at'      => optional($nextSession->starts_at)->toIso8601String(),
                'specialist_name'   => optional($nextSession->specialist)->name,
                'specialist_avatar' => optional($nextSession->specialist)->avatar,
                'organization_name' => optional($nextSession->organization)->name,
                'join_url'          => $nextSession->join_url,
                'can_join'          => $canJoin,
            ] : null;

            $intake = PatientIntake::with('recommendedSpecialist')
                ->firstWhere('user_id', $user->id);

            $intakePayload = $intake ? [
                'completed' => $intake->isComplete(),
                'full_name' => $intake->full_name,
                'severity_level' => $intake->severity_level,
                'impact_level' => $intake->impact_level,
                'preferred_session_mode' => $intake->preferred_session_mode,
                'risk_flags' => array_values($intake->risk_flags ?? []),
                'primary_issue' => $intake->primary_issue,
                'benefit_score' => (int) $intake->benefit_score,
                'triage_category' => $intake->triage_category,
                'triage_reason' => is_array($intake->triage_recommendation)
                    ? ($intake->triage_recommendation['reason'] ?? null)
                    : null,
                'recommended_specialist' => $intake->recommendedSpecialist ? [
                    'id' => $intake->recommendedSpecialist->id,
                    'name' => $intake->recommendedSpecialist->name,
                ] : null,
                'referral_physician_recommended' => (bool) $intake->referral_physician_recommended,
                'external_physician_recommended' => (bool) ($intake->external_physician_recommended ?? false),
                'recovery_unlocked' => (bool) ($intake->recovery_unlocked ?? false),
                'onboarding_step' => $intake->onboarding_step,
                'pre_session_completed' => (bool) $intake->pre_session_completed_at,
                'updated_at' => optional($intake->updated_at)->toIso8601String(),
            ] : [
                'completed' => false,
                'full_name' => null,
                'severity_level' => null,
                'impact_level' => null,
                'preferred_session_mode' => null,
                'risk_flags' => [],
                'primary_issue' => null,
                'benefit_score' => null,
                'triage_category' => null,
                'triage_reason' => null,
                'recommended_specialist' => null,
                'referral_physician_recommended' => false,
                'external_physician_recommended' => false,
                'recovery_unlocked' => false,
                'onboarding_step' => null,
                'pre_session_completed' => false,
                'updated_at' => null,
            ];

            return [
                'role' => $role,
                'stats' => [
                    'upcoming_sessions' => $upcomingCount,
                    'unread_messages'   => 0,
                    'points'            => (int) ($user->points ?? 0),
                ],
                'intake' => $intakePayload,
                'next_session' => $nextSessionPayload,
                'onboarding' => $role === 'patient' ? $this->onboardingPayload($intake) : null,
                'shortcuts' => [
                    ['id'=>'community','title'=>'المجتمع','route'=>'community'],
                    ['id'=>'sessions','title'=>'الجلسات','route'=>'sessions'],
                    ['id'=>'specialists','title'=>'الأخصائيون','route'=>'specialists'],
                    ['id'=>'library','title'=>'المكتبة','route'=>'library'],
                ],
            ];
        });

        return response()->json($payload);
    }

    private function onboardingPayload(?PatientIntake $intake): array
    {
        $step = $intake?->onboarding_step;
        $intakeDone = $intake?->isComplete() ?? false;
        $needsIntake = !$intakeDone;
        $needsPreSession = $intakeDone && !$intake->pre_session_completed_at;
        $hasVentPost = false;
        $maybeNeedsVent = $intakeDone
            && $intake
            && $intake->pre_session_completed_at
            && !in_array($step, ['vent_done', 'ready'], true);
        if ($maybeNeedsVent) {
            $hasVentPost = (bool) Cache::remember(
                "vent:exists:{$intake->user_id}",
                60,
                fn () => VentPost::where('user_id', $intake->user_id)->exists()
            );
        }
        $needsFirstVent = $maybeNeedsVent && !$hasVentPost;
        return [
            'step' => $step ?? ($needsIntake ? 'intake' : ($needsPreSession ? 'pre_session' : 'ready')),
            'needs_intake' => $needsIntake,
            'needs_pre_session' => $needsPreSession,
            'needs_vent' => $needsFirstVent,
            'journal_unlocked' => (bool) ($intake?->recovery_unlocked ?? false),
        ];
    }
}
