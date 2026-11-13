<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    use HasFactory;

    protected $table = 'system_notifications';

    protected $fillable = [
        'role',
        'title',
        'message',
        'category',
        'icon',
        'color',
        'link',
        'reference_id',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Scope for a specific role or ALL.
     */
    public function scopeForRole($query, ?string $role)
    {
        if (!$role || strtoupper($role) === 'ALL') {
            return $query;
        }

        return $query->where(function ($q) use ($role) {
            $q->where('role', strtoupper($role))
              ->orWhere('role', 'ALL');
        });
    }

    /**
     * Scope unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
