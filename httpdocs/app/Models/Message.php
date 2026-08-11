<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model {
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['chat_id','sender_id','type','body','created_at'];
    protected $casts = ['created_at'=>'datetime:c'];
    public function chat(){ return $this->belongsTo(Chat::class); }
    public function sender(){ return $this->belongsTo(User::class,'sender_id'); }
}
