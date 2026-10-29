<?php

namespace Database\Seeders;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
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

        $this->command?->info("Clearing Work Centers, Sections, Manual Productions, Allocations, Assignments, and Calculations...");

        Schema::disableForeignKeyConstraints();

        // 1. Reset tables (Clears all previous snapshots, manual productions, assignments, and machines)
        $tablesToReset = [
            'production_section_manual_production_employees',
            'production_section_manual_productions',
            'production_work_center_section_calculations',
            'production_work_center_calculations',
            'production_work_center_activity_logs',
            'production_external_worker_allocations',
            'production_work_center_employees',
            'production_machines',
            'production_work_center_sections',
            'production_work_centers',
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
                        // Driver / platform fallback
                    }
                }
                $this->command?->info("✓ Cleared table: `{$table}`");
            }
        }

        Schema::enableForeignKeyConstraints();

        // --------------------------------------------------------------------------
        // 2. Ensure Standard Production Shifts Exist
        // --------------------------------------------------------------------------
        $this->command?->info("Setting up active Production Shifts...");

        $morningShift = ProductionShift::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'SH-MORN'],
            [
                'name'             => 'Plant Morning Shift (06:00 - 14:30)',
                'start_time'       => '06:00:00',
                'end_time'         => '14:30:00',
                'break_minutes'    => 30,
                'overtime_allowed' => true,
                'active'           => true,
            ]
        );
        $morningShift->update(['active' => true, 'start_time' => '06:00:00', 'end_time' => '14:30:00', 'break_minutes' => 30]);

        $eveningShift = ProductionShift::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'SH-EVEN'],
            [
                'name'             => 'Plant Evening Shift (14:00 - 22:30)',
                'start_time'       => '14:00:00',
                'end_time'         => '22:30:00',
                'break_minutes'    => 30,
                'overtime_allowed' => true,
                'active'           => true,
            ]
        );
        $eveningShift->update(['active' => true, 'start_time' => '14:00:00', 'end_time' => '22:30:00', 'break_minutes' => 30]);

        $nightShift = ProductionShift::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'SH-NIGHT'],
            [
                'name'             => 'Plant Night Shift (22:00 - 06:30)',
                'start_time'       => '22:00:00',
                'end_time'         => '06:30:00',
                'break_minutes'    => 30,
                'overtime_allowed' => true,
                'active'           => true,
            ]
        );
        $nightShift->update(['active' => true, 'start_time' => '22:00:00', 'end_time' => '06:30:00', 'break_minutes' => 30]);

        $dayShift = ProductionShift::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'SH-DAY'],
            [
                'name'             => 'General Day Shift (09:00 - 17:30)',
                'start_time'       => '09:00:00',
                'end_time'         => '17:30:00',
                'break_minutes'    => 45,
                'overtime_allowed' => false,
                'active'           => true,
            ]
        );
        $dayShift->update(['active' => true, 'start_time' => '09:00:00', 'end_time' => '17:30:00', 'break_minutes' => 45]);

        // --------------------------------------------------------------------------
        // 3. Ensure External Worker Pay Grades Exist
        // --------------------------------------------------------------------------
        $this->command?->info("Setting up External Worker Pay Grades...");

        $payGradeA = ExternalWorkerPayGrade::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'PG-A'],
            [
                'name'        => 'Grade A - Master Craftsman',
                'hourly_rate' => 350.00,
                'description' => 'Certified Master Machinist and Robotic Cell Programmer',
                'active'      => true,
            ]
        );
        $payGradeA->update(['active' => true, 'hourly_rate' => 350.00]);

        $payGradeB = ExternalWorkerPayGrade::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'PG-B'],
            [
                'name'        => 'Grade B - Skilled Welder & Technician',
                'hourly_rate' => 220.00,
                'description' => 'ASME Certified Welder, CNC Operator, and Fixture Fitter',
                'active'      => true,
            ]
        );
        $payGradeB->update(['active' => true, 'hourly_rate' => 220.00]);

        $payGradeC = ExternalWorkerPayGrade::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'PG-C'],
            [
                'name'        => 'Grade C - Semi-Skilled Operator',
                'hourly_rate' => 150.00,
                'description' => 'Machine Loader, Sheet Metal Bender, and Deburring Specialist',
                'active'      => true,
            ]
        );
        $payGradeC->update(['active' => true, 'hourly_rate' => 150.00]);

        $payGradeD = ExternalWorkerPayGrade::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'PG-D'],
            [
                'name'        => 'Grade D - General Helper',
                'hourly_rate' => 100.00,
                'description' => 'Material Handler, Hand Assembly Assistant, and Packaging Crew',
                'active'      => true,
            ]
        );
        $payGradeD->update(['active' => true, 'hourly_rate' => 100.00]);

        // --------------------------------------------------------------------------
        // 4. Ensure Dedicated Internal HRMS Employees Exist with Clean Monthly CTC
        // --------------------------------------------------------------------------
        $this->command?->info("Ensuring Internal HRMS Employees with proper CTC Salaries...");

        $employeesData = [
            ['name' => 'Rajesh Sharma',    'salary' => 45000.00, 'role' => 'Lead CNC Machinist'],
            ['name' => 'Aarti Patel',      'salary' => 38000.00, 'role' => 'CNC Milling Specialist'],
            ['name' => 'Vikram Singhania', 'salary' => 52000.00, 'role' => 'Senior TIG Welder'],
            ['name' => 'Rohan Kulkarni',   'salary' => 42000.00, 'role' => 'Robotic Cell Tech'],
            ['name' => 'Devendra Verma',   'salary' => 39000.00, 'role' => 'Laser Cut Operator'],
            ['name' => 'Manish Gupta',     'salary' => 36000.00, 'role' => 'Turret Punch Operator'],
            ['name' => 'Sandeep Tiwari',   'salary' => 48000.00, 'role' => 'Press Brake Master'],
            ['name' => 'Sneha Rao',        'salary' => 60000.00, 'role' => 'SMT Line Engineer'],
            ['name' => 'Pooja Deshmukh',   'salary' => 44000.00, 'role' => 'Pneumatic System Tech'],
            ['name' => 'Arun Joshi',       'salary' => 55000.00, 'role' => 'Night Shift Supervisor'],
            ['name' => 'Kavita Nair',      'salary' => 34000.00, 'role' => 'Assembly Lead'],
            ['name' => 'Deepak Chauhan',   'salary' => 32000.00, 'role' => 'Precision Assembler'],
            ['name' => 'Suresh Reddy',     'salary' => 30000.00, 'role' => 'Packaging Specialist'],
            ['name' => 'Kiran Mehta',      'salary' => 40000.00, 'role' => 'Quality Inspector'],
        ];

        $seededEmployees = [];
        foreach ($employeesData as $idx => $empInfo) {
            $empCode = sprintf("EMP-PRD-%03d", $idx + 1);
            $emp = Employee::firstOrCreate(
                ['tenant_id' => $tenantId, 'employee_id' => $empCode],
                [
                    'full_name'       => $empInfo['name'],
                    'job_title'       => $empInfo['role'],
                    'current_salary'  => $empInfo['salary'],
                    'date_of_joining' => '2023-01-15',
                    'gender'          => in_array($idx, [1, 7, 8, 10]) ? 'Female' : 'Male',
                    'status'          => 1,
                ]
            );
            $emp->update([
                'full_name'      => $empInfo['name'],
                'current_salary' => $empInfo['salary'],
                'status'         => 1,
            ]);
            $seededEmployees[] = $emp;
        }

        // --------------------------------------------------------------------------
        // 5. Work Center 1: Precision Machining & Fabrication (WC-PMF-01)
        // --------------------------------------------------------------------------
        $this->command?->info("Creating Work Center 1: Precision Machining & Fabrication...");

        $wc1 = WorkCenter::create([
            'tenant_id'   => $tenantId,
            'name'        => 'Precision Machining & Fabrication',
            'code'        => 'WC-PMF-01',
            'description' => 'Heavy-duty multi-axis CNC milling, high-precision lathe turning, and robotic TIG welding cells.',
            'status'      => WorkCenter::STATUS_ACTIVE,
        ]);

        // Section 1.1: CNC Milling & High-Speed Machining (Machine Heavy)
        $sec1_1 = WorkCenterSection::create([
            'tenant_id'        => $tenantId,
            'work_center_id'   => $wc1->id,
            'name'             => 'CNC Milling & High-Speed Machining',
            'code'             => 'SEC-CNC-01',
            'description'      => 'Multi-axis high-speed machining centers operating two continuous production shifts.',
            'status'           => 'active',
            'assigned_shifts'  => [$morningShift->id, $eveningShift->id],
        ]);

        // Assign Machines to Section 1.1
        $m1 = Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => $sec1_1->id,
            'code'                    => 'MAC-5AX-01',
            'name'                    => 'DMG MORI 5-Axis VMC',
            'machine_type'            => 'VMC',
            'manufacturer'            => 'DMG MORI',
            'model_number'            => 'DMU 50 Gen 3',
            'capacity'                => 35.00,
            'energy_source'           => Machine::ENERGY_ELECTRICITY,
            'consumption_per_hour'    => 45.00,
            'energy_rate_per_unit'    => 0.20,
            'operating_cost_per_hour' => 9.00, // Exact match: 45 * 0.20 = 9.00
            'status'                  => Machine::STATUS_ACTIVE,
            'installation_date'       => '2025-01-15',
            'maintenance_status'      => 'operational',
        ]);

        $m2 = Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => $sec1_1->id,
            'code'                    => 'MAC-VMC-02',
            'name'                    => 'Haas VF-4 3-Axis VMC',
            'machine_type'            => 'VMC',
            'manufacturer'            => 'Haas Automation',
            'model_number'            => 'VF-4SS',
            'capacity'                => 40.00,
            'energy_source'           => Machine::ENERGY_ELECTRICITY,
            'consumption_per_hour'    => 30.00,
            'energy_rate_per_unit'    => 0.20,
            'operating_cost_per_hour' => 7.50, // Base energy 6.00 + 1.50 additional overhead
            'status'                  => Machine::STATUS_ACTIVE,
            'installation_date'       => '2025-02-10',
            'maintenance_status'      => 'operational',
        ]);

        $m3 = Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => $sec1_1->id,
            'code'                    => 'MAC-LAT-CNC',
            'name'                    => 'Mazak Quick Turn CNC Turning Center',
            'machine_type'            => 'CNC Lathe',
            'manufacturer'            => 'Mazak',
            'model_number'            => 'Quick Turn 250-MSY',
            'capacity'                => 45.00,
            'energy_source'           => Machine::ENERGY_ELECTRICITY,
            'consumption_per_hour'    => 25.00,
            'energy_rate_per_unit'    => 0.20,
            'operating_cost_per_hour' => 4.50, // Base energy 5.00 - 0.50 deductible
            'status'                  => Machine::STATUS_ACTIVE,
            'installation_date'       => '2025-03-10',
            'maintenance_status'      => 'operational',
        ]);

        // Assign Internal Employees to Section 1.1
        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc1->id,
            'section_id'     => $sec1_1->id,
            'employee_id'    => $seededEmployees[0]->id, // Rajesh Sharma (₹45,000/mo)
            'active'         => true,
        ]);
        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc1->id,
            'section_id'     => $sec1_1->id,
            'employee_id'    => $seededEmployees[1]->id, // Aarti Patel (₹38,000/mo)
            'active'         => true,
        ]);

        // Allocate External Contractors to Section 1.1
        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec1_1->id,
            'shift_id'                   => $morningShift->id,
            'pay_grade_id'               => $payGradeB->id,
            'number_of_external_workers' => 2,
            'active'                     => true,
        ]);
        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec1_1->id,
            'shift_id'                   => $eveningShift->id,
            'pay_grade_id'               => $payGradeC->id,
            'number_of_external_workers' => 2,
            'active'                     => true,
        ]);

        // Section 1.2: Robotic & TIG Welding Cell (Mixed: Machine + Manual Batches)
        $sec1_2 = WorkCenterSection::create([
            'tenant_id'        => $tenantId,
            'work_center_id'   => $wc1->id,
            'name'             => 'Robotic & TIG Welding Fabrication',
            'code'             => 'SEC-WLD-02',
            'description'      => 'Automated robotic welding cells integrated with custom manual jig-fitting stations.',
            'status'           => 'active',
            'assigned_shifts'  => [$morningShift->id, $eveningShift->id],
        ]);

        // Machine for Section 1.2
        Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => $sec1_2->id,
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
        ]);

        // Internal Employees for Section 1.2
        $assignWeld1 = WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc1->id,
            'section_id'     => $sec1_2->id,
            'employee_id'    => $seededEmployees[2]->id, // Vikram Singhania (₹52,000/mo)
            'active'         => true,
        ]);
        $assignWeld2 = WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc1->id,
            'section_id'     => $sec1_2->id,
            'employee_id'    => $seededEmployees[3]->id, // Rohan Kulkarni (₹42,000/mo)
            'active'         => true,
        ]);

        // Manual Production Batches for Section 1.2
        $manualBatch1 = SectionManualProduction::create([
            'tenant_id'         => $tenantId,
            'section_id'        => $sec1_2->id,
            'shift_id'          => $morningShift->id,
            'batch_name'        => 'Precision Frame Manual TIG Tack-Welding',
            'capacity_per_hour' => 15.00,
            'active'            => true,
        ]);
        $manualBatch1->employees()->attach($assignWeld1->id);

        $manualBatch2 = SectionManualProduction::create([
            'tenant_id'         => $tenantId,
            'section_id'        => $sec1_2->id,
            'shift_id'          => $eveningShift->id,
            'batch_name'        => 'Heavy Bracket Custom Fixture Assembly',
            'capacity_per_hour' => 12.00,
            'active'            => true,
        ]);
        $manualBatch2->employees()->attach($assignWeld2->id);

        // External allocations for Section 1.2 (linked to manual batches)
        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec1_2->id,
            'shift_id'                   => $morningShift->id,
            'pay_grade_id'               => $payGradeB->id,
            'manual_production_id'       => $manualBatch1->id,
            'number_of_external_workers' => 1,
            'active'                     => true,
        ]);
        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec1_2->id,
            'shift_id'                   => $eveningShift->id,
            'pay_grade_id'               => $payGradeC->id,
            'manual_production_id'       => $manualBatch2->id,
            'number_of_external_workers' => 2,
            'active'                     => true,
        ]);

        // --------------------------------------------------------------------------
        // 6. Work Center 2: Automated Sheet Metal & Forming (WC-SMF-02)
        // --------------------------------------------------------------------------
        $this->command?->info("Creating Work Center 2: Automated Sheet Metal & Forming...");

        $wc2 = WorkCenter::create([
            'tenant_id'   => $tenantId,
            'name'        => 'Automated Sheet Metal & Forming',
            'code'        => 'WC-SMF-02',
            'description' => 'Industrial laser cutting, heavy CNC turret punching, and hydraulic sheet bending press brakes.',
            'status'      => WorkCenter::STATUS_ACTIVE,
        ]);

        // Section 2.1: Heavy Cutting & Turret Punching (Alternative Fuels: Diesel + Electric)
        $sec2_1 = WorkCenterSection::create([
            'tenant_id'        => $tenantId,
            'work_center_id'   => $wc2->id,
            'name'             => 'Heavy Cutting & Turret Punching',
            'code'             => 'SEC-CUT-01',
            'description'      => 'High-power fiber laser profiling and hydraulic turret punch press stations.',
            'status'           => 'active',
            'assigned_shifts'  => [$morningShift->id, $eveningShift->id],
        ]);

        Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => $sec2_1->id,
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
        ]);

        Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => $sec2_1->id,
            'code'                    => 'MAC-PLS-DSL',
            'name'                    => 'Heavy Duty Diesel Plasma Cutter',
            'machine_type'            => 'Plasma Cutter',
            'manufacturer'            => 'Hypertherm',
            'model_number'            => 'Powermax 125 HD',
            'capacity'                => 25.00,
            'energy_source'           => Machine::ENERGY_DIESEL,
            'consumption_per_hour'    => 5.00,
            'energy_rate_per_unit'    => 1.20,
            'operating_cost_per_hour' => 7.00, // Base energy 6.00 + 1.00 additional maintenance
            'status'                  => Machine::STATUS_ACTIVE,
            'installation_date'       => '2025-03-01',
            'maintenance_status'      => 'operational',
        ]);

        Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => $sec2_1->id,
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
        ]);

        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc2->id,
            'section_id'     => $sec2_1->id,
            'employee_id'    => $seededEmployees[4]->id, // Devendra Verma (₹39,000/mo)
            'active'         => true,
        ]);
        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc2->id,
            'section_id'     => $sec2_1->id,
            'employee_id'    => $seededEmployees[5]->id, // Manish Gupta (₹36,000/mo)
            'active'         => true,
        ]);

        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec2_1->id,
            'shift_id'                   => $morningShift->id,
            'pay_grade_id'               => $payGradeC->id,
            'number_of_external_workers' => 3,
            'active'                     => true,
        ]);
        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec2_1->id,
            'shift_id'                   => $eveningShift->id,
            'pay_grade_id'               => $payGradeD->id,
            'number_of_external_workers' => 2,
            'active'                     => true,
        ]);

        // Section 2.2: Hydraulic Press & Sheet Bending (Single Shift: Day Shift)
        $sec2_2 = WorkCenterSection::create([
            'tenant_id'        => $tenantId,
            'work_center_id'   => $wc2->id,
            'name'             => 'Hydraulic Press & Sheet Bending',
            'code'             => 'SEC-PRS-02',
            'description'      => 'Precision CNC hydraulic press brake bending and plate edge flanging.',
            'status'           => 'active',
            'assigned_shifts'  => [$dayShift->id],
        ]);

        Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => $sec2_2->id,
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
        ]);

        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc2->id,
            'section_id'     => $sec2_2->id,
            'employee_id'    => $seededEmployees[6]->id, // Sandeep Tiwari (₹48,000/mo)
            'active'         => true,
        ]);

        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec2_2->id,
            'shift_id'                   => $dayShift->id,
            'pay_grade_id'               => $payGradeC->id,
            'number_of_external_workers' => 2,
            'active'                     => true,
        ]);

        // --------------------------------------------------------------------------
        // 7. Work Center 3: Final Assembly & Quality Packaging (WC-AQP-03)
        // --------------------------------------------------------------------------
        $this->command?->info("Creating Work Center 3: Final Assembly & Quality Packaging...");

        $wc3 = WorkCenter::create([
            'tenant_id'   => $tenantId,
            'name'        => 'Final Assembly & Quality Packaging',
            'code'        => 'WC-AQP-03',
            'description' => 'Automated electronic surface-mount placement, pneumatic screwdriving, and manual packaging lines.',
            'status'      => WorkCenter::STATUS_ACTIVE,
        ]);

        // Section 3.1: Electronic SMT & Robotic Assembly (3-Shift 24/7 Operation)
        $sec3_1 = WorkCenterSection::create([
            'tenant_id'        => $tenantId,
            'work_center_id'   => $wc3->id,
            'name'             => 'Electronic SMT & Robotic Assembly',
            'code'             => 'SEC-SMT-01',
            'description'      => 'High-speed automated surface-mount assembly and robotic screwdriving line.',
            'status'           => 'active',
            'assigned_shifts'  => [$morningShift->id, $eveningShift->id, $nightShift->id],
        ]);

        Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => $sec3_1->id,
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
        ]);

        Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => $sec3_1->id,
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
        ]);

        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc3->id,
            'section_id'     => $sec3_1->id,
            'employee_id'    => $seededEmployees[7]->id, // Sneha Rao (₹60,000/mo)
            'active'         => true,
        ]);
        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc3->id,
            'section_id'     => $sec3_1->id,
            'employee_id'    => $seededEmployees[8]->id, // Pooja Deshmukh (₹44,000/mo)
            'active'         => true,
        ]);
        WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc3->id,
            'section_id'     => $sec3_1->id,
            'employee_id'    => $seededEmployees[9]->id, // Arun Joshi (₹55,000/mo)
            'active'         => true,
        ]);

        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec3_1->id,
            'shift_id'                   => $morningShift->id,
            'pay_grade_id'               => $payGradeC->id,
            'number_of_external_workers' => 2,
            'active'                     => true,
        ]);
        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec3_1->id,
            'shift_id'                   => $eveningShift->id,
            'pay_grade_id'               => $payGradeC->id,
            'number_of_external_workers' => 2,
            'active'                     => true,
        ]);
        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec3_1->id,
            'shift_id'                   => $nightShift->id,
            'pay_grade_id'               => $payGradeB->id,
            'number_of_external_workers' => 2,
            'active'                     => true,
        ]);

        // Section 3.2: Manual Hand Assembly & Packaging Lines (100% Pure Manual Section)
        $sec3_2 = WorkCenterSection::create([
            'tenant_id'        => $tenantId,
            'work_center_id'   => $wc3->id,
            'name'             => 'Manual Hand Assembly & Packaging Lines',
            'code'             => 'SEC-ASY-02',
            'description'      => 'Dedicated manual assembly bench lines for hardware insertion, wiring harnesses, and outer protective packaging.',
            'status'           => 'active',
            'assigned_shifts'  => [$morningShift->id, $eveningShift->id],
        ]);

        $assignManual1 = WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc3->id,
            'section_id'     => $sec3_2->id,
            'employee_id'    => $seededEmployees[10]->id, // Kavita Nair (₹34,000/mo)
            'active'         => true,
        ]);
        $assignManual2 = WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc3->id,
            'section_id'     => $sec3_2->id,
            'employee_id'    => $seededEmployees[11]->id, // Deepak Chauhan (₹32,000/mo)
            'active'         => true,
        ]);
        $assignManual3 = WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $wc3->id,
            'section_id'     => $sec3_2->id,
            'employee_id'    => $seededEmployees[12]->id, // Suresh Reddy (₹30,000/mo)
            'active'         => true,
        ]);

        $batch3_1 = SectionManualProduction::create([
            'tenant_id'         => $tenantId,
            'section_id'        => $sec3_2->id,
            'shift_id'          => $morningShift->id,
            'batch_name'        => 'Precision Enclosure Final Hand Assembly',
            'capacity_per_hour' => 25.00,
            'active'            => true,
        ]);
        $batch3_1->employees()->attach([$assignManual1->id, $assignManual2->id]);

        $batch3_2 = SectionManualProduction::create([
            'tenant_id'         => $tenantId,
            'section_id'        => $sec3_2->id,
            'shift_id'          => $eveningShift->id,
            'batch_name'        => 'Quality Boxing, Labeling & Pallet Wrapping',
            'capacity_per_hour' => 30.00,
            'active'            => true,
        ]);
        $batch3_2->employees()->attach([$assignManual3->id]);

        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec3_2->id,
            'shift_id'                   => $morningShift->id,
            'pay_grade_id'               => $payGradeD->id,
            'manual_production_id'       => $batch3_1->id,
            'number_of_external_workers' => 3,
            'active'                     => true,
        ]);
        ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sec3_2->id,
            'shift_id'                   => $eveningShift->id,
            'pay_grade_id'               => $payGradeD->id,
            'manual_production_id'       => $batch3_2->id,
            'number_of_external_workers' => 4,
            'active'                     => true,
        ]);

        // --------------------------------------------------------------------------
        // 8. Standalone Machines (Unassigned - Available for UI Testing)
        // --------------------------------------------------------------------------
        $this->command?->info("Seeding standalone machines ready for UI testing...");

        Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => null, // Standalone
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
        ]);

        Machine::create([
            'tenant_id'               => $tenantId,
            'section_id'              => null, // Standalone
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
        ]);

        $this->command?->info("====================================================================");
        $this->command?->info("✓ TEST SEEDER COMPLETED SUCCESSFULLY!");
        $this->command?->info("  - 3 Work Centers created (WC-PMF-01, WC-SMF-02, WC-AQP-03)");
        $this->command?->info("  - 6 Distinct Sections created (Machine-heavy, Mixed, Pure manual, Day shift, 3-shift 24/7)");
        $this->command?->info("  - Machines, Employees, Shifts, External Allocations, and Manual Batches wired cleanly");
        $this->command?->info("  - 2 Standalone Machines ready for UI assignment testing");
        $this->command?->info("  - All previous snapshots cleared: ready for fresh calculation test in browser!");
        $this->command?->info("====================================================================");
    }
}
