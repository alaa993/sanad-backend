<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class SpecialistBlockedTime extends Model {
    use HasFactory;
    protected $fillable = ['specialist_id','start_at','end_at','reason'];
    protected $casts = ['start_at'=>'datetime','end_at'=>'datetime'];
}
