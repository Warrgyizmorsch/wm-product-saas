<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class MultiModelUiParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $tenantId = 1;
    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create([
            'id' => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->seed(RbacSeeder::class);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'admin',
        ]);

        foreach (['super_admin', 'production_manager'] as $roleSlug) {
            $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->first();
            if ($role) {
                UserRole::create([
                    'tenant_id' => $this->tenantId,
                    'user_id' => $this->user->id,
                    'role_id' => $role->id,
                ]);
            }
        }

        $this->actingAs($this->user);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Piece',
            'code' => 'PCS',
            'status' => 'active',
        ]);

        session(['tenant_id' => $this->tenantId, 'errors' => new ViewErrorBag()]);
        view()->share('errors', new ViewErrorBag());
    }

    public function test_product_create_and_edit_page_contains_default_production_model_field(): void
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('inventory.products.create'));
        $response->assertStatus(200);
        $response->assertSee('default_production_model');
        $response->assertSee('Pure Manufacturing');
        $response->assertSee('Complete Subcontracting');

        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Subcontract Item',
            'sku' => 'SUB-ITEM-01',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'default_production_model' => 'subcontract_company_material',
        ]);

        $editResponse = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('inventory.products.edit', $product->id));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('default_production_model');
        $editResponse->assertSee('Subcontracting with Company Material');
    }

    public function test_bom_create_and_edit_page_contains_bom_type_contextual_help(): void
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.boms.create'));
        $response->assertStatus(200);
        $response->assertSee('Manufacturing:');
        $response->assertSee('Subcontracting:');
    }

    public function test_production_order_create_page_contains_production_model_select(): void
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.create'));
        $response->assertStatus(200);
        $response->assertSee('production_model');
        $response->assertSee('Pure Manufacturing');
        $response->assertSee('Hybrid Manufacturing + Subcontracting');
    }

    public function test_get_engineering_options_returns_default_production_model(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Hybrid Product',
            'sku' => 'HYB-001',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'default_production_model' => 'hybrid',
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->getJson(route('production.plans.engineering-options', ['product_id' => $product->id]));

        $response->assertStatus(200);
        $response->assertJsonPath('default_production_model', 'hybrid');
    }

    public function test_production_order_detail_renders_subcontracting_tab_with_cost_and_balance_details(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Subcontract Widget',
            'sku' => 'SUB-WDG-01',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $product->id,
            'bom_number' => 'BOM-SUB-01',
            'bom_name' => 'Subcontract BOM',
            'version' => '1.0',
            'status' => 'active',
            'effective_date' => now()->toDateString(),
        ]);
        
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-2026-SUB1',
            'product_id' => $product->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 10,
            'production_model' => 'subcontract_company_material',
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $wc = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Outsource Work Center',
            'code' => 'WC-OUT-01',
            'status' => 'active',
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'work_center_id' => $wc->id,
            'operation_number' => 'OP-020',
            'name' => 'Coating Operation',
            'sequence' => 1,
            'sequence_order' => 1,
            'is_external' => true,
            'subcontract_cost_per_unit' => 500,
            'subcontract_lead_time_days' => 3,
            'dispatch_buffer_days' => 1,
            'return_buffer_days' => 1,
            'status' => 'ready',
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-subcontract']));
        $response->assertStatus(200);
        $response->assertSee('Company Material Subcontracting');
        $response->assertSee('Subcontracting');
        $response->assertSee('Authoritative Cost');
        $response->assertSee('Company Material Balance at Subcontractor');
    }

    public function test_mes_operator_screen_renders_external_subcontract_panel_when_is_external_true(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Turnkey Assembly',
            'sku' => 'TRN-ASY-01',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $product->id,
            'bom_number' => 'BOM-TRN-01',
            'bom_name' => 'Turnkey BOM',
            'version' => '1.0',
            'status' => 'active',
            'effective_date' => now()->toDateString(),
        ]);

        $wc = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Assembly Work Center',
            'code' => 'WC-ASY-01',
            'status' => 'active',
        ]);
        
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-2026-TRN1',
            'product_id' => $product->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 20,
            'status' => 'in_progress',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'work_center_id' => $wc->id,
            'operation_number' => 'OP-010',
            'name' => 'External Heat Treatment',
            'sequence' => 1,
            'sequence_order' => 1,
            'is_external' => true,
            'material_supply_type' => 'company_supplied',
            'subcontract_lead_time_days' => 5,
            'status' => 'ready',
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.operator.execution', $op->id));
        $response->assertStatus(200);
        $response->assertSee('External Subcontracted Operation');
        $response->assertSee('Company Supplied');
        $response->assertSee('Procurement Status');
        $response->assertSee('Dispatch');
    }

    public function test_production_dashboard_renders_subcontracting_metrics_card(): void
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Multi-Model Subcontracting');
        $response->assertSee('Awaiting PR');
        $response->assertSee('At Vendor');
        $response->assertSee('Vendor Delayed');
        $response->assertSee('QC Pending');
        $response->assertSee('Vendor Rework');
    }

    public function test_external_subcontract_operation_bypasses_unassigned_machine_validation(): void
    {
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Header Tank Crimping & TIG Welding Center',
            'code' => 'WC-WELD-01',
            'status' => 'active',
        ]);

        \App\Domains\Production\Models\Machine::create([
            'tenant_id' => $this->tenantId,
            'work_center_id' => $wc->id,
            'name' => 'TIG Machine 1',
            'code' => 'MCH-TIG-01',
            'status' => 'active',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Subcontract Tank Item',
            'sku' => 'SUB-TNK-01',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-2026-TNK1',
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $orderOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'work_center_id' => $wc->id,
            'operation_number' => 'OP-020',
            'name' => 'Outsourced TIG Welding',
            'sequence' => 20,
            'sequence_order' => 20,
            'is_external' => true,
            'status' => 'ready',
        ]);

        $schedule = \App\Domains\Production\Models\ProductionSchedule::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'schedule_number' => 'SCH-2026-TNK1',
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        \App\Domains\Production\Models\ProductionScheduleOperation::create([
            'tenant_id' => $this->tenantId,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $orderOp->id,
            'work_center_id' => $wc->id,
            'machine_id' => null,
            'sequence' => 20,
            'planned_start' => now(),
            'planned_finish' => now()->addHours(4),
            'status' => 'waiting',
        ]);

        $validationService = app(\App\Domains\Production\Services\SchedulePreReleaseValidationService::class);
        $result = $validationService->validate($schedule);

        $unassignedErrors = array_filter($result['errors'], fn($e) => $e['code'] === 'UNASSIGNED_MACHINE');
        $this->assertEmpty($unassignedErrors, 'External outsourced operation should not throw UNASSIGNED_MACHINE error.');
        $this->assertTrue($result['can_release']);
    }
}
