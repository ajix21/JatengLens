<?php

namespace App\Models;

use App\Enums\FetchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FetchLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'social_account_id', 'status', 'new_posts', 'updated_posts', 'message', 'fetched_at',
    ];

    protected $casts = [
        'status' => FetchStatus::class,
        'new_posts' => 'integer',
        'updated_posts' => 'integer',
        'fetched_at' => 'datetime',
    ];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
