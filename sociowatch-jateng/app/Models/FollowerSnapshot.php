<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowerSnapshot extends Model
{
    protected $fillable = [
        'social_account_id', 'followers_count', 'following_count',
        'post_count', 'recorded_at',
    ];

    protected $casts = [
        'recorded_at'     => 'datetime',
        'followers_count' => 'integer',
        'following_count' => 'integer',
        'post_count'      => 'integer',
    ];

    public function socialAccount()
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
