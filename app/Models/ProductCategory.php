<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasUuidV7;

    protected $fillable = ['uuid', 'name'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category', 'name');
    }
}
