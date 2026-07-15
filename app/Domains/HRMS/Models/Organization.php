<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class Organization extends BaseModel
{
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'email',
        'phone',
        'website',
        'subscription_plan',
        'status',
    ];

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Get-or-create the current tenant's default Organization. Never assume a
     * platform-wide id=1 — each tenant gets its own row via the tenant scope.
     */
    public static function currentDefault(): self
    {
        return static::firstOrCreate(
            ['slug' => 'default-organization'],
            [
                'name' => 'Default Organization',
                'subscription_plan' => 'enterprise',
                'status' => true,
            ]
        );
    }
}
