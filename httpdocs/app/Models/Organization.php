<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class Organization extends Model {
  use HasFactory;
  protected $fillable = ['name','about','status'];
  protected $casts = ['about'=>'array'];
  public function specialists(){ return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps(); }
}
