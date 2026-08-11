<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatParticipant extends Model {
    use HasFactory;
    protected $fillable = ['chat_id','user_id','role','joined_at','left_at'];
    public function chat(){ return $this->belongsTo(Chat::class); }
    public function user(){ return $this->belongsTo(User::class); }
}
