<?php

namespace Database\Seeders;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\BomLifecycleService;
use App\Domains\Production\Services\CountdownCalculationEngine;
use App\Domains\Production\Services\RoutingLifecycleService;
use App\Domains\Production\Services\RoutingOperationCostCalculator;
use App\Domains\Production\Services\SectionCalculationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NonWovenPlantSeeder — Vishal Non-Woven: materials, work centres, routings and bills of materials.
 *
 * Built like the TEST production seeders: the data lives in this class (one private method per table, listed in
 * tables()), every reference is a code, units are Kg / Pcs by code, tenant / company / branch are looked up. What is
 * derived (work-centre and section calculation snapshots, routing operation costs, cost snapshots, approvals, the BOM
 * master list) is produced by the application's own services from that data, so it matches what the screens would create.
 *
 * Seeds, for the fallback tenant, in the Bangalore branch (BLR-TECH):
 *  - Materials: raw materials, semi-finished rolls, finished goods, scrap (23 products) with opening stock in 5 warehouses
 *  - Shifts, work centres, sections, machines, manual packing crews, external-worker pay grades, demo employees,
 *    staffing and external-worker allocations (what the work-centre cost and capacity figures stand on)
 *  - Routings with operations (approved, cost snapshot stamped) and BOMs with operation materials (approved)
 *
 * A client seeder: run it AFTER `php artisan db:seed` (the next db:seed wipes it again — CleanBeforeHrmsSeeder):
 *   php artisan db:seed --class=NonWovenPlantSeeder       or       php scratch_production/run_nonwoven_demo_seed.php
 * Needs the Pcs / Kg units and the Bangalore branch that db:seed creates. Money is INR = the base currency (config/currency.php).
 * To make a seeder for another client, copy this class and change the rows in the table methods.
 */
class NonWovenPlantSeeder extends Seeder
{
    /** The demo plant is the Bangalore branch; found by code, never by id. */
    public const DEMO_BRANCH_CODE = 'BLR-TECH';

    /** @var array<string, int> unit code => id, for the tenant being seeded */
    private array $uomIds = [];

    /** @var array<string, int> product sku => the unit of that product */
    private array $productUom = [];
    /** Columns of production_machines that hold money. */
    private const MACHINE_MONEY_COLUMNS = ['energy_rate_per_unit', 'operating_cost_per_hour'];

    public function run(?int $tenantId = null): void
    {
        $data = self::loadData();

        // tenant, company and branch are looked up (the fallback tenant, its first company, the Bangalore branch), never assumed
        $tenantId = $this->resolveTenantId($tenantId);
        $companyId = (int) (DB::table('companies')->where('tenant_id', $tenantId)->orderBy('id')->value('id')
            ?? throw new \RuntimeException("No company found for tenant {$tenantId}."));
        $branchId = (int) (DB::table('branches')->where('tenant_id', $tenantId)->where('code', self::DEMO_BRANCH_CODE)->value('id')
            ?? throw new \RuntimeException('Branch ' . self::DEMO_BRANCH_CODE . " not found for tenant {$tenantId}. HrmsDemoSeeder (db:seed) creates it."));
        $this->uomIds = DB::table('uoms')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();

        $rate = self::rateToBase($data['meta']['money_currency'] ?? null);
        // 1. Shifts
        $shiftsCount = $this->seedShifts($data['production_shifts'] ?? [], $tenantId);

        // 2. Warehouses (Branch 3)
        $warehousesCount = $this->seedWarehouses($data['warehouses'] ?? [], $tenantId, $branchId, $companyId);

        // 3. Products (Shared across branches, branch_id = null)
        $productsCount = $this->seedProducts($data['products'] ?? [], $tenantId, $companyId, $rate);
        $this->productUom = DB::table('products')->where('tenant_id', $tenantId)->pluck('uom_id', 'sku')->all();

        // 4. Opening Stock (Branch 3)
        $stockCount = $this->seedOpeningStock($data['opening_stock'] ?? [], $tenantId, $branchId, $companyId, $rate);

        // 5. Work Centers
        $wcCount = $this->seedWorkCenters($data['production_work_centers'] ?? [], $tenantId, $rate);

        // 6. Sections
        $secCount = $this->seedSections($data['production_work_center_sections'] ?? [], $tenantId);

        // 7. Machines & Section links
        $machinesCount = $this->seedMachines($data['production_machines'] ?? [], $tenantId, $rate);

        // 8. Manual Batches
        $batchesCount = $this->seedManualBatches($data['production_section_manual_productions'] ?? [], $tenantId);

        // 8b. External Worker Pay Grades
        $payGradesCount = $this->seedPayGrades($data['production_external_worker_pay_grades'] ?? [], $tenantId, $rate);

        // 8c. Dedicated Demo Employees (Branch 3)
        $empMap = $this->seedEmployees($data['employees'] ?? [], $tenantId, $companyId, $branchId, $rate);
        $employeesCount = count($empMap);

        // 8d. Work Center & Section Employees
        $wcEmployeesCount = $this->seedWorkCenterEmployees($data['production_work_center_employees'] ?? [], $tenantId, $empMap);

        // 8e. Manual Batch Employees
        $batchEmpCount = $this->seedManualBatchEmployees($data['production_section_manual_production_employees'] ?? [], $tenantId, $empMap);

        // 8f. External Worker Allocations
        $extAllocCount = $this->seedExternalWorkerAllocations($data['production_external_worker_allocations'] ?? [], $tenantId);

        // 9. Work Center Calculations (Full Snapshot)
        $calcsCount = $this->recalculateWorkCenters($data['production_work_centers'] ?? [], $tenantId);

        // 10. Routings
        $routingsCount = $this->seedRoutings($data['production_routings'] ?? [], $tenantId);

        // 11. Routing Operations (calculated & stamped)
        $opsCount = $this->seedRoutingOperations($data['production_routing_operations'] ?? [], $tenantId);

        // 11b. Approve Routings (generates cost_snapshot and RoutingApproval audit logs)
        $approvedRoutingsCount = $this->approveRoutings($data['production_routings'] ?? [], $tenantId);

        // 12. BOMs
        $bomsCount = $this->seedBoms($data['production_boms'] ?? [], $tenantId);

        // 13. BOM Items (Tier 1 Recipe)
        $bomItemsCount = $this->seedBomItems($data['production_routing_operation_materials'] ?? [], $tenantId);

        // 14. Routing Operation Materials (Tier 2 Allocations)
        $allocCount = $this->seedRoutingOperationMaterials($data['production_routing_operation_materials'] ?? [], $tenantId);

        // 14b. Approve BOMs (generates status=approved, ProductionBomApproval audit logs)
        $approvedBomsCount = $this->approveBoms($data['production_boms'] ?? [], $tenantId);

        $this->command?->info("NonWovenPlantSeeder completed for tenant {$tenantId}:");
        $this->command?->info("  Shifts: {$shiftsCount}, Warehouses: {$warehousesCount}, Products: {$productsCount}, Stock Rows: {$stockCount}");
        $this->command?->info("  Work Centers: {$wcCount}, Sections: {$secCount}, Machines: {$machinesCount}, Batches: {$batchesCount}");
        $this->command?->info("  Pay Grades: {$payGradesCount}, Employees: {$employeesCount}, Staff Links: {$wcEmployeesCount}, Batch Links: {$batchEmpCount}, Allocations: {$extAllocCount}");
        $this->command?->info("  Calculations: {$calcsCount}, Routings: {$routingsCount} (Approved: {$approvedRoutingsCount}), Operations: {$opsCount}");
        $this->command?->info("  BOMs: {$bomsCount} (Approved: {$approvedBomsCount}), BOM Items: {$bomItemsCount}, Material Allocations: {$allocCount}");
    }

    /** The plant as data (see tables()). */
    public static function loadData(): array
    {
        return (new self())->tables();
    }

    /**
     * Units of the data file's currency per 1 unit of the base currency (config/currency.php: every currency's rate is
     * relative to the base currency, INR = 1 while the base is INR). Value in base = value in the file / this rate.
     */
    public static function rateToBase(?string $currency): float
    {
        if (!$currency) {
            return 1.0;
        }

        $rate = (float) config("currency.currencies.{$currency}.rate", 0);

        return $rate > 0 ? $rate : 1.0;
    }

    private function resolveTenantId(?int $tenantId): int
    {
        if ($tenantId) {
            return $tenantId;
        }

        $tenant = \App\Models\Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? \App\Models\Tenant::where('slug', 'demo')->first()
            ?? \App\Models\Tenant::first();

        return (int) ($tenant?->id ?? throw new \RuntimeException('There is no tenant to seed into.'));
    }

    /** The id of this tenant's unit with that code (Kg, Pcs ...). */
    private function uomId(string $code): int
    {
        return $this->uomIds[$code]
            ?? throw new \RuntimeException("Unit of measure {$code} not found for this tenant (AddTestMastersSeeder, run by db:seed, adds Pcs, Mtr, Set and Kg).");
    }

    // ─── 1. SHIFTS ────────────────────────────────────────────────────────────

    private function seedShifts(array $rows, int $tenantId): int
    {
        $columns = Schema::getColumnListing('production_shifts');
        $count = 0;

        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            if (!$code) continue;

            $values = array_intersect_key($row, array_flip($columns));
            unset($values['id'], $values['tenant_id'], $values['code']);

            $values['updated_at'] = now();

            $key = ['tenant_id' => $tenantId, 'code' => $code];
            if (DB::table('production_shifts')->where($key)->exists()) {
                DB::table('production_shifts')->where($key)->update($values);
            } else {
                DB::table('production_shifts')->insert($key + $values + ['created_at' => now()]);
            }
            $count++;
        }

        return $count;
    }

    // ─── 2. WAREHOUSES ────────────────────────────────────────────────────────

    private function seedWarehouses(array $rows, int $tenantId, int $branchId, int $companyId): int
    {
        $columns = Schema::getColumnListing('warehouses');
        $count = 0;

        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            if (!$code) continue;

            $values = array_intersect_key($row, array_flip($columns));
            unset($values['id'], $values['tenant_id'], $values['code']);

            $values['branch_id'] = $branchId;
            $values['company_id'] = $companyId;
            $values['status'] = $row['status'] ?? 'active';
            $values['updated_at'] = now();

            $key = ['tenant_id' => $tenantId, 'code' => $code];
            if (DB::table('warehouses')->where($key)->exists()) {
                DB::table('warehouses')->where($key)->update($values);
            } else {
                DB::table('warehouses')->insert($key + $values + ['created_at' => now()]);
            }
            $count++;
        }

        return $count;
    }

    // ─── 3. PRODUCTS ──────────────────────────────────────────────────────────

    private function seedProducts(array $rows, int $tenantId, int $companyId, float $rate): int
    {
        $columns = Schema::getColumnListing('products');
        $count = 0;

        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            if (!$code) continue;

            $values = array_intersect_key($row, array_flip($columns));
            unset($values['id'], $values['tenant_id'], $values['sku']);

            if (isset($row['unit_cost'])) {
                $convertedCost = round((float) $row['unit_cost'] / $rate, 4);
                $values['unit_cost'] = $convertedCost;
                $values['cost_price'] = $convertedCost;
            }

            if (isset($row['selling_price'])) {
                $values['selling_price'] = round((float) $row['selling_price'] / $rate, 4);
            }

            $values['uom_id'] = $this->uomId($row['uom'] ?? throw new \RuntimeException("Product {$code} has no unit (uom)."));
            $values['sku'] = $code;
            $values['company_id'] = $companyId;
            $values['branch_id'] = null; // Shared across branches
            $values['status'] = $row['status'] ?? 'active';
            $values['updated_at'] = now();

            $key = ['tenant_id' => $tenantId, 'sku' => $code];
            if (DB::table('products')->where($key)->exists()) {
                DB::table('products')->where($key)->update($values);
            } else {
                DB::table('products')->insert($key + $values + ['created_at' => now()]);
            }
            $count++;
        }

        return $count;
    }

    // ─── 4. OPENING STOCK ─────────────────────────────────────────────────────

    private function seedOpeningStock(array $rows, int $tenantId, int $branchId, int $companyId, float $rate): int
    {
        $pMap = DB::table('products')->where('tenant_id', $tenantId)->pluck('id', 'sku')->all();
        $wMap = DB::table('warehouses')->where('tenant_id', $tenantId)->where('branch_id', $branchId)->pluck('id', 'code')->all();
        $count = 0;

        foreach ($rows as $row) {
            $productSku = $row['product_code'] ?? null;
            $whCode = $row['warehouse_code'] ?? null;
            $qty = (float) ($row['quantity'] ?? 0);
            $unitCostBase = round((float) ($row['unit_cost'] ?? 0) / $rate, 4);

            $productId = $pMap[$productSku] ?? null;
            $warehouseId = $wMap[$whCode] ?? null;

            if (!$productId) {
                throw new \RuntimeException("Product {$productSku} not found for opening stock");
            }
            if (!$warehouseId) {
                throw new \RuntimeException("Warehouse {$whCode} not found for opening stock in Branch {$branchId}");
            }

            $stockKey = [
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ];

            $totalCost = round($qty * $unitCostBase, 4);
            $stockValues = [
                'company_id' => $companyId,
                'quantity' => $qty,
                'available_qty' => $qty,
                'reserved_qty' => 0.00,
                'unit_cost' => $unitCostBase,
                'updated_at' => now(),
            ];

            if (DB::table('product_warehouse_stocks')->where($stockKey)->exists()) {
                DB::table('product_warehouse_stocks')->where($stockKey)->update($stockValues);
            } else {
                DB::table('product_warehouse_stocks')->insert($stockKey + $stockValues + ['created_at' => now()]);
            }

            // Ledger Stock Transaction
            $txExists = DB::table('stock_transactions')
                ->where('tenant_id', $tenantId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('reference_type', 'Opening Stock')
                ->exists();

            if (!$txExists && $qty > 0) {
                DB::table('stock_transactions')->insert([
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'type' => 'IN',
                    'quantity' => $qty,
                    'unit_cost' => $unitCostBase,
                    'total_value' => $totalCost,
                    'balance_qty' => $qty,
                    'reference_type' => 'Opening Stock',
                    'reference_id' => $productId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $count++;
        }

        return $count;
    }

    // ─── 5. WORK CENTERS ──────────────────────────────────────────────────────

    private function seedWorkCenters(array $rows, int $tenantId, float $rate): int
    {
        $columns = Schema::getColumnListing('production_work_centers');
        $count = 0;

        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            if (!$code) {
                throw new \RuntimeException("Work center row missing code");
            }

            $values = array_intersect_key($row, array_flip($columns));
            unset($values['id'], $values['tenant_id'], $values['code']);

            if (isset($row['overhead_cost'])) {
                $values['overhead_cost'] = round((float) $row['overhead_cost'] / $rate, 4);
            }

            $values['updated_at'] = now();

            $key = ['tenant_id' => $tenantId, 'code' => $code];
            if (DB::table('production_work_centers')->where($key)->exists()) {
                DB::table('production_work_centers')->where($key)->update($values);
            } else {
                DB::table('production_work_centers')->insert($key + $values + ['created_at' => now()]);
            }
            $count++;
        }

        return $count;
    }

    // ─── 6. SECTIONS ──────────────────────────────────────────────────────────

    private function seedSections(array $rows, int $tenantId): int
    {
        $columns = Schema::getColumnListing('production_work_center_sections');
        $wcMap = DB::table('production_work_centers')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $shiftsMap = DB::table('production_shifts')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $count = 0;

        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            $wcCode = $row['work_center_code'] ?? null;
            $wcId = $wcMap[$wcCode] ?? null;

            if (!$code || !$wcId) {
                throw new \RuntimeException("Section code {$code} or work center {$wcCode} invalid");
            }

            $values = array_intersect_key($row, array_flip($columns));
            unset($values['id'], $values['tenant_id'], $values['work_center_id'], $values['code']);

            $values['work_center_id'] = $wcId;
            $values['updated_at'] = now();

            // Map assigned shifts to array of shift IDs
            $shiftIds = [];
            if (isset($row['assigned_shifts']) && is_array($row['assigned_shifts'])) {
                foreach ($row['assigned_shifts'] as $sCode) {
                    if (isset($shiftsMap[$sCode])) {
                        $shiftIds[] = (int) $shiftsMap[$sCode];
                    } else {
                        throw new \RuntimeException("Shift {$sCode} not found for section {$code}");
                    }
                }
            }
            $values['assigned_shifts'] = json_encode(array_values($shiftIds));

            $key = ['tenant_id' => $tenantId, 'code' => $code];
            if (DB::table('production_work_center_sections')->where($key)->exists()) {
                DB::table('production_work_center_sections')->where($key)->update($values);
                $sectionId = DB::table('production_work_center_sections')->where($key)->value('id');
            } else {
                $sectionId = DB::table('production_work_center_sections')->insertGetId($key + $values + ['created_at' => now()]);
            }

            // Also keep legacy pivot table synced if present
            if (Schema::hasTable('production_work_center_section_shifts')) {
                DB::table('production_work_center_section_shifts')->where('section_id', $sectionId)->delete();
                foreach ($shiftIds as $sId) {
                    DB::table('production_work_center_section_shifts')->insert([
                        'section_id' => $sectionId,
                        'shift_id' => $sId,
                    ]);
                }
            }

            $count++;
        }

        return $count;
    }

    // ─── 7. MACHINES ──────────────────────────────────────────────────────────

    private function seedMachines(array $rows, int $tenantId, float $rate): int
    {
        $columns = Schema::getColumnListing('production_machines');
        $secMap = DB::table('production_work_center_sections')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $count = 0;

        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            if (!$code) {
                throw new \RuntimeException("Machine row missing code");
            }

            $values = array_intersect_key($row, array_flip($columns));
            unset($values['id'], $values['tenant_id'], $values['code']);

            foreach (self::MACHINE_MONEY_COLUMNS as $col) {
                if (isset($row[$col])) {
                    $values[$col] = round((float) $row[$col] / $rate, 4);
                }
            }

            if (isset($row['section_code'])) {
                if (!isset($secMap[$row['section_code']])) {
                    throw new \RuntimeException("Section {$row['section_code']} not found for machine {$code}");
                }
                $values['section_id'] = (int) $secMap[$row['section_code']];
            }

            $values['updated_at'] = now();
            $values['deleted_at'] = null;

            $key = ['tenant_id' => $tenantId, 'code' => $code];
            if (DB::table('production_machines')->where($key)->exists()) {
                DB::table('production_machines')->where($key)->update($values);
            } else {
                DB::table('production_machines')->insert($key + $values + ['created_at' => now()]);
            }
            $count++;
        }

        return $count;
    }

    // ─── 8. MANUAL BATCHES ────────────────────────────────────────────────────

    private function seedManualBatches(array $rows, int $tenantId): int
    {
        $columns = Schema::getColumnListing('production_section_manual_productions');
        $secMap = DB::table('production_work_center_sections')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $shiftsMap = DB::table('production_shifts')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();

        // Remove obsolete demo batches in machine sections (NW-SEC-RCY, NW-SEC-WRP, NW-SEC-BAG) so re-running clears old phantom rows
        $validBatchNames = array_column($rows, 'batch_name');
        $obsoleteBatches = DB::table('production_section_manual_productions')
            ->where('tenant_id', $tenantId)
            ->where('batch_name', 'like', 'NW-BAT-%')
            ->whereNotIn('batch_name', $validBatchNames)
            ->get();

        foreach ($obsoleteBatches as $obs) {
            DB::table('production_external_worker_allocations')
                ->where('tenant_id', $tenantId)
                ->where('manual_production_id', $obs->id)
                ->update(['manual_production_id' => null]);

            DB::table('production_section_manual_production_employees')
                ->where('manual_production_id', $obs->id)
                ->delete();

            DB::table('production_routing_operations')
                ->where('tenant_id', $tenantId)
                ->where('section_manual_production_id', $obs->id)
                ->update(['section_manual_production_id' => null]);

            DB::table('production_section_manual_productions')
                ->where('id', $obs->id)
                ->delete();
        }

        $count = 0;
        foreach ($rows as $row) {
            $batchName = $row['batch_name'] ?? null;
            $secCode = $row['section_code'] ?? null;
            $shiftCode = $row['shift_code'] ?? null;

            if (!$batchName) {
                throw new \RuntimeException("Manual batch row missing batch_name");
            }

            $secId = $secMap[$secCode] ?? null;
            if (!$secId) {
                throw new \RuntimeException("Section {$secCode} not found for manual batch {$batchName}");
            }

            $shiftId = $shiftsMap[$shiftCode] ?? null;
            if (!$shiftId) {
                throw new \RuntimeException("Shift {$shiftCode} not found for manual batch {$batchName}");
            }

            $values = array_intersect_key($row, array_flip($columns));
            unset($values['id'], $values['tenant_id'], $values['section_id'], $values['shift_id'], $values['batch_name']);

            $values['section_id'] = $secId;
            $values['shift_id'] = $shiftId;
            $values['updated_at'] = now();

            $key = ['tenant_id' => $tenantId, 'section_id' => $secId, 'batch_name' => $batchName];
            if (DB::table('production_section_manual_productions')->where($key)->exists()) {
                DB::table('production_section_manual_productions')->where($key)->update($values);
            } else {
                DB::table('production_section_manual_productions')->insert($key + $values + ['created_at' => now()]);
            }
            $count++;
        }

        return $count;
    }

    // ─── 8B. EXTERNAL WORKER PAY GRADES ───────────────────────────────────────

    private function seedPayGrades(array $rows, int $tenantId, float $rate): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            if (!$code) {
                throw new \RuntimeException("Pay grade row missing code");
            }

            $hourlyRateBase = isset($row['hourly_rate']) ? round((float) $row['hourly_rate'] / $rate, 4) : 1.0;

            DB::table('production_external_worker_pay_grades')->updateOrInsert([
                'tenant_id' => $tenantId,
                'code' => $code,
            ], [
                'name' => $row['name'],
                'hourly_rate' => $hourlyRateBase,
                'description' => $row['description'] ?? null,
                'active' => $row['active'] ?? 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    // ─── 8C. DEDICATED DEMO EMPLOYEES (BRANCH 3) ──────────────────────────────

    private function seedEmployees(array $rows, int $tenantId, int $companyId, int $branchId, float $rate): array
    {
        $shiftMap = DB::table('production_shifts')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $empMap = [];

        // the production department and its designations (found by name; nothing is invented when HRMS data is not there)
        $deptId = DB::table('departments')->where('tenant_id', $tenantId)->where('name', \Database\Seeders\Support\TestProductionLinks::EMPLOYEE_DEPARTMENT)->value('id');
        $payGroupId = DB::table('pay_groups')->where('tenant_id', $tenantId)->where('name', 'Operations & Staff Pay Group')->value('id');
        $leavePlanId = DB::table('leave_plans')->where('tenant_id', $tenantId)->where('name', 'Standard Corporate Leave Policy')->value('id');
        $designations = new \Database\Seeders\Support\TestProductionLinks($tenantId);
        if ($deptId) {
            $designations->ensureDesignations($rows);
        }

        foreach ($rows as $row) {
            $code = $row['employee_code'] ?? null;
            if (!$code) {
                throw new \RuntimeException("Employee row missing employee_code");
            }

            $shiftCode = $row['shift_code'] ?? null;
            $shiftId = $shiftMap[$shiftCode] ?? null;
            if (!$shiftId) {
                throw new \RuntimeException("Shift {$shiftCode} not found for employee {$code}");
            }

            $monthlyInr = (float) ($row['monthly_salary_inr'] ?? 0);
            $annualInr = $monthlyInr * 12;
            $annualBase = round($annualInr / $rate, 4);

            $emp = Employee::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenantId,
                'employee_id' => $code,
            ], [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'full_name' => $row['full_name'],
                'job_title' => $row['job_title'],
                'shift_id' => $shiftId,
                'current_salary' => $annualBase,
                'status' => 1,
                'employment_type' => 'Full-time',
                'employee_stage' => 'Confirmed',
                'date_of_joining' => now()->subMonths(6)->toDateString(),
            ]);

            DB::table('employees')->where('id', $emp->id)->update([
                'department_id' => $deptId,
                'designation_id' => $deptId ? DB::table('designations')->where('department_id', $deptId)->where('name', $designations->designationFor((string) $row['job_title'])['name'])->value('id') : null,
                'pay_group_id' => $payGroupId,
                'leave_plan_id' => $leavePlanId,
            ]);

            $empMap[$code] = $emp->id;        }

        return $empMap;
    }

    // ─── 8D. WORK CENTER & SECTION EMPLOYEES ──────────────────────────────────

    private function seedWorkCenterEmployees(array $rows, int $tenantId, array $empMap): int
    {
        $wcMap = DB::table('production_work_centers')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $secMap = DB::table('production_work_center_sections')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();

        // Clean stale assignments for demo work centers before inserting
        $demoWcCodes = ['NW-WC-EXT', 'NW-WC-CNV', 'NW-WC-FAB'];
        $demoWcIds = array_values(array_intersect_key($wcMap, array_flip($demoWcCodes)));
        if (!empty($demoWcIds)) {
            DB::table('production_work_center_employees')
                ->where('tenant_id', $tenantId)
                ->whereIn('work_center_id', $demoWcIds)
                ->delete();
        }

        $count = 0;
        foreach ($rows as $row) {
            $wcCode = $row['work_center_code'] ?? null;
            $secCode = $row['section_code'] ?? null;
            $empCode = $row['employee_code'] ?? null;

            $wcId = $wcMap[$wcCode] ?? null;
            if (!$wcId) {
                throw new \RuntimeException("Work center {$wcCode} not found for employee assignment {$empCode}");
            }

            $secId = !empty($secCode) ? ($secMap[$secCode] ?? null) : null;
            if (!empty($secCode) && !$secId) {
                throw new \RuntimeException("Section {$secCode} not found for employee assignment {$empCode}");
            }

            $empId = $empMap[$empCode] ?? null;
            if (!$empId) {
                throw new \RuntimeException("Employee {$empCode} not found in employee map");
            }

            DB::table('production_work_center_employees')->insert([
                'tenant_id' => $tenantId,
                'work_center_id' => $wcId,
                'section_id' => $secId,
                'employee_id' => $empId,
                'active' => $row['active'] ?? 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    // ─── 8E. MANUAL BATCH EMPLOYEES ───────────────────────────────────────────

    private function seedManualBatchEmployees(array $rows, int $tenantId, array $empMap): int
    {
        // First delete stale batch employee links for all demo batches
        $demoBatchIds = DB::table('production_section_manual_productions')
            ->where('tenant_id', $tenantId)
            ->where('batch_name', 'like', 'NW-BAT-%')
            ->pluck('id')
            ->all();

        if (!empty($demoBatchIds)) {
            DB::table('production_section_manual_production_employees')
                ->whereIn('manual_production_id', $demoBatchIds)
                ->delete();
        }

        $count = 0;
        foreach ($rows as $row) {
            $batchName = $row['batch_name'] ?? null;
            $empCode = $row['employee_code'] ?? null;

            $batch = DB::table('production_section_manual_productions')
                ->where('tenant_id', $tenantId)
                ->where('batch_name', $batchName)
                ->first();

            if (!$batch) {
                throw new \RuntimeException("Batch {$batchName} not found when linking employee {$empCode}");
            }

            $empId = $empMap[$empCode] ?? null;
            if (!$empId) {
                throw new \RuntimeException("Employee {$empCode} not found when linking to batch {$batchName}");
            }

            // Must match employee_id AND section_id = batch->section_id!
            $wcEmpId = DB::table('production_work_center_employees')
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $empId)
                ->where('section_id', $batch->section_id)
                ->value('id');

            if (!$wcEmpId) {
                throw new \RuntimeException("Work center employee record not found for employee {$empCode} in section {$batch->section_id} of batch {$batchName}");
            }

            DB::table('production_section_manual_production_employees')->insert([
                'manual_production_id' => $batch->id,
                'work_center_employee_id' => $wcEmpId,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    // ─── 8F. EXTERNAL WORKER ALLOCATIONS ──────────────────────────────────────

    private function seedExternalWorkerAllocations(array $rows, int $tenantId): int
    {
        $secMap = DB::table('production_work_center_sections')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $shiftMap = DB::table('production_shifts')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $pgMap = DB::table('production_external_worker_pay_grades')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $batchMap = DB::table('production_section_manual_productions')->where('tenant_id', $tenantId)->pluck('id', 'batch_name')->all();

        // Clear stale allocations for demo sections
        $demoSecCodes = [
            'NW-SEC-EXT', 'NW-SEC-MB', 'NW-SEC-RCY', 'NW-SEC-SLT', 'NW-SEC-PRT',
            'NW-SEC-WRP', 'NW-SEC-BAG', 'NW-SEC-MSK', 'NW-SEC-PCK'
        ];
        $demoSecIds = array_values(array_intersect_key($secMap, array_flip($demoSecCodes)));
        if (!empty($demoSecIds)) {
            DB::table('production_external_worker_allocations')
                ->where('tenant_id', $tenantId)
                ->whereIn('section_id', $demoSecIds)
                ->delete();
        }

        $count = 0;
        foreach ($rows as $row) {
            $secCode = $row['section_code'] ?? null;
            $shiftCode = $row['shift_code'] ?? null;
            $pgCode = $row['pay_grade_code'] ?? null;
            $batchName = $row['batch_name'] ?? null;

            $secId = $secMap[$secCode] ?? null;
            if (!$secId) {
                throw new \RuntimeException("Section {$secCode} not found for external worker allocation");
            }

            $shiftId = $shiftMap[$shiftCode] ?? null;
            if (!$shiftId) {
                throw new \RuntimeException("Shift {$shiftCode} not found for external worker allocation");
            }

            $pgId = $pgMap[$pgCode] ?? null;
            if (!$pgId) {
                throw new \RuntimeException("Pay grade {$pgCode} not found for external worker allocation");
            }

            $batchId = !empty($batchName) ? ($batchMap[$batchName] ?? null) : null;
            if (!empty($batchName) && !$batchId) {
                throw new \RuntimeException("Batch {$batchName} not found for external worker allocation");
            }

            DB::table('production_external_worker_allocations')->insert([
                'tenant_id' => $tenantId,
                'section_id' => $secId,
                'shift_id' => $shiftId,
                'pay_grade_id' => $pgId,
                'manual_production_id' => $batchId,
                'number_of_external_workers' => (int) ($row['workers_count'] ?? 1),
                'active' => $row['active'] ?? 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    // ─── 9. WORK CENTER RECALCULATIONS ────────────────────────────────────────

    private function recalculateWorkCenters(array $rows, int $tenantId): int
    {
        $codes = array_column($rows, 'code');
        $wcs = WorkCenter::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('code', $codes)
            ->get();

        $engine = app(CountdownCalculationEngine::class);
        $count = 0;

        foreach ($wcs as $wc) {
            try {
                $engine->recalculateNow($wc, 'manual', CountdownCalculationEngine::SCOPE_ALL);
                $count++;
            } catch (\Throwable $e) {
                throw new \RuntimeException("Calculation failed for Work Center {$wc->code}: " . $e->getMessage(), 0, $e);
            }
        }

        return $count;
    }

    // ─── 10. ROUTINGS ──────────────────────────────────────────────────────────

    private function seedRoutings(array $rows, int $tenantId): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $routing = Routing::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('routing_number', $row['routing_number'])
                ->first();

            $routingData = [
                'name' => $row['name'],
                'version' => $row['version'] ?? '1.0.0',
                'revision' => 1,
                'is_default' => $row['is_default'] ?? 1,
                'status' => 'draft',
                'effective_from' => now()->toDateString(),
                'created_by' => 1,
                'deleted_at' => null,
            ];

            if ($routing) {
                if ($routing->trashed()) {
                    $routing->restore();
                }
                $routing->update($routingData);
            } else {
                Routing::withoutGlobalScopes()->create(array_merge([
                    'tenant_id' => $tenantId,
                    'routing_number' => $row['routing_number'],
                ], $routingData));
            }
            $count++;
        }

        return $count;
    }

    // ─── 11. ROUTING OPERATIONS ────────────────────────────────────────────────

    private function seedRoutingOperations(array $rows, int $tenantId): int
    {
        $calc = app(RoutingOperationCostCalculator::class);
        $sectionService = app(SectionCalculationService::class);
        $count = 0;

        // Group rows by routing_number to clean stale operations and handle chaining
        $rowsByRouting = [];
        foreach ($rows as $row) {
            $rowsByRouting[$row['routing_number']][] = $row;
        }

        foreach ($rowsByRouting as $routingNumber => $routingOps) {
            $routing = Routing::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('routing_number', $routingNumber)
                ->first();

            if (!$routing) {
                throw new \RuntimeException("Routing {$routingNumber} not found for operations seeding");
            }

            // Identify active sequences in this seed run
            $seedSequences = array_column($routingOps, 'sequence');

            // Find any stale operations in this routing that are not in the seed data
            $staleOps = RoutingOperation::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('routing_id', $routing->id)
                ->whereNotIn('sequence', $seedSequences)
                ->get();

            foreach ($staleOps as $staleOp) {
                // Delete ROM allocations pointing to stale op first (due to restrictOnDelete)
                DB::table('production_routing_operation_materials')
                    ->where('tenant_id', $tenantId)
                    ->where('routing_operation_id', $staleOp->id)
                    ->delete();

                $staleOp->forceDelete();
            }

            $createdOps = [];

            // First pass: create/update all operations with cost calculation & snapshots
            foreach ($routingOps as $row) {
                $section = WorkCenterSection::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('code', $row['section_code'])
                    ->first();

                if (!$section) {
                    throw new \RuntimeException("Section {$row['section_code']} not found for operation {$row['operation_number']} in routing {$routingNumber}");
                }

                $machineId = null;
                if (!empty($row['machine_code'])) {
                    $machineId = DB::table('production_machines')
                        ->where('tenant_id', $tenantId)
                        ->where('code', $row['machine_code'])
                        ->value('id');

                    if (!$machineId) {
                        throw new \RuntimeException("Machine {$row['machine_code']} not found for operation {$row['operation_number']} in routing {$routingNumber}");
                    }
                }

                $batchId = null;
                if (!empty($row['batch_name'])) {
                    $batchId = DB::table('production_section_manual_productions')
                        ->where('tenant_id', $tenantId)
                        ->where('batch_name', $row['batch_name'])
                        ->value('id');

                    if (!$batchId) {
                        throw new \RuntimeException("Batch {$row['batch_name']} not found for operation {$row['operation_number']} in routing {$routingNumber}");
                    }
                }

                $opData = [
                    'tenant_id' => $tenantId,
                    'routing_id' => $routing->id,
                    'sequence' => $row['sequence'],
                    'operation_number' => $row['operation_number'],
                    'name' => $row['name'],
                    'operation_type' => $row['operation_type'] ?? 'manufacturing',
                    'work_center_id' => $section->work_center_id,
                    'section_id' => $section->id,
                    'machine_id' => $machineId,
                    'section_manual_production_id' => $batchId,
                    'setup_time_minutes' => (float) ($row['setup_time_minutes'] ?? 0),
                    'setup_time_per_piece_minutes' => (float) ($row['setup_time_per_piece_minutes'] ?? 0),
                    'processing_time_minutes' => (float) ($row['processing_time_minutes'] ?? 0),
                    'expected_yield_percentage' => (float) ($row['expected_yield_percentage'] ?? 100),
                    'quality_required' => (bool) ($row['quality_required'] ?? false),
                    'deleted_at' => null,
                ];

                $resources = $sectionService->getSnapshotResourceCosts($section);
                $calcResult = $calc->calculate($opData, $resources);

                if ($calcResult['error'] !== null) {
                    throw new \RuntimeException("Cost calculation error for operation {$row['operation_number']} in {$routingNumber}: {$calcResult['error']}");
                }

                foreach (RoutingOperationCostCalculator::FIELDS as $field) {
                    $opData[$field] = $calcResult['figures'][$field] ?? 0;
                }
                $opData['resource_snapshot'] = $calc->buildResourceSnapshot($opData, $calcResult['resource'], $resources);
                $opData['section_calculation_id'] = $resources['section_calculation_id'] ?? null;
                $opData['section_snapshot_number'] = $resources['snapshot_number'] ?? null;
                $opData['work_center_calculation_id'] = $resources['work_center_calculation_id'] ?? null;
                $opData['costs_calculated_at'] = now();

                $existingOp = RoutingOperation::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('routing_id', $routing->id)
                    ->where('sequence', $row['sequence'])
                    ->first();

                if ($existingOp) {
                    if ($existingOp->trashed()) {
                        $existingOp->restore();
                    }
                    $existingOp->update($opData);
                    $op = $existingOp;
                } else {
                    $op = RoutingOperation::withoutGlobalScopes()->create($opData);
                }

                $createdOps[$row['operation_number']] = $op;
                $count++;
            }

            // Second pass: chain previous_operation_id
            foreach ($routingOps as $row) {
                if (!empty($row['previous_operation_number'])) {
                    $prevOp = $createdOps[$row['previous_operation_number']] ?? null;
                    if ($prevOp && isset($createdOps[$row['operation_number']])) {
                        $createdOps[$row['operation_number']]->update([
                            'previous_operation_id' => $prevOp->id,
                        ]);
                    }
                }
            }
        }

        return $count;
    }

    // ─── 11B. APPROVE ROUTINGS ────────────────────────────────────────────────

    private function approveRoutings(array $rows, int $tenantId): int
    {
        $lifecycle = app(RoutingLifecycleService::class);
        $count = 0;

        foreach ($rows as $row) {
            $routing = Routing::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('routing_number', $row['routing_number'])
                ->first();

            if (!$routing) {
                throw new \RuntimeException("Routing {$row['routing_number']} not found for approval");
            }

            $lifecycle->transitionOnApproval($routing, 1);
            $count++;
        }

        return $count;
    }

    // ─── 12. BOMS ─────────────────────────────────────────────────────────────

    private function seedBoms(array $rows, int $tenantId): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $product = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('sku', $row['product_code'])
                ->first();

            if (!$product) {
                throw new \RuntimeException("Product {$row['product_code']} not found for BOM {$row['bom_number']}");
            }

            $routing = null;
            if (!empty($row['routing_number'])) {
                $routing = Routing::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('routing_number', $row['routing_number'])
                    ->first();

                if (!$routing) {
                    throw new \RuntimeException("Routing {$row['routing_number']} not found for BOM {$row['bom_number']}");
                }
            }

            $bom = ProductionBom::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('bom_number', $row['bom_number'])
                ->first();

            $bomData = [
                'bom_name' => $row['bom_name'],
                'bom_type' => 'manufacturing',
                'usage_context' => 'manufacturing',
                'product_id' => $product->id,
                'routing_id' => $routing?->id,
                'base_quantity' => (float) ($row['base_quantity'] ?? 1.0),
                'base_uom_id' => $this->productUom[$row['product_code']] ?? throw new \RuntimeException("BOM {$row['bom_number']}: product {$row['product_code']} has no unit."),
                'version' => $row['version'] ?? '1.0.0',
                'revision' => 1,
                'status' => 'draft',
                'effective_date' => now()->toDateString(),
                'created_by' => 1,
                'deleted_at' => null,
            ];

            if ($bom) {
                if ($bom->trashed()) {
                    $bom->restore();
                }
                $bom->update($bomData);
            } else {
                ProductionBom::withoutGlobalScopes()->create(array_merge([
                    'tenant_id' => $tenantId,
                    'bom_number' => $row['bom_number'],
                ], $bomData));
            }

            $count++;
        }

        return $count;
    }

    // ─── 13. BOM ITEMS (TIER 1 RECIPE) ─────────────────────────────────────────

    /**
     * Tier 1 (one row per material per BOM) is not written by hand: it is derived from the operation allocations (Tier 2)
     * exactly like ProductionBomService::persistBomItems does when a BOM is saved in the UI — quantity = the primary
     * allocations, alternative_quantity = the alternative ones, gross = primary quantity with scrap, role primary /
     * alternative / mixed, the unit of the material.
     *
     * @param  array<int, array<string, mixed>>  $allocations  rows of production_routing_operation_materials in the data file
     */
    private function seedBomItems(array $allocations, int $tenantId): int
    {
        $bomIds = DB::table('production_boms')->where('tenant_id', $tenantId)->whereIn('bom_number', array_unique(array_column($allocations, 'bom_number')))->pluck('id', 'bom_number')->all();

        if (!empty($bomIds)) {
            DB::table('production_bom_items')->where('tenant_id', $tenantId)->whereIn('bom_id', array_values($bomIds))->delete();
        }

        $hasChildBom = Schema::hasColumn('production_bom_items', 'child_bom_id');
        $materialIds = DB::table('products')->where('tenant_id', $tenantId)->pluck('id', 'sku')->all();

        $groups = [];
        foreach ($allocations as $row) {
            $groups[$row['bom_number']][$row['material_code']][] = $row;
        }

        $count = 0;
        foreach ($groups as $bomNumber => $materials) {
            if (!isset($bomIds[$bomNumber])) {
                throw new \RuntimeException("BOM {$bomNumber} not found for BOM item");
            }

            foreach ($materials as $materialCode => $rows) {
                if (!isset($materialIds[$materialCode])) {
                    throw new \RuntimeException("Material {$materialCode} not found for BOM {$bomNumber}");
                }

                $primary = 0.0;
                $alternative = 0.0;
                $gross = 0.0;
                $childBomId = null;
                foreach ($rows as $row) {
                    $quantity = (float) $row['quantity'];
                    if (!empty($row['is_alternative'])) {
                        $alternative += $quantity;
                    } else {
                        $primary += $quantity;
                        $gross += $quantity * (1 + ((float) ($row['material_scrap_percentage'] ?? 0)) / 100);
                    }
                    if (!empty($row['child_bom_code'])) {
                        $childBomId ??= DB::table('production_boms')->where('tenant_id', $tenantId)->where('bom_number', $row['child_bom_code'])->value('id')
                            ?? throw new \RuntimeException("Child BOM {$row['child_bom_code']} not found for BOM {$bomNumber}");
                    }
                }

                $itemData = [
                    'tenant_id' => $tenantId,
                    'bom_id' => $bomIds[$bomNumber],
                    'material_id' => $materialIds[$materialCode],
                    'quantity' => $primary,
                    'alternative_quantity' => round($alternative, 6),
                    'gross_quantity' => round($gross, 6),
                    'uom_id' => $this->productUom[$materialCode] ?? throw new \RuntimeException("Material {$materialCode} has no unit."),
                    'allocation_role' => $primary > 0 && $alternative > 0 ? 'mixed' : ($alternative > 0 ? 'alternative' : 'primary'),
                    'material_scrap_percentage' => 0.0,
                ];

                if ($hasChildBom && $childBomId) {
                    $itemData['child_bom_id'] = $childBomId;
                }

                ProductionBomItem::withoutGlobalScopes()->create($itemData);
                $count++;
            }
        }

        return $count;
    }
    // ─── 14. ROUTING OPERATION MATERIALS (TIER 2 ALLOCATIONS) ──────────────────

    private function seedRoutingOperationMaterials(array $rows, int $tenantId): int
    {
        // Delete stale routing operation materials for demo BOMs
        $bomNumbers = array_unique(array_column($rows, 'bom_number'));
        $bomIds = DB::table('production_boms')
            ->where('tenant_id', $tenantId)
            ->whereIn('bom_number', $bomNumbers)
            ->pluck('id')
            ->all();

        if (!empty($bomIds)) {
            DB::table('production_routing_operation_materials')
                ->where('tenant_id', $tenantId)
                ->whereIn('bom_id', $bomIds)
                ->delete();
        }

        $count = 0;
        foreach ($rows as $row) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('bom_number', $row['bom_number'])
                ->first();

            $routing = Routing::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('routing_number', $row['routing_number'])
                ->first();

            if (!$bom) {
                throw new \RuntimeException("BOM {$row['bom_number']} not found for ROM");
            }
            if (!$routing) {
                throw new \RuntimeException("Routing {$row['routing_number']} not found for ROM in BOM {$row['bom_number']}");
            }

            $op = RoutingOperation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('routing_id', $routing->id)
                ->where('sequence', $row['operation_sequence'])
                ->first();

            $material = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('sku', $row['material_code'])
                ->first();

            if (!$op) {
                throw new \RuntimeException("Operation sequence {$row['operation_sequence']} in {$routing->routing_number} not found for ROM in BOM {$bom->bom_number}");
            }
            if (!$material) {
                throw new \RuntimeException("Material {$row['material_code']} not found for ROM in BOM {$bom->bom_number}");
            }

            $bomItem = ProductionBomItem::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('bom_id', $bom->id)
                ->where('material_id', $material->id)
                ->first();

            $childBomId = null;
            if (!empty($row['child_bom_code'])) {
                $childBomId = DB::table('production_boms')
                    ->where('tenant_id', $tenantId)
                    ->where('bom_number', $row['child_bom_code'])
                    ->value('id');

                if (!$childBomId) {
                    throw new \RuntimeException("Child BOM {$row['child_bom_code']} not found for ROM in BOM {$bom->bom_number}");
                }
            }

            $replacesId = null;
            if (!empty($row['replaces_material_code'])) {
                $primaryMaterial = Product::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('sku', $row['replaces_material_code'])
                    ->first();

                if ($primaryMaterial) {
                    $replacesId = RoutingOperationMaterial::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('bom_id', $bom->id)
                        ->where('routing_operation_id', $op->id)
                        ->where('material_id', $primaryMaterial->id)
                        ->where('is_alternative', false)
                        ->value('id');
                }
            }

            $isAlt = (bool) ($row['is_alternative'] ?? false);

            RoutingOperationMaterial::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'bom_id' => $bom->id,
                'routing_operation_id' => $op->id,
                'material_id' => $material->id,
                'child_bom_id' => $childBomId,
                'bom_item_id' => $bomItem?->id,
                'is_alternative' => $isAlt,
                'quantity' => (float) $row['quantity'],
                'uom_id' => $this->productUom[$row['material_code']] ?? throw new \RuntimeException("Material {$row['material_code']} has no unit."),
                'priority' => isset($row['priority']) ? (int) $row['priority'] : null,
                'replaces_routing_material_id' => $replacesId,
                'material_scrap_percentage' => (float) ($row['material_scrap_percentage'] ?? 0.0),
                'gross_quantity' => RoutingOperationMaterial::calculateGrossQuantity((float) $row['quantity'], (float) ($row['material_scrap_percentage'] ?? 0.0)),
            ]);

            $count++;
        }

        return $count;
    }

    // ─── 14B. APPROVE BOMS ────────────────────────────────────────────────────

    private function approveBoms(array $rows, int $tenantId): int
    {
        $lifecycle = app(BomLifecycleService::class);
        $count = 0;

        foreach ($rows as $row) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('bom_number', $row['bom_number'])
                ->first();

            if (!$bom) {
                throw new \RuntimeException("BOM {$row['bom_number']} not found for approval");
            }

            $lifecycle->transitionOnApproval($bom, 1);
            $count++;
        }

        return $count;
    }

    /**
     * The plant as data, one method per table, in the order it is loaded. Every reference is a code (section 'NW-SEC-EXT',
     * machine 'NW-EXT-01', material 'RM-PP', unit 'Kg' ...), never an id. Money is INR, the base currency.
     *
     * @return array<string, mixed>
     */
    public function tables(): array
    {
        return [
            'meta' => ['money_currency' => 'INR'],
            'production_shifts' => $this->productionShifts(),
            'warehouses' => $this->warehouses(),
            'products' => $this->products(),
            'opening_stock' => $this->openingStock(),
            'production_work_centers' => $this->productionWorkCenters(),
            'production_work_center_sections' => $this->productionWorkCenterSections(),
            'production_machines' => $this->productionMachines(),
            'production_section_manual_productions' => $this->productionSectionManualProductions(),
            'production_external_worker_pay_grades' => $this->productionExternalWorkerPayGrades(),
            'employees' => $this->employees(),
            'production_work_center_employees' => $this->productionWorkCenterEmployees(),
            'production_section_manual_production_employees' => $this->productionSectionManualProductionEmployees(),
            'production_external_worker_allocations' => $this->productionExternalWorkerAllocations(),
            'production_routings' => $this->productionRoutings(),
            'production_routing_operations' => $this->productionRoutingOperations(),
            'production_boms' => $this->productionBoms(),
            'production_routing_operation_materials' => $this->productionRoutingOperationMaterials(),
        ];
    }

    /** production_shifts: 4 rows. Four continuous / general shifts (Shift A, B, C and the general day shift). */
    private function productionShifts(): array
    {
        return [
            ['code' => 'NW-SH-MRN', 'name' => 'Morning Continuous Shift (Shift A)', 'start_time' => '06:00', 'end_time' => '14:30', 'break_minutes' => 30, 'overtime_allowed' => 1, 'active' => 1],
            ['code' => 'NW-SH-EVE', 'name' => 'Evening Continuous Shift (Shift B)', 'start_time' => '14:00', 'end_time' => '22:30', 'break_minutes' => 30, 'overtime_allowed' => 1, 'active' => 1],
            ['code' => 'NW-SH-NGT', 'name' => 'Night Continuous Shift (Shift C)', 'start_time' => '22:00', 'end_time' => '06:30', 'break_minutes' => 30, 'overtime_allowed' => 1, 'active' => 1],
            ['code' => 'NW-SH-DAY', 'name' => 'General Day Shift', 'start_time' => '09:00', 'end_time' => '18:00', 'break_minutes' => 60, 'overtime_allowed' => 1, 'active' => 1],
        ];
    }

    /** warehouses: 5 rows. The plant sits in the Bangalore branch (BLR-TECH): these warehouses are visible while that branch is the active one. */
    private function warehouses(): array
    {
        return [
            ['code' => 'NW-WH-RM', 'name' => 'Raw Materials Warehouse', 'type' => 'standard', 'address' => 'Plant Gate 1, Inward Storage Bay A', 'is_default' => 0, 'status' => 'active'],
            ['code' => 'NW-WH-ROLL', 'name' => 'Jumbo & Slit Roll Store', 'type' => 'standard', 'address' => 'Intermediate SFG Roll Racks, Hall 2', 'is_default' => 0, 'status' => 'active'],
            ['code' => 'NW-WH-FG', 'name' => 'Finished Goods Warehouse', 'type' => 'standard', 'address' => 'Dispatch Dock 3, Main Warehousing Block', 'is_default' => 1, 'status' => 'active'],
            ['code' => 'NW-WH-SCRAP', 'name' => 'Scrap & Regrind Yard', 'type' => 'standard', 'address' => 'Rear Plant Yard, Recycling Storage Area', 'is_default' => 0, 'status' => 'active'],
            ['code' => 'NW-WH-SUBCON', 'name' => 'Subcontractor Processing Transit', 'type' => 'subcontractor', 'address' => 'Outward Job Work Staging Bay', 'is_default' => 0, 'status' => 'active'],
        ];
    }

    /** products: 23 rows. Materials: raw materials (RM-), semi-finished rolls (SFG-), finished goods (FG-) and scrap (SCR-). Unit by code: Kg or Pcs. Money in INR. */
    private function products(): array
    {
        return [
            ['code' => 'RM-PP', 'name' => 'Virgin Polypropylene (PP) Granules', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'trade', 'unit_cost' => 102, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-MB-WHT', 'name' => 'White Masterbatch Pellets', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'trade', 'unit_cost' => 255, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-MB-BLU', 'name' => 'Blue Masterbatch Pellets', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'trade', 'unit_cost' => 255, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-FILLER', 'name' => 'Calcium Carbonate (CaCO3) Filler', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'trade', 'unit_cost' => 42.5, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-RECYCLE', 'name' => 'In-House Recycled PP Granules', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'manufacture', 'unit_cost' => 59.5, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-MELTBLOWN', 'name' => 'Meltblown BFE 99% Filter Fabric', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'trade', 'unit_cost' => 425, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-EARLOOP', 'name' => 'Elastic Spandex Earloop Cord', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'trade', 'unit_cost' => 340, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-NOSEWIRE', 'name' => 'Polyethylene / Metal Nose Bridge Strip', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'trade', 'unit_cost' => 170, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'SFG-ROLL-50-WHT', 'name' => 'Non-Woven Spunbond Roll 50 GSM White (100 inch)', 'type' => 'semi_finished', 'uom' => 'Kg', 'supplier_method' => 'manufacture', 'unit_cost' => 127.5, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'SFG-ROLL-50-BLU', 'name' => 'Non-Woven Spunbond Roll 50 GSM Blue (100 inch)', 'type' => 'semi_finished', 'uom' => 'Kg', 'supplier_method' => 'manufacture', 'unit_cost' => 127.5, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'SFG-ROLL-20-WHT', 'name' => 'Non-Woven Spunbond Roll 20 GSM White (63 inch)', 'type' => 'semi_finished', 'uom' => 'Kg', 'supplier_method' => 'manufacture', 'unit_cost' => 136, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'SFG-ROLL-20-BLU', 'name' => 'Non-Woven Spunbond Roll 20 GSM Blue (63 inch)', 'type' => 'semi_finished', 'uom' => 'Kg', 'supplier_method' => 'manufacture', 'unit_cost' => 136, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'SFG-SLIT-50-50IN', 'name' => 'Non-Woven Slit Roll 50 GSM White (50 inch)', 'type' => 'semi_finished', 'uom' => 'Kg', 'supplier_method' => 'manufacture', 'unit_cost' => 127.5, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'SFG-SLIT-50-20IN', 'name' => 'Non-Woven Slit Roll 50 GSM White (20 inch)', 'type' => 'semi_finished', 'uom' => 'Kg', 'supplier_method' => 'manufacture', 'unit_cost' => 127.5, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'FG-BAG-DCUT', 'name' => 'D-Cut Non-Woven Shopping Bag (12x16 White)', 'type' => 'finished_good', 'uom' => 'Pcs', 'supplier_method' => 'manufacture', 'unit_cost' => 2.55, 'status' => 'active', 'selling_price' => 4.25],
            ['code' => 'FG-MASK-3PLY', 'name' => '3-Ply Surgical Face Mask (Blue)', 'type' => 'finished_good', 'uom' => 'Pcs', 'supplier_method' => 'manufacture', 'unit_cost' => 1.7, 'status' => 'active', 'selling_price' => 3.4],
            ['code' => 'SCR-RECYCLABLE', 'name' => 'Recyclable Spunbond Edge-Trim Offcuts', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'manufacture', 'unit_cost' => 0, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'SCR-WASTE', 'name' => 'Non-Recyclable Plant Waste & Sweepings', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'manufacture', 'unit_cost' => 0, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-CORE-TUBE', 'name' => 'Heavy-Duty Paper Core Tube 3-inch (Roll Winding)', 'type' => 'raw_material', 'uom' => 'Pcs', 'supplier_method' => 'trade', 'unit_cost' => 42.5, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-WRAP-FILM', 'name' => 'Stretch Wrap Packaging Film (Roll Wrapping)', 'type' => 'raw_material', 'uom' => 'Kg', 'supplier_method' => 'trade', 'unit_cost' => 127.5, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-LABEL-ROLL', 'name' => 'Barcode Identification & QC Label (All Ops)', 'type' => 'raw_material', 'uom' => 'Pcs', 'supplier_method' => 'trade', 'unit_cost' => 0.85, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-BOX-BAG', 'name' => 'Master Corrugated Shipping Box (1,000 D-Cut Bags)', 'type' => 'raw_material', 'uom' => 'Pcs', 'supplier_method' => 'trade', 'unit_cost' => 42.5, 'status' => 'active', 'selling_price' => 0],
            ['code' => 'RM-BOX-MASK', 'name' => 'Printed Inner Dispenser Box (50 3-Ply Masks)', 'type' => 'raw_material', 'uom' => 'Pcs', 'supplier_method' => 'trade', 'unit_cost' => 8.5, 'status' => 'active', 'selling_price' => 0],
        ];
    }

    /** opening_stock: 18 rows. Opening stock per material and warehouse. Cost in INR. */
    private function openingStock(): array
    {
        return [
            ['product_code' => 'RM-PP', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 15000, 'unit_cost' => 102],
            ['product_code' => 'RM-MB-WHT', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 1200, 'unit_cost' => 255],
            ['product_code' => 'RM-MB-BLU', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 800, 'unit_cost' => 255],
            ['product_code' => 'RM-FILLER', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 3000, 'unit_cost' => 42.5],
            ['product_code' => 'RM-MELTBLOWN', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 300, 'unit_cost' => 425],
            ['product_code' => 'RM-EARLOOP', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 150, 'unit_cost' => 340],
            ['product_code' => 'RM-NOSEWIRE', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 80, 'unit_cost' => 170],
            ['product_code' => 'RM-RECYCLE', 'warehouse_code' => 'NW-WH-SCRAP', 'quantity' => 600, 'unit_cost' => 59.5],
            ['product_code' => 'SCR-RECYCLABLE', 'warehouse_code' => 'NW-WH-SCRAP', 'quantity' => 350, 'unit_cost' => 0],
            ['product_code' => 'SFG-ROLL-50-WHT', 'warehouse_code' => 'NW-WH-ROLL', 'quantity' => 2500, 'unit_cost' => 127.5],
            ['product_code' => 'SFG-ROLL-20-BLU', 'warehouse_code' => 'NW-WH-ROLL', 'quantity' => 600, 'unit_cost' => 136],
            ['product_code' => 'SFG-SLIT-50-50IN', 'warehouse_code' => 'NW-WH-ROLL', 'quantity' => 500, 'unit_cost' => 127.5],
            ['product_code' => 'SFG-SLIT-50-20IN', 'warehouse_code' => 'NW-WH-ROLL', 'quantity' => 300, 'unit_cost' => 127.5],
            ['product_code' => 'RM-CORE-TUBE', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 2000, 'unit_cost' => 42.5],
            ['product_code' => 'RM-WRAP-FILM', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 600, 'unit_cost' => 127.5],
            ['product_code' => 'RM-LABEL-ROLL', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 15000, 'unit_cost' => 0.85],
            ['product_code' => 'RM-BOX-BAG', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 1200, 'unit_cost' => 42.5],
            ['product_code' => 'RM-BOX-MASK', 'warehouse_code' => 'NW-WH-RM', 'quantity' => 4000, 'unit_cost' => 8.5],
        ];
    }

    /** production_work_centers: 3 rows. Three work centres. Overhead is INR per day. */
    private function productionWorkCenters(): array
    {
        return [
            ['code' => 'NW-WC-EXT', 'name' => 'Spunbond & Meltblown Extrusion Hub', 'description' => 'Continuous polymer extrusion lines and raw scrap recovery.', 'status' => 'active', 'overhead_cost' => 2550, 'calculation_countdown_minutes' => 10],
            ['code' => 'NW-WC-CNV', 'name' => 'Roll Converting & Secondary Processing', 'description' => 'Master roll slitting, rewinding, coating, and roll packaging.', 'status' => 'active', 'overhead_cost' => 1700, 'calculation_countdown_minutes' => 10],
            ['code' => 'NW-WC-FAB', 'name' => 'Bag & Mask Converting Plant', 'description' => 'Converted finished goods manufacturing and final packing.', 'status' => 'active', 'overhead_cost' => 2125, 'calculation_countdown_minutes' => 10],
        ];
    }

    /** production_work_center_sections: 9 rows. Sections with the shifts they run. machine_codes only documents which machines belong to the section (the machine row names its section). */
    private function productionWorkCenterSections(): array
    {
        return [
            ['code' => 'NW-SEC-EXT', 'work_center_code' => 'NW-WC-EXT', 'name' => 'Spunbond Extrusion Cell', 'description' => 'High-throughput continuous spunbond fabric extrusion lines (kg/hr).', 'status' => 'active', 'assigned_shifts' => ['NW-SH-MRN', 'NW-SH-EVE', 'NW-SH-NGT'], 'machine_codes' => ['NW-EXT-01', 'NW-EXT-02']],
            ['code' => 'NW-SEC-MB', 'work_center_code' => 'NW-WC-EXT', 'name' => 'Meltblown Extrusion Cell', 'description' => 'Fine micro-fiber meltblown filter media extrusion (kg/hr).', 'status' => 'active', 'assigned_shifts' => ['NW-SH-MRN', 'NW-SH-EVE'], 'machine_codes' => ['NW-MB-01']],
            ['code' => 'NW-SEC-RCY', 'work_center_code' => 'NW-WC-EXT', 'name' => 'Scrap Recycling & Pelletizing', 'description' => 'Edge trim off-cut agglomeration and re-granulation (kg/hr).', 'status' => 'active', 'assigned_shifts' => ['NW-SH-NGT', 'NW-SH-DAY'], 'machine_codes' => ['NW-RCY-01']],
            ['code' => 'NW-SEC-SLT', 'work_center_code' => 'NW-WC-CNV', 'name' => 'Slitting & Rewinding Line', 'description' => 'Width slitting and length rewinding of master rolls into child rolls (kg/hr).', 'status' => 'active', 'assigned_shifts' => ['NW-SH-MRN', 'NW-SH-EVE'], 'machine_codes' => ['NW-SLT-01', 'NW-RWD-01']],
            ['code' => 'NW-SEC-PRT', 'work_center_code' => 'NW-WC-CNV', 'name' => 'Lamination & Flexo Printing', 'description' => 'PE/BOPP film coating and 2-color roll-to-roll flexo printing (kg/hr).', 'status' => 'active', 'assigned_shifts' => ['NW-SH-DAY', 'NW-SH-EVE'], 'machine_codes' => ['NW-LAM-01', 'NW-PRT-01']],
            ['code' => 'NW-SEC-WRP', 'work_center_code' => 'NW-WC-CNV', 'name' => 'Roll Wrapping & Finishing', 'description' => 'Stretch-film roll packaging and pallet preparation (kg/hr).', 'status' => 'active', 'assigned_shifts' => ['NW-SH-DAY', 'NW-SH-EVE'], 'machine_codes' => ['NW-WRP-01']],
            ['code' => 'NW-SEC-BAG', 'work_center_code' => 'NW-WC-FAB', 'name' => 'Automatic Bag Making Line', 'description' => 'High-speed D-cut bag conversion and handle punching (pcs/hr).', 'status' => 'active', 'assigned_shifts' => ['NW-SH-MRN', 'NW-SH-EVE'], 'machine_codes' => ['NW-BAG-01', 'NW-BAG-02']],
            ['code' => 'NW-SEC-MSK', 'work_center_code' => 'NW-WC-FAB', 'name' => 'Ultrasonic Mask Line', 'description' => 'Automated 3-ply pleated body forming and earloop ultrasonic welding (pcs/hr).', 'status' => 'active', 'assigned_shifts' => ['NW-SH-MRN', 'NW-SH-EVE'], 'machine_codes' => ['NW-MSK-01']],
            ['code' => 'NW-SEC-PCK', 'work_center_code' => 'NW-WC-FAB', 'name' => 'Manual Packing & Boxing', 'description' => 'Pure manual station for bag and mask carton packing, banding, and boxing (pcs/hr).', 'status' => 'active', 'assigned_shifts' => ['NW-SH-DAY', 'NW-SH-EVE'], 'machine_codes' => []],
        ];
    }

    /** production_machines: 12 rows. Machines. energy_rate_per_unit and operating_cost_per_hour are INR. */
    private function productionMachines(): array
    {
        return [
            ['code' => 'NW-EXT-01', 'section_code' => 'NW-SEC-EXT', 'name' => 'Spunbond Extrusion Line 1 (100 inch)', 'machine_type' => 'spunbond_line', 'capacity' => 250, 'energy_source' => 'electricity', 'consumption_per_hour' => 220, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 2125, 'status' => 'active', 'installation_date' => '2022-04-15', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
            ['code' => 'NW-EXT-02', 'section_code' => 'NW-SEC-EXT', 'name' => 'Spunbond Extrusion Line 2 (63 inch)', 'machine_type' => 'spunbond_line', 'capacity' => 150, 'energy_source' => 'electricity', 'consumption_per_hour' => 140, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 1360, 'status' => 'active', 'installation_date' => '2023-09-01', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
            ['code' => 'NW-MB-01', 'section_code' => 'NW-SEC-MB', 'name' => 'Meltblown Line (63 inch)', 'machine_type' => 'meltblown_line', 'capacity' => 60, 'energy_source' => 'electricity', 'consumption_per_hour' => 150, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 1275, 'status' => 'active', 'installation_date' => '2024-02-10', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
            ['code' => 'NW-SLT-01', 'section_code' => 'NW-SEC-SLT', 'name' => 'Slitter-Rewinder (width cutting, up to 100 inch)', 'machine_type' => 'slitter_rewinder', 'capacity' => 600, 'energy_source' => 'electricity', 'consumption_per_hour' => 7.5, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 0, 'status' => 'active', 'installation_date' => '2022-05-20', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
            ['code' => 'NW-RWD-01', 'section_code' => 'NW-SEC-SLT', 'name' => 'Doctor Rewinder (length cutting)', 'machine_type' => 'rewinder', 'capacity' => 400, 'energy_source' => 'electricity', 'consumption_per_hour' => 5.5, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 0, 'status' => 'active', 'installation_date' => '2022-05-20', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
            ['code' => 'NW-LAM-01', 'section_code' => 'NW-SEC-PRT', 'name' => 'Lamination Line (BOPP / PE coating)', 'machine_type' => 'lamination_line', 'capacity' => 300, 'energy_source' => 'electricity', 'consumption_per_hour' => 45, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 425, 'status' => 'active', 'installation_date' => '2024-06-05', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
            ['code' => 'NW-PRT-01', 'section_code' => 'NW-SEC-PRT', 'name' => 'Roll-to-Roll Flexo Printing Machine (2 colour)', 'machine_type' => 'flexo_printer', 'capacity' => 150, 'energy_source' => 'electricity', 'consumption_per_hour' => 12, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 0, 'status' => 'active', 'installation_date' => '2023-01-18', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
            ['code' => 'NW-BAG-01', 'section_code' => 'NW-SEC-BAG', 'name' => 'D-Cut Bag Making Machine 1', 'machine_type' => 'bag_making', 'capacity' => 4800, 'energy_source' => 'electricity', 'consumption_per_hour' => 15, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 127.5, 'status' => 'active', 'installation_date' => '2022-08-11', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
            ['code' => 'NW-BAG-02', 'section_code' => 'NW-SEC-BAG', 'name' => 'D-Cut Bag Making Machine 2', 'machine_type' => 'bag_making', 'capacity' => 4800, 'energy_source' => 'electricity', 'consumption_per_hour' => 15, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 127.5, 'status' => 'under_maintenance', 'installation_date' => '2023-03-27', 'maintenance_status' => 'under_maintenance', 'current_state' => 'Maintenance', 'current_state_reason' => 'Ultrasonic horn replacement'],
            ['code' => 'NW-MSK-01', 'section_code' => 'NW-SEC-MSK', 'name' => '3-Ply Mask Machine (body + earloop welding)', 'machine_type' => 'mask_machine', 'capacity' => 6000, 'energy_source' => 'electricity', 'consumption_per_hour' => 9, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 0, 'status' => 'active', 'installation_date' => '2023-11-02', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
            ['code' => 'NW-RCY-01', 'section_code' => 'NW-SEC-RCY', 'name' => 'Scrap Recycling Pelletizer', 'machine_type' => 'pelletizer', 'capacity' => 150, 'energy_source' => 'electricity', 'consumption_per_hour' => 55, 'energy_rate_per_unit' => 8.5, 'operating_cost_per_hour' => 425, 'status' => 'active', 'installation_date' => '2022-10-09', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
            ['code' => 'NW-WRP-01', 'section_code' => 'NW-SEC-WRP', 'name' => 'Roll Stretch-Wrapping Machine', 'machine_type' => 'stretch_wrapper', 'capacity' => 1200, 'energy_source' => 'others', 'consumption_per_hour' => 0, 'energy_rate_per_unit' => 0, 'operating_cost_per_hour' => 42.5, 'status' => 'active', 'installation_date' => '2022-06-30', 'maintenance_status' => 'operational', 'current_state' => 'Idle'],
        ];
    }

    /** production_section_manual_productions: 2 rows. Manual packing crews (a section whose output comes from people, not machines). */
    private function productionSectionManualProductions(): array
    {
        return [
            ['section_code' => 'NW-SEC-PCK', 'shift_code' => 'NW-SH-DAY', 'batch_name' => 'NW-BAT-PCK-DAY', 'capacity_per_hour' => 700, 'active' => 1],
            ['section_code' => 'NW-SEC-PCK', 'shift_code' => 'NW-SH-EVE', 'batch_name' => 'NW-BAT-PCK-EVE', 'capacity_per_hour' => 700, 'active' => 1],
        ];
    }

    /** production_external_worker_pay_grades: 3 rows. Hourly pay of external workers, INR. */
    private function productionExternalWorkerPayGrades(): array
    {
        return [
            ['code' => 'NW-PG-UNSKILL', 'name' => 'Plant Helper / Material Handler', 'hourly_rate' => 85, 'description' => 'Raw material feeding, edge trim handling, floor cleanup', 'active' => 1],
            ['code' => 'NW-PG-SEMISKILL', 'name' => 'Converting Packer / Sorter', 'hourly_rate' => 127.5, 'description' => 'Roll wrapping, bagging boxing, dispenser box packing', 'active' => 1],
            ['code' => 'NW-PG-SKILLED', 'name' => 'Converting Machine Operator / QC Inspector', 'hourly_rate' => 170, 'description' => 'Extruder line attendance, bag machine operation, quality sampling', 'active' => 1],
        ];
    }

    /** employees: 17 rows. Demo staff of the Bangalore plant; monthly salary in INR, department / designation / pay group are set by the seeder. */
    private function employees(): array
    {
        return [
            ['employee_code' => 'NW-EMP-001', 'full_name' => 'Kavitha Raman', 'job_title' => 'Extrusion Line Operator', 'work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-EXT', 'shift_code' => 'NW-SH-MRN', 'monthly_salary_inr' => 35000],
            ['employee_code' => 'NW-EMP-002', 'full_name' => 'Rajesh Nair', 'job_title' => 'Extrusion Line Operator', 'work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-EXT', 'shift_code' => 'NW-SH-EVE', 'monthly_salary_inr' => 35000],
            ['employee_code' => 'NW-EMP-003', 'full_name' => 'Manjunath K', 'job_title' => 'Extrusion Line Operator', 'work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-EXT', 'shift_code' => 'NW-SH-NGT', 'monthly_salary_inr' => 38000],
            ['employee_code' => 'NW-EMP-004', 'full_name' => 'Deepak Gowda', 'job_title' => 'Meltblown Line Specialist', 'work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-MB', 'shift_code' => 'NW-SH-MRN', 'monthly_salary_inr' => 40000],
            ['employee_code' => 'NW-EMP-005', 'full_name' => 'Srinivas Murthy', 'job_title' => 'Meltblown Line Technician', 'work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-MB', 'shift_code' => 'NW-SH-EVE', 'monthly_salary_inr' => 36000],
            ['employee_code' => 'NW-EMP-006', 'full_name' => 'Shivakumar B', 'job_title' => 'Pelletizer Machine Operator', 'work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-RCY', 'shift_code' => 'NW-SH-DAY', 'monthly_salary_inr' => 28000],
            ['employee_code' => 'NW-EMP-007', 'full_name' => 'Harish Patel', 'job_title' => 'Slitter Rewinder Operator', 'work_center_code' => 'NW-WC-CNV', 'section_code' => 'NW-SEC-SLT', 'shift_code' => 'NW-SH-MRN', 'monthly_salary_inr' => 32000],
            ['employee_code' => 'NW-EMP-008', 'full_name' => 'Anand Kumar', 'job_title' => 'Slitter Rewinder Operator', 'work_center_code' => 'NW-WC-CNV', 'section_code' => 'NW-SEC-SLT', 'shift_code' => 'NW-SH-EVE', 'monthly_salary_inr' => 32000],
            ['employee_code' => 'NW-EMP-009', 'full_name' => 'Pradeep Shenoy', 'job_title' => 'Flexo Print Master', 'work_center_code' => 'NW-WC-CNV', 'section_code' => 'NW-SEC-PRT', 'shift_code' => 'NW-SH-DAY', 'monthly_salary_inr' => 34000],
            ['employee_code' => 'NW-EMP-010', 'full_name' => 'Venkatesh Rao', 'job_title' => 'Roll Finishing Operator', 'work_center_code' => 'NW-WC-CNV', 'section_code' => 'NW-SEC-WRP', 'shift_code' => 'NW-SH-DAY', 'monthly_salary_inr' => 30000],
            ['employee_code' => 'NW-EMP-011', 'full_name' => 'Girish Prasad', 'job_title' => 'Roll Finishing Operator', 'work_center_code' => 'NW-WC-CNV', 'section_code' => 'NW-SEC-WRP', 'shift_code' => 'NW-SH-EVE', 'monthly_salary_inr' => 30000],
            ['employee_code' => 'NW-EMP-012', 'full_name' => 'Ramesh Babu', 'job_title' => 'Bag Machine Lead Operator', 'work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-BAG', 'shift_code' => 'NW-SH-MRN', 'monthly_salary_inr' => 30000],
            ['employee_code' => 'NW-EMP-013', 'full_name' => 'Suresh Reddy', 'job_title' => 'Bag Machine Operator', 'work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-BAG', 'shift_code' => 'NW-SH-EVE', 'monthly_salary_inr' => 30000],
            ['employee_code' => 'NW-EMP-014', 'full_name' => 'Pooja Hegde', 'job_title' => 'Ultrasonic Mask Line Operator', 'work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-MSK', 'shift_code' => 'NW-SH-MRN', 'monthly_salary_inr' => 28000],
            ['employee_code' => 'NW-EMP-015', 'full_name' => 'Geetha Lakshmi', 'job_title' => 'Mask Line Operator', 'work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-MSK', 'shift_code' => 'NW-SH-EVE', 'monthly_salary_inr' => 28000],
            ['employee_code' => 'NW-EMP-016', 'full_name' => 'Radha Krishna', 'job_title' => 'Packing Crew Lead', 'work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-PCK', 'shift_code' => 'NW-SH-DAY', 'monthly_salary_inr' => 26000],
            ['employee_code' => 'NW-EMP-017', 'full_name' => 'Santosh Patil', 'job_title' => 'Packing Crew Lead', 'work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-PCK', 'shift_code' => 'NW-SH-EVE', 'monthly_salary_inr' => 26000],
        ];
    }

    /** production_work_center_employees: 17 rows. Which employee works in which work centre / section. */
    private function productionWorkCenterEmployees(): array
    {
        return [
            ['work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-EXT', 'employee_code' => 'NW-EMP-001', 'active' => 1],
            ['work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-EXT', 'employee_code' => 'NW-EMP-002', 'active' => 1],
            ['work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-EXT', 'employee_code' => 'NW-EMP-003', 'active' => 1],
            ['work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-MB', 'employee_code' => 'NW-EMP-004', 'active' => 1],
            ['work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-MB', 'employee_code' => 'NW-EMP-005', 'active' => 1],
            ['work_center_code' => 'NW-WC-EXT', 'section_code' => 'NW-SEC-RCY', 'employee_code' => 'NW-EMP-006', 'active' => 1],
            ['work_center_code' => 'NW-WC-CNV', 'section_code' => 'NW-SEC-SLT', 'employee_code' => 'NW-EMP-007', 'active' => 1],
            ['work_center_code' => 'NW-WC-CNV', 'section_code' => 'NW-SEC-SLT', 'employee_code' => 'NW-EMP-008', 'active' => 1],
            ['work_center_code' => 'NW-WC-CNV', 'section_code' => 'NW-SEC-PRT', 'employee_code' => 'NW-EMP-009', 'active' => 1],
            ['work_center_code' => 'NW-WC-CNV', 'section_code' => 'NW-SEC-WRP', 'employee_code' => 'NW-EMP-010', 'active' => 1],
            ['work_center_code' => 'NW-WC-CNV', 'section_code' => 'NW-SEC-WRP', 'employee_code' => 'NW-EMP-011', 'active' => 1],
            ['work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-BAG', 'employee_code' => 'NW-EMP-012', 'active' => 1],
            ['work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-BAG', 'employee_code' => 'NW-EMP-013', 'active' => 1],
            ['work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-MSK', 'employee_code' => 'NW-EMP-014', 'active' => 1],
            ['work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-MSK', 'employee_code' => 'NW-EMP-015', 'active' => 1],
            ['work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-PCK', 'employee_code' => 'NW-EMP-016', 'active' => 1],
            ['work_center_code' => 'NW-WC-FAB', 'section_code' => 'NW-SEC-PCK', 'employee_code' => 'NW-EMP-017', 'active' => 1],
        ];
    }

    /** production_section_manual_production_employees: 2 rows. Which employee belongs to which manual crew. */
    private function productionSectionManualProductionEmployees(): array
    {
        return [
            ['batch_name' => 'NW-BAT-PCK-DAY', 'employee_code' => 'NW-EMP-016'],
            ['batch_name' => 'NW-BAT-PCK-EVE', 'employee_code' => 'NW-EMP-017'],
        ];
    }

    /** production_external_worker_allocations: 17 rows. External workers per section and shift. */
    private function productionExternalWorkerAllocations(): array
    {
        return [
            ['section_code' => 'NW-SEC-RCY', 'shift_code' => 'NW-SH-DAY', 'pay_grade_code' => 'NW-PG-UNSKILL', 'workers_count' => 2, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-RCY', 'shift_code' => 'NW-SH-NGT', 'pay_grade_code' => 'NW-PG-UNSKILL', 'workers_count' => 2, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-WRP', 'shift_code' => 'NW-SH-DAY', 'pay_grade_code' => 'NW-PG-SEMISKILL', 'workers_count' => 2, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-WRP', 'shift_code' => 'NW-SH-EVE', 'pay_grade_code' => 'NW-PG-SEMISKILL', 'workers_count' => 2, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-PCK', 'shift_code' => 'NW-SH-DAY', 'pay_grade_code' => 'NW-PG-SEMISKILL', 'workers_count' => 3, 'batch_name' => 'NW-BAT-PCK-DAY', 'active' => 1],
            ['section_code' => 'NW-SEC-PCK', 'shift_code' => 'NW-SH-EVE', 'pay_grade_code' => 'NW-PG-SEMISKILL', 'workers_count' => 3, 'batch_name' => 'NW-BAT-PCK-EVE', 'active' => 1],
            ['section_code' => 'NW-SEC-EXT', 'shift_code' => 'NW-SH-MRN', 'pay_grade_code' => 'NW-PG-SKILLED', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-EXT', 'shift_code' => 'NW-SH-EVE', 'pay_grade_code' => 'NW-PG-SKILLED', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-EXT', 'shift_code' => 'NW-SH-NGT', 'pay_grade_code' => 'NW-PG-SKILLED', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-MB', 'shift_code' => 'NW-SH-MRN', 'pay_grade_code' => 'NW-PG-SKILLED', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-MB', 'shift_code' => 'NW-SH-EVE', 'pay_grade_code' => 'NW-PG-SKILLED', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-SLT', 'shift_code' => 'NW-SH-MRN', 'pay_grade_code' => 'NW-PG-SEMISKILL', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-SLT', 'shift_code' => 'NW-SH-EVE', 'pay_grade_code' => 'NW-PG-SEMISKILL', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-BAG', 'shift_code' => 'NW-SH-MRN', 'pay_grade_code' => 'NW-PG-SEMISKILL', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-BAG', 'shift_code' => 'NW-SH-EVE', 'pay_grade_code' => 'NW-PG-SEMISKILL', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-MSK', 'shift_code' => 'NW-SH-MRN', 'pay_grade_code' => 'NW-PG-SEMISKILL', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
            ['section_code' => 'NW-SEC-MSK', 'shift_code' => 'NW-SH-EVE', 'pay_grade_code' => 'NW-PG-SEMISKILL', 'workers_count' => 1, 'batch_name' => null, 'active' => 1],
        ];
    }

    /** production_routings: 5 rows. Five routings (extrusion 100", extrusion 63", slitting, bag making, mask making). */
    private function productionRoutings(): array
    {
        return [
            ['routing_number' => 'RT-EXT-SPUNBOND', 'name' => 'Spunbond Fabric Extrusion Routing (100 inch Line 1)', 'version' => '1.0.0', 'is_default' => 1],
            ['routing_number' => 'RT-EXT-SPUNBOND-63', 'name' => 'Spunbond Fabric Extrusion Routing (63 inch Line 2)', 'version' => '1.0.0', 'is_default' => 1],
            ['routing_number' => 'RT-SLT-ROLLS', 'name' => 'Roll Slitting & Rewinding Routing', 'version' => '1.0.0', 'is_default' => 1],
            ['routing_number' => 'RT-BAG-DCUT', 'name' => 'D-Cut Bag Making Routing', 'version' => '1.0.0', 'is_default' => 1],
            ['routing_number' => 'RT-MASK-3PLY', 'name' => '3-Ply Surgical Face Mask Routing', 'version' => '1.0.0', 'is_default' => 1],
        ];
    }

    /** production_routing_operations: 11 rows. Operations of every routing; times, rates and costs are calculated from the section snapshot by the seeder. */
    private function productionRoutingOperations(): array
    {
        return [
            ['routing_number' => 'RT-EXT-SPUNBOND', 'sequence' => 10, 'operation_number' => 'OP-010', 'previous_operation_number' => null, 'name' => 'Spunbond Continuous Extrusion, Bonding & In-Line Winding', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-EXT', 'machine_code' => 'NW-EXT-01', 'batch_name' => null, 'setup_time_minutes' => 30, 'setup_time_per_piece_minutes' => 0.001, 'processing_time_minutes' => 0.24, 'expected_yield_percentage' => 100, 'quality_required' => true],
            ['routing_number' => 'RT-EXT-SPUNBOND', 'sequence' => 20, 'operation_number' => 'OP-020', 'previous_operation_number' => 'OP-010', 'name' => 'Roll Finishing, Inspection & Wrapping', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-WRP', 'machine_code' => 'NW-WRP-01', 'batch_name' => null, 'setup_time_minutes' => 5, 'setup_time_per_piece_minutes' => 0.0005, 'processing_time_minutes' => 0.05, 'expected_yield_percentage' => 100, 'quality_required' => true],
            ['routing_number' => 'RT-EXT-SPUNBOND-63', 'sequence' => 10, 'operation_number' => 'OP-010', 'previous_operation_number' => null, 'name' => 'Spunbond Continuous Extrusion, Bonding & In-Line Winding (Line 2)', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-EXT', 'machine_code' => 'NW-EXT-02', 'batch_name' => null, 'setup_time_minutes' => 30, 'setup_time_per_piece_minutes' => 0.001, 'processing_time_minutes' => 0.4, 'expected_yield_percentage' => 100, 'quality_required' => true],
            ['routing_number' => 'RT-EXT-SPUNBOND-63', 'sequence' => 20, 'operation_number' => 'OP-020', 'previous_operation_number' => 'OP-010', 'name' => 'Roll Finishing, Inspection & Wrapping', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-WRP', 'machine_code' => 'NW-WRP-01', 'batch_name' => null, 'setup_time_minutes' => 5, 'setup_time_per_piece_minutes' => 0.0005, 'processing_time_minutes' => 0.05, 'expected_yield_percentage' => 100, 'quality_required' => true],
            ['routing_number' => 'RT-SLT-ROLLS', 'sequence' => 10, 'operation_number' => 'OP-010', 'previous_operation_number' => null, 'name' => 'Rotary Shear Width Slitting & Rewinding', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-SLT', 'machine_code' => 'NW-SLT-01', 'batch_name' => null, 'setup_time_minutes' => 20, 'setup_time_per_piece_minutes' => 0.001, 'processing_time_minutes' => 0.1, 'expected_yield_percentage' => 100, 'quality_required' => true],
            ['routing_number' => 'RT-SLT-ROLLS', 'sequence' => 20, 'operation_number' => 'OP-020', 'previous_operation_number' => 'OP-010', 'name' => 'Slit Roll Palletizing, Strapping & Wrapping', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-WRP', 'machine_code' => 'NW-WRP-01', 'batch_name' => null, 'setup_time_minutes' => 5, 'setup_time_per_piece_minutes' => 0.0005, 'processing_time_minutes' => 0.05, 'expected_yield_percentage' => 100, 'quality_required' => true],
            ['routing_number' => 'RT-BAG-DCUT', 'sequence' => 10, 'operation_number' => 'OP-010', 'previous_operation_number' => null, 'name' => 'Roll Conversion & D-Cut Punching', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-BAG', 'machine_code' => 'NW-BAG-01', 'batch_name' => null, 'setup_time_minutes' => 30, 'setup_time_per_piece_minutes' => 0.0002, 'processing_time_minutes' => 0.0125, 'expected_yield_percentage' => 100, 'quality_required' => true],
            ['routing_number' => 'RT-BAG-DCUT', 'sequence' => 20, 'operation_number' => 'OP-020', 'previous_operation_number' => 'OP-010', 'name' => 'Manual Bundle Counting & QC Inspection', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-PCK', 'machine_code' => null, 'batch_name' => 'NW-BAT-PCK-DAY', 'setup_time_minutes' => 5, 'setup_time_per_piece_minutes' => 0.0002, 'processing_time_minutes' => 0.024, 'expected_yield_percentage' => 100, 'quality_required' => true],
            ['routing_number' => 'RT-BAG-DCUT', 'sequence' => 30, 'operation_number' => 'OP-030', 'previous_operation_number' => 'OP-020', 'name' => 'Carton Boxing & Pallet Packing', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-PCK', 'machine_code' => null, 'batch_name' => 'NW-BAT-PCK-DAY', 'setup_time_minutes' => 10, 'setup_time_per_piece_minutes' => 0.0005, 'processing_time_minutes' => 0.0857, 'expected_yield_percentage' => 100, 'quality_required' => false],
            ['routing_number' => 'RT-MASK-3PLY', 'sequence' => 10, 'operation_number' => 'OP-010', 'previous_operation_number' => null, 'name' => 'Pleating, Nose Wire & Earloop Ultrasonic Welding', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-MSK', 'machine_code' => 'NW-MSK-01', 'batch_name' => null, 'setup_time_minutes' => 25, 'setup_time_per_piece_minutes' => 0.0002, 'processing_time_minutes' => 0.01, 'expected_yield_percentage' => 100, 'quality_required' => true],
            ['routing_number' => 'RT-MASK-3PLY', 'sequence' => 20, 'operation_number' => 'OP-020', 'previous_operation_number' => 'OP-010', 'name' => 'Dispenser Box Packing (50 Pcs/Box) & QC', 'operation_type' => 'manufacturing', 'section_code' => 'NW-SEC-PCK', 'machine_code' => null, 'batch_name' => 'NW-BAT-PCK-DAY', 'setup_time_minutes' => 5, 'setup_time_per_piece_minutes' => 0.0005, 'processing_time_minutes' => 0.0857, 'expected_yield_percentage' => 100, 'quality_required' => true],
        ];
    }

    /** production_boms: 8 rows. Bills of materials. Base quantity per 100 kg (rolls) or per 1,000 pcs (bags, masks); the unit comes from the product. */
    private function productionBoms(): array
    {
        return [
            ['bom_number' => 'BOM-ROLL-50-WHT', 'bom_name' => '50 GSM White Spunbond Jumbo Roll (100 inch)', 'product_code' => 'SFG-ROLL-50-WHT', 'routing_number' => 'RT-EXT-SPUNBOND', 'base_quantity' => 100, 'version' => '1.0.0', 'is_default' => 1],
            ['bom_number' => 'BOM-ROLL-50-BLU', 'bom_name' => '50 GSM Blue Spunbond Jumbo Roll (100 inch)', 'product_code' => 'SFG-ROLL-50-BLU', 'routing_number' => 'RT-EXT-SPUNBOND', 'base_quantity' => 100, 'version' => '1.0.0', 'is_default' => 1],
            ['bom_number' => 'BOM-ROLL-20-WHT', 'bom_name' => '20 GSM White Spunbond Jumbo Roll (63 inch)', 'product_code' => 'SFG-ROLL-20-WHT', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'base_quantity' => 100, 'version' => '1.0.0', 'is_default' => 1],
            ['bom_number' => 'BOM-ROLL-20-BLU', 'bom_name' => '20 GSM Blue Spunbond Jumbo Roll (63 inch)', 'product_code' => 'SFG-ROLL-20-BLU', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'base_quantity' => 100, 'version' => '1.0.0', 'is_default' => 1],
            ['bom_number' => 'BOM-SLIT-50-50IN', 'bom_name' => 'Slit Roll 50 GSM White (50 inch Width)', 'product_code' => 'SFG-SLIT-50-50IN', 'routing_number' => 'RT-SLT-ROLLS', 'base_quantity' => 100, 'version' => '1.0.0', 'is_default' => 1],
            ['bom_number' => 'BOM-SLIT-50-20IN', 'bom_name' => 'Slit Roll 50 GSM White (20 inch Width)', 'product_code' => 'SFG-SLIT-50-20IN', 'routing_number' => 'RT-SLT-ROLLS', 'base_quantity' => 100, 'version' => '1.0.0', 'is_default' => 1],
            ['bom_number' => 'BOM-BAG-DCUT', 'bom_name' => 'D-Cut Non-Woven Shopping Bag 12x16 White', 'product_code' => 'FG-BAG-DCUT', 'routing_number' => 'RT-BAG-DCUT', 'base_quantity' => 1000, 'version' => '1.0.0', 'is_default' => 1],
            ['bom_number' => 'BOM-MASK-3PLY', 'bom_name' => '3-Ply Surgical Face Mask (Blue)', 'product_code' => 'FG-MASK-3PLY', 'routing_number' => 'RT-MASK-3PLY', 'base_quantity' => 1000, 'version' => '1.0.0', 'is_default' => 1],
        ];
    }

    /** production_routing_operation_materials: 41 rows. What each operation consumes. This is the recipe: the BOM master list (Tier 1) is derived from it like the BOM screen does, units come from the materials. */
    private function productionRoutingOperationMaterials(): array
    {
        return [
            ['bom_number' => 'BOM-ROLL-50-WHT', 'routing_number' => 'RT-EXT-SPUNBOND', 'operation_sequence' => 10, 'material_code' => 'RM-PP', 'quantity' => 95.68, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-50-WHT', 'routing_number' => 'RT-EXT-SPUNBOND', 'operation_sequence' => 10, 'material_code' => 'RM-MB-WHT', 'quantity' => 3.12, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-50-WHT', 'routing_number' => 'RT-EXT-SPUNBOND', 'operation_sequence' => 10, 'material_code' => 'RM-FILLER', 'quantity' => 5.2, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-50-WHT', 'routing_number' => 'RT-EXT-SPUNBOND', 'operation_sequence' => 10, 'material_code' => 'RM-CORE-TUBE', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-50-WHT', 'routing_number' => 'RT-EXT-SPUNBOND', 'operation_sequence' => 20, 'material_code' => 'RM-LABEL-ROLL', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-50-BLU', 'routing_number' => 'RT-EXT-SPUNBOND', 'operation_sequence' => 10, 'material_code' => 'RM-PP', 'quantity' => 95.68, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-50-BLU', 'routing_number' => 'RT-EXT-SPUNBOND', 'operation_sequence' => 10, 'material_code' => 'RM-MB-BLU', 'quantity' => 3.12, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-50-BLU', 'routing_number' => 'RT-EXT-SPUNBOND', 'operation_sequence' => 10, 'material_code' => 'RM-FILLER', 'quantity' => 5.2, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-50-BLU', 'routing_number' => 'RT-EXT-SPUNBOND', 'operation_sequence' => 10, 'material_code' => 'RM-CORE-TUBE', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-50-BLU', 'routing_number' => 'RT-EXT-SPUNBOND', 'operation_sequence' => 20, 'material_code' => 'RM-LABEL-ROLL', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-20-WHT', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'operation_sequence' => 10, 'material_code' => 'RM-PP', 'quantity' => 99.84, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-20-WHT', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'operation_sequence' => 10, 'material_code' => 'RM-MB-WHT', 'quantity' => 3.12, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-20-WHT', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'operation_sequence' => 10, 'material_code' => 'RM-FILLER', 'quantity' => 1.04, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-20-WHT', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'operation_sequence' => 10, 'material_code' => 'RM-CORE-TUBE', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-20-WHT', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'operation_sequence' => 20, 'material_code' => 'RM-LABEL-ROLL', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-20-BLU', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'operation_sequence' => 10, 'material_code' => 'RM-PP', 'quantity' => 99.84, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-20-BLU', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'operation_sequence' => 10, 'material_code' => 'RM-MB-BLU', 'quantity' => 3.12, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-20-BLU', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'operation_sequence' => 10, 'material_code' => 'RM-FILLER', 'quantity' => 1.04, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-20-BLU', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'operation_sequence' => 10, 'material_code' => 'RM-CORE-TUBE', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-ROLL-20-BLU', 'routing_number' => 'RT-EXT-SPUNBOND-63', 'operation_sequence' => 20, 'material_code' => 'RM-LABEL-ROLL', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-SLIT-50-50IN', 'routing_number' => 'RT-SLT-ROLLS', 'operation_sequence' => 10, 'material_code' => 'SFG-ROLL-50-WHT', 'quantity' => 100, 'is_alternative' => false, 'material_scrap_percentage' => 2, 'child_bom_code' => 'BOM-ROLL-50-WHT'],
            ['bom_number' => 'BOM-SLIT-50-50IN', 'routing_number' => 'RT-SLT-ROLLS', 'operation_sequence' => 10, 'material_code' => 'RM-CORE-TUBE', 'quantity' => 2, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-SLIT-50-50IN', 'routing_number' => 'RT-SLT-ROLLS', 'operation_sequence' => 20, 'material_code' => 'RM-WRAP-FILM', 'quantity' => 0.5, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-SLIT-50-50IN', 'routing_number' => 'RT-SLT-ROLLS', 'operation_sequence' => 20, 'material_code' => 'RM-LABEL-ROLL', 'quantity' => 2, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-SLIT-50-20IN', 'routing_number' => 'RT-SLT-ROLLS', 'operation_sequence' => 10, 'material_code' => 'SFG-ROLL-50-WHT', 'quantity' => 100, 'is_alternative' => false, 'material_scrap_percentage' => 2, 'child_bom_code' => 'BOM-ROLL-50-WHT'],
            ['bom_number' => 'BOM-SLIT-50-20IN', 'routing_number' => 'RT-SLT-ROLLS', 'operation_sequence' => 10, 'material_code' => 'RM-CORE-TUBE', 'quantity' => 5, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-SLIT-50-20IN', 'routing_number' => 'RT-SLT-ROLLS', 'operation_sequence' => 20, 'material_code' => 'RM-WRAP-FILM', 'quantity' => 0.5, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-SLIT-50-20IN', 'routing_number' => 'RT-SLT-ROLLS', 'operation_sequence' => 20, 'material_code' => 'RM-LABEL-ROLL', 'quantity' => 5, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-BAG-DCUT', 'routing_number' => 'RT-BAG-DCUT', 'operation_sequence' => 10, 'material_code' => 'SFG-ROLL-50-WHT', 'quantity' => 13.6, 'is_alternative' => false, 'material_scrap_percentage' => 2, 'child_bom_code' => 'BOM-ROLL-50-WHT'],
            ['bom_number' => 'BOM-BAG-DCUT', 'routing_number' => 'RT-BAG-DCUT', 'operation_sequence' => 10, 'material_code' => 'SFG-SLIT-50-50IN', 'quantity' => 13.6, 'is_alternative' => true, 'priority' => 2, 'replaces_material_code' => 'SFG-ROLL-50-WHT', 'material_scrap_percentage' => 2, 'child_bom_code' => 'BOM-SLIT-50-50IN'],
            ['bom_number' => 'BOM-BAG-DCUT', 'routing_number' => 'RT-BAG-DCUT', 'operation_sequence' => 10, 'material_code' => 'SFG-SLIT-50-20IN', 'quantity' => 13.6, 'is_alternative' => true, 'priority' => 3, 'replaces_material_code' => 'SFG-ROLL-50-WHT', 'material_scrap_percentage' => 2, 'child_bom_code' => 'BOM-SLIT-50-20IN'],
            ['bom_number' => 'BOM-BAG-DCUT', 'routing_number' => 'RT-BAG-DCUT', 'operation_sequence' => 20, 'material_code' => 'RM-LABEL-ROLL', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-BAG-DCUT', 'routing_number' => 'RT-BAG-DCUT', 'operation_sequence' => 30, 'material_code' => 'RM-BOX-BAG', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-MASK-3PLY', 'routing_number' => 'RT-MASK-3PLY', 'operation_sequence' => 10, 'material_code' => 'SFG-ROLL-20-BLU', 'quantity' => 0.66, 'is_alternative' => false, 'material_scrap_percentage' => 2, 'child_bom_code' => 'BOM-ROLL-20-BLU'],
            ['bom_number' => 'BOM-MASK-3PLY', 'routing_number' => 'RT-MASK-3PLY', 'operation_sequence' => 10, 'material_code' => 'SFG-ROLL-20-WHT', 'quantity' => 0.66, 'is_alternative' => false, 'material_scrap_percentage' => 2, 'child_bom_code' => 'BOM-ROLL-20-WHT'],
            ['bom_number' => 'BOM-MASK-3PLY', 'routing_number' => 'RT-MASK-3PLY', 'operation_sequence' => 10, 'material_code' => 'RM-MELTBLOWN', 'quantity' => 0.6, 'is_alternative' => false, 'material_scrap_percentage' => 2, 'child_bom_code' => null],
            ['bom_number' => 'BOM-MASK-3PLY', 'routing_number' => 'RT-MASK-3PLY', 'operation_sequence' => 10, 'material_code' => 'RM-EARLOOP', 'quantity' => 0.4, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-MASK-3PLY', 'routing_number' => 'RT-MASK-3PLY', 'operation_sequence' => 10, 'material_code' => 'RM-NOSEWIRE', 'quantity' => 0.35, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-MASK-3PLY', 'routing_number' => 'RT-MASK-3PLY', 'operation_sequence' => 20, 'material_code' => 'RM-BOX-MASK', 'quantity' => 20, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-MASK-3PLY', 'routing_number' => 'RT-MASK-3PLY', 'operation_sequence' => 20, 'material_code' => 'RM-BOX-BAG', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
            ['bom_number' => 'BOM-MASK-3PLY', 'routing_number' => 'RT-MASK-3PLY', 'operation_sequence' => 20, 'material_code' => 'RM-LABEL-ROLL', 'quantity' => 1, 'is_alternative' => false, 'child_bom_code' => null],
        ];
    }
}
