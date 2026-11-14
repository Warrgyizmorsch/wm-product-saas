<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * ProductionE2EDemoSeeder
 *
 * Standalone Master Seeder for end-to-end production test data:
 * - Master Data: Products, Inventory, Shifts, Pay Grades, Employees, Work Centers, Sections, Machines, Batches, Staffing, Allocations, Calculations
 * - Engineering Data: Routings, Operations, Operation Materials, Approvals, BOMs, BOM Items, BOM Approvals
 * - Execution Data: Production Orders, Operations, Reservations, Requisitions, Issues, Batches, Receipts, Schedules, Schedule Operations, WIPs, WIP Transactions, Timelines
 *
 * NOTE: This seeder is deliberately isolated and is NOT called by DatabaseSeeder.
 * It is invoked by running the single-command scratch runner:
 * `php scratch_production/run_production_master_seed.php`
 */
class ProductionE2EDemoSeeder extends Seeder
{
    public function run(int $tenantId = 1): void
    {
        $this->command?->info("========================================================");
        $this->command?->info("  STARTING PRODUCTION E2E DEMO SEEDER (Tenant: {$tenantId})");
        $this->command?->info("========================================================");

        $this->callWith(TestProductionMasterDataSeeder::class, ['tenantId' => $tenantId]);
        $this->callWith(TestProductionEngineeringSeeder::class, ['tenantId' => $tenantId]);
        $this->callWith(TestProductionExecutionSeeder::class, ['tenantId' => $tenantId]);

        $this->command?->info("========================================================");
        $this->command?->info("  PRODUCTION E2E DEMO SEEDER COMPLETED SUCCESSFULLY!");
        $this->command?->info("========================================================");
    }
}
