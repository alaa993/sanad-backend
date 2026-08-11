<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationBeneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'patient_id',
        'assigned_specialist_id',
        'status',
        'risk_level',
        'primary_issue',
        'notes',
        'last_session_at',
    ];

    protected $casts = [
        'last_session_at' => 'datetime',
    ];
}
