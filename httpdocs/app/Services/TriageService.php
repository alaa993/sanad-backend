<?php

namespace App\Services;

use App\Models\PatientIntake;
use Illuminate\Support\Facades\DB;

class TriageService
{
    public function evaluateAndApply(PatientIntake $intake): PatientIntake
    {
        $flags = array_map('strtolower', array_values($intake->risk_flags ?? []));
        $symptoms = array_map('strtolower', array_values($intake->symptoms ?? []));
        $narrative = strtolower((string) ($intake->primary_issue ?? ''));
        $hasMedication = $this->containsAny($symptoms, ['medication', 'دواء'])
            || str_contains($narrative, 'دواء');

        $category = null;
        $specialistLabel = null;
        $reason = null;

        if ($this->containsAny($flags, ['bipolar', 'ثنائي']) || str_contains($narrative, 'ثنائي')) {
            $category = 'bipolar';
            $specialistLabel = 'طبيب نفسي';
            $reason = 'تشير المؤشرات إلى تقلبات مزاجية تحتاج تقييماً طبياً.';
        } elseif ($this->containsAny($flags, ['schizophrenia', 'فصام']) || str_contains($narrative, 'فصام')) {
            $category = 'schizophrenia';
            $specialistLabel = 'طبيب نفسي';
            $reason = 'مؤشرات تستدعي متابعة طبية متخصصة.';
        } elseif ($this->containsAny($flags, ['children', 'أطفال']) || str_contains($narrative, 'طفل')) {
            $category = 'children';
            $specialistLabel = 'أخصائي أطفال';
            $reason = 'الحالة مرتبطة بمرحلة الطفولة أو السلوك.';
        } elseif ($this->containsAny($flags, ['anx_dep']) || $this->containsAny($symptoms, ['anxiety', 'depression', 'قلق', 'اكتئاب'])) {
            $category = 'anx_dep';
            $specialistLabel = $hasMedication ? 'طبيب نفسي' : 'أخصائي علاج معرفي';
            $reason = $hasMedication ? 'الاعتماد على دواء يستدعي تقييماً طبياً.' : 'قلق/اكتئاب معتدل يمكن البدء بجلسات دعم.';
        } elseif ($this->containsAny($flags, ['mild', 'identity']) || $this->containsAny($symptoms, ['sleep', 'adhd'])) {
            $category = $this->containsAny($flags, ['identity']) ? 'identity' : 'mild';
            $specialistLabel = 'أخصائي نفسي';
            $reason = 'حالة تحتاج دعماً علاجياً منظماً.';
        }

        if ($category) {
            $intake->triage_category = $category;
            $intake->triage_recommendation = [
                'specialist' => $specialistLabel,
                'reason' => $reason,
            ];
            $specialistId = $this->resolveSpecialistId($category, $hasMedication);
            if ($specialistId) {
                $intake->recommended_specialist_id = $specialistId;
                $intake->recommended_specialist_notes = $reason;
            }
        }

        return $intake;
    }

    private function resolveSpecialistId(string $category, bool $needsPsychiatrist): ?int
    {
        $query = DB::table('users as u')
            ->join('specialist_profiles as sp', 'sp.user_id', '=', 'u.id')
            ->where('u.role', 'specialist')
            ->where('sp.status', 'approved');

        if ($needsPsychiatrist || in_array($category, ['bipolar', 'schizophrenia'], true)) {
            $query->where(function ($q) {
                $q->where('sp.specialty', 'like', '%psychiat%')
                    ->orWhere('sp.specialty', 'like', '%طبيب%');
            });
        } elseif ($category === 'children') {
            $query->where(function ($q) {
                $q->where('sp.specialty', 'like', '%child%')
                    ->orWhere('sp.specialty', 'like', '%أطفال%');
            });
        } else {
            $query->where(function ($q) {
                $q->where('sp.specialty', 'like', '%psycholog%')
                    ->orWhere('sp.specialty', 'like', '%أخصائي%')
                    ->orWhere('sp.specialty', 'like', '%cbt%');
            });
        }

        $id = $query->orderBy('u.id')->value('u.id');
        return $id ? (int) $id : null;
    }

    private function containsAny(array $haystack, array $needles): bool
    {
        foreach ($haystack as $item) {
            foreach ($needles as $needle) {
                if (str_contains($item, strtolower($needle))) {
                    return true;
                }
            }
        }
        return false;
    }
}
