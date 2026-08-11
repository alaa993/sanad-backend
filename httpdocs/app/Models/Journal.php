<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class Journal extends Model { use HasFactory; public $timestamps=false;
  protected $fillable=['user_id','entry','created_at']; protected $casts=['created_at'=>'datetime:c'];
}
