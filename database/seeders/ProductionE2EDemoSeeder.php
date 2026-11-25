<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\LoadsTestProductionData;
use Illuminate\Database\Seeder;

/**
 * ProductionE2EDemoSeeder
 *
 * Standalone Master Seeder for end-to-end production test data:
 * - Master Data: Products, Inventory, Shifts, Pay Grades, Employees, Work Centers, Sections, Machines, Batches, Staffing, Allocations, Calculations
 * - Engineering Data: Routings, Operations, Operation Materials, Approvals, BOMs, BOM Items, BOM Approvals
 * - Execution Data: Production Orders, Operations, Reservations, Requisitions, Issues, Batches, Receipts, Schedules, Schedule Operations, WIPs, WIP Transactions, Timelines
 *
 * Called by DatabaseSeeder after AddTestMastersSeeder (CleanBeforeHrmsSeeder has emptied the production and inventory
 * tables by then, HrmsDemoSeeder has added the HR data). It can also be run on its own:
 * `php artisan db:seed --class=ProductionE2EDemoSeeder`, or through the scratch runner
 * `php scratch_production/run_production_master_seed.php`.
 *
 * The data lives in the three seeders below (one method per table, see their tables()). References to rows it does not
 * own — units, warehouses, shifts, accounts, HRMS rows — are natural keys ('uom' => 'Pcs', 'shift' => 'DAY' ...) looked up
 * by code, see Database\Seeders\Support\TestProductionLinks.
 */
class ProductionE2EDemoSeeder extends Seeder
{
    use LoadsTestProductionData;

    public function run(?int $tenantId = null): void
    {
        // no id given (db:seed): the fallback tenant of this installation
        $tenantId = $this->resolveTenantId($tenantId);

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
