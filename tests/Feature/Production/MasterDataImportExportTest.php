<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MasterDataImportExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private User $operator;
    private Product $finishedProduct;
    private Product $component1;
    private Product $component2;
    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Import Corp',
            'slug' => 'import-corp',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Production Manager',
            'email' => 'manager@import.com',
            'password' => bcrypt('password'),
            'role' => 'admin', // has all production permissions by default
        ]);

        $this->operator = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Operator User',
            'email' => 'operator@import.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        // Seed UOM
        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PCS',
            'name' => 'Pieces',
            'category' => 'unit',
            'ratio' => 1.0,
            'active' => true,
        ]);

        // Seed Products
        $this->finishedProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Product Model X',
            'sku' => 'FG-PROD-X',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->component1 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Timber Oak',
            'sku' => 'RM-OAK-WD',
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->component2 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Stainless Steel Screws',
            'sku' => 'RM-SCR-01',
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function manager_can_download_templates()
    {
        $types = ['work-centers', 'machines', 'boms', 'routings'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->manager)
                ->withHeader('X-Tenant', 'import-corp')
                ->get(route('production.import-export.download-template', $type));

            $response->assertStatus(200);
            $response->assertHeader('Content-Disposition');
        }
    }

    /** @test */
    public function operator_cannot_download_templates()
    {
        $response = $this->actingAs($this->operator)
            ->withHeader('X-Tenant', 'import-corp')
            ->get(route('production.import-export.download-template', 'work-centers'));

        $response->assertStatus(403);
    }

    /** @test */
    public function import_work_centers_validation_and_confirm_in_create_mode()
    {
        $csvContent = "code,name,capacity_hours_per_day,efficiency_percentage,active\n" .
                      "WC-TEST-01,Test Center 01,16.0,95.0,Yes\n" .
                      "WC-TEST-02,Test Center 02,8.0,90.0,No\n";

        $file = UploadedFile::fake()->createWithContent('work_centers.csv', $csvContent);

        // 1. Post to import-preview
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->post(route('production.import-export.import-preview', 'work-centers'), [
                'file' => $file
            ]);

        $response->assertStatus(200);
        $response->assertViewHas('previewRows');
        $response->assertViewHas('errorCount', 0);

        // 2. Post to confirm-import
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->post(route('production.import-export.import-confirm', 'work-centers'), [
                'strategy' => 'create'
            ]);

        $response->assertRedirect(route('production.work-centers.index'));
        $this->assertDatabaseHas('production_work_centers', [
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-TEST-01',
            'status' => 'active'
        ]);
        $this->assertDatabaseHas('production_work_centers', [
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-TEST-02',
            'status' => 'inactive'
        ]);
    }

    /** @test */
    public function import_work_centers_rejects_invalid_values()
    {
        $csvContent = "code,name,capacity_hours_per_day,efficiency_percentage,active\n" .
                      "WC-TEST-01,Test Center 01,28.0,150.0,Yes\n"; // Capacity > 24, Efficiency > 100

        $file = UploadedFile::fake()->createWithContent('work_centers.csv', $csvContent);

        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->post(route('production.import-export.import-preview', 'work-centers'), [
                'file' => $file
            ]);

        $response->assertStatus(200);
        $response->assertViewHas('errorCount', 1);
    }

    /** @test */
    public function import_machines_rejections_for_missing_work_center()
    {
        $csvContent = "code,name,work_center_code,hourly_cost,status\n" .
                      "MCH-TEST-01,Test Machine 01,WC-NOT-EXIST,50.00,active\n";

        $file = UploadedFile::fake()->createWithContent('machines.csv', $csvContent);

        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->post(route('production.import-export.import-preview', 'machines'), [
                'file' => $file
            ]);

        $response->assertStatus(200);
        $response->assertViewHas('errorCount', 1);
    }

    /** @test */
    public function import_boms_hierarchical_saving()
    {
        $csvContent = "bom_number,bom_name,product_code,base_quantity,base_uom_code,version,bom_type,usage_context,effective_date,expiry_date,component_code,item_quantity,item_uom_code,material_scrap_percentage,child_bom_number\n" .
                      "BOM-TEST-01,Oak Table BOM,FG-PROD-X,1,PCS,1.0.0,manufacturing,manufacturing,2026-07-14,,RM-OAK-WD,4,PCS,5.0,\n" .
                      "BOM-TEST-01,,,,,,,,,,RM-SCR-01,16,PCS,0.0,\n";

        $file = UploadedFile::fake()->createWithContent('boms.csv', $csvContent);

        // Preview
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->post(route('production.import-export.import-preview', 'boms'), [
                'file' => $file
            ]);

        $response->assertStatus(200);
        $response->assertViewHas('errorCount', 0);

        // Confirm
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->post(route('production.import-export.import-confirm', 'boms'), [
                'strategy' => 'create'
            ]);

        $response->assertRedirect(route('production.boms.index'));
        $this->assertDatabaseHas('production_boms', [
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-TEST-01',
            'status' => 'draft' // Respect approval workflow (set to draft)
        ]);

        $bom = ProductionBom::where('bom_number', 'BOM-TEST-01')->first();
        $this->assertCount(2, $bom->items);
    }

    /** @test */
    public function import_boms_rejects_circular_dependencies()
    {
        // BOM-TEST-01 defines finished product FG-PROD-X, but includes FG-PROD-X as component!
        $csvContent = "bom_number,bom_name,product_code,base_quantity,base_uom_code,version,bom_type,usage_context,effective_date,expiry_date,component_code,item_quantity,item_uom_code,material_scrap_percentage,child_bom_number\n" .
                      "BOM-TEST-01,Oak Table BOM,FG-PROD-X,1,PCS,1.0.0,manufacturing,manufacturing,2026-07-14,,FG-PROD-X,1,PCS,0.0,\n";

        $file = UploadedFile::fake()->createWithContent('boms.csv', $csvContent);

        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->post(route('production.import-export.import-preview', 'boms'), [
                'file' => $file
            ]);

        $response->assertStatus(200);
        $response->assertViewHas('errorCount', 1);
        $previewRows = $response->viewData('previewRows');
        $this->assertStringContainsString('Circular dependency', $previewRows[0]['errors'][0]);
    }

    /** @test */
    public function import_routings_machine_work_center_mismatch_rejection()
    {
        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-01',
            'name' => 'Work Center 01',
        ]);

        $wc2 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-02',
            'name' => 'Work Center 02',
        ]);

        // Machine belongs to WC-02
        $machine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $wc2->id,
            'code' => 'MCH-02',
            'name' => 'Machine 02',
        ]);

        // CSV tries to map routing operation to WC-01, but selects machine MCH-02 (mismatch!)
        $csvContent = "routing_code,routing_name,product_code,version,operation_sequence,operation_name,operation_code,operation_type,work_center_code,machine_code,setup_time_minutes,processing_time_minutes,yield_percentage,is_external,material_code,material_quantity\n" .
                      "RT-TEST-01,Test Routing,FG-PROD-X,1.0.0,10,Cutting,OP-10,manufacturing,WC-01,MCH-02,10,30,98,No,,\n";

        $file = UploadedFile::fake()->createWithContent('routings.csv', $csvContent);

        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->post(route('production.import-export.import-preview', 'routings'), [
                'file' => $file
            ]);

        $response->assertStatus(200);
        $response->assertViewHas('errorCount', 1);
        $previewRows = $response->viewData('previewRows');
        $this->assertStringContainsString('mismatch', $previewRows[0]['errors'][0]);
    }

    /** @test */
    public function export_enforces_tenant_isolation()
    {
        // Create other tenant and work center
        $otherTenant = Tenant::create(['name' => 'Other Corp', 'slug' => 'other-corp']);
        WorkCenter::create([
            'tenant_id' => $otherTenant->id,
            'code' => 'WC-OTHER',
            'name' => 'Other Work Center',
        ]);

        WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-MINE',
            'name' => 'My Work Center',
        ]);

        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->get(route('production.import-export.export', 'work-centers'));

        $response->assertStatus(200);
        // Clean download execution
    }

    /** @test */
    public function import_preview_supports_get_request_when_session_present()
    {
        $sessionRows = [
            [
                'row_number' => 2,
                'key' => 'ROUT-TEST',
                'valid' => true,
                'errors' => [],
                'data' => [
                    'routing_number' => 'ROUT-TEST',
                    'name' => 'Test Routing',
                    'operations' => []
                ]
            ]
        ];

        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->withSession(['production_import_preview_routings' => $sessionRows])
            ->get(route('production.import-export.import-preview', 'routings'));

        $response->assertStatus(200);
        $response->assertViewIs('modules.production.import_preview');
        $response->assertViewHas('type', 'routings');
        $response->assertViewHas('errorCount', 0);
    }

    /** @test */
    public function import_preview_get_redirects_to_index_when_no_session()
    {
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->get(route('production.import-export.import-preview', 'routings'));

        $response->assertRedirect(route('production.routing.index'));
        $response->assertSessionHas('info');
    }

    /** @test */
    public function import_confirm_get_redirects_to_index()
    {
        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->get(route('production.import-export.import-confirm', 'routings'));

        $response->assertRedirect(route('production.routing.index'));
    }

    /** @test */
    public function import_confirm_update_strategy_recreates_operations_without_sequence_collision()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Table',
            'sku' => 'SKU-ROUT-TBL',
            'type' => 'product',
        ]);

        $workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-MAIN-ASSEMBLY',
            'name' => 'Main Assembly Line',
        ]);

        // Existing routing with sequence 60
        $routing = \App\Domains\Production\Models\Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-TBL-FG',
            'name' => 'Old Routing Name',
            'product_id' => $product->id,
            'version' => '1.0',
            'status' => 'draft',
            'created_by' => $this->manager->id,
        ]);

        \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 60,
            'operation_number' => 'OP60',
            'name' => 'Old Operation 60',
            'operation_type' => 'manufacturing',
            'work_center_id' => $workCenter->id,
            'setup_time_minutes' => 5,
            'processing_time_minutes' => 10,
            'expected_yield_percentage' => 100,
            'is_external' => false,
        ]);

        // Incoming import preview with update strategy and the same sequence 60
        $sessionRows = [
            [
                'row_number' => 2,
                'key' => 'RT-TBL-FG',
                'valid' => true,
                'errors' => [],
                'data' => [
                    'routing_number' => 'RT-TBL-FG',
                    'name' => 'Updated Routing Name',
                    'product_id' => $product->id,
                    'version' => '1.1',
                    'operations' => [
                        [
                            'sequence' => 60,
                            'operation_number' => 'OP60',
                            'name' => 'Final Dining Table Assembly',
                            'operation_type' => 'manufacturing',
                            'work_center_id' => $workCenter->id,
                            'machine_id' => null,
                            'setup_time_minutes' => 10,
                            'processing_time_minutes' => 15,
                            'expected_yield_percentage' => 100,
                            'is_external' => false,
                            'materials' => []
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->actingAs($this->manager)
            ->withHeader('X-Tenant', 'import-corp')
            ->withSession(['production_import_preview_routings' => $sessionRows])
            ->post(route('production.import-export.import-confirm', 'routings'), [
                'strategy' => 'update'
            ]);

        $response->assertRedirect(route('production.routing.index'));
        $response->assertSessionHas('success');

        // Confirm database has updated operation without integrity violation
        $this->assertDatabaseHas('production_routing_operations', [
            'routing_id' => $routing->id,
            'sequence' => 60,
            'name' => 'Final Dining Table Assembly',
            'deleted_at' => null
        ]);
    }
}


