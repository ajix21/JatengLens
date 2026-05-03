<?php

namespace App\Http\Controllers\Concerns;

use App\Models\UserActivityLog;

trait LogsActivity
{
    protected function logActivity(string $action, string $targetType, int $targetId): void
    {
        UserActivityLog::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'ip_address'  => request()->ip(),
        ]);
    }
}
