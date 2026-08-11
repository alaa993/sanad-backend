<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CoachProgram extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'specialist_id', 'category', 'title', 'goals', 'active'];

    protected $casts = [
        'goals' => 'array',
        'active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function specialist()
    {
        return $this->belongsTo(User::class, 'specialist_id');
    }

    public function items()
    {
        return $this->hasMany(CoachPlanItem::class, 'program_id');
    }

    public function checkins()
    {
        return $this->hasMany(CoachCheckin::class, 'program_id');
    }
}
