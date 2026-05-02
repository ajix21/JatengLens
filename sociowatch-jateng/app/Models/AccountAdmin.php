<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAdmin extends Model
{
    protected $fillable = [
        'social_account_id',
        'full_name',
        'alias',
        'nik',
        'phone',
        'email',
        'occupation',
        'affiliation',
        'notes',
    ];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
