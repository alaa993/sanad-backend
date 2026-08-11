<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LibraryCategory extends Model
{
    use HasFactory;
    protected $fillable = ['title']; // JSON: {ar,en,tr}

    protected $casts = [
        'title' => 'array',
    ];

    public function articles()
    {
        return $this->hasMany(LibraryArticle::class, 'category_id');
    }
}
