<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
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
    ];

    protected $casts = [
        'changes' => 'array',
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
