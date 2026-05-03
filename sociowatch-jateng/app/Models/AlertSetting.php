<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertSetting extends Model
{
    protected $fillable = [
        'social_account_id', 'spike_up_threshold', 'spike_down_threshold',
        'milestone_values', 'keyword_spike_threshold', 'is_active',
    ];

    protected $casts = [
        'spike_up_threshold'   => 'decimal:2',
        'spike_down_threshold' => 'decimal:2',
        'milestone_values'     => 'array',
        'is_active'            => 'boolean',
    ];

    public function socialAccount()
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /** Get global setting (social_account_id = null), create default if missing */
    public static function global(): self
    {
        return static::firstOrCreate(
            ['social_account_id' => null],
            [
                'spike_up_threshold'       => 10.00,
                'spike_down_threshold'     => 10.00,
                'milestone_values'         => [1000, 5000, 10000, 50000, 100000],
                'keyword_spike_threshold'  => 50,
                'is_active'                => true,
            ]
        );
    }
}
