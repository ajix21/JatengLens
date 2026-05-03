<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'social_account_id', 'alert_type', 'threshold_value',
        'current_value', 'change_percent', 'message',
        'is_read', 'triggered_at',
    ];

    protected $casts = [
        'is_read'         => 'boolean',
        'triggered_at'    => 'datetime',
        'change_percent'  => 'decimal:2',
        'threshold_value' => 'integer',
        'current_value'   => 'integer',
    ];

    public function socialAccount()
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
