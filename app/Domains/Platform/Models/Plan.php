<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'monthly_price_per_user',
        'yearly_price_per_user',
        'currency',
        'billing_cycle',
        'max_users',
        'max_storage_mb',
        'trial_days',
        'features',
        'is_demo',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'monthly_price_per_user' => 'integer',
            'yearly_price_per_user' => 'integer',
            'max_users' => 'integer',
            'max_storage_mb' => 'integer',
            'trial_days' => 'integer',
            'features' => 'array',
            'is_demo' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(\App\Models\Tenant::class);
    }
}
