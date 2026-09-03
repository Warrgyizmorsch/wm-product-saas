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
        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (!$tenant) {
            return;
        }

        $company = \App\Domains\HRMS\Models\Company::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_name' => 'Warrgyizmorsch'],
            ['legal_name' => 'Warrgyizmorsch Pvt Ltd', 'status' => true]
        );

        $bu = \App\Domains\HRMS\Models\BusinessUnit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'TBU'],
            ['name' => 'Technology Business Unit', 'status' => true]
        );

        $branch = \App\Domains\HRMS\Models\Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'HQ'],
            ['business_unit_id' => $bu->id, 'name' => 'Headquarters', 'status' => true]
        );

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
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
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
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'name' => $wh['name'],
                    'address' => $wh['address'],
                    'is_default' => $wh['is_default'],
                    'status' => 'active',
                ]
            );
        }
    }
}
