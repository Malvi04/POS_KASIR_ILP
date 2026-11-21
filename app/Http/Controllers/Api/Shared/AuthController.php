<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public const ROLE_SLOTS = ['owner', 'manager', 'kasir'];

    /**
     * Bind the authenticated user to a dedicated per-role session & encrypted cookie slot
     * while preserving other roles' active sessions in the same browser.
     */
    public static function bindRoleSlot(Request $request, User $user, string $slot): void
    {
        $normalizedSlot = strtolower(trim($slot));
        if (!in_array($normalizedSlot, self::ROLE_SLOTS, true)) {
            $normalizedSlot = $user->isKasir()
                ? 'kasir'
                : ($user->isOwnerOrSuperadmin() ? 'owner' : 'manager');
        }

        // Capture any existing role slots from the current session or cookies before login
        $existingSlots = [];
        foreach (self::ROLE_SLOTS as $s) {
            $key = "pos_auth_uid_{$s}";
            $val = ($request->hasSession() ? $request->session()->get($key) : null) ?? $request->cookie($key);
            if ($val) {
                $existingSlots[$key] = (int) $val;
            }
        }
        $existingSlots["pos_auth_uid_{$normalizedSlot}"] = (int) $user->id;

        Auth::login($user, true);
        $user->syncLocationAccess();

        if ($request->hasSession()) {
            // Only rotate session ID if no other role slot was active, using migrate(false)
            // so concurrent tabs in the same browser never lose their session state.
            if (count($existingSlots) <= 1) {
                $request->session()->migrate(false);
            }
            foreach ($existingSlots as $k => $v) {
                $request->session()->put($k, $v);
            }
        }

        Cookie::queue(cookie("pos_auth_uid_{$normalizedSlot}", (string) $user->id, 60 * 24 * 7));
    }

    /**
     * Resolve which role slot is logging out and clear only that role's slot
     * if other role tabs are still logged in on the same browser.
     */
    public static function performLogout(Request $request): void
    {
        $targetRole = strtolower(trim((string) (
            $request->input('role')
            ?? $request->query('role')
            ?? $request->header('X-POS-Role', '')
        )));

        if (!in_array($targetRole, self::ROLE_SLOTS, true)) {
            $referer = (string) $request->headers->get('referer', '');
            if ($referer !== '') {
                $refPath = '/' . ltrim((string) parse_url($referer, PHP_URL_PATH), '/');
                $refQuery = (string) parse_url($referer, PHP_URL_QUERY);
                parse_str($refQuery, $refParams);
                $refRole = strtolower(trim((string) ($refParams['role'] ?? '')));

                if ($refRole === 'owner' || str_starts_with($refPath, '/owner') || in_array($refPath, ['/cabang', '/audit-log'], true)) {
                    $targetRole = 'owner';
                } elseif ($refRole === 'kasir' || str_starts_with($refPath, '/pos') || str_starts_with($refPath, '/kasir') || str_starts_with($refPath, '/cashier') || in_array($refPath, ['/select-shift', '/proses-kredit', '/cetak-struk', '/kupon'], true)) {
                    $targetRole = 'kasir';
                } elseif ($refRole === 'manager' || str_starts_with($refPath, '/manager') || in_array($refPath, ['/dashboard', '/inventory', '/members', '/member-detail', '/umkm-partners', '/promosi', '/absensi', '/reports', '/kelola-akun', '/settings', '/stock-opname', '/retur', '/retur-masuk', '/kirim-barang', '/pembelian-barang', '/suppliers'], true) || str_starts_with($refPath, '/suppliers/')) {
                    $targetRole = 'manager';
                }
            }
        }

        if (in_array($targetRole, self::ROLE_SLOTS, true)) {
            if ($request->hasSession()) {
                $request->session()->forget("pos_auth_uid_{$targetRole}");
            }
            Cookie::queue(Cookie::forget("pos_auth_uid_{$targetRole}"));

            $remainingUser = null;
            foreach (self::ROLE_SLOTS as $s) {
                if ($s === $targetRole) {
                    continue;
                }
                $uid = ($request->hasSession() ? $request->session()->get("pos_auth_uid_{$s}") : null)
                    ?? $request->cookie("pos_auth_uid_{$s}");
                if ($uid) {
                    $candidate = User::where('id', (int) $uid)->where('is_active', true)->first();
                    if ($candidate) {
                        $remainingUser = $candidate;
                        break;
                    }
                }
            }

            if ($remainingUser) {
                Auth::login($remainingUser, true);
                return;
            }
        }

        foreach (self::ROLE_SLOTS as $s) {
            if ($request->hasSession()) {
                $request->session()->forget("pos_auth_uid_{$s}");
            }
            Cookie::queue(Cookie::forget("pos_auth_uid_{$s}"));
        }

        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
    /**
     * Unified Login endpoint for Kasir, Manager, and Owner.
     */
    public function login(Request $request): JsonResponse
    {
        $role = strtolower($request->input('role', ''));

        // 1. PIN-based login (Owner / Supervisor via 6-digit PIN)
        if ($request->filled('pin') || ($role === 'owner' && !$request->filled('password') && $request->filled('pin'))) {
            $pin = trim((string) ($request->input('pin') ?? ''));
            if (strlen($pin) !== 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN otorisasi harus terdiri dari 6 digit angka.',
                ], 422);
            }

            // Find active user matching PIN with owner/superadmin or supervisor/manager privileges
            $candidates = User::where('is_active', true)
                ->where(function ($q) {
                    $q->whereRaw('LOWER(role) IN (?, ?, ?, ?, ?)', ['owner', 'superadmin', 'admin', 'manager', 'supervisor']);
                })
                ->get();

            $user = $candidates->first(fn($u) => $u->verifyPin($pin));

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN Otorisasi tidak valid! Silakan masukkan PIN 6 digit yang terdaftar.',
                ], 401);
            }

            $pinSlot = ($role === 'owner' || $user->isOwnerOrSuperadmin())
                ? 'owner'
                : ($user->isSupervisorOrManager() ? 'manager' : 'kasir');
            self::bindRoleSlot($request, $user, $pinSlot);

            return response()->json([
                'success' => true,
                'message' => 'Autentikasi Owner berhasil. Selamat datang, ' . $user->name,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'nik' => $user->nik,
                    'role' => $user->role,
                    'branch' => $user->branch,
                    'assigned_branches' => $user->getAssignedBranchesList(),
                ],
                'redirect_url' => url('/owner'),
            ]);
        }

        // 2. Credential-based login (Kasir & Manager)
        $validated = $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = trim($validated['identifier']);
        $password = (string) $validated['password'];

        $user = User::where(function ($q) use ($identifier) {
            $q->where('username', $identifier)
              ->orWhere('nik', $identifier)
              ->orWhere('email', $identifier);
        })->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak ditemukan. Periksa kembali NIK atau Username Anda.',
            ], 404);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => "Akun {$user->name} saat ini dinonaktifkan. Silakan hubungi Manager Koperasi.",
            ], 403);
        }

        // Verify Password or Cashier/Manager PIN safely (plain match, PIN match, or Bcrypt hash)
        $isPasswordValid = false;

        if ($password === (string) $user->password || (!empty($user->pin) && $password === (string) $user->pin)) {
            $isPasswordValid = true;
        } elseif (!empty($user->password) && str_starts_with((string) $user->password, '$2y$')) {
            try {
                if (Hash::check($password, $user->password)) {
                    $isPasswordValid = true;
                }
            } catch (\Throwable $e) {
                $isPasswordValid = false;
            }
        } elseif (!empty($user->pin) && str_starts_with((string) $user->pin, '$2y$')) {
            try {
                if (Hash::check($password, $user->pin)) {
                    $isPasswordValid = true;
                }
            } catch (\Throwable $e) {
                $isPasswordValid = false;
            }
        }

        if (!$isPasswordValid) {
            return response()->json([
                'success' => false,
                'message' => "Kata sandi atau PIN yang Anda masukkan salah untuk akun {$user->name}. Silakan coba lagi.",
            ], 401);
        }

        // Validate Role Access
        if ($role === 'kasir') {
            if (!$user->isKasir()) {
                return response()->json([
                    'success' => false,
                    'message' => "Akun {$user->name} terdaftar sebagai '{$user->role}', bukan Kasir Operasional. Gunakan portal peran yang sesuai.",
                ], 403);
            }
            $redirectUrl = url('/select-shift');
        } elseif ($role === 'manager') {
            if (!$user->isSupervisorOrManager()) {
                return response()->json([
                    'success' => false,
                    'message' => "Akun {$user->name} terdaftar sebagai '{$user->role}', tidak memiliki hak akses manajerial.",
                ], 403);
            }
            $redirectUrl = url('/dashboard');
        } elseif ($role === 'owner') {
            if (!$user->isOwner()) {
                return response()->json([
                    'success' => false,
                    'message' => "Akun {$user->name} terdaftar sebagai '{$user->role}', tidak memiliki hak akses pemilik (Owner).",
                ], 403);
            }
            $redirectUrl = url('/owner');
        } else {
            $redirectUrl = $user->isKasir() ? url('/select-shift') : ($user->isOwner() ? url('/owner') : url('/dashboard'));
        }

        if ($role === 'kasir') {
            $credSlot = 'kasir';
        } elseif ($role === 'manager') {
            $credSlot = 'manager';
        } elseif ($role === 'owner') {
            $credSlot = 'owner';
        } else {
            $credSlot = $user->isKasir() ? 'kasir' : ($user->isOwnerOrSuperadmin() ? 'owner' : 'manager');
        }
        self::bindRoleSlot($request, $user, $credSlot);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil! Selamat datang, ' . $user->name,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'nik' => $user->nik,
                'role' => $user->role,
                'branch' => $user->branch,
                'assigned_branches' => $user->getAssignedBranchesList(),
            ],
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Get currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'authenticated' => false,
                'user' => null,
            ], 401);
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'nik' => $user->nik,
                'role' => $user->role,
                'branch' => $user->branch,
                'has_pin' => !empty($user->pin),
                'assigned_branches' => $user->getAssignedBranchesList(),
            ],
        ]);
    }

    /**
     * Change password for the currently authenticated user.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Autentikasi diperlukan. Sesi login Anda telah berakhir.',
            ], 401);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:4|confirmed',
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 4 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        if (!$user->verifyPassword($validated['current_password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini yang Anda masukkan salah.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password akun berhasil diperbarui! Silakan gunakan password baru saat login.',
        ]);
    }

    /**
     * Change or configure 6-digit PIN for the currently authenticated user.
     */
    public function changePin(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Autentikasi diperlukan. Sesi login Anda telah berakhir.',
            ], 401);
        }

        $hasExistingPin = !empty($user->pin);

        $rules = [
            'pin' => 'required|string|size:6|regex:/^[0-9]+$/|confirmed',
            'current_pin' => 'nullable|string',
            'current_password' => 'nullable|string',
        ];

        $validated = $request->validate($rules, [
            'pin.required' => 'PIN baru wajib diisi.',
            'pin.size' => 'PIN kasir harus terdiri dari tepat 6 digit angka.',
            'pin.regex' => 'PIN hanya boleh berisi angka (0-9).',
            'pin.confirmed' => 'Konfirmasi PIN baru tidak cocok.',
        ]);

        // If user already has a PIN, verify current PIN or password
        if ($hasExistingPin) {
            $currentPin = $validated['current_pin'] ?? '';
            $currentPassword = $validated['current_password'] ?? '';

            if (empty($currentPin) && empty($currentPassword)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Silakan masukkan PIN saat ini atau Password akun untuk verifikasi keamanan.',
                ], 422);
            }

            $authorized = false;
            if (!empty($currentPin) && $user->verifyPin($currentPin)) {
                $authorized = true;
            } elseif (!empty($currentPassword) && $user->verifyPassword($currentPassword)) {
                $authorized = true;
            }

            if (!$authorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN saat ini atau Password verifikasi yang Anda masukkan salah.',
                ], 422);
            }
        }

        $user->update([
            'pin' => $validated['pin'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'PIN kasir 6 digit berhasil diperbarui!',
            'has_pin' => true,
        ]);
    }

    /**
     * Logout and destroy session.
     */
    public function logout(Request $request): JsonResponse
    {
        self::performLogout($request);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar dari sesi.',
            'redirect_url' => url('/'),
        ]);
    }

    /**
     * Public roster of active cashiers for portal login autocomplete.
     */
    public function cashiers(): JsonResponse
    {
        $cashiers = User::where('is_active', true)
            ->where(function ($q) {
                $q->whereRaw('LOWER(role) LIKE ?', ['%kasir%'])
                  ->orWhereRaw('LOWER(role) = ?', ['cashier']);
            })
            ->orderBy('name')
            ->get();

        $formatted = $cashiers->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'nik' => $u->nik,
                'role' => $u->role,
                'branch' => $u->branch,
                'assigned_branches' => $u->getAssignedBranchesList(),
                'is_active' => (bool) $u->is_active,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }
}
