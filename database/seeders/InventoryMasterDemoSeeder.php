<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;

class InventoryMasterDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo')->first() ?? Tenant::first();

        if (!$tenant) {
            return;
        }

        // Seed Vendors
        $vendors = [
            ['name' => 'Acme Supplies Ltd', 'code' => 'VEND-ACME', 'email' => 'info@acme.com', 'phone' => '1234567890'],
            ['name' => 'Apex Trade Corp', 'code' => 'VEND-APEX', 'email' => 'sales@apex.com', 'phone' => '0987654321'],
            ['name' => 'Matrix Logistics', 'code' => 'VEND-MATRIX', 'email' => 'support@matrix.com', 'phone' => '1122334455'],
        ];

        foreach ($vendors as $v) {
            Vendor::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $v['name']],
                [
                    'code' => $v['code'],
                    'email' => $v['email'],
                    'phone' => $v['phone'],
                    'status' => 'active',
                ]
            );
        }

        // Seed Warehouses
        $warehouses = [
            ['name' => 'Main Warehouse', 'code' => 'WH-MAIN', 'address' => 'Building A, Industrial Area, Sector 62', 'is_default' => true],
            ['name' => 'Secondary Warehouse', 'code' => 'WH-SEC', 'address' => 'Plot 15, Logistics Park, Phase 2', 'is_default' => false],
        ];

        foreach ($warehouses as $wh) {
            Warehouse::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $wh['code']],
                [
                    'name' => $wh['name'],
                    'address' => $wh['address'],
                    'is_default' => $wh['is_default'],
                    'status' => 'active',
                ]
            );
        }
    }
}
