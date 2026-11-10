<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $fillable = [
        'account_code',
        'account_name',
        'account_type', // ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }
}
