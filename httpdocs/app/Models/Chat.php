<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chat extends Model {
    use HasFactory;
    protected $fillable = ['subject','last_message','last_message_at'];
    public function participants(){ return $this->hasMany(ChatParticipant::class); }
    public function messages(){ return $this->hasMany(Message::class); }
}
