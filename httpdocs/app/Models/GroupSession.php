<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupSession extends Model {
    use HasFactory;

    protected $fillable = [
        'title',
        'topic',
        'description',
        'type',
        'start_at',
        'end_at',
        'status',
        'max_capacity',
        'is_public',
        'specialist_id',
        'join_url',
        'chat_id',
        'created_by',
        'age_category',
        'disorder_tag',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_public' => 'boolean',
        'max_capacity' => 'integer',
    ];

    public function specialist(){ return $this->belongsTo(User::class, 'specialist_id'); }
    public function chat(){ return $this->belongsTo(Chat::class, 'chat_id'); }
    public function participants(){ return $this->hasMany(GroupSessionParticipant::class, 'group_session_id'); }
}
