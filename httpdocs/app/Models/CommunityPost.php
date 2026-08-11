<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommunityPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_id', 'author_id', 'body', 'media_url', 'type',
        'post_kind', 'question_id', 'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function question()
    {
        return $this->belongsTo(CommunityPost::class, 'question_id');
    }

    public function answers()
    {
        return $this->hasMany(CommunityPost::class, 'question_id');
    }

    public function likes()
    {
        return $this->hasMany(CommunityPostLike::class, 'post_id');
    }

    public function comments()
    {
        return $this->hasMany(CommunityPostComment::class, 'post_id');
    }
}
