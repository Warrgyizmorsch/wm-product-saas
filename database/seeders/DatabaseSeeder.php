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
        $tenantSlug = config('tenancy.local_fallback_slug') ?: 'demo';
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
            PlatformAdminSeeder::class,
            CurrencySeeder::class,
            CrmStatusMasterSeeder::class,
            AccountingChartOfAccountsSeeder::class,
            PaymentTermSeeder::class,
            InventoryMasterDemoSeeder::class,
            // Kept for future Poona Radiators demo usage:
            // PoonaRadiatorsProductSeeder::class,
            // PoonaRadiatorsProductionSeeder::class,

            TableManufacturingProductSeeder::class,
            TableManufacturingProductionSeeder::class,
            HrmsDemoSeeder::class,
        ]);

        // Same default masters a tenant created from Tenant Console gets, for every
        // tenant. Runs last so it reuses the demo company/branch instead of adding its own.
        foreach (Tenant::query()->when(config('tenancy.seed_only'), fn ($q, $slugs) => $q->whereIn('slug', $slugs))->orderBy('id')->get() as $each) {
            app(\App\Core\Tenant\TenantProvisioner::class)->provision($each);
        }

        // $this->callWith(ProjectsDemoSeeder::class, ['options' => ['wipe' => true]]);
    }
}
