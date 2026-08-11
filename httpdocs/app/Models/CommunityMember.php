<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class CommunityMember extends Model { use HasFactory; protected $fillable=['community_id','user_id','role'];
  public function community(){ return $this->belongsTo(Community::class); }
  public function user(){ return $this->belongsTo(User::class); }
}
