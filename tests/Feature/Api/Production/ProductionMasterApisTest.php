<?php

namespace Tests\Feature\Api\Production;

use App\Domains\HRMS\Models\Asset;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductionMasterApisTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $planner;
    private Uom $uom;
    private Product $finishedGood;
    private Product $rawMaterial;
    private WorkCenter $workCenter;
    private Machine $machine;
    private string $secret = 'wm-production-secret-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name'   => 'Titan Manufacturing',
            'slug'   => 'titan-mfg',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->planner = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Titan Chief Planner',
            'email'     => 'chief@titan.test',
            'password'  => 'password',
            'role'      => 'admin',
        ]);

        $managerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id'   => $this->planner->id,
            'role_id'   => $managerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Pieces',
            'code'      => 'PCS',
        ]);

        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Heavy Turbine Rotor',
            'sku'       => 'FG-ROTOR-01',
            'type'      => 'finished_good',
            'status'    => 'active',
            'uom_id'    => $this->uom->id,
            'unit_cost' => 500.0,
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Titanium Ingot',
            'sku'       => 'RM-TITANIUM-01',
            'type'      => 'raw_material',
            'status'    => 'active',
            'uom_id'    => $this->uom->id,
            'unit_cost' => 120.0,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'CNC Milling Cell 1',
            'code'                  => 'WC-MILL-01',
            'work_center_type'      => 'work_center',
            'capacity_per_hour'     => 10.0,
            'efficiency_percentage' => 95.0,
            'cost_per_hour'         => 45.0,
            'status'                => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'name'           => '5-Axis CNC Mill Alpha',
            'code'           => 'MCH-CNC-001',
            'machine_type'   => 'cnc_mill',
            'status'         => Machine::STATUS_ACTIVE,
            'capacity'       => 1.0,
        ]);
    }

    private function headers(): array
    {
        return [
            'Accept'        => 'application/json',
            'X-Tenant'      => $this->tenant->slug,
            'X-API-SECRET'  => $this->secret,
            'Authorization' => 'Bearer ' . $this->planner->createToken('test-token')->plainTextToken,
        ];
    }

    public function test_bom_reject_and_cancel_lifecycle(): void
    {
        $bom = ProductionBom::create([
            'tenant_id'      => $this->tenant->id,
            'bom_number'     => 'BOM-REJ-01',
            'bom_name'       => 'Rejection Test BOM',
            'bom_type'       => 'manufacturing',
            'product_id'     => $this->finishedGood->id,
            'base_quantity'  => 1.0,
            'base_uom_id'    => $this->uom->id,
            'version'        => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status'         => 'pending_approval',
            'created_by'     => $this->planner->id,
        ]);

        // Reject BOM
        $rejectRes = $this->withHeaders($this->headers())->postJson("/api/v1/production/boms/{$bom->id}/reject", [
            'comments' => 'Material specification incomplete.',
        ]);
        $rejectRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => ['status' => 'draft'],
            ]);

        // Cancel BOM
        $cancelRes = $this->withHeaders($this->headers())->postJson("/api/v1/production/boms/{$bom->id}/cancel", [
            'comments' => 'Project discontinued.',
        ]);
        $cancelRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => ['status' => 'cancelled'],
            ]);
    }

    public function test_bom_export_returns_file(): void
    {
        $res = $this->withHeaders($this->headers())->get('/api/v1/production/boms/export');
        $res->assertStatus(200);
        $this->assertStringContainsString('boms_export.xlsx', $res->headers->get('content-disposition'));
    }

    public function test_bom_import_succeeds_with_valid_csv(): void
    {
        $csvContent = implode("\n", [
            'bom_number,bom_name,product_code,base_quantity,base_uom_code,version,bom_type,usage_context,effective_date,expiry_date,component_code,item_quantity,item_uom_code,material_scrap_percentage,child_bom_number',
            "BOM-IMP-01,Imported BOM,{$this->finishedGood->sku},1,{$this->uom->code},1.0.0,manufacturing,manufacturing," . now()->toDateString() . ",,{$this->rawMaterial->sku},2,{$this->uom->code},0,",
        ]);

        $file = UploadedFile::fake()->createWithContent('boms.csv', $csvContent);

        $res = $this->withHeaders($this->headers())->post('/api/v1/production/boms/import', [
            'file'     => $file,
            'strategy' => 'create',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status'        => 'completed',
                    'success_count' => 1,
                ],
            ]);

        $this->assertDatabaseHas('production_boms', [
            'tenant_id'  => $this->tenant->id,
            'bom_number' => 'BOM-IMP-01',
        ]);
    }

    public function test_routing_operations_and_lifecycle(): void
    {
        $routing = Routing::create([
            'tenant_id'      => $this->tenant->id,
            'routing_number' => 'RT-OPS-01',
            'name'           => 'Operation Test Routing',
            'product_id'     => $this->finishedGood->id,
            'version'        => '1.0.0',
            'status'         => 'draft',
            'created_by'     => $this->planner->id,
        ]);

        RoutingOperation::create([
            'tenant_id'          => $this->tenant->id,
            'routing_id'         => $routing->id,
            'sequence'           => 10,
            'operation_number'   => 'OP-10',
            'name'               => 'Rough Milling',
            'operation_type'     => 'manufacturing',
            'work_center_id'     => $this->workCenter->id,
            'machine_id'         => $this->machine->id,
            'setup_time_minutes' => 15,
            'processing_time_minutes' => 45,
            'expected_yield_percentage' => 100,
        ]);

        // GET operations
        $opsRes = $this->withHeaders($this->headers())->getJson("/api/v1/production/routings/{$routing->id}/operations");
        $opsRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertCount(1, $opsRes->json('data'));

        // Reject routing
        $routing->update(['status' => 'pending_approval']);
        $rejectRes = $this->withHeaders($this->headers())->postJson("/api/v1/production/routings/{$routing->id}/reject", [
            'comments' => 'Sequence revision needed.',
        ]);
        $rejectRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => ['status' => 'draft'],
            ]);

        // Cancel routing
        $cancelRes = $this->withHeaders($this->headers())->postJson("/api/v1/production/routings/{$routing->id}/cancel", [
            'comments' => 'Not needed.',
        ]);
        $cancelRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => ['status' => 'cancelled'],
            ]);

        // Delete draft routing
        $draftRouting = Routing::create([
            'tenant_id'      => $this->tenant->id,
            'routing_number' => 'RT-DEL-01',
            'name'           => 'Deletable Routing',
            'product_id'     => $this->finishedGood->id,
            'version'        => '2.0.0',
            'status'         => 'draft',
            'created_by'     => $this->planner->id,
        ]);

        $delRes = $this->withHeaders($this->headers())->deleteJson("/api/v1/production/routings/{$draftRouting->id}");
        $delRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_routing_export_returns_file(): void
    {
        $res = $this->withHeaders($this->headers())->get('/api/v1/production/routings/export');
        $res->assertStatus(200);
        $this->assertStringContainsString('routings_export.xlsx', $res->headers->get('content-disposition'));
    }

    public function test_routing_import_succeeds(): void
    {
        $csvContent = implode("\n", [
            'routing_code,routing_name,product_code,version,operation_sequence,operation_code,operation_name,operation_type,work_center_code,machine_code,setup_time_minutes,processing_time_minutes,yield_percentage,is_external,material_code,material_quantity',
            "RT-IMP-01,Imported Routing,{$this->finishedGood->sku},1.0.0,1,OP-01,Rough Cut,manufacturing,{$this->workCenter->code},{$this->machine->code},10,30,100,no,,",
        ]);

        $file = UploadedFile::fake()->createWithContent('routings.csv', $csvContent);

        $res = $this->withHeaders($this->headers())->post('/api/v1/production/routings/import', [
            'file'     => $file,
            'strategy' => 'create',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status'        => 'completed',
                    'success_count' => 1,
                ],
            ]);

        $this->assertDatabaseHas('routings', [
            'tenant_id'      => $this->tenant->id,
            'routing_number' => 'RT-IMP-01',
        ]);
    }

    public function test_work_center_delete_and_protection(): void
    {
        // 1. Delete an unused work center succeeds
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Disposable Work Center',
            'code'      => 'WC-DISP-01',
            'status'    => 'active',
        ]);

        $delRes = $this->withHeaders($this->headers())->deleteJson("/api/v1/production/work-centers/{$wc->id}");
        $delRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // 2. Delete work center with routing operations fails (409)
        $routing = Routing::create([
            'tenant_id'      => $this->tenant->id,
            'routing_number' => 'RT-WC-TEST',
            'name'           => 'WC Test Routing',
            'product_id'     => $this->finishedGood->id,
            'version'        => '1.0.0',
            'status'         => 'active',
            'created_by'     => $this->planner->id,
        ]);

        RoutingOperation::create([
            'tenant_id'          => $this->tenant->id,
            'routing_id'         => $routing->id,
            'sequence'           => 1,
            'operation_number'   => 'OP-01',
            'name'               => 'Milling',
            'operation_type'     => 'manufacturing',
            'work_center_id'     => $this->workCenter->id,
            'machine_id'         => $this->machine->id,
            'setup_time_minutes' => 0,
            'processing_time_minutes' => 10,
            'expected_yield_percentage' => 100,
        ]);

        $delRes2 = $this->withHeaders($this->headers())->deleteJson("/api/v1/production/work-centers/{$this->workCenter->id}");
        $delRes2->assertStatus(403);
    }

    public function test_work_center_export_and_import(): void
    {
        $res = $this->withHeaders($this->headers())->get('/api/v1/production/work-centers/export');
        $res->assertStatus(200);
        $this->assertStringContainsString('work_centers_export.xlsx', $res->headers->get('content-disposition'));

        $csvContent = implode("\n", [
            'code,name,capacity_hours_per_day,efficiency_percentage,active',
            'WC-IMP-01,Imported Work Center,8,100,yes',
        ]);

        $file = UploadedFile::fake()->createWithContent('work_centers.csv', $csvContent);

        $importRes = $this->withHeaders($this->headers())->post('/api/v1/production/work-centers/import', [
            'file'     => $file,
            'strategy' => 'create',
        ]);

        $importRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status'        => 'completed',
                    'success_count' => 1,
                ],
            ]);

        $this->assertDatabaseHas('production_work_centers', [
            'tenant_id' => $this->tenant->id,
            'code'      => 'WC-IMP-01',
        ]);
    }

    public function test_machine_link_unlink_asset_and_delete(): void
    {
        $company = \App\Domains\HRMS\Models\Company::create([
            'tenant_id'    => $this->tenant->id,
            'company_name' => 'Titan Corp',
        ]);

        $category = \App\Domains\HRMS\Models\AssetCategory::create([
            'company_id' => $company->id,
            'name'       => 'Machinery & Equipment',
        ]);

        $asset = new Asset([
            'company_id'        => $company->id,
            'asset_category_id' => $category->id,
            'asset_code'        => 'AST-HAAS-001',
            'name'              => 'Haas 5-Axis Mill Capital Asset',
            'purchase_cost'     => 150000.0,
            'status'            => 'in_service',
            'purchase_date'     => now()->toDateString(),
        ]);
        $asset->tenant_id = $this->tenant->id;
        $asset->save();

        // Link asset
        $linkRes = $this->withHeaders($this->headers())->postJson("/api/v1/production/machines/{$this->machine->id}/link-asset", [
            'asset_id' => $asset->id,
        ]);
        $linkRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals($asset->id, $this->machine->fresh()->asset_id);

        // Unlink asset
        $unlinkRes = $this->withHeaders($this->headers())->postJson("/api/v1/production/machines/{$this->machine->id}/unlink-asset");
        $unlinkRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertNull($this->machine->fresh()->asset_id);

        // Delete unreferenced machine
        $delRes = $this->withHeaders($this->headers())->deleteJson("/api/v1/production/machines/{$this->machine->id}");
        $delRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_machine_export_and_import(): void
    {
        $res = $this->withHeaders($this->headers())->get('/api/v1/production/machines/export');
        $res->assertStatus(200);
        $this->assertStringContainsString('machines_export.xlsx', $res->headers->get('content-disposition'));

        $csvContent = implode("\n", [
            'code,name,work_center_code,hourly_cost,status',
            "MCH-IMP-01,Imported Lathe,{$this->workCenter->code},55.0,active",
        ]);

        $file = UploadedFile::fake()->createWithContent('machines.csv', $csvContent);

        $importRes = $this->withHeaders($this->headers())->post('/api/v1/production/machines/import', [
            'file'     => $file,
            'strategy' => 'create',
        ]);

        $importRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status'        => 'completed',
                    'success_count' => 1,
                ],
            ]);

        $this->assertDatabaseHas('production_machines', [
            'tenant_id' => $this->tenant->id,
            'code'      => 'MCH-IMP-01',
        ]);
    }
}
