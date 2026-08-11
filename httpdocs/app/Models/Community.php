<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Community extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'about', 'visibility', 'owner_id', 'kind', 'organization_id', 'category'];

    protected $casts = ['name' => 'array', 'about' => 'array'];

    public function members()
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function posts()
    {
        return $this->hasMany(CommunityPost::class);
    }
}
