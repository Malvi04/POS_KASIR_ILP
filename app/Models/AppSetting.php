<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    use HasFactory;

    protected $table = 'app_settings';

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /**
     * Get a setting value by key with high-performance caching.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $record = static::where('key', $key)->first();
            return $record !== null ? $record->value : $default;
        });
    }

    /**
     * Set/update a setting value and invalidate cache immediately.
     */
    public static function set(string $key, mixed $value, string $group = 'general'): self
    {
        $record = static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value, 'group' => $group]
        );

        Cache::forget("setting_{$key}");
        Cache::forget('all_app_settings');

        return $record;
    }

    /**
     * Get all settings grouped.
     */
    public static function getAllGrouped(): array
    {
        return Cache::remember('all_app_settings', 3600, function () {
            $settings = static::all();
            $grouped = [];
            foreach ($settings as $s) {
                $grouped[$s->group][$s->key] = $s->value;
            }
            return $grouped;
        });
    }
}
