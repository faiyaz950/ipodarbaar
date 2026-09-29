<?php

namespace App\Models;

use Database\Factories\BlogAuthorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A byline for blog posts, with its own profile page (E-E-A-T).
 */
class BlogAuthor extends Model
{
    /** @use HasFactory<BlogAuthorFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'role', 'bio'];

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    public function url(): string
    {
        return route('blog.author', $this->slug);
    }
}
