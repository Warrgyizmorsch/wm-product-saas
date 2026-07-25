<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOrderAndWipUiTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;
    private Product $product;
    private ProductionOrder $order;
    private ProductionWip $wip;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::factory()->create([
            'id'   => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role'      => 'admin',
        ]);
        $this->actingAs($this->user);

        $this->product = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Widget Ultra',
            'sku'       => 'WDG-ULT-01',
            'type'      => 'finished_good',
        ]);

        $this->order = ProductionOrder::create([
            'tenant_id'         => $this->tenantId,
            'order_number'      => 'PO-2026-9901',
            'product_id'        => $this->product->id,
            'quantity_ordered'  => 50,
            'quantity_produced' => 10,
            'status'            => ProductionOrder::STATUS_RELEASED,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(7)->toDateString(),
        ]);

        $this->wip = ProductionWip::create([
            'tenant_id'            => $this->tenantId,
            'production_order_id'  => $this->order->id,
            'product_id'           => $this->product->id,
            'quantity'             => 50,
            'available_quantity'   => 40,
            'completed_quantity'   => 10,
            'status'               => 'active',
        ]);
    }

    /** @test */
    public function production_order_index_page_renders_and_excludes_other_tenant_orders()
    {
        $otherTenantId = 999;
        Tenant::factory()->create(['id' => $otherTenantId, 'slug' => 'other-tenant']);

        $otherProduct = Product::create([
            'tenant_id' => $otherTenantId,
            'name'      => 'Foreign Item',
            'sku'       => 'FRG-01',
            'type'      => 'finished_good',
        ]);

        $otherOrder = ProductionOrder::create([
            'tenant_id'         => $otherTenantId,
            'order_number'      => 'PO-OTHER-777',
            'product_id'        => $otherProduct->id,
            'quantity_ordered'  => 100,
            'quantity_produced' => 0,
            'status'            => ProductionOrder::STATUS_DRAFT,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.index'));

        $response->assertStatus(200);
        $response->assertSee('PO-2026-9901');
        $response->assertDontSee('PO-OTHER-777');
    }

    /** @test */
    public function production_order_show_page_renders_with_null_relations_and_timeline()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $this->order->id));

        $response->assertStatus(200);
        $response->assertSee('PO-2026-9901');
        $response->assertSee('Widget Ultra');
    }

    /** @test */
    public function cross_tenant_production_order_show_access_is_denied()
    {
        $otherTenantId = 999;
        Tenant::factory()->create(['id' => $otherTenantId, 'slug' => 'other-tenant']);

        $otherProduct = Product::create([
            'tenant_id' => $otherTenantId,
            'name'      => 'Secret Item',
            'sku'       => 'SCR-01',
            'type'      => 'finished_good',
        ]);

        $otherOrder = ProductionOrder::create([
            'tenant_id'         => $otherTenantId,
            'order_number'      => 'PO-SECRET-001',
            'product_id'        => $otherProduct->id,
            'quantity_ordered'  => 10,
            'quantity_produced' => 0,
            'status'            => ProductionOrder::STATUS_DRAFT,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $otherOrder->id));

        $response->assertStatus(404);
    }

    /** @test */
    public function wip_index_page_renders_kpi_summary_cards_and_filters()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.wip.index'));

        $response->assertStatus(200);
        $response->assertSee('Total WIP Cards');
        $response->assertSee('PO-2026-9901');
    }

    /** @test */
    public function wip_show_page_renders_quantity_summary_and_empty_movement_history()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.wip.show', $this->wip->id));

        $response->assertStatus(200);
        $response->assertSee('Widget Ultra');
    }

    /** @test */
    public function cross_tenant_wip_access_is_denied()
    {
        $otherTenantId = 999;
        Tenant::factory()->create(['id' => $otherTenantId, 'slug' => 'other-tenant']);

        $otherProduct = Product::create([
            'tenant_id' => $otherTenantId,
            'name'      => 'Other WIP Item',
            'sku'       => 'OWIP-01',
            'type'      => 'finished_good',
        ]);

        $otherOrder = ProductionOrder::create([
            'tenant_id'         => $otherTenantId,
            'order_number'      => 'PO-OTHER-WIP',
            'product_id'        => $otherProduct->id,
            'quantity_ordered'  => 10,
            'quantity_produced' => 0,
            'status'            => ProductionOrder::STATUS_DRAFT,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(5)->toDateString(),
        ]);

        $otherWip = ProductionWip::create([
            'tenant_id'           => $otherTenantId,
            'production_order_id' => $otherOrder->id,
            'product_id'          => $otherProduct->id,
            'quantity'            => 10,
            'available_quantity'  => 10,
            'completed_quantity'  => 0,
            'status'              => 'active',
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.wip.show', $otherWip->id));

        $response->assertStatus(404);
    }

    /** @test */
    public function production_order_show_page_renders_generate_schedule_button_and_schedules_list()
    {
        // 1. Initially there are no schedules, verify the message is present
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $this->order->id));

        $response->assertStatus(200);
        $response->assertSee('Generate Schedule');
        $response->assertSee('No schedule has been generated for this production order yet.');

        // 2. Create a schedule for this production order
        $schedule = \App\Domains\Production\Models\ProductionSchedule::create([
            'tenant_id' => $this->tenantId,
            'schedule_number' => 'SCH-TEST-99',
            'production_order_id' => $this->order->id,
            'scheduling_type' => 'forward',
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'created_by' => $this->user->id,
        ]);

        // 3. Verify that the schedule is listed under the Overview tab and the button is hidden
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $this->order->id));

        $response->assertStatus(200);
        $response->assertSee('SCH-TEST-99');
        $response->assertDontSee('No schedule has been generated for this production order yet.');
        $response->assertDontSee('Generate Schedule');
    }
}
