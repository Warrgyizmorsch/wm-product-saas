<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Services\BomExplosionService;
use App\Domains\Production\Services\ProductionBomService;
use App\Domains\Production\Services\ProductionCostService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * BM-30: Targeted feature tests for BOM Overhaul behaviors (Phases 1-4).
 */
class BomOverhaulTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private Product $fg;
    private Product $semiFinished;
    private Product $rawMaterial;
    private Routing $routing;
    private RoutingOperation $op10;
    private RoutingOperation $op20;
    private string $apiSecret = 'wm-production-secret-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'BOM Overhaul Tenant',
            'slug' => 'bom-overhaul',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'BOM Engineer',
            'email' => 'bom-eng@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $managerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $managerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);

        $this->fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Assembly',
            'sku' => 'FG-BOM-OV-1',
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'unit_cost' => 100.0,
        ]);

        $this->semiFinished = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sub Assembly Unit',
            'sku' => 'SF-BOM-OV-1',
            'type' => 'semi_finished',
            'supplier_method' => 'manufacture',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'unit_cost' => 40.0,
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Aluminum Bar',
            'sku' => 'RM-BOM-OV-1',
            'type' => 'raw_material',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'unit_cost' => 10.0,
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-OV-001',
            'name' => 'Overhaul Test Routing',
            'status' => Routing::STATUS_ACTIVE,
        ]);

        $this->op10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Primary Cut',
        ]);

        $this->op20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Final Pack',
        ]);
    }

    public function test_scheduler_command_activates_scheduled_bom_and_expires_past_bom(): void
    {
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $oldActive = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SCHED-OLD',
            'bom_name' => 'Old Active BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->fg->id,
            'routing_id' => $this->routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => $yesterday,
            'status' => ProductionBom::STATUS_APPROVED,
        ]);

        $scheduledBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SCHED-NEW',
            'bom_name' => 'Scheduled BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->fg->id,
            'routing_id' => $this->routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '2.0.0',
            'effective_date' => $today,
            'status' => ProductionBom::STATUS_SCHEDULED,
        ]);

        $expiringBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-EXP-OLD',
            'bom_name' => 'Expiring Sub BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->semiFinished->id,
            'routing_id' => $this->routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => Carbon::today()->subDays(5)->toDateString(),
            'expiry_date' => $yesterday,
            'status' => ProductionBom::STATUS_APPROVED,
        ]);

        $this->artisan('production:process-bom-dates', ['--date' => $today])
            ->assertExitCode(0);

        $this->assertSame(ProductionBom::STATUS_APPROVED, $scheduledBom->fresh()->status);
        $this->assertSame(ProductionBom::STATUS_INACTIVE, $oldActive->fresh()->status);
        $this->assertSame(ProductionBom::STATUS_INACTIVE, $expiringBom->fresh()->status);
    }

    public function test_deleted_operation_advisory_blocks_approval_until_reassigned(): void
    {
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-DEL-OP',
            'bom_name' => 'Deleted Op Advisory BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->fg->id,
            'routing_id' => $this->routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => Carbon::today()->toDateString(),
            'status' => ProductionBom::STATUS_PENDING_APPROVAL,
        ]);

        $allocation = RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'routing_operation_id' => $this->op10->id,
            'material_id' => $this->rawMaterial->id,
            'quantity' => 5.0,
            'gross_quantity' => 5.0,
            'uom_id' => $this->uom->id,
        ]);

        // Soft-delete the operation after allocation was already attached
        $this->op10->delete();

        $advisories = $bom->fresh()->routing_advisory;
        $codes = array_column($advisories, 'code');
        $this->assertContains('operation_removed', $codes);

        $opRemoved = collect($advisories)->firstWhere('code', 'operation_removed');
        $this->assertTrue($opRemoved['blocks_approval']);

        // A pending BOM is not editable (draft only) — the deleted card is checked on a draft in the edit flow
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.boms.edit', $bom->id))
            ->assertStatus(403);

        // Approval is blocked while material still sits on removed operation
        $service = app(ProductionBomService::class);
        try {
            $service->approve($bom->id, $this->user->id);
            $this->fail('Expected approve() to throw when an allocation points to a deleted operation.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('OP-10', $e->getMessage());
        }

        // Reassign to active op20 -> clears block and permits approval
        $allocation->update(['routing_operation_id' => $this->op20->id]);
        $this->assertNotContains('operation_removed', array_column($bom->fresh()->routing_advisory, 'code'));

        $approved = $service->approve($bom->id, $this->user->id);
        $this->assertSame(ProductionBom::STATUS_APPROVED, $approved->status);
    }

    public function test_option_2b_routing_advisory_codes_and_api_exposure(): void
    {
        $draftRouting = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-OV-DRAFT',
            'name' => 'Draft Routing',
            'status' => Routing::STATUS_DRAFT,
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-ADV-API',
            'bom_name' => 'Advisory API BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->fg->id,
            'routing_id' => $draftRouting->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => Carbon::today()->toDateString(),
            'status' => ProductionBom::STATUS_DRAFT,
        ]);

        $res = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
            'X-API-SECRET' => $this->apiSecret,
            'Authorization' => 'Bearer ' . $this->user->createToken('test-token')->plainTextToken,
        ])->getJson('/api/v1/production/boms/' . $bom->id);

        $res->assertStatus(200);
        $advisories = $res->json('data.routing_advisory');
        $this->assertIsArray($advisories);
        $this->assertSame('not_active', $advisories[0]['code'] ?? null);
        $this->assertTrue($advisories[0]['blocks_approval'] ?? false);

        // Inactive / cancelled BOMs suppress advisories under Option 2B
        $bom->update(['status' => ProductionBom::STATUS_INACTIVE]);
        $this->assertSame([], $bom->fresh()->routing_advisory);

        $bom->update(['status' => ProductionBom::STATUS_CANCELLED]);
        $this->assertSame([], $bom->fresh()->routing_advisory);
    }

    public function test_gross_quantity_persisted_and_consumed_by_cost_and_explosion_services(): void
    {
        $service = app(ProductionBomService::class);
        $bom = $service->create(ProductionBomDTO::fromArray([
            'bom_number' => 'BOM-GROSS-1',
            'bom_name' => 'Gross Quantity BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->fg->id,
            'routing_id' => $this->routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => Carbon::today()->toDateString(),
            'items' => [
                [
                    'routing_operation_id' => $this->op10->id,
                    'material_id' => $this->rawMaterial->id,
                    'quantity' => 10.0,
                    'uom_id' => $this->uom->id,
                    'material_scrap_percentage' => 25.0,
                ],
            ],
        ]), $this->user->id);

        $allocation = $bom->operationMaterials()->first();
        $this->assertNotNull($allocation);
        $this->assertEquals(12.5, (float) $allocation->gross_quantity);

        $bom->update(['status' => ProductionBom::STATUS_APPROVED]);

        // Cost service consumes gross_quantity (12.5 * 10.0 unit_cost = 125.0)
        $cost = app(ProductionCostService::class)->calculateCost($bom->fresh());
        $this->assertEquals(125.0, round((float) $cost['material_cost'], 4));

        // Explosion service multiplies gross_quantity by demand (12.5 * 2 = 25.0)
        $explosion = app(BomExplosionService::class)->explode($this->fg->id, 2.0, $this->tenant->id);
        $flatRow = collect($explosion['flat'])->firstWhere('product_id', $this->rawMaterial->id);
        $this->assertNotNull($flatRow);
        $this->assertEquals(25.0, round((float) $flatRow['gross_quantity'], 4));

        // BM-IMPL-04: master row carries the gross quantity too
        $this->assertEquals(12.5, (float) $bom->fresh()->items()->first()->gross_quantity);
    }

    public function test_copy_without_saving_for_duplicate_and_create_revision(): void
    {
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-COPY-ONLY',
            'bom_name' => 'Original BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->fg->id,
            'routing_id' => $this->routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => Carbon::today()->toDateString(),
            'status' => ProductionBom::STATUS_APPROVED,
        ]);

        // Duplicate redirects with query parameters without creating DB records
        $dupRes = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.boms.duplicate', $bom->id), [
                'new_version' => '1.1.0',
            ]);
        $dupRes->assertRedirect(route('production.boms.create', [
            'copy_from' => $bom->id,
            'mode' => 'duplicate',
            'version' => '1.1.0',
        ]));
        $this->assertSame(1, ProductionBom::count());

        // Create Revision redirects with bumped version without creating DB records
        $revRes = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.boms.create-revision', $bom->id), [
                'bump_type' => 'minor',
            ]);
        $revRes->assertRedirect(route('production.boms.create', [
            'copy_from' => $bom->id,
            'mode' => 'revision',
            'version' => '1.1.0',
        ]));
        $this->assertSame(1, ProductionBom::count());
    }

    public function test_validate_bom_tree_for_execution_blocks_inactive_and_expired_boms(): void
    {
        $service = app(ProductionBomService::class);
        $yesterday = Carbon::yesterday()->toDateString();

        $inactiveBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-EXEC-INACT',
            'bom_name' => 'Inactive Root BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->fg->id,
            'routing_id' => $this->routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => Carbon::today()->toDateString(),
            'status' => ProductionBom::STATUS_INACTIVE,
        ]);

        try {
            $service->validateBomTreeForExecution($inactiveBom, $this->tenant->id);
            $this->fail('Expected inactive root BOM to fail execution validation.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('not active', $e->getMessage());
        }

        // Expired root BOM
        $expiredBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-EXEC-EXP',
            'bom_name' => 'Expired Root BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->fg->id,
            'routing_id' => $this->routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.1',
            'effective_date' => Carbon::today()->subDays(5)->toDateString(),
            'expiry_date' => $yesterday,
            'status' => ProductionBom::STATUS_APPROVED,
        ]);

        try {
            $service->validateBomTreeForExecution($expiredBom, $this->tenant->id);
            $this->fail('Expected expired root BOM to fail execution validation.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('expired', $e->getMessage());
        }

        // Active parent with manufactured semi_finished material having no active sub-BOM
        $activeParent = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-EXEC-OK',
            'bom_name' => 'Parent With Missing Sub',
            'bom_type' => 'manufacturing',
            'product_id' => $this->fg->id,
            'routing_id' => $this->routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '2.0.0',
            'effective_date' => Carbon::today()->toDateString(),
            'status' => ProductionBom::STATUS_APPROVED,
        ]);

        $subAlloc = RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $activeParent->id,
            'routing_operation_id' => $this->op10->id,
            'material_id' => $this->semiFinished->id,
            'quantity' => 1.0,
            'gross_quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);

        try {
            $service->validateBomTreeForExecution($activeParent->fresh(), $this->tenant->id);
            $this->fail('Expected manufactured semi_finished without active sub-BOM to fail execution validation.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('no active or approved sub-BOM', $e->getMessage());
        }

        // Activating sub-BOM allows execution tree validation to pass cleanly
        $activeSubBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-ACTIVE',
            'bom_name' => 'Active Sub BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->semiFinished->id,
            'routing_id' => $this->routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => Carbon::today()->toDateString(),
            'status' => ProductionBom::STATUS_APPROVED,
        ]);
        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $activeSubBom->id,
            'routing_operation_id' => $this->op10->id,
            'material_id' => $this->rawMaterial->id,
            'quantity' => 2.0,
            'gross_quantity' => 2.0,
            'uom_id' => $this->uom->id,
        ]);
        $subAlloc->update(['child_bom_id' => $activeSubBom->id]);

        $service->validateBomTreeForExecution($activeParent->fresh(), $this->tenant->id);
        $this->assertTrue(true);
    }
}

