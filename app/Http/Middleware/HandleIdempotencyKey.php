<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class HandleIdempotencyKey
{
    /**
     * Prevent duplicate mutations when the browser lags or a user double-clicks
     * by deduplicating requests carrying the same X-Idempotency-Key header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $rawKey = trim((string) $request->header('X-Idempotency-Key', ''));
        if ($rawKey === '' || strlen($rawKey) > 128) {
            return $next($request);
        }

        $actor = Auth::id()
            ? 'u:' . Auth::id()
            : 's:' . ($request->hasSession() ? $request->session()->getId() : ($request->ip() ?? 'anon'));

        $fingerprint = hash('sha256', implode('|', [
            $actor,
            $request->method(),
            $request->path(),
            $rawKey,
        ]));

        $cacheKey = 'idempotency:res:' . $fingerprint;
        $lockKey = 'idempotency:lock:' . $fingerprint;

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['status'], $cached['body'])) {
            return response()->json($cached['body'], (int) $cached['status'])
                ->header('X-Idempotent-Replay', 'true');
        }

        try {
            return Cache::lock($lockKey, 15)->block(5, function () use ($request, $next, $cacheKey) {
                $cachedInside = Cache::get($cacheKey);
                if (is_array($cachedInside) && isset($cachedInside['status'], $cachedInside['body'])) {
                    return response()->json($cachedInside['body'], (int) $cachedInside['status'])
                        ->header('X-Idempotent-Replay', 'true');
                }

                $response = $next($request);

                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    $decoded = json_decode((string) $response->getContent(), true);
                    if (is_array($decoded)) {
                        Cache::put($cacheKey, [
                            'status' => $response->getStatusCode(),
                            'body' => $decoded,
                        ], now()->addMinutes(10));
                    }
                }

                return $response;
            });
        } catch (LockTimeoutException) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan sedang diproses, mohon tunggu beberapa saat.',
            ], 409);
        }
    }
}
