<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CoachPlanItem extends Model
{
    use HasFactory;

    protected $fillable = ['program_id', 'kind', 'title', 'schedule', 'meta', 'is_done', 'done_at'];

    protected $casts = [
        'meta' => 'array',
        'is_done' => 'boolean',
        'done_at' => 'datetime',
    ];

    public function program()
    {
        return $this->belongsTo(CoachProgram::class, 'program_id');
    }
}
