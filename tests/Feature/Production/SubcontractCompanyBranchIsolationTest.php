<?php

namespace Tests\Feature\Production;

use App\Core\Branch\BranchContext;
use App\Core\Company\CompanyContext;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\DeliveryChallanItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubcontractCompanyBranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Company $companyA;
    protected Company $companyB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $userA;
    protected User $userB;
    protected Product $product;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected Vendor $vendorA;
    protected Vendor $vendorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'slug' => 'isolation-tenant',
            'status' => 'active',
            'name' => 'Isolation Tenant',
        ]);

        $this->companyA = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Company A',
            'currency' => 'INR',
            'status' => 1,
        ]);

        $this->companyB = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Company B',
            'currency' => 'INR',
            'status' => 1,
        ]);

        $this->branchA = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'name' => 'Branch A',
            'code' => 'BR-A',
            'status' => 1,
        ]);

        $this->branchB = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'name' => 'Branch B',
            'code' => 'BR-B',
            'status' => 1,
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'User Company A',
            'email' => 'user.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'name' => 'User Company B',
            'email' => 'user.b@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Raw Casting',
            'sku' => 'CAST-001',
            'unit_of_measure' => 'PCS',
        ]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Store A',
            'code' => 'WH-A',
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'name' => 'Store B',
            'code' => 'WH-B',
        ]);

        $this->vendorA = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Vendor Alpha',
            'code' => 'VEND-A',
            'status' => 'active',
        ]);

        $this->vendorB = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'name' => 'Vendor Beta',
            'code' => 'VEND-B',
            'status' => 'active',
        ]);
    }

    public function test_challan_creation_assigns_correct_company_and_branch()
    {
        $this->actingAs($this->userA);
        app(CompanyContext::class)->set($this->companyA);
        app(BranchContext::class)->set($this->branchA);

        $orderA = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'order_number' => 'PO-CO-A-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.store'), [
                'production_order_id' => $orderA->id,
                'vendor_id' => $this->vendorA->id,
                'warehouse_id' => $this->warehouseA->id,
                'challan_date' => now()->toDateString(),
                'status' => 'draft',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'warehouse_id' => $this->warehouseA->id,
                        'quantity' => 5,
                        'unit_of_measure' => 'PCS',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('delivery_challans', [
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'production_order_id' => $orderA->id,
            'vendor_id' => $this->vendorA->id,
        ]);

        $this->assertDatabaseHas('delivery_challan_items', [
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
    }

    public function test_cannot_create_challan_with_incompatible_company_order()
    {
        $this->actingAs($this->userA);
        app(CompanyContext::class)->set($this->companyA);
        app(BranchContext::class)->set($this->branchA);

        // Order belongs to Company B
        $orderB = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchB->id,
            'order_number' => 'PO-CO-B-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.store'), [
                'production_order_id' => $orderB->id,
                'vendor_id' => $this->vendorA->id,
                'warehouse_id' => $this->warehouseA->id,
                'challan_date' => now()->toDateString(),
                'status' => 'draft',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'warehouse_id' => $this->warehouseA->id,
                        'quantity' => 5,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['production_order_id']);
    }

    public function test_cannot_create_challan_with_incompatible_company_vendor()
    {
        $this->actingAs($this->userA);
        app(CompanyContext::class)->set($this->companyA);
        app(BranchContext::class)->set($this->branchA);

        $orderA = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'order_number' => 'PO-CO-A-002',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        // Attempt using Vendor B
        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.subcontract.delivery-challans.store'), [
                'production_order_id' => $orderA->id,
                'vendor_id' => $this->vendorB->id,
                'warehouse_id' => $this->warehouseA->id,
                'challan_date' => now()->toDateString(),
                'status' => 'draft',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'warehouse_id' => $this->warehouseA->id,
                        'quantity' => 5,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['vendor_id']);
    }

    public function test_company_b_user_cannot_view_or_dispatch_company_a_challan()
    {
        // Create challan belonging to Company A
        $challanA = DeliveryChallan::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'challan_number' => 'DC-TEST-COA-001',
            'vendor_id' => $this->vendorA->id,
            'warehouse_id' => $this->warehouseA->id,
            'challan_date' => now(),
            'status' => 'draft',
            'dispatched_wip_qty' => 5,
        ]);

        // Act as User B (Company B)
        $this->actingAs($this->userB);
        app(CompanyContext::class)->set($this->companyB);
        app(BranchContext::class)->set($this->branchB);

        // 1. Repository listing must not include Company A's challan
        $repo = app(\App\Domains\Production\Repositories\DeliveryChallanRepositoryInterface::class);
        $paginated = $repo->paginate($this->tenant->id);
        $this->assertFalse($paginated->getCollection()->contains('id', $challanA->id));

        // 2. Direct show view route must be forbidden or scoped out
        $responseShow = $this->get(route('production.subcontract.delivery-challans.show', $challanA->id));
        $this->assertTrue(in_array($responseShow->status(), [403, 404]));

        // 3. Dispatch route must be forbidden
        $responseDispatch = $this->post(route('production.subcontract.delivery-challans.dispatch', $challanA->id));
        $this->assertTrue(in_array($responseDispatch->status(), [403, 404]));
    }
}
