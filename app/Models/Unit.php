<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasUuidV7;

    protected $fillable = ['uuid', 'name', 'symbol'];
}
