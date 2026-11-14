<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;

/** Deductor details printed on 26Q and Form 16A (TAN, responsible person). */
class TdsConfiguration extends BaseModel
{
    protected $table = 'tds_configurations';

    protected $fillable = [
        'tenant_id', 'company_id', 'tan', 'deductor_type', 'deductor_name', 'deductor_pan',
        'responsible_person_name', 'responsible_person_designation', 'responsible_person_pan',
        'address', 'city', 'state', 'pincode', 'email', 'phone',
    ];

    public static function current(): ?self
    {
        return static::query()->orderByRaw('company_id is null')->first();
    }
}
