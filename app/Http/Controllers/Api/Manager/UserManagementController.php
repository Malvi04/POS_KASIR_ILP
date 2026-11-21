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

        // Calculate role counts in a single SQL aggregate (or reuse $users when unfiltered)
        $isUnfiltered = !$request->filled('role') && !$request->filled('branch') && !$request->filled('status') && !$request->filled('search');
        $countSource = $isUnfiltered ? $users : User::select(['role', 'is_active'])->get();
        $counts = [
            'total' => $countSource->count(),
            'active' => $countSource->where('is_active', true)->count(),
            'kasir' => $countSource->filter(fn($u) => $u->isKasir())->count(),
            'supervisor' => $countSource->filter(fn($u) => in_array(strtolower($u->role), ['supervisor', 'spv']))->count(),
            'manager' => $countSource->filter(fn($u) => strtolower($u->role) === 'manager')->count(),
            'owner' => $countSource->filter(fn($u) => $u->isOwnerOrSuperadmin())->count(),
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
                'branch' => $u->branch ?? '',
                'assigned_branches' => $u->getAssignedBranchesList(),
                'is_active' => (bool) $u->is_active,
                'pin' => $u->pin ?? '',
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
            'branch' => 'required|string|max:200',
            'assigned_branches' => 'nullable|array',
            'password' => 'required|string|min:4',
            'is_active' => 'nullable|boolean',
        ], [
            'pin.size' => 'PIN harus berupa 6 digit angka.',
            'pin.regex' => 'PIN hanya boleh berisi angka numerik.',
            'username.unique' => 'Username ini sudah digunakan.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'nik.unique' => 'NIK ini sudah terdaftar pada pengguna lain.',
        ]);

        $assignedBranches = $validated['assigned_branches'] ?? null;
        if (empty($assignedBranches)) {
            $assignedBranches = [
                [
                    'branch' => $validated['branch'],
                    'status' => 'AKTIF',
                    'assignedAt' => date('Y-m-d')
                ]
            ];
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'nik' => $validated['nik'] ?? ('USR-' . time()),
            'role' => $validated['role'],
            'pin' => !empty($validated['pin']) ? $validated['pin'] : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'branch' => $validated['branch'],
            'assigned_branches' => $assignedBranches,
            'password' => Hash::make($validated['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->syncLocationAccess();

        $userData = $user->toArray();
        $userData['assigned_branches'] = $user->getAssignedBranchesList();

        return response()->json([
            'success' => true,
            'message' => "Pengguna {$user->name} ({$user->role}) berhasil didaftarkan.",
            'data' => $userData,
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
                'assigned_branches' => $user->getAssignedBranchesList(),
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
            'name' => 'sometimes|required|string|max:150',
            'username' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'nik' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'role' => 'sometimes|required|string|max:50',
            'pin' => 'nullable|string|size:6|regex:/^[0-9]+$/',
            'branch' => 'nullable|string|max:200',
            'assigned_branches' => 'nullable|array',
            'password' => 'nullable|string|min:4',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (isset($validated['username'])) $updateData['username'] = $validated['username'];
        if (isset($validated['email'])) $updateData['email'] = $validated['email'];
        if (array_key_exists('nik', $validated)) $updateData['nik'] = $validated['nik'] ?? $user->nik;
        if (isset($validated['role'])) $updateData['role'] = $validated['role'];
        if ($request->has('is_active')) $updateData['is_active'] = $request->boolean('is_active');

        // Handle Branch & Assigned Branches
        if ($request->has('assigned_branches')) {
            $assigned = $request->input('assigned_branches');
            if (is_array($assigned)) {
                $updateData['assigned_branches'] = array_values($assigned);

                // Recompute branch string based on active assignments
                $activeNames = [];
                foreach ($assigned as $item) {
                    $st = strtoupper($item['status'] ?? 'AKTIF');
                    if ($st === 'AKTIF' && !empty($item['branch'])) {
                        $activeNames[] = trim($item['branch']);
                    }
                }
                if (!empty($activeNames)) {
                    $updateData['branch'] = implode(', ', array_unique($activeNames));
                } elseif (!empty($validated['branch'])) {
                    $updateData['branch'] = $validated['branch'];
                }
            }
        } elseif (!empty($validated['branch'])) {
            $updateData['branch'] = $validated['branch'];
            // Sync assigned_branches array if branch changed
            $parts = array_filter(array_map('trim', explode(',', $validated['branch'])));
            $updateData['assigned_branches'] = array_map(function($p) {
                return ['branch' => $p, 'status' => 'AKTIF', 'assignedAt' => date('Y-m-d')];
            }, $parts);
        }

        if (!empty($validated['pin'])) {
            $updateData['pin'] = $validated['pin'];
        }

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);
        $user->refresh();
        $user->syncLocationAccess();

        $userData = $user->toArray();
        $userData['assigned_branches'] = $user->getAssignedBranchesList();

        return response()->json([
            'success' => true,
            'message' => "Data pengguna {$user->name} berhasil diperbarui di database.",
            'data' => $userData,
        ]);
    }

    /**
     * Update branch assignment specifically (Dedicated endpoint for Manager & Owner).
     */
    public function updateBranchAssignment(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'assigned_branches' => 'required|array',
            'branch' => 'nullable|string|max:200',
        ]);

        $assigned = $validated['assigned_branches'];
        $activeBranches = [];
        foreach ($assigned as $ab) {
            $status = strtoupper($ab['status'] ?? 'AKTIF');
            if ($status === 'AKTIF' && !empty($ab['branch'])) {
                $activeBranches[] = trim($ab['branch']);
            }
        }

        $branchString = !empty($activeBranches)
            ? implode(', ', array_unique($activeBranches))
            : ($validated['branch'] ?? ($assigned[0]['branch'] ?? 'Pusat'));

        $user->update([
            'assigned_branches' => array_values($assigned),
            'branch' => $branchString,
        ]);

        $user->refresh();
        $user->syncLocationAccess();

        return response()->json([
            'success' => true,
            'message' => "Penugasan cabang untuk {$user->name} berhasil disimpan ke database.",
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'nik' => $user->nik,
                'role' => $user->role,
                'branch' => $user->branch,
                'assigned_branches' => $user->getAssignedBranchesList(),
            ],
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
