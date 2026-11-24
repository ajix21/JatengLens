<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'social_account_id', 'platform', 'post_url', 'media_url', 'content',
        'media_type', 'likes_count', 'comments_count', 'shares_count',
        'views_count', 'posted_at', 'is_flagged', 'flag_reason', 'external_id',
        'sentiment', 'sentiment_manual',
    ];

    protected $casts = [
        'likes_count'      => 'integer',
        'comments_count'   => 'integer',
        'shares_count'     => 'integer',
        'views_count'      => 'integer',
        'posted_at'        => 'datetime',
        'is_flagged'       => 'boolean',
        'sentiment_manual' => 'boolean',
    ];

    public static function sentimentBadge(string $sentiment): array
    {
        return match ($sentiment) {
            'sensitif' => ['label' => 'Sensitif', 'bg' => 'bg-red-100', 'text' => 'text-red-700', 'hex' => '#ef4444'],
            'negatif'  => ['label' => 'Negatif',  'bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'hex' => '#f97316'],
            'positif'  => ['label' => 'Positif',  'bg' => 'bg-green-100', 'text' => 'text-green-700', 'hex' => '#22c55e'],
            default    => ['label' => 'Netral',   'bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'hex' => '#6b7280'],
        };
    }

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
