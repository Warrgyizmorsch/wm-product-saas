<?php

namespace App\Casts;

use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Rich-text column that is sanitized on write (new data) and on read
 * (rows stored before sanitizing existed), so views can print it with {!! !!}.
 */
class SafeHtml implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : HtmlSanitizer::clean((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : HtmlSanitizer::clean((string) $value);
    }
}
