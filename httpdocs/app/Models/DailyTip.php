<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyTip extends Model
{
    use HasFactory;

    protected $fillable = ['tip_date', 'title', 'body', 'active', 'created_by'];

    protected $casts = [
        'title' => 'array',
        'body' => 'array',
        'active' => 'boolean',
        'tip_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
