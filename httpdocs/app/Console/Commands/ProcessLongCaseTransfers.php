<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\PatientIntake;
use App\Services\PushNotificationService;
use App\Services\SpecialistTransferService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessLongCaseTransfers extends Command
{
    protected $signature = 'sanad:process-long-case-transfers';
    protected $description = 'Recommend psychiatrist referral after 3 months of psychologist care';

    public function handle(SpecialistTransferService $transferService, PushNotificationService $push): int
    {
        $thresholdDays = (int) config('appointments.long_case_days', 90);
        $cutoff = Carbon::now()->subDays(max(30, $thresholdDays));
        $processed = 0;

        $intakes = PatientIntake::query()
            ->where(function ($q) {
                $q->where('referral_physician_recommended', false)
                    ->orWhereNull('referral_physician_recommended');
            })
            ->whereIn('issue_duration', ['more_3m', 'more_year'])
            ->get();

        foreach ($intakes as $intake) {
            if ($this->recommendIfEligible($intake, $cutoff, $transferService, $push)) {
                $processed++;
            }
        }

        $patientIds = Appointment::query()
            ->where('status', 'completed')
            ->whereNotNull('patient_id')
            ->where('starts_at', '<=', $cutoff)
            ->pluck('patient_id')
            ->unique();

        foreach ($patientIds as $patientId) {
            $intake = PatientIntake::where('user_id', $patientId)->first();
            if (!$intake || $intake->referral_physician_recommended) {
                continue;
            }
            if ($this->recommendIfEligible($intake, $cutoff, $transferService, $push)) {
                $processed++;
            }
        }

        $this->info("Physician referrals recommended: {$processed}");

        return self::SUCCESS;
    }

    private function recommendIfEligible(
        PatientIntake $intake,
        Carbon $cutoff,
        SpecialistTransferService $transferService,
        PushNotificationService $push
    ): bool {
        if ($intake->referral_physician_recommended) {
            return false;
        }

        $firstCompleted = Appointment::query()
            ->where('patient_id', $intake->user_id)
            ->where('status', 'completed')
            ->orderBy('starts_at')
            ->first();

        if (!$firstCompleted || !$firstCompleted->starts_at || $firstCompleted->starts_at->gt($cutoff)) {
            return false;
        }

        $psychiatristId = $transferService->findPsychiatrist($firstCompleted->organization_id);
        if (!$psychiatristId) {
            return false;
        }

        $currentSpecialistId = Appointment::query()
            ->where('patient_id', $intake->user_id)
            ->where('status', 'completed')
            ->orderByDesc('starts_at')
            ->value('specialist_id');

        if ($currentSpecialistId && $transferService->isPsychiatrist((int) $currentSpecialistId)) {
            return false;
        }

        $intake->referral_physician_recommended = true;
        $intake->referral_recommended_at = now();
        $intake->recommended_specialist_id = $psychiatristId;
        $intake->recommended_specialist_notes = __('After 3 months of care, a psychiatrist consultation is recommended.');
        $intake->external_physician_recommended = true;
        $intake->external_physician_notes = __('If an external psychiatrist is preferred, coordinate through your organization or primary care provider.');
        $intake->save();

        $push->notifyUser(
            (int) $intake->user_id,
            __('Physician referral recommended'),
            __('Your care plan suggests consulting a psychiatrist. You can book from the specialists list.'),
            ['type' => 'physician_referral', 'specialist_id' => (string) $psychiatristId]
        );

        return true;
    }
}
