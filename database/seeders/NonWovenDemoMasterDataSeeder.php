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
 *  - Dedicated Demo Employees (NW-EMP-* for Branch 3)
 *  - Work Center & Section Employees (production_work_center_employees)
 *  - Manual Batch Employees (production_section_manual_production_employees)
 *  - External Worker Allocations (production_external_worker_allocations)
 *  - Work Center & Section Calculation Snapshots (via CountdownCalculationEngine)
 *  - Routings (RT-*) - Approved with cost_snapshot and audit log
 *  - Routing Operations (calculated & resource stamped, sequenced and chained)
 *  - BOMs (BOM-*) - Approved with audit log
 *  - BOM Items (Tier 1 Recipe)
 *  - Routing Operation Materials (Tier 2 Allocations with child BOM links)
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

        // 8c. Dedicated Demo Employees (Branch 3)
        $empMap = $this->seedEmployees($data['employees'] ?? [], $tenantId, self::DEMO_COMPANY_ID, self::DEMO_BRANCH_ID, $rate);
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
        $bomItemsCount = $this->seedBomItems($data['production_bom_items'] ?? [], $tenantId);

        // 14. Routing Operation Materials (Tier 2 Allocations)
        $allocCount = $this->seedRoutingOperationMaterials($data['production_routing_operation_materials'] ?? [], $tenantId);

        // 14b. Approve BOMs (generates status=approved, ProductionBomApproval audit logs)
        $approvedBomsCount = $this->approveBoms($data['production_boms'] ?? [], $tenantId);

        $this->command?->info("NonWovenDemoMasterDataSeeder completed for tenant {$tenantId}:");
        $this->command?->info("  Shifts: {$shiftsCount}, Warehouses: {$warehousesCount}, Products: {$productsCount}, Stock Rows: {$stockCount}");
        $this->command?->info("  Work Centers: {$wcCount}, Sections: {$secCount}, Machines: {$machinesCount}, Batches: {$batchesCount}");
        $this->command?->info("  Pay Grades: {$payGradesCount}, Employees: {$employeesCount}, Staff Links: {$wcEmployeesCount}, Batch Links: {$batchEmpCount}, Allocations: {$extAllocCount}");
        $this->command?->info("  Calculations: {$calcsCount}, Routings: {$routingsCount} (Approved: {$approvedRoutingsCount}), Operations: {$opsCount}");
        $this->command?->info("  BOMs: {$bomsCount} (Approved: {$approvedBomsCount}), BOM Items: {$bomItemsCount}, Material Allocations: {$allocCount}");
    }

    public static function loadData(): ?array
    {
        if (!file_exists(self::DATA_FILE)) {
            return null;
        }

        $data = json_decode(file_get_contents(self::DATA_FILE), true);

        return is_array($data) ? $data : null;
    }

    /** Units of the data file's currency per 1 unit of base currency (85 INR per USD). */
    public static function rateToBase(?string $currency): float
    {
        if (!$currency) {
            return 1.0;
        }

        if (config('currency.base') === 'USD') {
            return (float) config("currency.currencies.{$currency}.rate", 85.0);
        }

        if ($currency === 'INR') {
            $usdRate = (float) config('currency.currencies.USD.rate', 0.011765);

            return $usdRate > 0 ? round(1.0 / $usdRate, 4) : 85.0;
        }

        $rate = (float) config("currency.currencies.{$currency}.rate", 0);

        return $rate > 0 ? $rate : 1.0;
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

            $empMap[$code] = $emp->id;
        }

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
                'base_uom_id' => $row['uom_id'] ?? 4,
                'version' => $row['version'] ?? '1.0.0',
                'revision' => 1,
                'status' => 'draft',
                'is_active' => true,
                'is_default' => $row['is_default'] ?? 1,
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

    private function seedBomItems(array $rows, int $tenantId): int
    {
        // Delete stale BOM items for demo BOMs
        $bomNumbers = array_unique(array_column($rows, 'bom_number'));
        $bomIds = DB::table('production_boms')
            ->where('tenant_id', $tenantId)
            ->whereIn('bom_number', $bomNumbers)
            ->pluck('id')
            ->all();

        if (!empty($bomIds)) {
            DB::table('production_bom_items')
                ->where('tenant_id', $tenantId)
                ->whereIn('bom_id', $bomIds)
                ->delete();
        }

        $hasChildBom = Schema::hasColumn('production_bom_items', 'child_bom_id');
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

            if (!$bom) {
                throw new \RuntimeException("BOM {$row['bom_number']} not found for BOM item");
            }
            if (!$material) {
                throw new \RuntimeException("Material {$row['material_code']} not found for BOM {$row['bom_number']}");
            }

            $childBomId = null;
            if (!empty($row['child_bom_code'])) {
                $childBomId = DB::table('production_boms')
                    ->where('tenant_id', $tenantId)
                    ->where('bom_number', $row['child_bom_code'])
                    ->value('id');

                if (!$childBomId) {
                    throw new \RuntimeException("Child BOM {$row['child_bom_code']} not found for BOM {$row['bom_number']}");
                }
            }

            $itemData = [
                'tenant_id' => $tenantId,
                'bom_id' => $bom->id,
                'material_id' => $material->id,
                'quantity' => (float) $row['quantity'],
                'alternative_quantity' => isset($row['alternative_quantity']) ? (float) $row['alternative_quantity'] : null,
                'gross_quantity' => (float) ($row['gross_quantity'] ?? $row['quantity']),
                'uom_id' => $row['uom_id'] ?? 4,
                'allocation_role' => $row['allocation_role'] ?? 'primary',
                'material_scrap_percentage' => (float) ($row['material_scrap_percentage'] ?? 0.0),
            ];

            if ($hasChildBom && $childBomId) {
                $itemData['child_bom_id'] = $childBomId;
            }

            ProductionBomItem::withoutGlobalScopes()->create($itemData);
            $count++;
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
}
