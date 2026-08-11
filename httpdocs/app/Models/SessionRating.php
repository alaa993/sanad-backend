<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'rater_id',
        'ratee_id',
        'direction',
        'score',
        'comment',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
