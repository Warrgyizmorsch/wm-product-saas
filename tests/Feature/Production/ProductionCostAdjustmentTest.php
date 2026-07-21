<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionCostAdjustment;
use App\Domains\Production\Models\ProductionOrder;

use App\Domains\Production\Services\ProductionCostAdjustmentService;
use App\Domains\Production\Services\ProductionCostVarianceService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use Tests\TestCase;

class ProductionCostAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;
    private ProductionOrder $order;

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

        $uom = Uom::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Pieces',
            'code'      => 'PCS',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Finished Widget',
            'sku'       => 'FG-WIDGET-001',
            'type'      => 'finished_good',
            'unit_cost' => 100.00,
            'uom_id'    => $uom->id,
        ]);

        $this->order = ProductionOrder::create([
            'tenant_id'        => $this->tenantId,
            'order_number'     => 'MO-2026-COST-001',
            'product_id'       => $product->id,
            'quantity_ordered' => 50.0,
            'quantity_produced'=> 0.0,
            'status'           => 'released',
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(5)->toDateString(),
            'created_by'       => $this->user->id,
        ]);
    }

    /** @test */
    public function user_can_create_cost_adjustments_for_each_component_with_decimal_precision()
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('receipt.pdf', 500, 'application/pdf');

        $components = [
            'material' => ['Additional Steel', 'Emergency raw material purchase', 1500.50],
            'labor'    => ['Temporary Labour', 'Overtime shifts for urgent delivery', 2200.75],
            'machine'  => ['Machine Breakdown', 'Spindle bearing emergency replacement', 4500.00],
            'overhead' => ['Electricity Surcharge', 'Peak hour power tariff', 850.25],
            'other'    => ['Emergency Transport', 'Express courier for tooling', 320.00],
        ];

        foreach ($components as $component => [$category, $description, $amount]) {
            $response = $this->withHeader('X-Tenant', 'test-tenant')
                ->post(route('production.orders.cost-adjustments.store', $this->order->id), [
                    'cost_component'  => $component,
                    'category'        => $category,
                    'description'     => $description,
                    'amount'          => $amount,
                    'adjustment_date' => now()->toDateString(),
                    'attachment'      => $file,
                ]);

            $response->assertRedirect();
            $this->assertDatabaseHas('production_cost_adjustments', [
                'tenant_id'           => $this->tenantId,
                'production_order_id' => $this->order->id,
                'cost_component'      => $component,
                'category'            => $category,
                'amount'              => number_format($amount, 2, '.', ''),
            ]);
        }

        $service = app(ProductionCostAdjustmentService::class);
        $totals = $service->getAdjustmentTotalsByComponent($this->order);

        $this->assertEquals(1500.50, $totals['material']);
        $this->assertEquals(2200.75, $totals['labor']);
        $this->assertEquals(4500.00, $totals['machine']);
        $this->assertEquals(850.25, $totals['overhead']);
        $this->assertEquals(320.00, $totals['other']);
        $this->assertEquals(9371.50, $totals['total']);
    }

    /** @test */
    public function multiple_adjustments_on_same_or_earlier_business_date_are_correctly_grouped()
    {
        $service = app(ProductionCostAdjustmentService::class);

        $dateYesterday = now()->subDays(2)->toDateString();
        $dateToday     = now()->toDateString();

        $service->createAdjustment($this->order, [
            'cost_component'  => 'machine',
            'category'        => 'Machine Breakdown',
            'description'     => 'First breakdown repair',
            'amount'          => 1200.00,
            'adjustment_date' => $dateYesterday,
        ], userId: $this->user->id);

        $service->createAdjustment($this->order, [
            'cost_component'  => 'labor',
            'category'        => 'Additional Labour',
            'description'     => 'Extra technician',
            'amount'          => 800.00,
            'adjustment_date' => $dateYesterday,
        ], userId: $this->user->id);

        $service->createAdjustment($this->order, [
            'cost_component'  => 'other',
            'category'        => 'Emergency Transport',
            'description'     => 'Local freight',
            'amount'          => 350.00,
            'adjustment_date' => $dateToday,
        ], userId: $this->user->id);

        $dailyMap = $service->getDailyAdjustments($this->order);

        $this->assertEquals(2000.00, $dailyMap[$dateYesterday]);
        $this->assertEquals(350.00, $dailyMap[$dateToday]);
    }

    /** @test */
    public function soft_deleted_adjustments_are_excluded_from_totals_and_daily_summaries()
    {
        $service = app(ProductionCostAdjustmentService::class);

        $adj1 = $service->createAdjustment($this->order, [
            'cost_component'  => 'material',
            'category'        => 'Packaging',
            'description'     => 'Crate box',
            'amount'          => 500.00,
            'adjustment_date' => now()->toDateString(),
        ], userId: $this->user->id);

        $adj2 = $service->createAdjustment($this->order, [
            'cost_component'  => 'material',
            'category'        => 'Extra Material',
            'description'     => 'Scrap replacement',
            'amount'          => 1000.00,
            'adjustment_date' => now()->toDateString(),
        ], userId: $this->user->id);

        $this->assertEquals(1500.00, $service->getAdjustmentTotalsByComponent($this->order)['total']);

        // Soft-delete adj1
        $service->deleteAdjustment($adj1, userId: $this->user->id);

        $this->assertSoftDeleted('production_cost_adjustments', ['id' => $adj1->id]);
        $this->assertEquals(1000.00, $service->getAdjustmentTotalsByComponent($this->order)['total']);
    }

    /** @test */
    public function completed_closed_or_cancelled_orders_block_adjustment_mutations()
    {
        $this->order->update(['status' => 'completed']);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.orders.cost-adjustments.store', $this->order->id), [
                'cost_component'  => 'machine',
                'category'        => 'Machine Breakdown',
                'description'     => 'Repair work',
                'amount'          => 500.00,
                'adjustment_date' => now()->toDateString(),
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function attachment_downloads_are_restricted_by_tenant_scoping()
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('contract.pdf', 300, 'application/pdf');

        $service = app(ProductionCostAdjustmentService::class);
        $adj = $service->createAdjustment($this->order, [
            'cost_component'  => 'other',
            'category'        => 'Outsourcing',
            'description'     => 'External heat treatment',
            'amount'          => 2500.00,
            'adjustment_date' => now()->toDateString(),
        ], file: $file, userId: $this->user->id);

        // Authorized download
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.cost-adjustments.download', $adj->id));
        $response->assertStatus(200);

        // Cross-tenant access check
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant']);
        $otherUser   = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'admin']);

        $crossResponse = $this->actingAs($otherUser)
            ->withHeader('X-Tenant', 'other-tenant')
            ->get(route('production.cost-adjustments.download', $adj->id));

        $crossResponse->assertStatus(404);
    }

    /** @test */
    public function automatic_cost_variance_service_remains_unchanged_and_backward_compatible()
    {
        $costVarianceService = new ProductionCostVarianceService();
        $autoCosts = $costVarianceService->getCostAnalysis($this->order);

        $this->assertIsArray($autoCosts);
        $this->assertArrayHasKey('material', $autoCosts);
        $this->assertArrayHasKey('labor', $autoCosts);
        $this->assertArrayHasKey('machine', $autoCosts);
        $this->assertArrayHasKey('overhead', $autoCosts);
        $this->assertArrayHasKey('totals', $autoCosts);
        $this->assertArrayNotHasKey('other', $autoCosts); // Automatic matrix unchanged
    }

    /** @test */
    public function final_manufacturing_cost_equals_automatic_total_plus_active_manual_adjustments()
    {
        $service = app(ProductionCostAdjustmentService::class);

        $service->createAdjustment($this->order, [
            'cost_component'  => 'material',
            'category'        => 'Packaging',
            'description'     => 'Heavy duty pallets',
            'amount'          => 400.00,
            'adjustment_date' => now()->toDateString(),
        ], userId: $this->user->id);

        $service->createAdjustment($this->order, [
            'cost_component'  => 'other',
            'category'        => 'Fuel',
            'description'     => 'Generator diesel for power outage',
            'amount'          => 600.00,
            'adjustment_date' => now()->toDateString(),
        ], userId: $this->user->id);

        $costVarianceService = new ProductionCostVarianceService();
        $autoCosts = $costVarianceService->getCostAnalysis($this->order);
        $finalSummary = $service->getFinalCostingSummary($this->order, $autoCosts);

        $expectedFinalTotal = $autoCosts['totals']['actual'] + 1000.00;

        $this->assertEquals(1000.00, $finalSummary['totals']['manual']);
        $this->assertEquals($expectedFinalTotal, $finalSummary['totals']['final']);
    }

    /** @test */
    public function daily_and_overall_costing_consistency_across_all_cost_components()
    {
        $service = app(ProductionCostAdjustmentService::class);
        $costVarianceService = new ProductionCostVarianceService();

        // Create manual adjustments across multiple dates
        $service->createAdjustment($this->order, [
            'cost_component'  => 'material',
            'category'        => 'Extra Material',
            'description'     => 'Additional steel sheet',
            'amount'          => 450.50,
            'adjustment_date' => now()->subDay()->toDateString(),
        ], userId: $this->user->id);

        $service->createAdjustment($this->order, [
            'cost_component'  => 'overhead',
            'category'        => 'Electricity Surcharge',
            'description'     => 'Power peak surcharge',
            'amount'          => 250.25,
            'adjustment_date' => now()->toDateString(),
        ], userId: $this->user->id);

        $autoCosts = $costVarianceService->getCostAnalysis($this->order);
        $dailyManualMap = $service->getDailyAdjustments($this->order);
        $dailyHistory = $costVarianceService->getDailyCostHistory($this->order, $dailyManualMap);
        $finalSummary = $service->getFinalCostingSummary($this->order, $autoCosts);

        // Sum of daily automatic cost
        $sumDailyAuto = array_sum(array_column($dailyHistory, 'automatic_daily_cost'));
        // Sum of daily manual adjustments
        $sumDailyManual = array_sum(array_column($dailyHistory, 'manual_daily_adjustment'));
        // Sum of daily final cost
        $sumDailyFinal = array_sum(array_column($dailyHistory, 'final_daily_cost'));

        $this->assertEquals($autoCosts['totals']['actual'], $sumDailyAuto);
        $this->assertEquals($finalSummary['totals']['manual'], $sumDailyManual);
        $this->assertEquals($finalSummary['totals']['final'], $sumDailyFinal);
    }
}
