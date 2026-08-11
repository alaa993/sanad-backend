<?php

namespace App\Support;

use App\Models\PatientIntake;

class Privacy
{
    public static function patientCode(int $id): string
    {
        return 'P' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public static function maskedPatientName(?string $name, int $id): string
    {
        $code = self::patientCode($id);
        $first = $name ? mb_substr(trim($name), 0, 1, 'UTF-8') : null;
        if ($first) {
            return $first . '. (' . $code . ')';
        }
        return 'Patient ' . $code;
    }

    public static function patientStub(?int $id, ?string $name = null): array
    {
        if (!$id) {
            return ['id' => null, 'code' => null, 'name' => null];
        }
        return [
            'id'   => $id,
            'code' => self::patientCode($id),
            'name' => self::maskedPatientName($name, $id),
        ];
    }

    public static function sanitizeIntake(?PatientIntake $intake): ?array
    {
        if (!$intake) {
            return null;
        }

        return [
            'primary_issue'         => $intake->primary_issue,
            'severity_level'        => $intake->severity_level,
            'impact_level'          => $intake->impact_level,
            'risk_flags'            => $intake->risk_flags,
            'triage_category'       => $intake->triage_category,
            'triage_recommendation' => $intake->triage_recommendation,
            'updated_at'            => optional($intake->updated_at)->toIso8601String(),
        ];
    }
}
