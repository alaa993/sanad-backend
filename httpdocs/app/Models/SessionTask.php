<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'title',
        'description',
        'type',
        'status',
        'patient_answer',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
