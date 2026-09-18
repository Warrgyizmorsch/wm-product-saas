<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately NOT tenant-scoped (extends plain Model, not BaseModel) — this
 * is provider-level configuration (e.g. the active payment gateway), the
 * same for every tenant, not a per-tenant row.
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
