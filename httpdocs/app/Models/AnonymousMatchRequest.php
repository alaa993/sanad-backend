<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnonymousMatchRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'gender', 'match_gender', 'mode', 'status',
        'partner_id', 'chat_id', 'alias_self', 'alias_partner',
        'matched_at', 'ended_at', 'expires_at',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'ended_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
