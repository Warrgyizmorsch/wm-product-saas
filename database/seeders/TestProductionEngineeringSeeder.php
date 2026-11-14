<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TestProductionEngineeringSeeder extends Seeder
{
    /**
     * Seed Routings, Operations, Materials, Approvals, BOMs, and BOM Items.
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

        // 1. Routings
        if (!empty($data['routings'])) {
            $this->seedTable('routings', $data['routings'], $tenantId);
        }

        // 2. Routing Operations
        if (!empty($data['production_routing_operations'])) {
            $this->seedTable('production_routing_operations', $data['production_routing_operations'], $tenantId);
        }

        // 3. Routing Operation Materials
        if (!empty($data['production_routing_operation_materials'])) {
            $this->seedTable('production_routing_operation_materials', $data['production_routing_operation_materials'], $tenantId);
        }

        // 4. Routing Approvals
        if (!empty($data['production_routing_approvals'])) {
            $this->seedTable('production_routing_approvals', $data['production_routing_approvals'], $tenantId);
        }

        // 5. BOMs
        if (!empty($data['production_boms'])) {
            $this->seedTable('production_boms', $data['production_boms'], $tenantId);
        }

        // 6. BOM Items
        if (!empty($data['production_bom_items'])) {
            $this->seedTable('production_bom_items', $data['production_bom_items'], $tenantId);
        }

        // 7. BOM Approvals
        if (!empty($data['production_bom_approvals'])) {
            $this->seedTable('production_bom_approvals', $data['production_bom_approvals'], $tenantId);
        }

        Schema::enableForeignKeyConstraints();

        $this->command?->info("TestProductionEngineeringSeeder completed successfully for Tenant ID {$tenantId}.");
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

            if (isset($row['deleted_at']) && $row['deleted_at'] === null) {
                unset($row['deleted_at']);
            }

            $id = $row['id'] ?? null;
            if ($id !== null) {
                DB::table($table)->updateOrInsert(['id' => $id], $row);
            } else {
                DB::table($table)->insert($row);
            }
        }
    }
}
