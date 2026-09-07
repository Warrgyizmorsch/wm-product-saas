<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Branch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionRequisitionSlipItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UomNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Company $company;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Warrgyizmorsch Tenant',
            'slug' => 'warrgyizmorsch',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Test Company',
            'company_code' => 'COMP-01',
            'is_default' => 1,
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Main Branch',
            'code' => 'BR-01',
            'is_default' => 1,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'uom-user@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_material_request_show_page_renders_safely_when_uom_relation_is_null(): void
    {
        $uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);

        $fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Finished Good',
            'sku' => 'FG-001',
            'type' => 'goods',
            'uom_id' => $uom->id,
        ]);

        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Raw Material Box',
            'sku' => 'RM-BOX-01',
            'type' => 'raw_material',
            'uom_id' => $uom->id,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'PO-0001',
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'status' => 'released',
            'start_date' => now(),
            'due_date' => now()->addDays(7),
            'end_date' => now()->addDays(7),
        ]);

        $slip = ProductionRequisitionSlip::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'production_order_id' => $order->id,
            'requisition_number' => 'MR-0001',
            'requisition_date' => now(),
            'slip_number' => 'MR-0001',
            'status' => 'submitted',
        ]);

        $item = ProductionRequisitionSlipItem::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'production_requisition_slip_id' => $slip->id,
            'product_id' => $rm->id,
            'uom_id' => $uom->id,
            'quantity_planned' => 10,
            'quantity_reserved' => 5,
            'quantity_issued' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession([
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'branch_id' => $this->branch->id,
            ])
            ->get("http://warrgyizmorsch.localhost/inventory/material-requests/{$slip->id}");

        $response->assertStatus(200);
        $response->assertSee('RM-BOX-01');
    }

    public function test_product_show_page_renders_safely_when_uom_relation_is_null(): void
    {
        $uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'No UOM Product',
            'sku' => 'NO-UOM-001',
            'type' => 'goods',
            'uom_id' => $uom->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession([
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'branch_id' => $this->branch->id,
            ])
            ->get("http://warrgyizmorsch.localhost/inventory/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertSee('NO-UOM-001');
    }
}
