<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PostTag extends Model
{
    protected $fillable = ['name', 'color'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag_pivot');
    }
}
