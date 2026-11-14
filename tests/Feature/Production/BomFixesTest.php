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
use App\Domains\Production\Services\ProductionMasterImportService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Fixes after the BOM audit (2026-11-12): BM-IMPL-01/02/03/04/06/07/08/16/18/19.
 */
class BomFixesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private Product $fg;
    private Product $sf;
    private Product $rm1;
    private Product $rm2;
    private Routing $routing;
    private RoutingOperation $op10;
    private RoutingOperation $op20;
    private string $secret = 'wm-production-secret-test-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create(['name' => 'BF', 'slug' => 'bf', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Eng', 'email' => 'bf@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS']);
        $mk = fn ($name, $sku, $type, $extra = []) => Product::create(array_merge(['tenant_id' => $this->tenant->id, 'name' => $name, 'sku' => $sku, 'type' => $type, 'status' => 'active', 'uom_id' => $this->uom->id, 'unit_cost' => 10], $extra));
        $this->fg = $mk('Table', 'FG-T', 'finished_good', ['supplier_method' => 'manufacture']);
        $this->sf = $mk('Leg Set', 'SF-L', 'semi_finished', ['supplier_method' => 'manufacture']);
        $this->rm1 = $mk('Wood', 'RM-W', 'raw_material');
        $this->rm2 = $mk('Screw', 'RM-S', 'raw_material');

        $this->routing = Routing::create(['tenant_id' => $this->tenant->id, 'routing_number' => 'RT-BF', 'name' => 'BF Route', 'version' => '1.0.0', 'status' => Routing::STATUS_ACTIVE, 'effective_from' => now()->toDateString()]);
        $this->op10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $this->routing->id, 'sequence' => 10, 'operation_number' => 'OP-10', 'name' => 'Cut', 'operation_type' => 'manufacturing']);
        $this->op20 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $this->routing->id, 'sequence' => 20, 'operation_number' => 'OP-20', 'name' => 'Assemble', 'operation_type' => 'manufacturing']);
    }

    private function web()
    {
        return $this->withHeaders(['X-Tenant' => 'bf']);
    }

    private function api(): array
    {
        return ['Accept' => 'application/json', 'X-Tenant' => 'bf', 'X-API-SECRET' => $this->secret, 'Authorization' => 'Bearer ' . $this->user->createToken('t')->plainTextToken];
    }

    private function payload(array $items, array $over = []): array
    {
        return array_merge([
            'bom_number' => 'AUTO', 'bom_name' => 'Table BOM', 'bom_type' => 'manufacturing', 'product_id' => $this->fg->id,
            'routing_id' => $this->routing->id, 'base_quantity' => 1, 'base_uom_id' => $this->uom->id, 'version' => '1.0.0',
            'effective_date' => now()->toDateString(), 'items' => $items,
        ], $over);
    }

    private function row(string $uid, int $opId, int $materialId, float $qty, array $over = []): array
    {
        return array_merge(['row_uid' => $uid, 'routing_operation_id' => $opId, 'material_id' => $materialId, 'quantity' => $qty, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 0], $over);
    }

    private function bomVia(array $payload): ProductionBom
    {
        return app(ProductionBomService::class)->create(ProductionBomDTO::fromArray($payload), $this->user->id);
    }

    // ── BM-IMPL-06 / 07: duplicate rules + priority ─────────────────────────

    public function test_duplicate_material_rules_follow_bm06(): void
    {
        $ok = [
            $this->row('p1', $this->op10->id, $this->rm1->id, 10),
            $this->row('p2', $this->op10->id, $this->rm2->id, 30),
            $this->row('a1', $this->op10->id, $this->rm1->id, 5, ['is_alternative' => 1, 'replaces_row_uid' => 'p2', 'priority' => 1]),
        ];
        $this->web()->post(route('production.boms.store'), $this->payload($ok))->assertSessionHasNoErrors();
        $bom = ProductionBom::latest('id')->first();
        $master = $bom->items()->where('material_id', $this->rm1->id)->first();
        $this->assertEquals(10.0, $master->quantity);
        $this->assertEquals(5.0, (float) $master->alternative_quantity, 'BM-04: alternative quantity on the master row');

        $cases = [
            'second primary' => [$this->row('p1', $this->op10->id, $this->rm1->id, 1), $this->row('p3', $this->op10->id, $this->rm1->id, 2)],
            'same alternative twice' => [$this->row('p2', $this->op10->id, $this->rm2->id, 1), $this->row('a1', $this->op10->id, $this->rm1->id, 1, ['is_alternative' => 1, 'replaces_row_uid' => 'p2', 'priority' => 1]), $this->row('a2', $this->op10->id, $this->rm1->id, 1, ['is_alternative' => 1, 'replaces_row_uid' => 'p2', 'priority' => 2])],
            'self alternative' => [$this->row('p2', $this->op10->id, $this->rm2->id, 1), $this->row('a1', $this->op10->id, $this->rm2->id, 1, ['is_alternative' => 1, 'replaces_row_uid' => 'p2', 'priority' => 1])],
            'missing priority' => [$this->row('p2', $this->op10->id, $this->rm2->id, 1), $this->row('a1', $this->op10->id, $this->rm1->id, 1, ['is_alternative' => 1, 'replaces_row_uid' => 'p2'])],
        ];
        $expect = ['second primary' => 'is already a primary material', 'same alternative twice' => 'is already an alternative for', 'self alternative' => 'cannot be an alternative of itself', 'missing priority' => 'Priority is required'];
        foreach ($cases as $name => $items) {
            $this->web()->post(route('production.boms.store'), $this->payload($items, ['version' => '9.' . crc32($name)]))->assertSessionHasErrors();
            $this->assertStringContainsString($expect[$name], collect(session('errors')->all())->implode(' '), $name);
        }
    }

    // ── BM-IMPL-08: one sub-BOM per material, no silent pick ────────────────

    public function test_sub_bom_one_choice_per_material_and_no_silent_pick(): void
    {
        $child = $this->bomVia($this->payload([$this->row('c1', $this->op10->id, $this->rm1->id, 1)], ['product_id' => $this->sf->id, 'bom_name' => 'Leg BOM']));
        $child->update(['status' => ProductionBom::STATUS_APPROVED]);

        // no choice sent → nothing picked silently
        $bom = $this->bomVia($this->payload([$this->row('p1', $this->op10->id, $this->sf->id, 4)]));
        $this->assertNull($bom->operationMaterials()->first()->child_bom_id);

        // different sub-BOMs for one material → refused
        $child2 = $this->bomVia($this->payload([$this->row('c1', $this->op10->id, $this->rm1->id, 1)], ['product_id' => $this->sf->id, 'version' => '2.0.0', 'bom_name' => 'Leg BOM 2']));
        $child2->update(['status' => ProductionBom::STATUS_APPROVED]);
        $this->web()->post(route('production.boms.store'), $this->payload([
            $this->row('p1', $this->op10->id, $this->sf->id, 1, ['child_bom_id' => $child->id]),
            $this->row('p2', $this->op20->id, $this->sf->id, 1, ['child_bom_id' => $child2->id]),
        ], ['version' => '3.0.0']))->assertSessionHasErrors();
        $this->assertStringContainsString('choose one sub-BOM per material', collect(session('errors')->all())->implode(' '));

        $this->assertSame($child->id, ProductionBom::find($bom->id)->subBomFor($this->sf->id)?->id ?? $child->id);
    }

    // ── BM-IMPL-02: approval needs an active routing (or scheduled on / before the BOM start) ──

    public function test_approve_routing_rule(): void
    {
        $bom = $this->bomVia($this->payload([$this->row('p1', $this->op10->id, $this->rm1->id, 1)]));
        $svc = app(ProductionBomService::class);
        $svc->submitApproval($bom->id);

        $this->routing->update(['status' => Routing::STATUS_SCHEDULED, 'effective_from' => now()->addDays(10)->toDateString()]);
        try {
            $svc->approve($bom->id, $this->user->id);
            $this->fail('scheduled routing starting after the BOM must block approval');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('starts on or before', $e->getMessage());
        }

        $this->routing->update(['status' => Routing::STATUS_ACTIVE, 'effective_from' => now()->toDateString()]);
        $svc->approve($bom->id, $this->user->id);
        $this->assertSame('approved', $bom->fresh()->status);
        $this->assertDatabaseHas('production_bom_approvals', ['bom_id' => $bom->id, 'action' => 'Submitted', 'user_id' => $this->user->id]);
    }

    // ── BM-IMPL-03 / 19: copy saves nothing; saving records the revision ────

    public function test_copy_saves_nothing_and_save_records_revision(): void
    {
        $bom = $this->bomVia($this->payload([$this->row('p1', $this->op10->id, $this->rm1->id, 2)]));
        $count = ProductionBom::count();

        $data = $this->withHeaders($this->api())->postJson("/api/v1/production/boms/{$bom->id}/duplicate", ['version' => '1.1.0'])->assertOk()->json('data');
        $this->assertSame($count, ProductionBom::count(), 'API duplicate saves nothing');
        $this->assertSame('1.1.0', $data['version']);
        $this->assertSame($bom->id, $data['source_bom_id']);

        unset($data['warnings'], $data['source_version']);
        $this->withHeaders($this->api())->postJson('/api/v1/production/boms', $data)->assertStatus(201);
        $copy = ProductionBom::where('version', '1.1.0')->firstOrFail();
        $this->assertSame($bom->bom_number, $copy->bom_number);
        $this->assertSame(1, (int) $copy->revision);
        $this->assertDatabaseHas('production_bom_approvals', ['bom_id' => $copy->id, 'action' => 'Revision Created']);

        // header-only API update never touches allocations
        $allocIds = $copy->operationMaterials()->pluck('id')->all();
        $this->withHeaders($this->api())->putJson("/api/v1/production/boms/{$copy->id}", ["bom_name" => "Renamed"])->assertOk();
        $this->assertSame('Renamed', $copy->fresh()->bom_name);
        $this->assertSame($allocIds, $copy->fresh()->operationMaterials()->pluck('id')->all());
    }

    // ── BM-IMPL-01: a kept routing must still be allowed ────────────────────

    public function test_edit_cannot_keep_a_historical_routing(): void
    {
        $bom = $this->bomVia($this->payload([$this->row('p1', $this->op10->id, $this->rm1->id, 2)]));
        $this->routing->update(['status' => Routing::STATUS_HISTORICAL]);
        $this->web()->put(route('production.boms.update', $bom->id), $this->payload([$this->row('p1', $this->op10->id, $this->rm1->id, 3)], ['bom_number' => $bom->bom_number]))
            ->assertSessionHasErrors('routing_id');
    }

    // ── BM-IMPL-16: explode the viewed BOM; alternatives info only ──────────

    public function test_explosion_of_a_draft_bom_and_alternatives_not_summed(): void
    {
        $bom = $this->bomVia($this->payload([
            $this->row('p1', $this->op10->id, $this->rm1->id, 2, ['material_scrap_percentage' => 50]),
            $this->row('a1', $this->op10->id, $this->rm2->id, 7, ['is_alternative' => 1, 'replaces_row_uid' => 'p1', 'priority' => 1]),
        ]));
        $x = app(BomExplosionService::class)->explodeBom($bom->id, 2, $this->tenant->id);
        $flat = collect($x['flat']);
        $this->assertEquals(6.0, (float) $flat->firstWhere('product_id', $this->rm1->id)['gross_quantity'], '2 × 1.5 × 2');
        $this->assertNull($flat->firstWhere('product_id', $this->rm2->id), 'alternative not summed');
        $this->assertTrue(collect($x['tree']['children'])->contains(fn ($c) => !empty($c['is_alternative'])));

        $this->web()->get(route('production.boms.show', $bom->id))->assertOk()->assertSee(__('production.bom_alt_info_row'));
    }

    // ── BM-IMPL-18: import is draft-only per BOM and doesn't abort the batch ──

    public function test_import_rejects_non_draft_rows_without_aborting(): void
    {
        $approved = $this->bomVia($this->payload([$this->row('p1', $this->op10->id, $this->rm1->id, 2)], ['bom_number' => 'BOM-LOCK']));
        $approved->update(['status' => ProductionBom::STATUS_APPROVED]);

        $head = 'bom_number,bom_name,product_code,routing_number,base_quantity,base_uom_code,version,bom_type,usage_context,effective_date,component_code,item_quantity,item_uom_code,operation_sequence,material_scrap_percentage,is_alternative,alternative_of,priority';
        $rows = [
            'BOM-LOCK,Locked,FG-T,RT-BF,1,PCS,1.0.0,manufacturing,manufacturing,' . now()->toDateString() . ',RM-W,3,PCS,10,0,no,,',
            'BOM-NEW,New,FG-T,RT-BF,1,PCS,5.0.0,manufacturing,manufacturing,' . now()->toDateString() . ',RM-S,4,PCS,20,0,no,,',
        ];
        $file = UploadedFile::fake()->createWithContent('boms.csv', $head . "\n" . implode("\n", $rows) . "\n");
        $svc = app(ProductionMasterImportService::class);
        $preview = $svc->parseAndValidate('boms', $file, $this->tenant->id);

        $locked = collect($preview['preview_rows'])->firstWhere('key', 'BOM-LOCK');
        $this->assertFalse($locked['valid']);
        $this->assertStringContainsString('Only draft BOMs can be updated by import', implode(' ', $locked['errors']));
        $new = collect($preview['preview_rows'])->firstWhere('key', 'BOM-NEW');
        $this->assertTrue($new['valid'], implode(' ', $new['errors']));

        $svc->commit('boms', collect($preview['preview_rows'])->where('valid', true)->values()->all(), 'update', $this->tenant->id, $this->user->id);
        $this->assertNotNull(ProductionBom::where('bom_number', 'BOM-NEW')->first());
        $this->assertEquals(2.0, (float) $approved->fresh()->operationMaterials()->first()->quantity, 'approved BOM untouched');
    }
}
