<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentPost extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'alias', 'body', 'hidden_at'];

    protected $casts = [
        'hidden_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reactions()
    {
        return $this->hasMany(VentReaction::class);
    }

    public function reports()
    {
        return $this->hasMany(VentReport::class);
    }
}
