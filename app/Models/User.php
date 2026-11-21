<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'nik',
        'role',
        'pin',
        'branch',
        'assigned_branches',
        'is_active',
        'password',
    ];

    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'assigned_branches' => 'array',
        ];
    }

    public function isSupervisorOrAdmin(): bool
    {
        $role = strtolower($this->role ?? '');
        return in_array($role, ['supervisor', 'admin', 'manager', 'owner', 'superadmin', 'spv']);
    }

    public function isOwner(): bool
    {
        return $this->isOwnerOrSuperadmin();
    }

    public function isOwnerOrSuperadmin(): bool
    {
        $role = strtolower($this->role ?? '');
        return in_array($role, ['owner', 'superadmin', 'admin']);
    }

    public function isSupervisorOrManager(): bool
    {
        $role = strtolower($this->role ?? '');
        return in_array($role, ['supervisor', 'manager', 'owner', 'superadmin', 'admin', 'spv']);
    }

    public function isKasir(): bool
    {
        $role = strtolower($this->role ?? '');
        return str_contains($role, 'kasir') || $role === 'cashier';
    }

    public function verifyPin(string $pin): bool
    {
        if (empty($this->pin)) {
            return false;
        }
        if (str_starts_with($this->pin, '$2y$') || str_starts_with($this->pin, '$2a$')) {
            return Hash::check($pin, $this->pin);
        }
        return (string) $this->pin === (string) $pin;
    }

    public function verifyPassword(string $password): bool
    {
        return Hash::check($password, $this->password) || $this->password === $password;
    }

    /**
     * Dapatkan daftar penugasan multi-cabang kasir/pengguna secara lengkap.
     */
    public function getAssignedBranchesList(): array
    {
        if (!empty($this->assigned_branches) && is_array($this->assigned_branches)) {
            return array_values($this->assigned_branches);
        }

        $branchString = trim($this->branch ?? '');
        if ($branchString === '') {
            return [
                [
                    'branch' => 'Pusat',
                    'status' => 'AKTIF',
                    'assignedAt' => $this->created_at ? $this->created_at->format('Y-m-d') : 'Otomatis'
                ]
            ];
        }

        $parts = array_filter(array_map('trim', explode(',', $branchString)));
        if (empty($parts)) {
            $parts = ['Pusat'];
        }

        $list = [];
        foreach ($parts as $p) {
            $list[] = [
                'branch' => $p,
                'status' => 'AKTIF',
                'assignedAt' => $this->created_at ? $this->created_at->format('Y-m-d') : 'Otomatis'
            ];
        }

        return $list;
    }

    /**
     * Dapatkan hanya nama-nama cabang yang berstatus AKTIF.
     */
    public function getActiveAssignedBranches(): array
    {
        $assignments = $this->getAssignedBranchesList();
        $actives = [];
        foreach ($assignments as $a) {
            $st = strtoupper($a['status'] ?? 'AKTIF');
            if ($st === 'AKTIF' && !empty($a['branch'])) {
                $actives[] = trim($a['branch']);
            }
        }
        return !empty($actives) ? array_values(array_unique($actives)) : [trim($this->branch ?? 'Pusat')];
    }

    /**
     * Sinkronkan relasi hak akses ke tabel user_location_access di database PostgreSQL.
     */
    public function syncLocationAccess(): void
    {
        try {
            $activeBranches = $this->getActiveAssignedBranches();
            $locationIds = [];
            foreach ($activeBranches as $branch) {
                $bLower = strtolower($branch);
                $loc = Location::where(function ($q) use ($bLower) {
                    if (str_contains($bLower, 'pusat')) {
                        $q->where('code', 'CAB-001')->orWhereRaw('LOWER(name) LIKE ?', ['%pusat%']);
                    } elseif (str_contains($bLower, 'cabang a') || str_contains($bLower, 'sektor a') || str_contains($bLower, 'cabang 1')) {
                        $q->where('code', 'CAB-002')->orWhereRaw('LOWER(name) LIKE ?', ['%cabang a%']);
                    } elseif (str_contains($bLower, 'cabang b') || str_contains($bLower, 'sektor b') || str_contains($bLower, 'cabang 2')) {
                        $q->where('code', 'CAB-003')->orWhereRaw('LOWER(name) LIKE ?', ['%cabang b%']);
                    } else {
                        $q->whereRaw('LOWER(name) = ?', [$bLower]);
                    }
                })->first();

                if ($loc) {
                    $locationIds[] = $loc->id;
                }
            }

            DB::table('user_location_access')
                ->where('user_id', $this->id)
                ->delete();

            $uniqueLocIds = array_unique($locationIds);
            foreach ($uniqueLocIds as $lId) {
                DB::table('user_location_access')->insert([
                    'user_id' => $this->id,
                    'location_id' => $lId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to sync user_location_access: ' . $e->getMessage());
        }
    }
}
