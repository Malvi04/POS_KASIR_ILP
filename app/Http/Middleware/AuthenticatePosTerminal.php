<?php

namespace App\Http\Middleware;

use App\Models\PosTerminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePosTerminal
{
    /**
     * Authenticate per-terminal Bearer token against `pos_terminals.api_token_hash` (SHA-256),
     * verify active status, update `last_seen_at`, and attach authoritative `$terminal` to Request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedToken = trim((string) $request->bearerToken());

        if ($providedToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Bearer token terminal tidak disertakan.',
            ], 401);
        }

        $tokenHash = PosTerminal::hashToken($providedToken);

        $terminal = PosTerminal::with('branch')
            ->where('api_token_hash', $tokenHash)
            ->first();

        if (!$terminal || !$terminal->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Token terminal kasir tidak valid atau telah dinonaktifkan.',
            ], 401);
        }

        if (!$terminal->branch || !$terminal->branch->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Lokasi cabang milik terminal ini tidak ditemukan atau sedang nonaktif.',
            ], 401);
        }

        $terminal->forceFill([
            'last_seen_at' => now(),
        ])->saveQuietly();

        // Bind authoritative PosTerminal instance onto Request attributes
        $request->attributes->set('pos_terminal', $terminal);

        return $next($request);
    }
}
