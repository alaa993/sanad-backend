<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupSessionParticipant extends Model {
    use HasFactory;

    protected $fillable = [
        'group_session_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function session(){ return $this->belongsTo(GroupSession::class, 'group_session_id'); }
    public function user(){ return $this->belongsTo(User::class, 'user_id'); }
}
