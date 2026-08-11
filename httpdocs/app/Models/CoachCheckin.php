<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CoachCheckin extends Model
{
    use HasFactory;

    protected $fillable = ['program_id', 'weight_kg', 'mood', 'note', 'logged_at'];

    protected $casts = [
        'logged_at' => 'datetime',
        'weight_kg' => 'decimal:2',
    ];

    public function program()
    {
        return $this->belongsTo(CoachProgram::class, 'program_id');
    }
}
