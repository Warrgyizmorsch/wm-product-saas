<?php

namespace Database\Seeders;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\CountdownCalculationEngine;
use App\Domains\Production\Services\RoutingOperationCostCalculator;
use App\Domains\Production\Services\SectionCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Non-woven demo plant — master data seeder.
 *
 * Seeds:
 *  - Shifts (NW-SH-*)
 *  - Warehouses (NW-WH-* for Branch 3)
 *  - Products (RM-*, SFG-*, FG-*, SCR-*)
 *  - Opening Stock (branch 3 warehouse stock & transactions)
 *  - Work Centers (NW-WC-*)
 *  - Sections (NW-SEC-*)
 *  - Machines (NW-EXT-*, NW-BAG-*, etc.)
 *  - Manual Batches (NW-BAT-*)
 *  - External Worker Pay Grades (NW-PG-*)
 *  - Work Center & Section Employees (production_work_center_employees)
 *  - Manual Batch Employees (production_section_manual_production_employees)
 *  - External Worker Allocations (production_external_worker_allocations)
 *  - Work Center & Section Calculation Snapshots (via CountdownCalculationEngine)
 *  - Routings (RT-*)
 *  - Routing Operations (OP-010, OP-020, etc., stamped with resource snapshot)
 *  - BOMs (BOM-*)
 *  - BOM Items (Tier 1 Recipe covering all operations)
 *  - Routing Operation Materials (Tier 2 Allocations covering all operations)
 *
 * Safely matched on CODE (never on fixed IDs). Nothing is wiped; foreign-key checks stay on.
 * Money in the data file is in INR and converted to base currency using config/currency.php (rate 85).
 *
 * NOT called by DatabaseSeeder. Run: php scratch_production/run_nonwoven_demo_seed.php
 */
class NonWovenDemoMasterDataSeeder extends Seeder
{
    public const DATA_FILE = __DIR__ . '/data/nonwoven_demo_data.json';
    public const DEMO_BRANCH_ID = 3;
    public const DEMO_COMPANY_ID = 1;

    /** Columns of production_machines that hold money. */
    private const MACHINE_MONEY_COLUMNS = ['energy_rate_per_unit', 'operating_cost_per_hour'];

    public function run(int $tenantId = 1): void
    {
        $data = self::loadData();
        if ($data === null) {
            $this->command?->error('Data file missing or invalid: ' . self::DATA_FILE);

            return;
        }

        $rate = self::rateToBase($data['meta']['money_currency'] ?? null);

        // 1. Shifts
        $shiftsCount = $this->seedShifts($data['production_shifts'] ?? [], $tenantId);

        // 2. Warehouses (Branch 3)
        $warehousesCount = $this->seedWarehouses($data['warehouses'] ?? [], $tenantId, self::DEMO_BRANCH_ID, self::DEMO_COMPANY_ID);

        // 3. Products (Shared across branches, branch_id = null)
        $productsCount = $this->seedProducts($data['products'] ?? [], $tenantId, self::DEMO_COMPANY_ID, $rate);

        // 4. Opening Stock (Branch 3)
        $stockCount = $this->seedOpeningStock($data['opening_stock'] ?? [], $tenantId, self::DEMO_BRANCH_ID, self::DEMO_COMPANY_ID, $rate);

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

        // 8c. Work Center & Section Employees
        $wcEmployeesCount = $this->seedWorkCenterEmployees($data['production_work_center_employees'] ?? [], $tenantId);

        // 8d. Manual Batch Employees
        $batchEmpCount = $this->seedManualBatchEmployees($data['production_section_manual_production_employees'] ?? [], $tenantId);

        // 8e. External Worker Allocations
        $extAllocCount = $this->seedExternalWorkerAllocations($data['production_external_worker_allocations'] ?? [], $tenantId);

        // 9. Work Center Calculations (Full Snapshot)
        $calcsCount = $this->recalculateWorkCenters($data['production_work_centers'] ?? [], $tenantId);

        // 10. Routings
        $routingsCount = $this->seedRoutings($data['production_routings'] ?? [], $tenantId);

        // 11. Routing Operations (calculated & stamped)
        $opsCount = $this->seedRoutingOperations($data['production_routing_operations'] ?? [], $tenantId);

        // 12. BOMs
        $bomsCount = $this->seedBoms($data['production_boms'] ?? [], $tenantId);

        // 13. BOM Items (Tier 1 Recipe)
        $bomItemsCount = $this->seedBomItems($data['production_bom_items'] ?? [], $tenantId);

        // 14. Routing Operation Materials (Tier 2 Allocations)
        $allocCount = $this->seedRoutingOperationMaterials($data['production_routing_operation_materials'] ?? [], $tenantId);

        $this->command?->info("NonWovenDemoMasterDataSeeder completed for tenant {$tenantId}:");
        $this->command?->info("  Shifts: {$shiftsCount}, Warehouses: {$warehousesCount}, Products: {$productsCount}, Stock Rows: {$stockCount}");
        $this->command?->info("  Work Centers: {$wcCount}, Sections: {$secCount}, Machines: {$machinesCount}, Batches: {$batchesCount}");
        $this->command?->info("  Pay Grades: {$payGradesCount}, Staff: {$wcEmployeesCount}, Batch Links: {$batchEmpCount}, Allocations: {$extAllocCount}");
        $this->command?->info("  Calculations: {$calcsCount}, Routings: {$routingsCount}, Operations: {$opsCount}");
        $this->command?->info("  BOMs: {$bomsCount}, BOM Items: {$bomItemsCount}, Material Allocations: {$allocCount}");
    }

    public static function loadData(): ?array
    {
        if (!file_exists(self::DATA_FILE)) {
            return null;
        }

        $data = json_decode(file_get_contents(self::DATA_FILE), true);

        return is_array($data) ? $data : null;
    }

    /** Units of the data file's currency per 1 unit of base currency (1 when the file is already in base). */
    public static function rateToBase(?string $currency): float
    {
        if (!$currency || $currency === config('currency.base', 'USD')) {
            return 1.0;
        }

        $rate = (float) config("currency.currencies.{$currency}.rate", 0);
        if ($rate <= 0) {
            throw new \RuntimeException("No exchange rate configured for {$currency} in config/currency.php.");
        }

        return $rate;
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
                $values['cost_price'] = round((float) $row['unit_cost'] / $rate, 4);
            }

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

            if (!$productId || !$warehouseId) continue;

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
            if (!$code) continue;

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

            if (!$code || !$wcId) continue;

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
            if (!$code) continue;

            $values = array_intersect_key($row, array_flip($columns));
            unset($values['id'], $values['tenant_id'], $values['code']);

            foreach (self::MACHINE_MONEY_COLUMNS as $col) {
                if (isset($row[$col])) {
                    $values[$col] = round((float) $row[$col] / $rate, 4);
                }
            }

            if (isset($row['section_code']) && isset($secMap[$row['section_code']])) {
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
        $count = 0;

        foreach ($rows as $row) {
            $batchName = $row['batch_name'] ?? null;
            $secCode = $row['section_code'] ?? null;
            $shiftCode = $row['shift_code'] ?? null;

            $secId = $secMap[$secCode] ?? null;
            $shiftId = $shiftsMap[$shiftCode] ?? null;

            if (!$batchName || !$secId || !$shiftId) continue;

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
            if (!$code) continue;

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

    // ─── 8C. WORK CENTER & SECTION EMPLOYEES ──────────────────────────────────

    private function seedWorkCenterEmployees(array $rows, int $tenantId): int
    {
        $wcMap = DB::table('production_work_centers')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $secMap = DB::table('production_work_center_sections')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $count = 0;

        foreach ($rows as $row) {
            $wcId = $wcMap[$row['work_center_code']] ?? null;
            $secId = !empty($row['section_code']) ? ($secMap[$row['section_code']] ?? null) : null;
            $empId = (int) $row['employee_id'];

            if (!$wcId || !$empId) continue;

            DB::table('production_work_center_employees')->updateOrInsert([
                'tenant_id' => $tenantId,
                'work_center_id' => $wcId,
                'section_id' => $secId,
                'employee_id' => $empId,
            ], [
                'active' => $row['active'] ?? 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    // ─── 8D. MANUAL BATCH EMPLOYEES ───────────────────────────────────────────

    private function seedManualBatchEmployees(array $rows, int $tenantId): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $batchName = $row['batch_name'] ?? null;
            $empId = (int) $row['employee_id'];

            $batchId = DB::table('production_section_manual_productions')
                ->where('tenant_id', $tenantId)
                ->where('batch_name', $batchName)
                ->value('id');

            if (!$batchId || !$empId) continue;

            $wcEmpId = DB::table('production_work_center_employees')
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $empId)
                ->value('id');

            if (!$wcEmpId) continue;

            DB::table('production_section_manual_production_employees')->updateOrInsert([
                'manual_production_id' => $batchId,
                'work_center_employee_id' => $wcEmpId,
            ], [
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    // ─── 8E. EXTERNAL WORKER ALLOCATIONS ──────────────────────────────────────

    private function seedExternalWorkerAllocations(array $rows, int $tenantId): int
    {
        $secMap = DB::table('production_work_center_sections')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $shiftMap = DB::table('production_shifts')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $pgMap = DB::table('production_external_worker_pay_grades')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
        $batchMap = DB::table('production_section_manual_productions')->where('tenant_id', $tenantId)->pluck('id', 'batch_name')->all();

        $count = 0;
        foreach ($rows as $row) {
            $secId = $secMap[$row['section_code']] ?? null;
            $shiftId = $shiftMap[$row['shift_code']] ?? null;
            $pgId = $pgMap[$row['pay_grade_code']] ?? null;
            $batchId = !empty($row['batch_name']) ? ($batchMap[$row['batch_name']] ?? null) : null;

            if (!$secId || !$shiftId || !$pgId) continue;

            DB::table('production_external_worker_allocations')->updateOrInsert([
                'tenant_id' => $tenantId,
                'section_id' => $secId,
                'shift_id' => $shiftId,
                'pay_grade_id' => $pgId,
                'manual_production_id' => $batchId,
            ], [
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
                $this->command?->warn("Calculation for WC {$wc->code} deferred: {$e->getMessage()}");
            }
        }

        return $count;
    }

    // ─── 10. ROUTINGS ──────────────────────────────────────────────────────────

    private function seedRoutings(array $rows, int $tenantId): int
    {
        $count = 0;
        foreach ($rows as $row) {
            Routing::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenantId,
                'routing_number' => $row['routing_number'],
            ], [
                'name' => $row['name'],
                'version' => $row['version'] ?? '1.0',
                'revision' => 1,
                'is_default' => $row['is_default'] ?? 1,
                'status' => 'active',
                'effective_from' => now()->subDay()->toDateString(),
            ]);
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

        foreach ($rows as $row) {
            $routing = Routing::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('routing_number', $row['routing_number'])
                ->first();

            if (!$routing) {
                continue;
            }

            $section = WorkCenterSection::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', $row['section_code'])
                ->first();

            $machineId = null;
            if (!empty($row['machine_code'])) {
                $machineId = DB::table('production_machines')
                    ->where('tenant_id', $tenantId)
                    ->where('code', $row['machine_code'])
                    ->value('id');
            }

            $batchId = null;
            if (!empty($row['batch_name'])) {
                $batchId = DB::table('production_section_manual_productions')
                    ->where('tenant_id', $tenantId)
                    ->where('batch_name', $row['batch_name'])
                    ->value('id');
            }

            $opData = [
                'tenant_id' => $tenantId,
                'routing_id' => $routing->id,
                'sequence' => $row['sequence'],
                'operation_number' => $row['operation_number'],
                'name' => $row['name'],
                'operation_type' => $row['operation_type'] ?? 'manufacturing',
                'work_center_id' => $section?->work_center_id,
                'section_id' => $section?->id,
                'machine_id' => $machineId,
                'section_manual_production_id' => $batchId,
                'setup_time_minutes' => (float) ($row['setup_time_minutes'] ?? 0),
                'processing_time_minutes' => (float) ($row['processing_time_minutes'] ?? 0),
                'expected_yield_percentage' => (float) ($row['expected_yield_percentage'] ?? 100),
                'quality_required' => (bool) ($row['quality_required'] ?? false),
            ];

            if ($section) {
                try {
                    $resources = $sectionService->getSnapshotResourceCosts($section);
                    $calcResult = $calc->calculate($opData, $resources);

                    if ($calcResult['error'] === null) {
                        foreach (RoutingOperationCostCalculator::FIELDS as $field) {
                            $opData[$field] = $calcResult['figures'][$field] ?? 0;
                        }
                        $opData['resource_snapshot'] = $calc->buildResourceSnapshot($opData, $calcResult['resource'], $resources);
                        $opData['section_calculation_id'] = $resources['section_calculation_id'] ?? null;
                        $opData['section_snapshot_number'] = $resources['snapshot_number'] ?? null;
                        $opData['work_center_calculation_id'] = $resources['work_center_calculation_id'] ?? null;
                        $opData['costs_calculated_at'] = now();
                    }
                } catch (\Throwable $e) {
                    // Fallback: save without snapshot if engine not ready
                }
            }

            RoutingOperation::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenantId,
                'routing_id' => $routing->id,
                'sequence' => $row['sequence'],
            ], $opData);

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
                continue;
            }

            $routing = Routing::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('routing_number', $row['routing_number'])
                ->first();

            ProductionBom::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenantId,
                'bom_number' => $row['bom_number'],
                'version' => $row['version'] ?? '1.0',
            ], [
                'bom_name' => $row['bom_name'],
                'bom_type' => 'manufacturing',
                'usage_context' => 'manufacturing',
                'product_id' => $product->id,
                'routing_id' => $routing?->id,
                'base_quantity' => (float) ($row['base_quantity'] ?? 1.0),
                'base_uom_id' => $row['uom_id'] ?? 4,
                'revision' => 1,
                'status' => 'approved',
                'is_active' => true,
                'is_default' => $row['is_default'] ?? 1,
                'effective_date' => now()->subDay()->toDateString(),
            ]);

            $count++;
        }

        return $count;
    }

    // ─── 13. BOM ITEMS (TIER 1 RECIPE) ─────────────────────────────────────────

    private function seedBomItems(array $rows, int $tenantId): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('bom_number', $row['bom_number'])
                ->first();

            $material = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('sku', $row['material_code'])
                ->first();

            if (!$bom || !$material) {
                continue;
            }

            ProductionBomItem::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenantId,
                'bom_id' => $bom->id,
                'material_id' => $material->id,
            ], [
                'quantity' => (float) $row['quantity'],
                'alternative_quantity' => isset($row['alternative_quantity']) ? (float) $row['alternative_quantity'] : null,
                'gross_quantity' => (float) ($row['gross_quantity'] ?? $row['quantity']),
                'uom_id' => $row['uom_id'] ?? 4,
                'allocation_role' => $row['allocation_role'] ?? 'primary',
                'material_scrap_percentage' => (float) ($row['material_scrap_percentage'] ?? 0.0),
            ]);

            $count++;
        }

        return $count;
    }

    // ─── 14. ROUTING OPERATION MATERIALS (TIER 2 ALLOCATIONS) ──────────────────

    private function seedRoutingOperationMaterials(array $rows, int $tenantId): int
    {
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

            if (!$bom || !$routing) {
                continue;
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

            if (!$op || !$material) {
                continue;
            }

            $bomItem = ProductionBomItem::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('bom_id', $bom->id)
                ->where('material_id', $material->id)
                ->first();

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

            RoutingOperationMaterial::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenantId,
                'bom_id' => $bom->id,
                'routing_operation_id' => $op->id,
                'material_id' => $material->id,
                'is_alternative' => $isAlt,
            ], [
                'bom_item_id' => $bomItem?->id,
                'quantity' => (float) $row['quantity'],
                'uom_id' => $row['uom_id'] ?? 4,
                'priority' => isset($row['priority']) ? (int) $row['priority'] : null,
                'replaces_routing_material_id' => $replacesId,
                'material_scrap_percentage' => (float) ($row['material_scrap_percentage'] ?? 0.0),
                'gross_quantity' => RoutingOperationMaterial::calculateGrossQuantity((float) $row['quantity'], (float) ($row['material_scrap_percentage'] ?? 0.0)),
            ]);

            $count++;
        }

        return $count;
    }
}
