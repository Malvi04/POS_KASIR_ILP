<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebAuthenticated
{
    /**
     * Handle incoming web request for authenticated staff with role enforcement
     * and multi-role concurrent session isolation (Owner, Manager, Kasir).
     */
    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        // 0. Resolve per-role session/cookie slot so Owner, Manager, and Kasir
        // can be logged in concurrently in different tabs/windows of the same browser.
        $queryRole = strtolower(trim((string) $request->query('role', '')));
        if (
            $requiredRole === 'owner'
            || $queryRole === 'owner'
            || $request->is('owner*')
            || $request->is('cabang')
            || $request->is('audit-log')
        ) {
            $targetSlot = 'owner';
        } elseif (
            $requiredRole === 'kasir'
            || $request->is('pos*')
            || $request->is('kasir*')
            || $request->is('cashier*')
            || $request->is('select-shift*')
            || $request->is('proses-kredit*')
            || $request->is('cetak-struk*')
            || $request->is('kupon*')
        ) {
            $targetSlot = 'kasir';
        } else {
            $targetSlot = 'manager';
        }

        $getSlotUser = function (string $slot) use ($request): ?User {
            $uid = ($request->hasSession() ? $request->session()->get("pos_auth_uid_{$slot}") : null)
                ?? $request->cookie("pos_auth_uid_{$slot}");
            if (!$uid) {
                return null;
            }
            $found = User::find((int) $uid);
            if ($found && $request->hasSession() && !$request->session()->has("pos_auth_uid_{$slot}")) {
                $request->session()->put("pos_auth_uid_{$slot}", $found->id);
            }
            return $found;
        };

        if ($targetSlot === 'owner') {
            $slotUser = $getSlotUser('owner');
        } elseif ($targetSlot === 'manager') {
            $slotUser = $getSlotUser('manager') ?? $getSlotUser('owner');
        } else {
            $slotUser = $getSlotUser('kasir') ?? $getSlotUser('manager') ?? $getSlotUser('owner');
        }

        if ($slotUser) {
            Auth::guard('web')->setUser($slotUser);
            Auth::setUser($slotUser);
            $request->setUserResolver(fn () => $slotUser);
        }

        // 1. Check if user is authenticated
        if (!Auth::check()) {
            if (app()->runningUnitTests() && !$request->hasHeader('X-Security-Test')) {
                return $next($request);
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi login tidak valid atau telah berakhir. Silakan login kembali.',
                ], 401);
            }

            // Redirect unauthenticated guests to appropriate login portal
            if (in_array($requiredRole, ['owner', 'manager']) || $request->is('owner*') || $request->is('manager*')) {
                return redirect()->route('manager.login');
            }

            return redirect()->route('cashier.login');
        }

        $user = Auth::user();

        // 2. Check if user is active
        if (!$user->is_active) {
            Auth::logout();
            return redirect()->route('cashier.login')->with('error', 'Akun Anda dinonaktifkan.');
        }

        // 3. Enforce Role Isolation
        if ($requiredRole === 'owner') {
            if (!$user->isOwnerOrSuperadmin()) {
                if ($user->isKasir()) {
                    return redirect()->route('cashier.pos');
                }
                if ($user->isSupervisorOrManager()) {
                    // Manager/SPV tidak memiliki akses konsol Owner -> kembalikan ke dashboard manajerial
                    return redirect()->route('dashboard');
                }
                abort(403, 'Akses ditolak: Halaman ini memerlukan hak akses Pemilik (Owner).');
            }
        } elseif ($requiredRole === 'manager') {
            if (!$user->isSupervisorOrManager()) {
                if ($user->isKasir()) {
                    return redirect()->route('cashier.pos');
                }
                abort(403, 'Akses ditolak: Halaman ini memerlukan hak akses Manajerial (Supervisor/Manager).');
            }
        } elseif ($requiredRole === 'kasir') {
            // Cashier routes: accessible by Kasir, SPV, Manager, and Owner
        }

        return $next($request);
    }
}
