<?php

namespace Tests\Feature\Inventory;

use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Warehouse $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Inventory Tenant',
            'slug' => 'test-inv-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        session(['tenant_slug' => $this->tenant->slug]);

        $this->seed(RbacSeeder::class);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Central Warehouse',
            'code' => 'WH-C1',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Copper Cable Reel',
            'sku' => 'COPPER-100',
            'unit_price' => 150.00,
        ]);
    }

    public function test_stock_transaction_creation_and_tenant_scope(): void
    {
        $this->actingAs($this->user);
        session(['tenant_slug' => $this->tenant->slug]);

        $tx = StockTransaction::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in',
            'quantity' => 100,
            'reference_type' => 'Manual Adjustment',
        ]);

        $this->assertDatabaseHas('stock_transactions', [
            'id' => $tx->id,
            'quantity' => 100,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_batch_and_serial_number_tracking(): void
    {
        $this->actingAs($this->user);
        session(['tenant_slug' => $this->tenant->slug]);

        $batch = Batch::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-2026-X',
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        $serial = SerialNumber::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'serial_number' => 'SN-1000928',
            'status' => 'Available',
        ]);

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'batch_number' => 'BATCH-2026-X',
        ]);

        $this->assertDatabaseHas('serial_numbers', [
            'id' => $serial->id,
            'serial_number' => 'SN-1000928',
        ]);
    }
}
