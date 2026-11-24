<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NarrativeBriefing extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_start',
        'period_end',
        'window_label',
        'source',
        'status',
        'executive_summary',
        'issue_clusters',
        'recommendations',
        'stats_snapshot',
        'error_message',
        'generated_by',
    ];

    protected $casts = [
        'period_start'    => 'datetime',
        'period_end'      => 'datetime',
        'issue_clusters'  => 'array',
        'recommendations' => 'array',
        'stats_snapshot'  => 'array',
    ];

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
