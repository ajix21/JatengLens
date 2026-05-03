<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Keyword extends Model
{
    protected $fillable = ['word', 'category', 'color', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_keyword_pivot')
            ->withPivot('occurrence_count');
    }

    public static function categoryColor(string $category): string
    {
        return match($category) {
            'sensitif' => '#ef4444',
            'negatif'  => '#f97316',
            'positif'  => '#22c55e',
            default    => '#6b7280',
        };
    }
}
