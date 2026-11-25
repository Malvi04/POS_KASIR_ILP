<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuidV7
{
    /**
     * Automatically populate the `uuid` attribute with a time-ordered UUIDv7 on creation.
     */
    public static function bootHasUuidV7(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * Scope query by UUID column.
     */
    public function scopeWhereUuid(Builder $query, string $uuid): Builder
    {
        return $query->where($this->qualifyColumn('uuid'), $uuid);
    }
}
