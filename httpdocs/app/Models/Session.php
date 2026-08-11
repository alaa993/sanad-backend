<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $table = 'therapy_sessions'; // 🔥 مهم جداً

    protected $fillable = [
        'user_id',
        'specialist_id',
        'organization_id',
        'type',
        'status',
        'scheduled_at',
        'notes'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function specialist()
    {
        return $this->belongsTo(User::class, 'specialist_id');
    }

    public function organization()
    {
        return $this->belongsTo(User::class, 'organization_id');
    }
}