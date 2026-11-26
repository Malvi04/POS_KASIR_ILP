<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosTerminal extends Model
{
    use HasUuidV7;

    protected $table = 'pos_terminals';

    protected $fillable = [
        'uuid',
        'branch_id',
        'terminal_code',
        'name',
        'api_token_hash',
        'is_active',
        'last_seen_at',
        'last_push_at',
        'last_pull_at',
    ];

    protected $hidden = [
        'api_token_hash',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_push_at' => 'datetime',
        'last_pull_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'branch_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'branch_id');
    }

    /**
     * Compute deterministic SHA-256 hex digest for a plain API token.
     */
    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Generate a cryptographically random plain API token formatted with branch & terminal prefix.
     */
    public static function generatePlainToken(string $branchCode = 'POS', string $terminalCode = 'KSR01'): string
    {
        $bSlug = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $branchCode) ?: 'pos');
        $tSlug = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $terminalCode) ?: 'ksr');

        return "pos_{$bSlug}_{$tSlug}_" . Str::random(44);
    }

    /**
     * Derive canonical short branch code (`PST`, `CBG1`, `CBG2`, etc.) from a Location record.
     */
    public static function canonicalBranchCode(?Location $location): string
    {
        if (!$location) {
            return 'PST';
        }

        $codeL = strtolower(trim((string) $location->code));
        $nameL = strtolower(trim((string) $location->name));

        if ($codeL === 'pst' || $codeL === 'cab-001' || str_contains($nameL, 'pusat')) {
            return 'PST';
        }
        if ($codeL === 'cbg1' || $codeL === 'cab-002' || str_contains($nameL, 'cabang a') || str_contains($nameL, 'cabang 1')) {
            return 'CBG1';
        }
        if ($codeL === 'cbg2' || $codeL === 'cab-003' || str_contains($nameL, 'cabang b') || str_contains($nameL, 'cabang 2')) {
            return 'CBG2';
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $location->code) ?: 'BR');
    }
}
