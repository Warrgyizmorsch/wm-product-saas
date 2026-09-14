<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Controllers\ScannerController;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductionRemainingUiGapsTargetedTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $pmUser;
    private User $operatorUser;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Targeted UI Gap Tenant',
            'slug'   => 'targeted-ui-gap-tenant',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->pmUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Production Manager',
            'email'     => 'pm-targeted@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'production_manager',
        ]);

        $this->operatorUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Machine Operator',
            'email'     => 'operator-targeted@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'machine_operator',
        ]);

        $uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Pieces',
            'code'      => 'PCS',
        ]);

        $this->product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'Industrial Gearbox FG',
            'sku'            => 'FG-GEAR-001',
            'type'           => 'manufactured',
            'item_type'      => 'Goods',
            'variation_type' => 'Single',
            'status'         => 'active',
            'selling_price'  => 500,
            'cost_price'     => 300,
            'unit_cost'      => 300,
            'uom_id'         => $uom->id,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id'     => $this->tenant->id,
            'name'          => 'Machining Center 1',
            'code'          => 'WC-MC-01',
            'capacity_type' => 'machine',
            'cost_per_hour' => 50.0,
            'status'        => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'         => $this->tenant->id,
            'work_center_id'    => $this->workCenter->id,
            'name'              => 'CNC Milling Machine Alpha',
            'code'              => 'MCH-CNC-001',
            'capacity_per_hour' => 10.0,
            'hourly_cost'       => 75.0,
            'status'            => 'active',
        ]);
    }

    public function test_machine_scanner_resolves_and_redirects_to_mes_machine_show_without_exception(): void
    {
        $this->actingAs($this->pmUser);
        session(['tenant_id' => $this->tenant->id]);

        $scannerController = app(ScannerController::class);

        $scanRequest = new Request([
            'code'   => 'MCH:' . $this->machine->code,
            'action' => 'view',
        ]);

        $response = $scannerController->scan($scanRequest);

        $this->assertEquals(302, $response->getStatusCode());
        $expectedUrl = route('production.mes.machines.show', $this->machine->id);
        $this->assertEquals($expectedUrl, $response->getTargetUrl());

        // Now test following the redirect as PM (who has production.mes.execute)
        $detailResponse = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->get($expectedUrl);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee($this->machine->name);
        $detailResponse->assertSee($this->machine->code);
    }

    public function test_machine_scanner_preserves_rbac_and_non_machine_scans(): void
    {
        $this->actingAs($this->pmUser);
        session(['tenant_id' => $this->tenant->id]);

        $scannerController = app(ScannerController::class);

        // Scan Work Center -> should redirect to work-centers.show
        $wcScanRequest = new Request([
            'code'   => 'WKC:' . $this->workCenter->code,
            'action' => 'view',
        ]);
        $wcResponse = $scannerController->scan($wcScanRequest);
        $this->assertEquals(302, $wcResponse->getStatusCode());
        $this->assertEquals(route('production.work-centers.show', $this->workCenter->id), $wcResponse->getTargetUrl());

        // Scan Product -> should redirect to inventory.products.show
        $prodScanRequest = new Request([
            'code'   => 'PRD:' . $this->product->sku,
            'action' => 'view',
        ]);
        $prodResponse = $scannerController->scan($prodScanRequest);
        $this->assertEquals(302, $prodResponse->getStatusCode());
        $this->assertEquals(route('inventory.products.show', $this->product->id), $prodResponse->getTargetUrl());
    }

    public function test_routing_creation_and_update_with_transfer_lag_minutes(): void
    {
        $this->actingAs($this->pmUser);
        session(['tenant_id' => $this->tenant->id]);

        // 1. Create Routing with transfer_lag_minutes = 15.0
        $createData = [
            'name'            => 'Precision Milling Routing',
            'product_id'      => $this->product->id,
            'version'         => '1.0',
            'is_default'      => true,
            'effective_from'  => today()->toDateString(),
            'operations'      => [
                [
                    'sequence'                  => 10,
                    'name'                      => 'Rough Cut Milling',
                    'operation_type'            => 'manufacturing',
                    'work_center_id'            => $this->workCenter->id,
                    'machine_id'                => $this->machine->id,
                    'setup_time_minutes'        => 30.0,
                    'processing_time_minutes'   => 12.0,
                    'wait_time_minutes'         => 5.0,
                    'expected_yield_percentage' => 100.0,
                    'queue_threshold_enabled'   => 1,
                    'transfer_batch_quantity'   => 20.0,
                    'transfer_lag_minutes'      => 15.0,
                ],
                [
                    'sequence'                  => 20,
                    'name'                      => 'Finish Polishing',
                    'operation_type'            => 'manufacturing',
                    'work_center_id'            => $this->workCenter->id,
                    'setup_time_minutes'        => 10.0,
                    'processing_time_minutes'   => 8.0,
                    'wait_time_minutes'         => 0.0,
                    'expected_yield_percentage' => 100.0,
                    'queue_threshold_enabled'   => 0,
                    'transfer_batch_quantity'   => 0.0,
                    'transfer_lag_minutes'      => 0,
                ],
            ],
        ];

        $storeResponse = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.routing.store'), $createData);

        $routing = Routing::where('tenant_id', $this->tenant->id)->where('name', 'Precision Milling Routing')->firstOrFail();
        $storeResponse->assertRedirect(route('production.routing.show', $routing->id));
        $this->assertCount(2, $routing->operations);

        $op1 = $routing->operations->where('sequence', 10)->first();
        $this->assertNotNull($op1);
        $this->assertEquals(15, (int) $op1->transfer_lag_minutes);
        $this->assertEquals(20.0, (float) $op1->transfer_batch_quantity);
        $this->assertTrue((bool) $op1->queue_threshold_enabled);

        $op2 = $routing->operations->where('sequence', 20)->first();
        $this->assertNotNull($op2);
        $this->assertEquals(0, (int) ($op2->transfer_lag_minutes ?? 0));

        // 2. Test Edit Page Loads transfer_lag_minutes in Alpine state
        $editResponse = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.routing.edit', $routing->id));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('transfer_lag_minutes: 15', false);
        $editResponse->assertSee('transfer_lag_minutes: 0', false);
        $editResponse->assertSee('Lag (m):', false);

        // 3. Test Show Page Displays Lag metadata
        $showResponse = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.routing.show', $routing->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Lag: 15m', false);

        // 4. Update routing to modify transfer_lag_minutes to 25.0
        $updateData = $createData;
        $updateData['operations'][0]['transfer_lag_minutes'] = 25.0;

        $updateResponse = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->put(route('production.routing.update', $routing->id), $updateData);
        $updateResponse->assertRedirect(route('production.routing.show', $routing->id));

        $freshOp1 = $routing->fresh()->operations()->where('sequence', 10)->first();
        $this->assertNotNull($freshOp1);
        $this->assertEquals(25, (int) $freshOp1->transfer_lag_minutes);

        // Verify updated show page
        $updatedShowResponse = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.routing.show', $routing->id));
        $updatedShowResponse->assertStatus(200);
        $updatedShowResponse->assertSee('Lag: 25m', false);
    }

    public function test_machine_index_and_direct_url_navigate_to_mes_machine_show(): void
    {
        $this->actingAs($this->pmUser);
        session(['tenant_id' => $this->tenant->id]);

        // 1. Direct URL to production.machines.show should redirect to MES machine detail
        $directResponse = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.machines.show', $this->machine->id));
        $directResponse->assertRedirect(route('production.mes.machines.show', $this->machine->id));

        // 2. Machine list table should contain clickable links to the MES machine detail page
        $indexResponse = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.machines.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee(route('production.mes.machines.show', $this->machine->id));
    }
}

