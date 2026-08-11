<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnonymousMatchReport extends Model
{
    use HasFactory;

    protected $fillable = ['match_request_id', 'reporter_id', 'reason', 'status'];

    public function matchRequest()
    {
        return $this->belongsTo(AnonymousMatchRequest::class, 'match_request_id');
    }
}
