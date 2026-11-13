<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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

            // Find user matching PIN with owner/superadmin or supervisor/manager privileges
            $user = User::where('pin', $pin)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereRaw('LOWER(role) IN (?, ?, ?, ?, ?)', ['owner', 'superadmin', 'admin', 'manager', 'supervisor']);
                })
                ->first();

            // Fallback for default system master PIN
            if (!$user && ($pin === '999888' || $pin === '123456')) {
                $user = User::whereRaw('LOWER(role) IN (?, ?)', ['owner', 'superadmin'])->first();
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN Otorisasi Owner tidak valid! Silakan masukkan PIN 6 digit yang terdaftar.',
                ], 401);
            }

            Auth::login($user, true);

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

        Auth::login($user, true);

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
            ],
        ]);
    }

    /**
     * Logout and destroy session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar dari sesi.',
            'redirect_url' => url('/'),
        ]);
    }
}
