<?php

namespace Database\Seeders;

use App\Core\Tenant\TenantRunner;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Domains\Production\Services\QualityPlanService;
use App\Models\Tenant;
use Database\Seeders\Concerns\LoadsTestProductionData;
use Database\Seeders\Support\TestProductionLinks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quality plans of the test production dataset (docs/production_seeder_audit_2026-11-26.md, SR-05; rules in
 * docs/production_quality_rules.md).
 *
 * Every plan is made through QualityPlanService exactly like the screens do — create → submit → approve, a second
 * version through createNextVersion → submit → approve (the old version becomes historical by itself) — so plan
 * numbers (QP-0001…, tenant sequence "quality_plan"), version families and the approval history come from the real
 * code. 30 plan families, 32 versions, every status and scope, chosen so the routing operations of
 * TestProductionEngineeringSeeder can carry 1–3 plans each (see the combinations table in the doc). The last four
 * (QP-0027 … QP-0030) belong to the subcontract examples (Part C).
 *
 * Runs after TestProductionMasterDataSeeder (sections and products) and before TestProductionEngineeringSeeder (which
 * links the approved section / process plans to routing operations). Units for numeric checks (mm, µm, N·m, degree)
 * are added here: AddTestMastersSeeder keeps its four units.
 */
class TestProductionQualitySeeder extends Seeder
{
    use LoadsTestProductionData;

    /** Units the numeric checks need, added to the tenant once (code => name). */
    public const UNITS = [
        'mm' => 'Millimetre',
        'um' => 'Micron (µm)',
        'Nm' => 'Newton metre',
        'deg' => 'Degree (angle)',
    ];

    /** Final state of a plan family after seeding. */
    private const APPROVED = 'approved';          // v1 approved
    private const TWO_VERSIONS = 'two_versions';  // v1 historical, v2 approved
    private const DRAFT = 'draft';                // created, never submitted
    private const REJECTED = 'rejected';          // created → submitted → rejected (back in draft, with the reason)
    private const PENDING = 'pending';            // created → submitted

    public function run(?int $tenantId = null): void
    {
        $tenantId = $this->resolveTenantId($tenantId);
        $links = new TestProductionLinks($tenantId);
        $userId = $links->userId(TestProductionLinks::SEED_USER_EMAIL);
        $tenant = Tenant::findOrFail($tenantId);

        $this->addUnits($tenantId);

        $created = app(TenantRunner::class)->run($tenant, function () use ($tenantId, $links, $userId) {
            $service = app(QualityPlanService::class);
            $count = 0;

            foreach ($this->plans() as $plan) {
                // safe to run twice: a family whose name exists already is left as it is
                if (ProductionQualityPlan::withTrashed()->where('tenant_id', $tenantId)->where('name', $plan['name'])->exists()) {
                    continue;
                }
                $this->seedFamily($service, $plan, $tenantId, $links, $userId);
                $count++;
            }

            return $count;
        });

        $this->command?->info("TestProductionQualitySeeder: {$created} quality plan famil" . ($created === 1 ? 'y' : 'ies') . " added for Tenant ID {$tenantId}.");
    }

    private function addUnits(int $tenantId): void
    {
        $columns = array_flip(Schema::getColumnListing('uoms'));

        foreach (self::UNITS as $code => $name) {
            if (DB::table('uoms')->where('tenant_id', $tenantId)->where('code', $code)->exists()) {
                continue;
            }
            DB::table('uoms')->insert(array_intersect_key([
                'tenant_id' => $tenantId, 'code' => $code, 'name' => $name, 'category' => 'Goods',
                'created_at' => now(), 'updated_at' => now(),
            ], $columns));
        }
    }

    /** One plan family through the lifecycle, ending in the state the data asks for. */
    private function seedFamily(QualityPlanService $service, array $plan, int $tenantId, TestProductionLinks $links, int $userId): void
    {
        $header = ['name' => $plan['name'], 'type' => $plan['scope']];
        if ($plan['scope'] === ProductionQualityPlan::SCOPE_SECTION) {
            $header['section_id'] = $links->sectionId($plan['section']);
        }
        if ($plan['scope'] === ProductionQualityPlan::SCOPE_PRODUCT) {
            $header['product_id'] = $links->productId($plan['product']);
        }

        $versions = $plan['versions'];
        $v1 = $service->create($tenantId, $header + ['parameters' => $this->parameters($versions[0], $links)], $userId);

        switch ($plan['state']) {
            case self::DRAFT:
                return;
            case self::PENDING:
                $service->submit($v1, $userId);

                return;
            case self::REJECTED:
                $service->reject($service->submit($v1, $userId), $userId, $plan['reject_reason']);

                return;
            case self::APPROVED:
                $service->approve($service->submit($v1, $userId), $userId, 'Approved for production use.');

                return;
            case self::TWO_VERSIONS:
                $v1 = $service->approve($service->submit($v1, $userId), $userId, 'Approved for production use.');
                $v2 = $service->createNextVersion($v1, $header + ['parameters' => $this->parameters($versions[1], $links)], $userId);
                $service->approve($service->submit($v2, $userId), $userId, $plan['v2_reason']);

                return;
        }

        throw new \RuntimeException("Test quality plan {$plan['name']}: unknown state {$plan['state']}.");
    }

    /** Parameter rows in the shape the plan form posts (unit code → uom_id of this tenant). */
    private function parameters(array $rows, TestProductionLinks $links): array
    {
        return array_map(function (array $row) use ($links) {
            if (isset($row['unit'])) {
                $row['uom_id'] = $links->uomId($row['unit']);
                unset($row['unit']);
            }

            return $row;
        }, $rows);
    }

    private static function num(string $name, float $min, float $max, string $unit, bool $mandatory = true): array
    {
        return ['name' => $name, 'type' => ProductionQualityPlanParameter::TYPE_NUMERIC, 'min_value' => $min, 'max_value' => $max, 'unit' => $unit, 'is_mandatory' => $mandatory];
    }

    private static function passFail(string $name, string $basis, bool $mandatory = true): array
    {
        return ['name' => $name, 'type' => ProductionQualityPlanParameter::TYPE_PASS_FAIL, 'basis' => $basis, 'is_mandatory' => $mandatory];
    }

    private static function text(string $name, bool $mandatory = false): array
    {
        return ['name' => $name, 'type' => ProductionQualityPlanParameter::TYPE_TEXT, 'is_mandatory' => $mandatory];
    }

    /**
     * The plan families, in creation order (QP-0001 … QP-0030). Sections by code, products by SKU.
     *
     * @return array<int, array<string, mixed>>
     */
    public function plans(): array
    {
        $section = ProductionQualityPlan::SCOPE_SECTION;
        $process = ProductionQualityPlan::SCOPE_PROCESS;
        $product = ProductionQualityPlan::SCOPE_PRODUCT;

        return [
            // ── Section plans ──────────────────────────────────────────────────────────
            ['name' => 'Edge deburr check', 'scope' => $section, 'section' => 'TEST-SEC-3', 'state' => self::APPROVED, 'versions' => [[
                self::passFail('Sharp edges', 'No sharp edge felt when the edge is run with a gloved hand'),
                self::num('Burr height', 0, 0.2, 'mm'),
            ]]],
            ['name' => 'Cut length check', 'scope' => $section, 'section' => 'TEST-SEC-1', 'state' => self::APPROVED, 'versions' => [[
                self::num('Cut length deviation', -0.5, 0.5, 'mm'),
                self::passFail('Cut squareness', 'No gap visible under a try-square held on the cut face'),
            ]]],
            ['name' => 'Bend angle check', 'scope' => $section, 'section' => 'TEST-SEC-2', 'state' => self::APPROVED, 'versions' => [[
                self::num('Bend angle', 89.5, 90.5, 'deg'),
                self::text('Springback notes'),
            ]]],
            ['name' => 'Weld inspection', 'scope' => $section, 'section' => 'TEST-SEC-8', 'state' => self::APPROVED, 'versions' => [[
                self::passFail('Porosity / cracks', 'None visible under a 5x lens along the full weld length'),
                self::num('Fillet weld size', 4, 6, 'mm'),
                self::text('Notes'),
            ]]],
            ['name' => 'Weld spatter & cleanup', 'scope' => $section, 'section' => 'TEST-SEC-8', 'state' => self::APPROVED, 'versions' => [[
                self::passFail('Spatter removed', 'No weld spatter left on visible faces'),
                self::passFail('Grinding marks', 'Grinding marks blended, not felt by hand', false),
            ]]],
            ['name' => 'Powder coat inspection', 'scope' => $section, 'section' => 'TEST-SEC-8', 'state' => self::APPROVED, 'versions' => [[
                self::num('Coating thickness', 60, 90, 'um'),
                self::passFail('Cross-hatch adhesion', 'Class 0–1 per ISO 2409 (no flaking at the cut lattice)'),
                self::passFail('Colour match', 'Matches the RAL 9005 master panel under daylight lamp', false),
            ]]],
            ['name' => 'Hand finish touch test', 'scope' => $section, 'section' => 'TEST-SEC-6', 'state' => self::APPROVED, 'versions' => [[
                self::passFail('Smooth to touch', 'No raised grain or rough patch felt across the surface'),
                self::text('Grit grade used'),
            ]]],
            ['name' => 'Seal bead check', 'scope' => $section, 'section' => 'TEST-SEC-5', 'state' => self::APPROVED, 'versions' => [[
                self::num('Bead width', 3, 5, 'mm'),
                self::passFail('Continuous bead', 'Bead unbroken along every edge, no gaps or bubbles'),
            ]]],
            ['name' => 'Assembly torque check', 'scope' => $section, 'section' => 'TEST-SEC-9', 'state' => self::APPROVED, 'versions' => [[
                self::num('Bolt torque', 22, 26, 'Nm'),
                self::passFail('All fasteners fitted', 'Every bolt, nut and washer of the drawing is fitted'),
            ]]],
            ['name' => 'Final crating inspection', 'scope' => $section, 'section' => 'TEST-SEC-10', 'state' => self::APPROVED, 'versions' => [[
                self::passFail('Surface scratches', 'No scratch longer than 5 mm on any visible face'),
                self::passFail('Label & barcode', 'Product label present and barcode scans'),
                self::passFail('Carton drop test', 'Sample carton survives a 600 mm corner drop', false),
            ]]],
            ['name' => 'Packing count & seal', 'scope' => $section, 'section' => 'TEST-SEC-10', 'state' => self::APPROVED, 'versions' => [[
                self::passFail('Parts count matches packing list', 'Every item on the packing list is in the carton'),
                self::passFail('Tape seal intact', 'Tape seal unbroken on all carton flaps'),
            ]]],
            // SD-02: v1 stays linked on routing 009 (outdated version demo, QR-09 / QR-19)
            ['name' => 'Flange hole check', 'scope' => $section, 'section' => 'TEST-SEC-7', 'state' => self::TWO_VERSIONS,
                'v2_reason' => 'Tighter hole pitch tolerance and thread check added.', 'versions' => [
                    [self::num('Hole pitch deviation', -0.2, 0.2, 'mm')],
                    [self::num('Hole pitch deviation', -0.1, 0.1, 'mm'), self::passFail('Thread go / no-go', 'Go gauge enters fully, no-go gauge does not')],
                ]],
            ['name' => 'CNC first-article check', 'scope' => $section, 'section' => 'TEST-SEC-7', 'state' => self::DRAFT, 'versions' => [[
                self::num('Hole position deviation', -0.1, 0.1, 'mm'),
            ]]],
            ['name' => 'Coating gloss check', 'scope' => $section, 'section' => 'TEST-SEC-8', 'state' => self::REJECTED,
                'reject_reason' => 'Add the gloss meter reading in GU instead of a pass / fail check.', 'versions' => [[
                    self::passFail('Gloss level', 'Gloss looks even across the panel'),
                ]]],

            // ── Process-general plans ──────────────────────────────────────────────────
            ['name' => 'Dimensional check — general', 'scope' => $process, 'state' => self::APPROVED, 'versions' => [[
                self::num('Length deviation', -1, 1, 'mm'),
                self::num('Squareness deviation', 0, 2, 'mm'),
            ]]],
            ['name' => 'Visual surface check — general', 'scope' => $process, 'state' => self::APPROVED, 'versions' => [[
                self::passFail('Dents / scratches', 'None larger than 2 mm seen from 1 m'),
                self::text('Notes'),
            ]]],
            ['name' => 'Safety & sharp-edge check — general', 'scope' => $process, 'state' => self::APPROVED, 'versions' => [[
                self::passFail('No sharp edges or burrs', 'Nothing that can cut a glove at handling points'),
                self::text('Guard notes'),
            ]]],
            // a plan with no mandatory check at all
            ['name' => 'Cleanliness check — general', 'scope' => $process, 'state' => self::APPROVED, 'versions' => [[
                self::passFail('Oil / dust free', 'Wipe test with a white cloth leaves no mark', false),
                self::text('Notes'),
            ]]],
            ['name' => 'Packaging check — general', 'scope' => $process, 'state' => self::PENDING, 'versions' => [[
                self::passFail('Carton undamaged', 'No crushed corner or tear on the carton'),
            ]]],

            // ── Product plans (one family per product, QM-14) ─────────────────────────
            ['name' => 'Steel leg column check', 'scope' => $product, 'product' => 'test-semi-01', 'state' => self::APPROVED, 'versions' => [[
                self::num('Leg length', 719, 721, 'mm'),
                self::passFail('Straightness', 'Within 0.5 mm over the full length on the surface plate'),
            ]]],
            ['name' => 'Corner brace check', 'scope' => $product, 'product' => 'test-semi-05', 'state' => self::APPROVED, 'versions' => [[
                self::num('Brace angle', 44, 46, 'deg'),
                self::passFail('Weld joint', 'Leg and bracket welded all round, no cracks'),
            ]]],
            ['name' => 'Base chassis check', 'scope' => $product, 'product' => 'test-semifinal-01', 'state' => self::APPROVED, 'versions' => [[
                self::num('Diagonal difference', 0, 3, 'mm'),
                self::passFail('Weld completeness', 'Every joint of the welding drawing is welded'),
            ]]],
            ['name' => 'Top board check', 'scope' => $product, 'product' => 'test-semifinal-03', 'state' => self::APPROVED, 'versions' => [[
                self::num('Board thickness', 24.5, 25.5, 'mm'),
                self::passFail('Edge seal', 'All four edges sealed, no bare wood visible'),
                self::text('Notes'),
            ]]],
            ['name' => 'Executive table final QC', 'scope' => $product, 'product' => 'test-final-01', 'state' => self::APPROVED, 'versions' => [[
                self::num('Top flatness', 0, 1.5, 'mm'),
                self::passFail('Wobble test', 'No rocking when pressed at each corner on a level floor'),
                self::passFail('Finish', 'No visible coating or board defect'),
                self::text('Remarks'),
            ]]],
            // SD-04: version history of a product plan; WIP final QC uses v2
            ['name' => 'Premium table final QC', 'scope' => $product, 'product' => 'test-final-02', 'state' => self::TWO_VERSIONS,
                'v2_reason' => 'Flatness tightened and cable routing check added.', 'versions' => [
                    [self::num('Top flatness', 0, 2, 'mm'), self::passFail('Wobble test', 'No rocking when pressed at each corner on a level floor')],
                    [self::num('Top flatness', 0, 1.5, 'mm'), self::passFail('Wobble test', 'No rocking when pressed at each corner on a level floor'), self::passFail('Cable routing', 'Harness clipped in the tray, no loose cable under the top')],
                ]],
            // traded product: the plan can exist; its GRN gate is deferred (QM-02)
            ['name' => 'Steel tube incoming check', 'scope' => $product, 'product' => 'test-raw-01', 'state' => self::APPROVED, 'versions' => [[
                self::num('Wall thickness', 1.9, 2.1, 'mm'),
                self::passFail('Rust', 'No red rust on the outside or at the tube ends'),
            ]]],

            // ── Subcontract examples (QP-0027 … QP-0030, Part C of the doc) ─────────────
            // an outsourced operation has no section, so only process plans can be linked to it (QR-16); its receipt
            // waits for this inspection like any shop-floor operation (TF-10)
            ['name' => 'Subcontract receipt check — general', 'scope' => $process, 'state' => self::APPROVED, 'versions' => [[
                self::passFail('Count matches dispatch slip', 'Units counted back equal the units on the dispatch slip, less any reported scrap'),
                self::passFail('Transit damage', 'No dent, bend or coating scuff from transport'),
                self::text('Vendor remarks'),
            ]]],
            ['name' => 'Zinc plating check — general', 'scope' => $process, 'state' => self::APPROVED, 'versions' => [[
                self::num('Zinc thickness', 8, 15, 'um'),
                self::passFail('Adhesion bend test', 'No flaking or peeling after a 90° bend of a sample piece'),
                self::passFail('White rust', 'No white rust spots on the plated surface', false),
            ]]],
            ['name' => 'Outdoor frame check', 'scope' => $product, 'product' => 'test-semifinal-04', 'state' => self::APPROVED, 'versions' => [[
                self::num('Galvanised coating thickness', 45, 85, 'um'),
                self::num('Diagonal difference', 0, 3, 'mm'),
                self::passFail('Coating coverage', 'No bare steel or drip runs at welds and tube ends'),
            ]]],
            ['name' => 'Outdoor table final QC', 'scope' => $product, 'product' => 'test-final-03', 'state' => self::APPROVED, 'versions' => [[
                self::num('Top flatness', 0, 1.5, 'mm'),
                self::passFail('Wobble test', 'No rocking when pressed at each corner on a level floor'),
                self::passFail('Outdoor finish', 'No bare zinc or coating gap on the frame'),
                self::text('Remarks'),
            ]]],
        ];
    }
}
