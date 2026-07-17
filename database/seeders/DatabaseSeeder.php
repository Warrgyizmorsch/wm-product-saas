<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenantSlug = config('tenancy.local_fallback_slug') ?: 'warrgyizmorsch';
        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $tenantSlug],
            [
                'name' => 'Demo Tenant',
                'domain' => null,
                'status' => Tenant::STATUS_ACTIVE,
                'plan' => Tenant::PLAN_ENTERPRISE,
                'subscription_status' => Tenant::SUBSCRIPTION_ACTIVE,
                'max_users' => 100,
                'max_storage_mb' => 10240,
                'plan_started_at' => now(),
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
                'settings' => [],
            ],
        );

        User::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => 'admin@example.com',
            ],
            [
                'name' => 'Demo Admin',
                'password' => 'password',
            ],
        );

        $this->call([
            RbacSeeder::class,
            InventoryMasterDemoSeeder::class,
            FurnitureManufacturingDemoSeeder::class,
            HrmsDemoSeeder::class,
            AccountingChartOfAccountsSeeder::class,
        ]);
    }
}
