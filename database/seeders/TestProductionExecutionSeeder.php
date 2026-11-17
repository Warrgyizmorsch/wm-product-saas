<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TestProductionExecutionSeeder extends Seeder
{
    /**
     * Seed Production Orders, Schedules, Operations, Requisitions, Reservations, Issues, Receipts, WIP, and Events.
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

        // 1. Production Orders
        if (!empty($data['production_orders'])) {
            $this->seedTable('production_orders', $data['production_orders'], $tenantId);
        }

        // 2. Order Operations & Dependencies
        if (!empty($data['production_order_operations'])) {
            $this->seedTable('production_order_operations', $data['production_order_operations'], $tenantId);
        }
        if (!empty($data['production_order_operation_dependencies'])) {
            $this->seedTable('production_order_operation_dependencies', $data['production_order_operation_dependencies'], $tenantId);
        }

        // 3. Progress Logs
        if (!empty($data['production_order_progress_logs'])) {
            $this->seedTable('production_order_progress_logs', $data['production_order_progress_logs'], $tenantId);
        }

        // 4. Reservations
        if (!empty($data['production_order_reservations'])) {
            $this->seedTable('production_order_reservations', $data['production_order_reservations'], $tenantId);
        }

        // 5. Material Requisition Slips & Items
        if (!empty($data['production_requisition_slips'])) {
            $this->seedTable('production_requisition_slips', $data['production_requisition_slips'], $tenantId);
        }
        if (!empty($data['production_requisition_slip_items'])) {
            $this->seedTable('production_requisition_slip_items', $data['production_requisition_slip_items'], $tenantId);
        }

        // 6. Material Issues & Batches
        if (!empty($data['production_order_issues'])) {
            $this->seedTable('production_order_issues', $data['production_order_issues'], $tenantId);
        }
        if (!empty($data['production_order_issue_batches'])) {
            $this->seedTable('production_order_issue_batches', $data['production_order_issue_batches'], $tenantId);
        }

        // 7. Order Receipts
        if (!empty($data['production_order_receipts'])) {
            $this->seedTable('production_order_receipts', $data['production_order_receipts'], $tenantId);
        }

        // 8. Schedules & Operations
        if (!empty($data['production_schedules'])) {
            $this->seedTable('production_schedules', $data['production_schedules'], $tenantId);
        }
        if (!empty($data['production_schedule_operations'])) {
            $this->seedTable('production_schedule_operations', $data['production_schedule_operations'], $tenantId);
        }

        // 9. WIP & WIP Transactions
        if (!empty($data['production_wips'])) {
            $this->seedTable('production_wips', $data['production_wips'], $tenantId);
        }
        if (!empty($data['production_wip_transactions'])) {
            $this->seedTable('production_wip_transactions', $data['production_wip_transactions'], $tenantId);
        }

        // 10. Event Timelines, Machine Downtimes, Machine States
        if (!empty($data['production_event_timelines'])) {
            $this->seedTable('production_event_timelines', $data['production_event_timelines'], $tenantId);
        }
        if (!empty($data['production_machine_downtimes'])) {
            $this->seedTable('production_machine_downtimes', $data['production_machine_downtimes'], $tenantId);
        }
        if (!empty($data['production_machine_state_histories'])) {
            $this->seedTable('production_machine_state_histories', $data['production_machine_state_histories'], $tenantId);
        }

        Schema::enableForeignKeyConstraints();

        $this->command?->info("TestProductionExecutionSeeder completed successfully for Tenant ID {$tenantId}.");
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
