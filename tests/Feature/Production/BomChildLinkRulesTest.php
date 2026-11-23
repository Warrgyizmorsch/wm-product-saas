<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
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
 * Child-BOM (sub-BOM) link rules found by the BOM-creation audit of 2026-11-23, decision BM-08:
 *  1. a soft-deleted sub-BOM cannot be linked and does not pass the execution tree check
 *  2. an expired sub-BOM cannot be linked (even when the parent has no expiry date)
 *  3. BOM import resolves a sub-BOM number to the version that can be selected today and refuses unknown numbers
 *  4. duplicate / revise does not carry a sub-BOM that can no longer be selected
 */
class BomChildLinkRulesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private Product $fg;
    private Product $sf;
    private Product $rm;
    private Routing $routing;
    private RoutingOperation $op10;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create(['name' => 'CL', 'slug' => 'cl', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Eng', 'email' => 'cl@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS']);
        $mk = fn ($name, $sku, $type, $extra = []) => Product::create(array_merge(['tenant_id' => $this->tenant->id, 'name' => $name, 'sku' => $sku, 'type' => $type, 'status' => 'active', 'uom_id' => $this->uom->id], $extra));
        $this->fg = $mk('Table', 'FG-T', 'finished_good', ['supplier_method' => 'manufacture']);
        $this->sf = $mk('Leg Set', 'SF-L', 'semi_finished', ['supplier_method' => 'manufacture']);
        $this->rm = $mk('Wood', 'RM-W', 'raw_material');

        $this->routing = Routing::create(['tenant_id' => $this->tenant->id, 'routing_number' => 'RT-CL', 'name' => 'CL Route', 'version' => '1.0.0', 'status' => Routing::STATUS_ACTIVE, 'effective_from' => now()->subDay()->toDateString()]);
        $this->op10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $this->routing->id, 'sequence' => 10, 'operation_number' => 'OP-10', 'name' => 'Cut', 'operation_type' => 'manufacturing']);
    }

    private function web()
    {
        return $this->withHeaders(['X-Tenant' => 'cl']);
    }

    private function payload(array $items, array $over = []): array
    {
        return array_merge([
            'bom_number' => 'AUTO', 'bom_name' => 'BOM', 'bom_type' => 'manufacturing', 'product_id' => $this->fg->id,
            'routing_id' => $this->routing->id, 'base_quantity' => 1, 'base_uom_id' => $this->uom->id, 'version' => '1.0.0',
            'effective_date' => now()->toDateString(), 'items' => $items,
        ], $over);
    }

    private function row(string $uid, int $materialId, float $qty, array $over = []): array
    {
        return array_merge(['row_uid' => $uid, 'routing_operation_id' => $this->op10->id, 'material_id' => $materialId, 'quantity' => $qty, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 0], $over);
    }

    private function bomVia(array $payload): ProductionBom
    {
        return app(ProductionBomService::class)->create(ProductionBomDTO::fromArray($payload), $this->user->id);
    }

    /** A sub-BOM of the leg set, moved to the wanted state. */
    private function subBom(array $state = [], string $version = '1.0.0', ?ProductionBom $newVersionOf = null): ProductionBom
    {
        // versions of one BOM share its number (Duplicate / Revise passes the source BOM)
        $child = $this->bomVia($this->payload([$this->row('c1', $this->rm->id, 1)], ['product_id' => $this->sf->id, 'bom_number' => 'BOM-LEG', 'bom_name' => 'Leg BOM', 'version' => $version, 'source_bom_id' => $newVersionOf?->id]));
        $child->update(array_merge(['status' => ProductionBom::STATUS_APPROVED], $state));

        return $child;
    }

    private function postParent(ProductionBom $child, array $over = [])
    {
        return $this->web()->post(route('production.boms.store'), $this->payload([$this->row('p1', $this->sf->id, 4, ['child_bom_id' => $child->id])], $over));
    }

    private function errorText(): string
    {
        return collect(session('errors')->all())->implode(' ');
    }

    // ── 1. soft-deleted sub-BOM ─────────────────────────────────────────────

    public function test_a_soft_deleted_sub_bom_cannot_be_linked(): void
    {
        $child = $this->subBom();
        $child->delete();

        $this->postParent($child)->assertSessionHasErrors();
        $this->assertStringContainsString('Linked child BOM not found', $this->errorText());
    }

    public function test_a_deleted_sub_bom_blocks_execution_and_warns_the_parent(): void
    {
        $child = $this->subBom();
        $parent = $this->bomVia($this->payload([$this->row('p1', $this->sf->id, 4, ['child_bom_id' => $child->id])]));
        $parent->update(['status' => ProductionBom::STATUS_APPROVED]);
        $child->delete();

        $parent = ProductionBom::withoutGlobalScopes()->find($parent->id);
        try {
            app(ProductionBomService::class)->validateBomTreeForExecution($parent, $this->tenant->id);
            $this->fail('A deleted sub-BOM must stop production.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('missing sub-BOM', $e->getMessage());
        }

        $this->assertTrue($parent->refreshWarningStatus());
        $this->assertStringContainsString('was deleted', (string) $parent->fresh()->warning_reason);
        $this->assertNull($parent->subBomFor($this->sf->id), 'a deleted sub-BOM is not exploded');
    }

    // ── 2. expired sub-BOM ──────────────────────────────────────────────────

    public function test_an_expired_sub_bom_cannot_be_linked_even_without_a_parent_expiry(): void
    {
        $child = $this->subBom(['expiry_date' => now()->subDays(3)->toDateString()]);

        $this->postParent($child)->assertSessionHasErrors();
        $this->assertStringContainsString('expired on', $this->errorText());
    }

    public function test_a_sub_bom_expiring_today_or_later_is_still_selectable(): void
    {
        $today = $this->subBom(['expiry_date' => now()->toDateString()]);
        $this->postParent($today)->assertSessionHasNoErrors();

        $later = $this->subBom(['expiry_date' => now()->addDays(30)->toDateString()], '2.0.0', $today);
        $this->postParent($later, ['version' => '2.0.0'])->assertSessionHasNoErrors();
    }

    // ── 3. import ───────────────────────────────────────────────────────────

    private function importCsv(string $childNumber, string $bomNumber = 'IMP-1'): array
    {
        $today = now()->toDateString();
        $csv = "bom_number,bom_name,product_code,routing_number,base_quantity,base_uom_code,version,bom_type,usage_context,effective_date,expiry_date,component_code,operation_sequence,item_quantity,item_uom_code,material_scrap_percentage,is_alternative,alternative_of,priority,notes,child_bom_number\n"
            . "{$bomNumber},Import,FG-T,RT-CL,1,PCS,1.0.0,manufacturing,manufacturing,{$today},,SF-L,10,4,PCS,0,No,,1,,{$childNumber}\n";

        return app(ProductionMasterImportService::class)
            ->parseAndValidate('boms', UploadedFile::fake()->createWithContent('boms.csv', $csv), $this->tenant->id)['preview_rows'];
    }

    public function test_import_resolves_the_sub_bom_number_to_the_selectable_version(): void
    {
        $old = $this->subBom(['status' => ProductionBom::STATUS_INACTIVE]);
        $new = $this->subBom([], '2.0.0', $old);
        $this->assertSame($old->bom_number, $new->bom_number, 'versions share one number');

        $rows = $this->importCsv('BOM-LEG');
        $this->assertTrue($rows[0]['valid'], json_encode($rows[0]['errors'] ?? $rows[0]));

        $m = new \ReflectionMethod(ProductionMasterImportService::class, 'resolveBomImportItems');
        $m->setAccessible(true);
        $out = $m->invoke(app(ProductionMasterImportService::class), [['material_id' => $this->sf->id, 'quantity' => 1, 'uom_id' => $this->uom->id, 'operation_sequence' => 10, 'child_bom_number' => 'BOM-LEG']], $this->routing->id, null, $this->tenant->id);
        $this->assertSame($new->id, $out[0]['child_bom_id'], 'not the oldest (inactive) version');
    }

    public function test_import_refuses_an_unknown_or_unselectable_sub_bom_number(): void
    {
        $this->subBom(['status' => ProductionBom::STATUS_INACTIVE]);

        foreach (['NO-SUCH-BOM', 'BOM-LEG'] as $number) {
            $rows = $this->importCsv($number, 'IMP-' . $number);
            $this->assertFalse($rows[0]['valid'], $number);
            $this->assertStringContainsString("Sub-BOM '{$number}'", json_encode($rows[0]['errors'] ?? $rows[0]));
        }
    }

    // ── 4. duplicate / revise ───────────────────────────────────────────────

    public function test_copy_points_the_material_at_the_current_sub_bom_and_says_so(): void
    {
        $old = $this->subBom();
        $parent = $this->bomVia($this->payload([$this->row('p1', $this->sf->id, 4, ['child_bom_id' => $old->id])]));
        $parent->update(['status' => ProductionBom::STATUS_APPROVED]);

        // a new sub-BOM version is approved: the old one is superseded
        $old->update(['status' => ProductionBom::STATUS_INACTIVE]);
        $new = $this->subBom([], '2.0.0', $old);

        $copy = app(ProductionBomService::class)->buildCopyPayload($parent->id, '1.0.1');
        $this->assertSame($new->id, $copy['items'][0]['child_bom_id']);
        $this->assertStringContainsString('replaced by BOM-LEG v2.0.0', implode(' ', $copy['warnings']));

        // and the copy is saveable as it is
        unset($copy['warnings'], $copy['source_version']);
        foreach ($copy['items'] as &$it) {
            unset($it['deleted_operation'], $it['sequence']);
        }
        unset($it);
        $this->web()->post(route('production.boms.store'), $copy)->assertSessionHasNoErrors();
    }

    public function test_copy_drops_the_sub_bom_when_none_is_active_any_more(): void
    {
        $old = $this->subBom();
        $parent = $this->bomVia($this->payload([$this->row('p1', $this->sf->id, 4, ['child_bom_id' => $old->id])]));
        $old->update(['status' => ProductionBom::STATUS_CANCELLED]);

        $copy = app(ProductionBomService::class)->buildCopyPayload($parent->id, '1.0.1');
        $this->assertNull($copy['items'][0]['child_bom_id']);
        $this->assertStringContainsString('no other sub-BOM is active', implode(' ', $copy['warnings']));
    }

    public function test_copy_keeps_a_sub_bom_that_is_still_selectable_without_a_warning(): void
    {
        $child = $this->subBom();
        $parent = $this->bomVia($this->payload([$this->row('p1', $this->sf->id, 4, ['child_bom_id' => $child->id])]));

        $copy = app(ProductionBomService::class)->buildCopyPayload($parent->id, '1.0.1');
        $this->assertSame($child->id, $copy['items'][0]['child_bom_id']);
        $this->assertSame([], $copy['warnings']);
    }
}
