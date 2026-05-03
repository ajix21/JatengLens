<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use Searchable;
    protected $fillable = [
        'social_account_id', 'platform', 'post_url', 'content',
        'media_type', 'likes_count', 'comments_count', 'shares_count',
        'views_count', 'posted_at', 'is_flagged', 'flag_reason',
    ];

    protected $casts = [
        'likes_count'    => 'integer',
        'comments_count' => 'integer',
        'shares_count'   => 'integer',
        'views_count'    => 'integer',
        'posted_at'      => 'datetime',
        'is_flagged'     => 'boolean',
    ];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PostTag::class, 'post_tag_pivot');
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class, 'post_keyword_pivot')
            ->withPivot('occurrence_count');
    }

    public function getTotalEngagementAttribute(): int
    {
        return $this->likes_count + $this->comments_count + $this->shares_count + $this->views_count;
    }

    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where('content', 'LIKE', '%' . $term . '%');
    }

    public function toSearchableArray(): array
    {
        return ['id' => $this->id, 'content' => $this->content];
    }
}
