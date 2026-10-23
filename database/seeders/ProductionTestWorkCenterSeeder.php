<?php

namespace Database\Seeders;

use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
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

        $this->command?->info("Cleaning Work Centers, Sections, and Pay Grades for Tenant #{$tenantId}...");

        Schema::disableForeignKeyConstraints();

        // 1. Unassign machines from sections so no foreign keys block truncation
        if (Schema::hasTable('production_machines')) {
            DB::table('production_machines')->update(['section_id' => null]);
        }

        // 2. Truncate tables to reset AUTO_INCREMENT IDs back to 1
        $tablesToReset = [
            'production_external_worker_allocations',
            'production_work_center_employees',
            'production_work_center_sections',
            'production_work_centers',
            'production_external_worker_pay_grades',
        ];

        foreach ($tablesToReset as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("TRUNCATE TABLE `{$table}`");
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->command?->info("Cleaned Work Centers, Sections, and Pay Grades. Existing employees & shifts preserved.");

        // ─── Resolve Existing Shifts (DO NOT CREATE NEW SHIFTS) ─────────────
        $existingShiftIds = ProductionShift::where('tenant_id', $tenantId)
            ->where('active', true)
            ->pluck('id')
            ->toArray();

        // Fallback to any shift if none active
        if (empty($existingShiftIds)) {
            $existingShiftIds = ProductionShift::where('tenant_id', $tenantId)->pluck('id')->toArray();
        }

        $shiftA = !empty($existingShiftIds) ? [$existingShiftIds[0]] : [];
        $shiftB = count($existingShiftIds) >= 2 ? [$existingShiftIds[1]] : $shiftA;
        $shiftAB = count($existingShiftIds) >= 2 
            ? [$existingShiftIds[0], $existingShiftIds[1]] 
            : $shiftA;

        // ─── Pay Grades (Create if not available) ───────────────────────────
        ExternalWorkerPayGrade::create([
            'tenant_id'   => $tenantId,
            'name'        => 'Grade A - Master Craftsman',
            'code'        => 'PG-MSTR',
            'hourly_rate' => 350.00,
            'description' => 'High precision CNC programming, toolmaking and fabrication.',
            'active'      => true,
        ]);

        ExternalWorkerPayGrade::create([
            'tenant_id'   => $tenantId,
            'name'        => 'Grade B - Skilled Welder & Technician',
            'code'        => 'PG-SKLD',
            'hourly_rate' => 220.00,
            'description' => 'Certified structural TIG/MIG welding and machine setup.',
            'active'      => true,
        ]);

        ExternalWorkerPayGrade::create([
            'tenant_id'   => $tenantId,
            'name'        => 'Grade C - Semi-Skilled Operator',
            'code'        => 'PG-SEMI',
            'hourly_rate' => 150.00,
            'description' => 'Mechanical sub-assembly and machine tending.',
            'active'      => true,
        ]);

        ExternalWorkerPayGrade::create([
            'tenant_id'   => $tenantId,
            'name'        => 'Grade D - General Helper',
            'code'        => 'PG-HLPR',
            'hourly_rate' => 100.00,
            'description' => 'Material staging, deburring, and shopfloor movement.',
            'active'      => true,
        ]);

        // ─── 5 Work Centers Configuration ────────────────────────────────────

        // 1. Work Center 1: NO SECTIONS (0 sections)
        WorkCenter::create([
            'tenant_id'        => $tenantId,
            'name'             => 'Primary CNC Machining Center',
            'code'             => 'WC-01',
            'work_center_type' => 'machining',
            'description'      => 'Standalone work center without any subdivided sections.',
            'location'         => 'Plant 1 - Bay A',
            'status'           => 'active',
            'overhead_cost'    => 150.00,
        ]);

        // 2. Work Center 2: TWO SECTIONS
        $wc2 = WorkCenter::create([
            'tenant_id'        => $tenantId,
            'name'             => 'Heavy Fabrication Center',
            'code'             => 'WC-02',
            'work_center_type' => 'fabrication',
            'description'      => 'Fabrication unit with cutting and welding sections.',
            'location'         => 'Plant 1 - Bay B',
            'status'           => 'active',
            'overhead_cost'    => 180.00,
        ]);

        WorkCenterSection::create([
            'tenant_id'                  => $tenantId,
            'work_center_id'             => $wc2->id,
            'name'                       => 'TIG/MIG Welding Section',
            'code'                       => 'SEC-02-A',
            'description'                => 'Argon shielded welding booths.',
            'status'                     => 'active',
            'supports_manual_production' => true,
            'manual_capacity_per_hour'   => 12.0,
            'assigned_shifts'            => $shiftAB,
        ]);

        WorkCenterSection::create([
            'tenant_id'                  => $tenantId,
            'work_center_id'             => $wc2->id,
            'name'                       => 'Structural Assembly Section',
            'code'                       => 'SEC-02-B',
            'description'                => 'Structural framing and alignment.',
            'status'                     => 'active',
            'supports_manual_production' => true,
            'manual_capacity_per_hour'   => 8.0,
            'assigned_shifts'            => $shiftA,
        ]);

        // 3. Work Center 3: THREE SECTIONS
        $wc3 = WorkCenter::create([
            'tenant_id'        => $tenantId,
            'name'             => 'Electronics & Sub-Assembly Center',
            'code'             => 'WC-03',
            'work_center_type' => 'assembly',
            'description'      => 'Multi-section modular assembly facility.',
            'location'         => 'Plant 2 - Bay A',
            'status'           => 'active',
            'overhead_cost'    => 120.00,
        ]);

        WorkCenterSection::create([
            'tenant_id'                  => $tenantId,
            'work_center_id'             => $wc3->id,
            'name'                       => 'PCB Prep & Harnessing Section',
            'code'                       => 'SEC-03-A',
            'description'                => 'Wiring harness and electronics staging.',
            'status'                     => 'active',
            'supports_manual_production' => true,
            'manual_capacity_per_hour'   => 20.0,
            'assigned_shifts'            => $shiftA,
        ]);

        WorkCenterSection::create([
            'tenant_id'                  => $tenantId,
            'work_center_id'             => $wc3->id,
            'name'                       => 'Mechanical Sub-Assembly Line',
            'code'                       => 'SEC-03-B',
            'description'                => 'Component level assembly and torquing.',
            'status'                     => 'active',
            'supports_manual_production' => true,
            'manual_capacity_per_hour'   => 15.0,
            'assigned_shifts'            => $shiftAB,
        ]);

        WorkCenterSection::create([
            'tenant_id'                  => $tenantId,
            'work_center_id'             => $wc3->id,
            'name'                       => 'Final Enclosure Boxing Section',
            'code'                       => 'SEC-03-C',
            'description'                => 'Chassis mounting and cover fitment.',
            'status'                     => 'active',
            'supports_manual_production' => true,
            'manual_capacity_per_hour'   => 10.0,
            'assigned_shifts'            => $shiftB,
        ]);

        // 4. Work Center 4: WITH MACHINE ASSIGNMENT
        $wc4 = WorkCenter::create([
            'tenant_id'        => $tenantId,
            'name'             => 'Precision CNC Milling Center',
            'code'             => 'WC-04',
            'work_center_type' => 'machining',
            'description'      => 'Machining work center with assigned CNC machines.',
            'location'         => 'Plant 2 - Bay B',
            'status'           => 'active',
            'overhead_cost'    => 200.00,
        ]);

        $sec4A = WorkCenterSection::create([
            'tenant_id'                  => $tenantId,
            'work_center_id'             => $wc4->id,
            'name'                       => '5-Axis CNC Milling Bay',
            'code'                       => 'SEC-04-A',
            'description'                => 'Complex 5-axis aerospace and precision parts.',
            'status'                     => 'active',
            'supports_manual_production' => false,
            'assigned_shifts'            => $shiftAB,
        ]);

        $sec4B = WorkCenterSection::create([
            'tenant_id'                  => $tenantId,
            'work_center_id'             => $wc4->id,
            'name'                       => 'Vertical Machining Bay',
            'code'                       => 'SEC-04-B',
            'description'                => 'High-speed VMC prismatics.',
            'status'                     => 'active',
            'supports_manual_production' => false,
            'assigned_shifts'            => $shiftA,
        ]);

        // Ensure machines exist and assign them to WC 4 sections
        $macVmc1 = Machine::updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'MAC-5AX-01'],
            ['name' => 'DMG MORI 5-Axis VMC', 'status' => Machine::STATUS_ACTIVE, 'section_id' => $sec4A->id]
        );
        $macVmc2 = Machine::updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'MAC-VMC-02'],
            ['name' => 'Haas VF-4 3-Axis VMC', 'status' => Machine::STATUS_ACTIVE, 'section_id' => $sec4B->id]
        );

        // 5. Work Center 5: WITH MACHINE ASSIGNMENT
        $wc5 = WorkCenter::create([
            'tenant_id'        => $tenantId,
            'name'             => 'Sheet Metal Punch & Press Center',
            'code'             => 'WC-05',
            'work_center_type' => 'fabrication',
            'description'      => 'Heavy stamping and hydraulic press operations with dedicated press machines.',
            'location'         => 'Plant 1 - Press Shop',
            'status'           => 'active',
            'overhead_cost'    => 160.00,
        ]);

        $sec5A = WorkCenterSection::create([
            'tenant_id'                  => $tenantId,
            'work_center_id'             => $wc5->id,
            'name'                       => 'Hydraulic Press & Bending Section',
            'code'                       => 'SEC-05-A',
            'description'                => 'CNC press brake and hydraulic deep drawing.',
            'status'                     => 'active',
            'supports_manual_production' => false,
            'assigned_shifts'            => $shiftAB,
        ]);

        $macPress1 = Machine::updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'MAC-PRS-100T'],
            ['name' => 'Amada 100-Ton Hydraulic Press Brake', 'status' => Machine::STATUS_ACTIVE, 'section_id' => $sec5A->id]
        );
        $macPunch1 = Machine::updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'MAC-PCH-CNC'],
            ['name' => 'Trumpf CNC Turret Punch Press', 'status' => Machine::STATUS_ACTIVE, 'section_id' => $sec5A->id]
        );

        $this->command?->info("✓ Successfully seeded 5 Work Centers (WC-01: 0 sec, WC-02: 2 sec, WC-03: 3 sec, WC-04: machines, WC-05: machines).");
        $this->command?->info("✓ 0 internal or external employees assigned. Ready for UI testing.");
    }
}
