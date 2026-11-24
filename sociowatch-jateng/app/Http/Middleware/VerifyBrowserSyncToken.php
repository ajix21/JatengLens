<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBrowserSyncToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('services.browser_sync.token', ''));

        // If no token is configured (e.g. local dev), allow request
        if ($expected === '') {
            return $next($request);
        }

        $provided = trim((string) ($request->header('X-Sync-Token') ?: $request->bearerToken() ?: ''));

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Token sinkronisasi tidak valid atau belum diatur (X-Sync-Token).',
            ], 401);
        }

        return $next($request);
    }
}
