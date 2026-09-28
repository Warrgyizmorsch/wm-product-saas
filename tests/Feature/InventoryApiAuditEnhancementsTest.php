<?php

namespace Tests\Feature;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Platform\Models\Transporter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryApiAuditEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private Branch $branch;
    private User $user;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Audit Test Tenant',
            'slug'   => 'audit-tenant',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->company = Company::create([
            'tenant_id'    => $this->tenant->id,
            'company_name' => 'Audit Company',
            'company_code' => 'AUD-01',
            'is_default'   => 1,
        ]);

        $this->branch = Branch::create([
            'tenant_id'   => $this->tenant->id,
            'company_id'  => $this->company->id,
            'name'        => 'Audit Branch',
            'code'        => 'BR-AUD-01',
            'is_default'  => 1,
        ]);

        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id'  => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'name'       => 'Inventory Api Tester',
            'email'      => 'tester@example.com',
            'password'   => bcrypt('secret123'),
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id'   => $this->tenant->id,
            'company_id'  => $this->company->id,
            'branch_id'   => $this->branch->id,
            'name'        => 'Main Central Store',
            'code'        => 'MCS-01',
            'type'        => 'finished_goods',
            'is_default'  => 1,
            'is_active'   => 1,
        ]);

        $this->product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'company_id'     => $this->company->id,
            'branch_id'      => $this->branch->id,
            'name'           => 'Precision Ball Bearing',
            'sku'            => 'PBB-6001',
            'item_type'      => 'Goods',
            'type'           => 'finished_good',
            'selling_price'  => 350.00,
            'cost_price'     => 180.00,
            'unit_cost'      => 180.00,
            'status'         => 'active',
            'reorder_point'  => 15.0,
        ]);

        ProductWarehouseStock::create([
            'tenant_id'     => $this->tenant->id,
            'company_id'    => $this->company->id,
            'branch_id'     => $this->branch->id,
            'product_id'    => $this->product->id,
            'warehouse_id'  => $this->warehouse->id,
            'quantity'      => 50.0,
            'reserved_qty'  => 10.0,
            'unit_cost'     => 180.00,
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    /** @test */
    public function can_fetch_product_warehouse_stocks_breakdown(): void
    {
        $response = $this->withHeader('X-Tenant', 'audit-tenant')
            ->getJson("/api/inventory/products/{$this->product->id}/warehouse-stocks");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('product.sku', 'PBB-6001')
            ->assertJsonPath('product.total_on_hand', 50)
            ->assertJsonPath('product.total_reserved', 10)
            ->assertJsonPath('product.net_available', 40);
    }

    /** @test */
    public function can_download_product_sample_csv(): void
    {
        $response = $this->withHeader('X-Tenant', 'audit-tenant')
            ->get('/api/inventory/products/download-sample');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    /** @test */
    public function can_manage_transporters_via_api(): void
    {
        $createRes = $this->withHeader('X-Tenant', 'audit-tenant')
            ->postJson('/api/inventory/transporters', [
                'name'           => 'Safexpress Logistics',
                'transporter_id' => 'TRP-SAFE-01',
                'phone'          => '9876543210',
                'gstin'          => '07AAAAA0000A1Z5',
            ]);

        $createRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Safexpress Logistics');

        $transporterId = $createRes->json('data.id');

        $indexRes = $this->withHeader('X-Tenant', 'audit-tenant')
            ->getJson('/api/inventory/transporters');

        $indexRes->assertOk()
            ->assertJsonPath('success', true);

        $updateRes = $this->withHeader('X-Tenant', 'audit-tenant')
            ->putJson("/api/inventory/transporters/{$transporterId}", [
                'city' => 'New Delhi',
            ]);

        $updateRes->assertOk()
            ->assertJsonPath('data.city', 'New Delhi');
    }

    /** @test */
    public function can_update_batch_and_serial_number_via_api(): void
    {
        $batch = Batch::create([
            'tenant_id'     => $this->tenant->id,
            'company_id'    => $this->company->id,
            'branch_id'     => $this->branch->id,
            'product_id'    => $this->product->id,
            'warehouse_id'  => $this->warehouse->id,
            'batch_number'  => 'BATCH-2026-A',
            'quantity'      => 100,
            'available_qty' => 100,
            'expiry_date'   => now()->addDays(20)->toDateString(),
        ]);

        $serial = SerialNumber::create([
            'tenant_id'     => $this->tenant->id,
            'company_id'    => $this->company->id,
            'branch_id'     => $this->branch->id,
            'product_id'    => $this->product->id,
            'warehouse_id'  => $this->warehouse->id,
            'batch_id'      => $batch->id,
            'serial_number' => 'SN-2026-001',
            'status'        => 'Available',
        ]);

        // Update Batch
        $batchRes = $this->withHeader('X-Tenant', 'audit-tenant')
            ->putJson("/api/inventory/batches/{$batch->id}", [
                'available_qty' => 95,
            ]);

        $batchRes->assertOk()
            ->assertJsonPath('data.available_qty', 95);

        // Update Serial
        $serialRes = $this->withHeader('X-Tenant', 'audit-tenant')
            ->putJson("/api/inventory/serials/{$serial->id}", [
                'status' => 'Damaged',
            ]);

        $serialRes->assertOk()
            ->assertJsonPath('data.status', 'Damaged');

        // Expiry Report
        $expiryRes = $this->withHeader('X-Tenant', 'audit-tenant')
            ->getJson('/api/inventory/reports/expiry?days=30');

        $expiryRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.expiring_soon_count', 1);
    }

    /** @test */
    public function can_calculate_mrp_shortage_and_generate_pr(): void
    {
        $calcRes = $this->withHeader('X-Tenant', 'audit-tenant')
            ->getJson("/api/inventory/mrp-shortage/calculate?product_id={$this->product->id}&quantity=100");

        $calcRes->assertOk()
            ->assertJsonPath('success', true);

        $prRes = $this->withHeader('X-Tenant', 'audit-tenant')
            ->postJson('/api/inventory/mrp-shortage/generate-pr', [
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id'   => $this->product->id,
                        'quantity'     => 50,
                        'shortage_qty' => 50,
                        'unit_cost'    => 180.00,
                    ]
                ],
            ]);

        $prRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['requisition_number', 'items']]);
    }
}
