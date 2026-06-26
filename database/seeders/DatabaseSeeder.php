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
        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Tenant',
                'domain' => null,
                'status' => 'active',
                'plan' => 'enterprise',
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
    }
}
