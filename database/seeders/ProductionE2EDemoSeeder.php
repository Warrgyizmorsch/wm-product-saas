<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\LoadsTestProductionData;
use Illuminate\Database\Seeder;

/**
 * ProductionE2EDemoSeeder
 *
 * Standalone Master Seeder for end-to-end production test data (docs/production_seeder_audit_2026-11-26.md):
 * - Master Data: Products, Shifts, Pay Grades, Employees, Work Centers, Sections, Machines, Batches, Staffing,
 *   Allocations; the calculation snapshots are made by the work-centre engine
 * - Quality: 30 quality plans (section / process / product, every status) through QualityPlanService
 * - Engineering Data: 12 routings (costed, QC plans linked, approved) and 17 BOMs (approved through the lifecycle)
 * - Subcontracting (Part C of the doc): 4 job-work vendors, 5 service products, outsourced operations in every mode
 *   (routings 010–012), the subcontract procurement setting
 * - Opening stock per product. No production orders: they are created in the UI.
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

        // order matters: the engine calculates the work centres (master), plans exist before routings link them,
        // child BOMs before parents; no production orders (docs/production_seeder_audit_2026-11-26.md)
        $this->callWith(TestProductionMasterDataSeeder::class, ['tenantId' => $tenantId]);
        $this->callWith(TestProductionQualitySeeder::class, ['tenantId' => $tenantId]);
        $this->callWith(TestProductionEngineeringSeeder::class, ['tenantId' => $tenantId]);
        $this->callWith(TestProductionStockSeeder::class, ['tenantId' => $tenantId]);

        $this->command?->info("========================================================");
        $this->command?->info("  PRODUCTION E2E DEMO SEEDER COMPLETED SUCCESSFULLY!");
        $this->command?->info("========================================================");
    }
}
