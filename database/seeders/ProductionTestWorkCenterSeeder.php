<?php

namespace Database\Seeders;

use App\Domains\Production\Models\Machine;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionTestWorkCenterSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $tenant = Tenant::create([
                'name'   => 'Demo Tenant',
                'slug'   => 'warrgyizmorsch',
                'status' => 'active',
                'plan'   => 'enterprise',
            ]);
        }
        $tenantId = $tenant->id;

        $this->command?->info("Clearing Work Centers, Sections, Manual Productions, Calculations, and Machines tables...");

        Schema::disableForeignKeyConstraints();

        // 1. Tables related to Work Centers, Sections, Manual Production, Calculations, and Machines
        $tablesToReset = [
            'production_section_manual_production_employees',
            'production_section_manual_productions',
            'production_work_center_section_calculations',
            'production_work_center_calculations',
            'production_work_center_activity_logs',
            'production_external_worker_allocations',
            'production_work_center_employees',
            'production_work_center_sections',
            'production_work_centers',
            'production_machines',
        ];

        foreach ($tablesToReset as $table) {
            if (Schema::hasTable($table)) {
                if (DB::getDriverName() === 'sqlite') {
                    DB::table($table)->delete();
                    if (Schema::hasTable('sqlite_sequence')) {
                        DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
                    }
                } else {
                    DB::statement("TRUNCATE TABLE `{$table}`");
                    try {
                        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                    } catch (\Throwable $e) {
                        // In case table or driver does not support ALTER AUTO_INCREMENT
                    }
                }
                $this->command?->info("✓ Cleared data and reset auto-increment to initial state for `{$table}`");
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->command?->info("Seeding standalone machines with complete operating cost and energy profiles...");

        // 2. Only seed machines with complete details and section_id = null (user will assign them)
        $machines = [
            [
                'code'                    => 'MAC-5AX-01',
                'name'                    => 'DMG MORI 5-Axis VMC',
                'machine_type'            => 'VMC',
                'manufacturer'            => 'DMG MORI',
                'model_number'            => 'DMU 50 Gen 3',
                'capacity'                => 35.00,
                'energy_source'           => Machine::ENERGY_ELECTRICITY,
                'consumption_per_hour'    => 45.00,
                'energy_rate_per_unit'    => 0.20,
                'operating_cost_per_hour' => 9.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2025-01-15',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-VMC-02',
                'name'                    => 'Haas VF-4 3-Axis VMC',
                'machine_type'            => 'VMC',
                'manufacturer'            => 'Haas Automation',
                'model_number'            => 'VF-4SS',
                'capacity'                => 40.00,
                'energy_source'           => Machine::ENERGY_ELECTRICITY,
                'consumption_per_hour'    => 30.00,
                'energy_rate_per_unit'    => 0.20,
                'operating_cost_per_hour' => 6.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2025-02-10',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-PRS-100T',
                'name'                    => 'Amada 100-Ton Hydraulic Press Brake',
                'machine_type'            => 'Press Brake',
                'manufacturer'            => 'Amada',
                'model_number'            => 'HFE-M2 1003',
                'capacity'                => 60.00,
                'energy_source'           => Machine::ENERGY_ELECTRICITY,
                'consumption_per_hour'    => 35.00,
                'energy_rate_per_unit'    => 0.20,
                'operating_cost_per_hour' => 7.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2024-11-20',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-PCH-CNC',
                'name'                    => 'Trumpf CNC Turret Punch Press',
                'machine_type'            => 'Punch Press',
                'manufacturer'            => 'Trumpf',
                'model_number'            => 'TruPunch 2000',
                'capacity'                => 50.00,
                'energy_source'           => Machine::ENERGY_ELECTRICITY,
                'consumption_per_hour'    => 40.00,
                'energy_rate_per_unit'    => 0.20,
                'operating_cost_per_hour' => 8.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2024-12-05',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-PLS-DSL',
                'name'                    => 'Heavy Duty Diesel Plasma Cutter',
                'machine_type'            => 'Plasma Cutter',
                'manufacturer'            => 'Hypertherm',
                'model_number'            => 'Powermax 125 HD',
                'capacity'                => 25.00,
                'energy_source'           => Machine::ENERGY_DIESEL,
                'consumption_per_hour'    => 5.00,
                'energy_rate_per_unit'    => 1.20,
                'operating_cost_per_hour' => 6.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2025-03-01',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-WLD-TIG',
                'name'                    => 'Lincoln Electric Robotic TIG Cell',
                'machine_type'            => 'Welding Cell',
                'manufacturer'            => 'Lincoln Electric',
                'model_number'            => 'Power Wave S500',
                'capacity'                => 30.00,
                'energy_source'           => Machine::ENERGY_ELECTRICITY,
                'consumption_per_hour'    => 20.00,
                'energy_rate_per_unit'    => 0.20,
                'operating_cost_per_hour' => 4.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2025-01-28',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-SMT-LSR',
                'name'                    => 'High Precision Laser SMT Assembler',
                'machine_type'            => 'SMT Assembler',
                'manufacturer'            => 'Yamaha',
                'model_number'            => 'YSM20R High-Speed',
                'capacity'                => 80.00,
                'energy_source'           => Machine::ENERGY_ELECTRICITY,
                'consumption_per_hour'    => 50.00,
                'energy_rate_per_unit'    => 0.20,
                'operating_cost_per_hour' => 10.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2024-10-15',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-ASY-PNE',
                'name'                    => 'Pneumatic Robotic Screw Feeder',
                'machine_type'            => 'Assembly System',
                'manufacturer'            => 'Atlas Copco',
                'model_number'            => 'Tensor STB33',
                'capacity'                => 60.00,
                'energy_source'           => Machine::ENERGY_OTHERS,
                'consumption_per_hour'    => 1.00,
                'energy_rate_per_unit'    => 5.00,
                'operating_cost_per_hour' => 5.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2025-02-20',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-LAT-CNC',
                'name'                    => 'Mazak Quick Turn CNC Turning Center',
                'machine_type'            => 'CNC Lathe',
                'manufacturer'            => 'Mazak',
                'model_number'            => 'Quick Turn 250-MSY',
                'capacity'                => 45.00,
                'energy_source'           => Machine::ENERGY_ELECTRICITY,
                'consumption_per_hour'    => 25.00,
                'energy_rate_per_unit'    => 0.20,
                'operating_cost_per_hour' => 5.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2025-03-10',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-INJ-120',
                'name'                    => 'Engel Victory Hydraulic Injection Molding',
                'machine_type'            => 'Injection Molding',
                'manufacturer'            => 'Engel',
                'model_number'            => 'Victory 330/80',
                'capacity'                => 120.00,
                'energy_source'           => Machine::ENERGY_ELECTRICITY,
                'consumption_per_hour'    => 55.00,
                'energy_rate_per_unit'    => 0.20,
                'operating_cost_per_hour' => 11.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2024-09-01',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-GEN-PET',
                'name'                    => 'Honda Portable Petrol Backup Generator',
                'machine_type'            => 'Generator',
                'manufacturer'            => 'Honda Power Equipment',
                'model_number'            => 'EU7000is Inverter',
                'capacity'                => 20.00,
                'energy_source'           => Machine::ENERGY_PETROL,
                'consumption_per_hour'    => 3.50,
                'energy_rate_per_unit'    => 1.60,
                'operating_cost_per_hour' => 5.60,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2025-01-05',
                'maintenance_status'      => 'operational',
            ],
            [
                'code'                    => 'MAC-LSR-CUT',
                'name'                    => 'Bystronic ByStar Fiber Laser Cutter',
                'machine_type'            => 'Laser Cutter',
                'manufacturer'            => 'Bystronic',
                'model_number'            => 'ByStar Fiber 10kW',
                'capacity'                => 50.00,
                'energy_source'           => Machine::ENERGY_ELECTRICITY,
                'consumption_per_hour'    => 60.00,
                'energy_rate_per_unit'    => 0.20,
                'operating_cost_per_hour' => 12.00,
                'status'                  => Machine::STATUS_ACTIVE,
                'installation_date'       => '2024-11-12',
                'maintenance_status'      => 'operational',
            ],
        ];

        foreach ($machines as $data) {
            Machine::create(array_merge($data, [
                'tenant_id'  => $tenantId,
                'section_id' => null, // Standalone machine ready to be assigned to sections by user
            ]));
            $this->command?->info("✓ Seeded machine {$data['code']}: {$data['name']} (Capacity: {$data['capacity']}, Operating Cost: \${$data['operating_cost_per_hour']}/hr, Energy: {$data['energy_source']})");
        }

        $this->command?->info("✓ Seeding complete! Only `production_machines` populated. Work centers, sections, manual productions, and calculations remain clear for manual setup.");
    }
}
