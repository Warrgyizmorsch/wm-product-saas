<?php
/**
 * 00_reset_production.php
 * 
 * Safely resets all production data for tenant 1 back to a clean state.
 * Keeps HRMS employees, products, and the 3 active HRMS shifts.
 * 
 * Usage: php scratch_production/00_reset_production.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tenantId = 1;

echo "========================================================" . PHP_EOL;
echo "  RESET PRODUCTION DATA (Tenant ID: $tenantId)" . PHP_EOL;
echo "========================================================" . PHP_EOL . PHP_EOL;

$tablesToDelete = [
    // Calculation / log / history
    'production_work_center_calculations',
    'production_work_center_section_calculations',
    'production_work_center_activity_logs',
    'production_event_timelines',
    'production_machine_state_histories',
    'production_machine_downtimes',
    
    // Maintenance
    'production_maintenance_work_order_spares',
    'production_maintenance_work_order_logs',
    'production_maintenance_work_order_assignments',
    'production_maintenance_work_orders',
    'production_pm_schedules',
    
    // Manual production
    'production_section_manual_production_employees',
    'production_section_manual_productions',

    // Worker allocations
    'production_internal_worker_allocations',
    'production_external_worker_allocations',
    'production_external_worker_pay_grades',
    'production_work_center_employees',

    // Operator / skill
    'production_operator_assignment_logs',
    'production_operator_assignments',
    'production_operator_skills',
    
    // Quality
    'production_quality_inspection_results',
    'production_quality_inspections',
    'production_quality_plan_parameters',
    'production_quality_plans',
    'production_ncrs',
    'production_capas',
    'production_deviations',
    
    // ECO
    'production_eco_approvals',
    'production_eco_items',
    'production_ecos',
    
    // Schedule
    'production_schedule_scenario_operations',
    'production_schedule_scenarios',
    'production_schedule_optimization_runs',
    'production_schedule_change_logs',
    'production_schedule_operations',
    'production_schedules',
    
    // Orders & Transactions
    'production_scrap_disposals',
    'production_rework_operations',
    'production_rework_orders',
    'production_order_remnant_allocations',
    'production_order_progress_logs',
    'production_order_issue_batches',
    'production_order_scraps',
    'production_order_reworks',
    'production_order_receipts',
    'production_order_issues',
    'production_order_reservations',
    'production_order_operation_dependencies',
    'production_order_operations',
    'production_order_requests',
    'production_orders',
    
    // Plans
    'production_plan_operations',
    'production_plan_requirements',
    'production_plans',
    
    // Batches / serial / lot / scan
    'production_batch_genealogies',
    'production_batches',
    'production_serial_numbers',
    'production_lot_traces',
    'production_scan_logs',
    
    // WIP / Cost
    'production_wip_transactions',
    'production_wips',
    'production_cost_adjustments',
    
    // Requisitions
    'production_requisition_slip_items',
    'production_requisition_slips',
    
    // Delivery challans
    'delivery_challan_items',
    'delivery_challans',
    
    // Dashboard / KPI / alerts
    'production_dashboard_preferences',
    'production_kpi_targets',
    'production_alert_configurations',
    
    // Routing details
    'production_routing_operation_materials',
    'production_routing_operation_alternate_machines',
    'production_routing_approvals',
    'production_routing_operations',
    
    // BOM details
    'production_bom_approvals',
    'production_bom_items',
    'production_boms',
    
    // Routings
    'routings',
    
    // Machines -> Sections -> Work Centers
    'production_machines',
    'production_work_center_sections',
    'production_work_centers',
    
    // Calendar
    'production_calendar_holidays',
    'production_calendars',
];

DB::statement('SET FOREIGN_KEY_CHECKS=0');
DB::beginTransaction();

try {
    $totalDeleted = 0;
    foreach ($tablesToDelete as $table) {
        if (!Schema::hasTable($table)) continue;
        
        $hasTenantId = Schema::hasColumn($table, 'tenant_id');
        if ($hasTenantId) {
            $deleted = DB::table($table)->where('tenant_id', $tenantId)->delete();
        } else {
            if ($table === 'production_section_manual_production_employees') {
                $deleted = DB::table($table)->delete();
            } else {
                continue;
            }
        }
        
        if ($deleted > 0) {
            echo "  ✓ $table: $deleted rows cleared" . PHP_EOL;
            $totalDeleted += $deleted;
        }
    }
    
    DB::commit();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    echo PHP_EOL . "Done! Cleared $totalDeleted rows. Production is fresh and ready." . PHP_EOL;
} catch (\Exception $e) {
    DB::rollBack();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

