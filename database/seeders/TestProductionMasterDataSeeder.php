<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TestProductionMasterDataSeeder extends Seeder
{
    /**
     * Seed Shifts, Pay Grades, Products, Stock, Employees, Work Centers, Sections, Machines, Batches, and Allocations.
     */
    public function run(int $tenantId = 1): void
    {
        $jsonPath = __DIR__ . '/data/production_test_data.json';
        if (!file_exists($jsonPath)) {
            $this->command?->error("Data file not found at: {$jsonPath}");
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) {
            $this->command?->error("Invalid JSON data in {$jsonPath}");
            return;
        }

        Schema::disableForeignKeyConstraints();

        // 1. Seed Products (38 test products)
        if (!empty($data['test_products'])) {
            $this->seedTable('products', $data['test_products'], $tenantId);
        }

        // 2. Ensure initial warehouse stocks & stock transactions for test products
        $this->seedInventoryStocksAndTransactions($tenantId);

        // 3. Seed Production Shifts (10 shifts)
        if (!empty($data['production_shifts'])) {
            $this->seedTable('production_shifts', $data['production_shifts'], $tenantId);
        }

        // 4. Seed External Worker Pay Grades (6 pay grades)
        if (!empty($data['production_external_worker_pay_grades'])) {
            $this->seedTable('production_external_worker_pay_grades', $data['production_external_worker_pay_grades'], $tenantId);
        }

        // 5. Seed Test Employees (30 test employees)
        if (!empty($data['test_employees'])) {
            $this->seedTable('employees', $data['test_employees'], $tenantId);
        }

        // 6. Seed Work Centers (3 WCs)
        if (!empty($data['production_work_centers'])) {
            $this->seedTable('production_work_centers', $data['production_work_centers'], $tenantId);
        }

        // 7. Seed Work Center Sections (10 Sections)
        if (!empty($data['production_work_center_sections'])) {
            $this->seedTable('production_work_center_sections', $data['production_work_center_sections'], $tenantId);
        }

        // 8. Seed Machines (15 Machines)
        if (!empty($data['production_machines'])) {
            $this->seedTable('production_machines', $data['production_machines'], $tenantId);
        }

        // 9. Seed Section Manual Productions (9 Batches)
        if (!empty($data['production_section_manual_productions'])) {
            $this->seedTable('production_section_manual_productions', $data['production_section_manual_productions'], $tenantId);
        }

        // 10. Seed Section Manual Production Employees Pivot (8 pivot entries)
        if (!empty($data['production_section_manual_production_employees'])) {
            $this->seedTable('production_section_manual_production_employees', $data['production_section_manual_production_employees'], $tenantId);
        }

        // 11. Seed Work Center Employees (29 staff assignments)
        if (!empty($data['production_work_center_employees'])) {
            $this->seedTable('production_work_center_employees', $data['production_work_center_employees'], $tenantId);
        }

        // 12. Seed External Worker Allocations (17 allocations)
        if (!empty($data['production_external_worker_allocations'])) {
            $this->seedTable('production_external_worker_allocations', $data['production_external_worker_allocations'], $tenantId);
        }

        // 13. Seed Calculations & Activity Logs
        if (!empty($data['production_work_center_calculations'])) {
            $this->seedTable('production_work_center_calculations', $data['production_work_center_calculations'], $tenantId);
        }
        if (!empty($data['production_work_center_section_calculations'])) {
            $this->seedTable('production_work_center_section_calculations', $data['production_work_center_section_calculations'], $tenantId);
        }
        if (!empty($data['production_work_center_activity_logs'])) {
            $this->seedTable('production_work_center_activity_logs', $data['production_work_center_activity_logs'], $tenantId);
        }

        Schema::enableForeignKeyConstraints();

        $this->command?->info("TestProductionMasterDataSeeder completed successfully for Tenant ID {$tenantId}.");
    }

    private function seedTable(string $table, array $rows, int $tenantId): void
    {
        if (empty($rows)) {
            return;
        }

        $hasTenantId = Schema::hasColumn($table, 'tenant_id');

        foreach ($rows as $row) {
            if ($hasTenantId) {
                $row['tenant_id'] = $tenantId;
            }

            // Remove timestamps if null or handle types
            if (isset($row['deleted_at']) && $row['deleted_at'] === null) {
                unset($row['deleted_at']);
            }

            // Check if primary key exists
            $id = $row['id'] ?? null;
            if ($id !== null) {
                DB::table($table)->updateOrInsert(['id' => $id], $row);
            } else {
                DB::table($table)->insert($row);
            }
        }
    }

    private function seedInventoryStocksAndTransactions(int $tenantId): void
    {
        $products = DB::table('products')->where('tenant_id', $tenantId)->where('sku', 'LIKE', 'test-%')->get();
        $warehouses = DB::table('warehouses')->where('tenant_id', $tenantId)->pluck('id', 'code')->toArray();
        $companyId = DB::table('companies')->where('tenant_id', $tenantId)->value('id') ?? 1;

        foreach ($products as $p) {
            $whCode = match ($p->type) {
                'raw_material' => 'WH-RM-TBL',
                'semi_finished' => 'WH-WIP-TBL',
                'finished_good' => 'WH-FG-TBL',
                default => 'WH-RM-TBL',
            };

            $whId = $warehouses[$whCode] ?? reset($warehouses);
            if (!$whId) {
                continue;
            }

            $qty = (float) ($p->opening_stock ?? 100);
            $rate = (float) ($p->cost_price ?? 50);

            DB::table('product_warehouse_stocks')->updateOrInsert(
                [
                    'tenant_id'    => $tenantId,
                    'product_id'   => $p->id,
                    'warehouse_id' => $whId,
                ],
                [
                    'company_id'    => $companyId,
                    'quantity'      => $qty,
                    'available_qty' => $qty,
                    'reserved_qty'  => 0.00,
                    'unit_cost'     => $rate,
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );

            DB::table('stock_transactions')->updateOrInsert(
                [
                    'tenant_id'      => $tenantId,
                    'product_id'     => $p->id,
                    'warehouse_id'   => $whId,
                    'reference_type' => 'Opening Stock',
                ],
                [
                    'company_id'   => $companyId,
                    'type'         => 'IN',
                    'reference_id' => $p->id,
                    'quantity'     => $qty,
                    'unit_cost'    => $rate,
                    'total_value'  => $qty * $rate,
                    'balance_qty'  => $qty,
                    'updated_at'   => now(),
                    'created_at'   => now(),
                ]
            );
        }
    }
}
