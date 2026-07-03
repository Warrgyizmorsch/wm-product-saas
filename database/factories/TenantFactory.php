<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'domain' => null,
            'billing_email' => fake()->companyEmail(),
            'status' => Tenant::STATUS_ACTIVE,
            'plan' => Tenant::PLAN_STARTER,
            'subscription_status' => Tenant::SUBSCRIPTION_ACTIVE,
            'max_users' => 10,
            'max_storage_mb' => 1024,
            'plan_started_at' => now(),
            'timezone' => 'UTC',
            'locale' => 'en',
            'settings' => [],
        ];
    }
}
