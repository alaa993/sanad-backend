<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LibraryArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'title', 'body', 'image', 'type', 'duration', 'active',
        'author_name', 'author_title', 'author_avatar',
        'video_url', 'thumbnail', 'tags',
    ];

    protected $casts = [
        'title' => 'array',
        'body'  => 'array',
        'active'=> 'boolean',
        'tags'  => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(LibraryCategory::class, 'category_id');
    }
}
