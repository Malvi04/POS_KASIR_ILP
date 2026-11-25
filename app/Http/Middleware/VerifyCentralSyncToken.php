<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCentralSyncToken
{
    /**
     * Validate Bearer token for background POS synchronization requests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) config('pos_sync.central_api_token', '');
        $providedToken = (string) $request->bearerToken();

        if ($configuredToken === '' || $providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Token sinkronisasi Server Pusat tidak valid atau tidak disertakan.',
            ], 401);
        }

        return $next($request);
    }
}
