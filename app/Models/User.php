<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

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
        ];
    }

    public function isSupervisorOrAdmin(): bool
    {
        $role = strtolower($this->role ?? '');
        return in_array($role, ['supervisor', 'admin', 'manager', 'owner', 'superadmin', 'spv']);
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
        return (string) $this->pin === (string) $pin;
    }

    public function verifyPassword(string $password): bool
    {
        return Hash::check($password, $this->password) || $this->password === $password;
    }
}
