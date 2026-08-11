<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VentReport extends Model
{
    use HasFactory;

    protected $fillable = ['vent_post_id', 'user_id', 'reason', 'status'];

    public function post()
    {
        return $this->belongsTo(VentPost::class, 'vent_post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
