<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class SpecialistProfile extends Model {
  use HasFactory;
  protected $fillable = [
    'user_id',
    'specialty',
    'languages',
    'years_exp',
    'accepting_new',
    'bio',
    'rate_cents',
    'currency',
    'status',
    'verification_notes',
  ];
  protected $casts = ['languages'=>'array','bio'=>'array'];
  public function user(){ return $this->belongsTo(User::class); }
  public function documents(){ return $this->hasMany(SpecialistDocument::class, 'user_id', 'user_id'); }
}
