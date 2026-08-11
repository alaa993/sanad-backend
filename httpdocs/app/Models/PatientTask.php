<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'appointment_id',
        'title',
        'description',
        'due_at',
        'reminder_at',
        'completed_at',
        'status',
        'completion_note',
        'meta',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'reminder_at' => 'datetime',
        'completed_at' => 'datetime',
        'completion_note' => 'string',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
