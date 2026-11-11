<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    public const TYPE_WAREHOUSE = 'WAREHOUSE';
    public const TYPE_STORE = 'STORE';

    protected $guarded = [];
}
