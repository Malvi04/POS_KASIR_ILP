<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Display a listing of the users with role and branch filters.
     * High performance, eager-loaded and optimized for low latency.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Filter by role
        if ($request->filled('role') && $request->role !== 'all') {
            $role = strtolower($request->role);
            if ($role === 'kasir') {
                $query->whereRaw('LOWER(role) LIKE ?', ['%kasir%']);
            } elseif ($role === 'supervisor') {
                $query->whereRaw('LOWER(role) IN (?, ?)', ['supervisor', 'spv']);
            } elseif ($role === 'manager') {
                $query->whereRaw('LOWER(role) = ?', ['manager']);
            } elseif ($role === 'owner') {
                $query->whereRaw('LOWER(role) IN (?, ?, ?)', ['owner', 'superadmin', 'admin']);
            } else {
                $query->whereRaw('LOWER(role) = ?', [$role]);
            }
        }

        // Filter by branch
        if ($request->filled('branch') && $request->branch !== 'all') {
            $query->where('branch', $request->branch);
        }

        // Filter by active status
        if ($request->filled('status') && $request->status !== 'all') {
            $isActive = strtolower($request->status) === 'aktif' || $request->status === '1' || $request->status === 'true';
            $query->where('is_active', $isActive);
        }

        // Search by name, NIK, username, email
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                  ->orWhere('nik', 'ILIKE', $search)
                  ->orWhere('username', 'ILIKE', $search)
                  ->orWhere('email', 'ILIKE', $search);
            });
        }

        $users = $query->orderBy('role')->orderBy('name')->get();

        // Calculate role counts for quick UI badges
        $allUsers = User::all();
        $counts = [
            'total' => $allUsers->count(),
            'active' => $allUsers->where('is_active', true)->count(),
            'kasir' => $allUsers->filter(fn($u) => $u->isKasir())->count(),
            'supervisor' => $allUsers->filter(fn($u) => in_array(strtolower($u->role), ['supervisor', 'spv']))->count(),
            'manager' => $allUsers->filter(fn($u) => strtolower($u->role) === 'manager')->count(),
            'owner' => $allUsers->filter(fn($u) => $u->isOwnerOrSuperadmin())->count(),
        ];

        $formatted = $users->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'nik' => $u->nik,
                'role' => $u->role,
                'role_category' => $u->isOwnerOrSuperadmin() ? 'Owner / Superadmin' : ($u->isSupervisorOrManager() ? 'Supervisor / Manager' : 'Kasir'),
                'branch' => $u->branch ?? 'Pusat',
                'is_active' => (bool) $u->is_active,
                'pin' => $u->pin ?? '123456',
                'created_at' => $u->created_at ? $u->created_at->format('Y-m-d H:i') : null,
            ];
        });

        return response()->json([
            'success' => true,
            'summary' => $counts,
            'data' => $formatted,
        ]);
    }

    /**
     * Store a newly created user (Kasir, Supervisor, Manager, Owner).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:150|unique:users,email',
            'nik' => 'nullable|string|max:50|unique:users,nik',
            'role' => 'required|string|max:50',
            'pin' => 'nullable|string|size:6|regex:/^[0-9]+$/',
            'branch' => 'required|string|max:100',
            'password' => 'required|string|min:4',
            'is_active' => 'nullable|boolean',
        ], [
            'pin.size' => 'PIN harus berupa 6 digit angka.',
            'pin.regex' => 'PIN hanya boleh berisi angka numerik.',
            'username.unique' => 'Username ini sudah digunakan.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'nik.unique' => 'NIK ini sudah terdaftar pada pengguna lain.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'nik' => $validated['nik'] ?? ('USR-' . time()),
            'role' => $validated['role'],
            'pin' => $validated['pin'] ?? '123456',
            'branch' => $validated['branch'],
            'password' => Hash::make($validated['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Pengguna {$user->name} ({$user->role}) berhasil didaftarkan.",
            'data' => $user,
        ], 201);
    }

    /**
     * Display the specified user detail.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'nik' => $user->nik,
                'role' => $user->role,
                'branch' => $user->branch,
                'is_active' => (bool) $user->is_active,
                'pin' => $user->pin,
                'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'nik' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|string|max:50',
            'pin' => 'nullable|string|size:6|regex:/^[0-9]+$/',
            'branch' => 'required|string|max:100',
            'password' => 'nullable|string|min:4',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'nik' => $validated['nik'] ?? $user->nik,
            'role' => $validated['role'],
            'branch' => $validated['branch'],
            'is_active' => $request->boolean('is_active', $user->is_active),
        ];

        if (!empty($validated['pin'])) {
            $updateData['pin'] = $validated['pin'];
        }

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "Data pengguna {$user->name} berhasil diperbarui.",
            'data' => $user,
        ]);
    }

    /**
     * Reset PIN for a user.
     */
    public function resetPin(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'pin' => 'required|string|size:6|regex:/^[0-9]+$/',
        ], [
            'pin.size' => 'PIN otorisasi harus tepat 6 digit angka.',
            'pin.regex' => 'PIN hanya boleh berisi angka 0-9.',
        ]);

        $user->update(['pin' => $validated['pin']]);

        return response()->json([
            'success' => true,
            'message' => "PIN otorisasi untuk {$user->name} berhasil diubah.",
            'pin' => $user->pin,
        ]);
    }

    /**
     * Reset password for a user.
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json([
            'success' => true,
            'message' => "Password portal untuk {$user->name} berhasil direset.",
        ]);
    }

    /**
     * Toggle active status of a user (Nonaktifkan / Aktifkan).
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Safeguard: Do not deactivate the last active Owner
        if ($user->isOwnerOrSuperadmin() && $user->is_active) {
            $activeOwners = User::whereRaw('LOWER(role) IN (?, ?, ?)', ['owner', 'superadmin', 'admin'])
                ->where('is_active', true)
                ->count();
            if ($activeOwners <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menonaktifkan satu-satunya akun Owner/Superadmin aktif sistem.',
                ], 422);
            }
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $statusStr = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil {$statusStr}.",
            'is_active' => $user->is_active,
            'data' => $user,
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Safety: Do not delete last active owner
        if ($user->isOwnerOrSuperadmin()) {
            $activeOwners = User::whereRaw('LOWER(role) IN (?, ?, ?)', ['owner', 'superadmin', 'admin'])->count();
            if ($activeOwners <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus satu-satunya akun Owner/Superadmin sistem.',
                ], 422);
            }
        }

        $userName = $user->name;
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "Akun pengguna {$userName} berhasil dihapus dari sistem.",
        ]);
    }
}
