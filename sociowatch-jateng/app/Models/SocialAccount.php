<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $fillable = [
        'platform',
        'username',
        'display_name',
        'profile_url',
        'profile_picture',
        'followers_count',
        'following_count',
        'post_count',
        'bio',
        'category_id',
        'region_id',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'followers_count' => 'integer',
        'following_count' => 'integer',
        'post_count'      => 'integer',
        'is_active'       => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(AccountAdmin::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function followerSnapshots(): HasMany
    {
        return $this->hasMany(FollowerSnapshot::class)->orderBy('recorded_at');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class)->orderByDesc('triggered_at');
    }

    public function alertSetting()
    {
        return $this->hasOne(AlertSetting::class);
    }
}
