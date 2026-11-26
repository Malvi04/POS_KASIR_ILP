<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory, HasUuidV7;

    protected $fillable = [
        'uuid',
        'log_id',
        'user_id',
        'user_name',
        'user_role',
        'location',
        'category',
        'action_name',
        'description',
        'reference',
        'status',
        'source',
        'changes',
        'synced_at',
        'sync_error',
    ];

    protected $casts = [
        'changes' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Helper to quickly record an audit log
     */
    public static function record(array $data): self
    {
        if (empty($data['log_id'])) {
            $data['log_id'] = 'LOG-' . date('Ymd') . '-' . str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        }
        return static::create($data);
    }
}
