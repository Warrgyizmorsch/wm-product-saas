<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionDeviation;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionReworkOperation;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionScanLog;
use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\DeviationService;
use App\Domains\Production\Services\ReworkService;
use App\Domains\Production\Services\ScrapService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionStabilizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function exposed_production_routes_point_to_existing_controller_methods(): void
    {
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! str_starts_with($name, 'production.')) {
                continue;
            }

            $uses = $route->getAction('uses');

            if ($uses instanceof \Closure) {
                continue;
            }

            if (is_string($uses) && str_contains($uses, '@')) {
                [$class, $method] = explode('@', $uses, 2);
            } elseif (is_array($uses) && count($uses) === 2) {
                [$class, $method] = $uses;
            } else {
                $this->fail("Production route [{$name}] has an unsupported action definition.");
            }

            $this->assertTrue(class_exists($class), "Controller [{$class}] for route [{$name}] does not exist.");
            $this->assertTrue(method_exists($class, $method), "Controller method [{$class}@{$method}] for route [{$name}] does not exist.");
        }
    }

    #[Test]
    public function removed_generic_quality_resource_routes_are_not_exposed(): void
    {
        $this->assertFalse(Route::has('production.inspections.edit'));
        $this->assertFalse(Route::has('production.ncrs.destroy'));
        $this->assertFalse(Route::has('production.capas.update'));
        $this->assertFalse(Route::has('production.rework.create'));
        $this->assertFalse(Route::has('production.scrap.show'));
        $this->assertFalse(Route::has('production.deviations.destroy'));
    }

    #[Test]
    public function scan_log_entity_code_does_not_read_another_tenants_entity(): void
    {
        [$tenantA, $tenantB] = $this->createTenants();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $productB = Product::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B Product',
            'sku' => 'TB-PROD',
            'type' => 'finished_good',
            'status' => 'active',
        ]);
        $orderB = ProductionOrder::create([
            'tenant_id' => $tenantB->id,
            'order_number' => 'ORD-TENANT-B',
            'product_id' => $productB->id,
            'quantity_ordered' => 1,
            'start_date' => today(),
            'end_date' => today(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $log = ProductionScanLog::create([
            'tenant_id' => $tenantA->id,
            'entity_type' => 'order',
            'entity_id' => $orderB->id,
            'scan_type' => 'barcode',
            'scanned_by' => $userA->id,
            'scanned_at' => now(),
        ]);

        $this->assertNotSame('ORD-TENANT-B', $log->getEntityCode());
    }

    #[Test]
    public function tenant_cannot_approve_another_tenants_scrap_or_deviation(): void
    {
        [$tenantA, $tenantB] = $this->createTenants();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        $scrap = ProductionScrapDisposal::create([
            'tenant_id' => $tenantB->id,
            'category' => 'finished_good',
            'reason_code' => 'defect',
            'quantity' => 1,
            'cost' => 0,
            'status' => 'pending_approval',
        ]);
        $deviation = ProductionDeviation::create([
            'tenant_id' => $tenantB->id,
            'deviation_number' => 'DEV-TB',
            'type' => 'temporary',
            'description' => 'Tenant B deviation',
            'status' => 'draft',
        ]);

        $scrapBlocked = false;
        try {
            app(ScrapService::class)->approveDisposal($scrap->id, $userA->id, $tenantA->id);
        } catch (ModelNotFoundException) {
            $scrapBlocked = true;
        }

        $deviationBlocked = false;
        try {
            app(DeviationService::class)->approveDeviation($deviation->id, $userA->id, 'SIGN', $tenantA->id);
        } catch (ModelNotFoundException) {
            $deviationBlocked = true;
        }

        $this->assertTrue($scrapBlocked);
        $this->assertTrue($deviationBlocked);
    }

    #[Test]
    public function tenant_cannot_execute_another_tenants_rework_operation(): void
    {
        [$tenantA, $tenantB] = $this->createTenants();
        $productB = Product::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B Product',
            'sku' => 'TB-RWK',
            'type' => 'finished_good',
            'status' => 'active',
        ]);
        $orderB = ProductionOrder::create([
            'tenant_id' => $tenantB->id,
            'order_number' => 'ORD-RWK-B',
            'product_id' => $productB->id,
            'quantity_ordered' => 1,
            'start_date' => today(),
            'end_date' => today(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);
        $workCenterB = WorkCenter::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B WC',
            'code' => 'TB-WC',
            'status' => 'active',
        ]);
        $ncrB = ProductionNcr::create([
            'tenant_id' => $tenantB->id,
            'ncr_number' => 'NCR-TB',
            'category' => 'process',
            'status' => 'open',
            'description' => 'Tenant B NCR',
        ]);
        $rework = ProductionReworkOrder::create([
            'tenant_id' => $tenantB->id,
            'rework_number' => 'RWK-TB',
            'ncr_id' => $ncrB->id,
            'original_production_order_id' => $orderB->id,
            'status' => 'draft',
        ]);
        $operation = ProductionReworkOperation::create([
            'tenant_id' => $tenantB->id,
            'rework_order_id' => $rework->id,
            'sequence' => 10,
            'name' => 'Repair',
            'work_center_id' => $workCenterB->id,
            'status' => 'waiting',
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(ReworkService::class)->startOperation($operation->id, $tenantA->id);
    }

    private function createTenants(): array
    {
        return [
            Tenant::factory()->create(['slug' => 'tenant-a']),
            Tenant::factory()->create(['slug' => 'tenant-b']),
        ];
    }
}
