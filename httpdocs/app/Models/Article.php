<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Factories\HasFactory;
class Article extends Model { use HasFactory;
  protected $fillable=['slug','title','body','tags','published','author_id','author_role'];
  protected $casts=['title'=>'array','body'=>'array','tags'=>'array','published'=>'boolean'];
}
