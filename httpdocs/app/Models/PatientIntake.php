<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientIntake extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'age',
        'occupation',
        'issue_duration',
        'severity_level',
        'impact_level',
        'preferred_session_mode',
        'risk_flags',
        'symptoms',
        'primary_issue',
        'benefit_score',
        'previous_consult',
        'consult_notes',
        'notes',
        'triage_category',
        'triage_recommendation',
        'recommended_specialist_id',
        'recommended_specialist_notes',
        'referral_physician_recommended',
        'referral_recommended_at',
        'initial_session_id',
        'onboarding_step',
        'recovery_unlocked',
        'pre_session_survey',
        'pre_session_completed_at',
        'external_physician_recommended',
        'external_physician_acknowledged_at',
        'external_physician_notes',
    ];

    protected $casts = [
        'symptoms' => 'array',
        'triage_recommendation' => 'array',
        'pre_session_survey' => 'array',
        'previous_consult' => 'boolean',
        'risk_flags' => 'array',
        'referral_physician_recommended' => 'boolean',
        'external_physician_recommended' => 'boolean',
        'recovery_unlocked' => 'boolean',
        'referral_recommended_at' => 'datetime',
        'pre_session_completed_at' => 'datetime',
        'external_physician_acknowledged_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recommendedSpecialist()
    {
        return $this->belongsTo(User::class, 'recommended_specialist_id');
    }

    public function initialSession()
    {
        return $this->belongsTo(Session::class, 'initial_session_id');
    }

    public function isComplete(): bool
    {
        if (!empty($this->primary_issue) || !empty($this->full_name)) {
            return true;
        }

        return in_array($this->onboarding_step, ['intake_done', 'pre_session_done', 'vent_done', 'ready'], true);
    }
}
