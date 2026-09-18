<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Exports\BomExport;
use App\Exports\MachineExport;
use App\Exports\ProductionOrderExport;
use App\Exports\ProductionPlanExport;
use App\Exports\ProductionWipExport;
use App\Exports\ProductionScheduleExport;
use App\Exports\RoutingExport;
use App\Exports\WorkCenterExport;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FilteredExportsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product1;
    private Product $product2;
    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Filtered Export Tenant',
            'slug' => 'filtered-export-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PCS',
            'name' => 'Pieces',
            'category' => 'unit',
            'ratio' => 1.0,
            'active' => true,
        ]);

        $this->product1 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Executive Wooden Desk',
            'sku' => 'DSK-OAK-01',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->product2 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ergonomic Mesh Chair',
            'sku' => 'CHR-MSH-02',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function bom_export_respects_product_id_status_search_and_sorting(): void
    {
        $bom1 = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product1->id,
            'bom_number' => 'BOM-001',
            'bom_name' => 'Standard Desk BOM',
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'effective_date' => now()->toDateString(),
            'version' => '1.0',
            'status' => 'approved',
        ]);

        $bom2 = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product2->id,
            'bom_number' => 'BOM-002',
            'bom_name' => 'Executive Chair BOM',
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'effective_date' => now()->toDateString(),
            'version' => '1.0',
            'status' => 'draft',
        ]);

        // 1. Filter by product_id
        $exportProduct1 = new BomExport($this->tenant->id, ['product_id' => $this->product1->id]);
        $rows = $exportProduct1->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('BOM-001', $rows->first()->bom->bom_number);

        // 2. Filter by status
        $exportDraft = new BomExport($this->tenant->id, ['status' => 'draft']);
        $rows = $exportDraft->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('BOM-002', $rows->first()->bom->bom_number);

        // 3. Search by Product SKU
        $exportSearchSku = new BomExport($this->tenant->id, ['search' => 'DSK-OAK']);
        $rows = $exportSearchSku->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('BOM-001', $rows->first()->bom->bom_number);

        // 4. Sort by bom_number desc
        $exportSort = new BomExport($this->tenant->id, ['sort_by' => 'bom_number', 'sort_order' => 'desc']);
        $rows = $exportSort->collection();
        $this->assertCount(2, $rows);
        $this->assertEquals('BOM-002', $rows->first()->bom->bom_number);
    }

    #[Test]
    public function routing_export_respects_product_id_status_search_and_sorting(): void
    {
        $routing1 = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product1->id,
            'routing_number' => 'RTG-001',
            'name' => 'Desk Assembly Process',
            'version' => '1.0',
            'status' => 'active',
        ]);

        $routing2 = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product2->id,
            'routing_number' => 'RTG-002',
            'name' => 'Chair Fabrication Process',
            'version' => '1.0',
            'status' => 'draft',
        ]);

        // 1. Filter by product_id
        $exportProduct2 = new RoutingExport($this->tenant->id, ['product_id' => $this->product2->id]);
        $rows = $exportProduct2->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('RTG-002', $rows->first()->routing->routing_number);

        // 2. Filter by status
        $exportActive = new RoutingExport($this->tenant->id, ['status' => 'active']);
        $rows = $exportActive->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('RTG-001', $rows->first()->routing->routing_number);

        // 3. Search by Product Name
        $exportSearchProd = new RoutingExport($this->tenant->id, ['search' => 'Executive Wooden']);
        $rows = $exportSearchProd->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('RTG-001', $rows->first()->routing->routing_number);

        // 4. Sort by routing_number desc
        $exportSort = new RoutingExport($this->tenant->id, ['sort_by' => 'routing_number', 'sort_order' => 'desc']);
        $rows = $exportSort->collection();
        $this->assertCount(2, $rows);
        $this->assertEquals('RTG-002', $rows->first()->routing->routing_number);
    }

    #[Test]
    public function work_center_export_respects_type_status_search_and_sorting(): void
    {
        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-CUT-01',
            'name' => 'Laser Cutting Center',
            'department_name' => 'Fabrication',
            'work_center_type' => 'internal',
            'cost_per_hour' => 120.0,
            'status' => 'active',
        ]);

        $wc2 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-PKG-02',
            'name' => 'Packaging Bay',
            'department_name' => 'Logistics',
            'work_center_type' => 'subcontractor',
            'cost_per_hour' => 45.0,
            'status' => 'inactive',
        ]);

        // 1. Filter by work_center_type
        $exportType = new WorkCenterExport($this->tenant->id, ['work_center_type' => 'subcontractor']);
        $rows = $exportType->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('WC-PKG-02', $rows->first()->code);

        // 2. Filter by status
        $exportStatus = new WorkCenterExport($this->tenant->id, ['status' => 'active']);
        $rows = $exportStatus->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('WC-CUT-01', $rows->first()->code);

        // 3. Search by department_name
        $exportSearchDept = new WorkCenterExport($this->tenant->id, ['search' => 'Fabrication']);
        $rows = $exportSearchDept->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('WC-CUT-01', $rows->first()->code);

        // 4. Sort by code desc
        $exportSort = new WorkCenterExport($this->tenant->id, ['sort_by' => 'code', 'sort_order' => 'desc']);
        $rows = $exportSort->collection();
        $this->assertCount(2, $rows);
        $this->assertEquals('WC-PKG-02', $rows->first()->code);
    }

    #[Test]
    public function machine_export_respects_work_center_status_search_and_sorting(): void
    {
        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-MCH-01',
            'name' => 'Machining Shop',
            'status' => 'active',
        ]);

        $wc2 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-MCH-02',
            'name' => 'Cutting Shop',
            'status' => 'active',
        ]);

        $mch1 = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $wc1->id,
            'code' => 'MCH-CNC-01',
            'name' => '5-Axis CNC Router',
            'machine_type' => 'CNC Milling',
            'status' => 'active',
        ]);

        $mch2 = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $wc2->id,
            'code' => 'MCH-SAW-02',
            'name' => 'Circular Band Saw',
            'machine_type' => 'Sawing',
            'status' => 'maintenance',
        ]);

        // 1. Filter by work_center_id
        $exportWc = new MachineExport($this->tenant->id, ['work_center_id' => $wc1->id]);
        $rows = $exportWc->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('MCH-CNC-01', $rows->first()->code);

        // 2. Filter by status
        $exportStatus = new MachineExport($this->tenant->id, ['status' => 'maintenance']);
        $rows = $exportStatus->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('MCH-SAW-02', $rows->first()->code);

        // 3. Search by machine_type
        $exportSearchType = new MachineExport($this->tenant->id, ['search' => 'Milling']);
        $rows = $exportSearchType->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('MCH-CNC-01', $rows->first()->code);

        // 4. Sort by code desc
        $exportSort = new MachineExport($this->tenant->id, ['sort_by' => 'code', 'sort_order' => 'desc']);
        $rows = $exportSort->collection();
        $this->assertCount(2, $rows);
        $this->assertEquals('MCH-SAW-02', $rows->first()->code);
    }

    #[Test]
    public function exports_support_custom_column_selection(): void
    {
        // 1. Verify ExportRegistry definitions
        $bomCols = \App\Exports\ExportRegistry::getColumnsForType('boms');
        $this->assertArrayHasKey('bom_number', $bomCols);
        $this->assertArrayHasKey('bom_name', $bomCols);
        $this->assertArrayHasKey('product_code', $bomCols);

        $rtgCols = \App\Exports\ExportRegistry::getColumnsForType('routings');
        $this->assertArrayHasKey('routing_code', $rtgCols);
        $this->assertArrayHasKey('operation_name', $rtgCols);

        $wcCols = \App\Exports\ExportRegistry::getColumnsForType('work-centers');
        $this->assertArrayHasKey('code', $wcCols);
        $this->assertArrayHasKey('capacity_hours_per_day', $wcCols);

        $mchCols = \App\Exports\ExportRegistry::getColumnsForType('machines');
        $this->assertArrayHasKey('code', $mchCols);
        $this->assertArrayHasKey('hourly_cost', $mchCols);

        // 2. BOM export with selected subset of columns
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product1->id,
            'bom_number' => 'BOM-CUSTOM-01',
            'bom_name' => 'Custom Column BOM',
            'base_quantity' => 2.5,
            'base_uom_id' => $this->uom->id,
            'effective_date' => now()->toDateString(),
            'version' => '1.0',
            'status' => 'approved',
        ]);

        $exportBomSubset = new BomExport($this->tenant->id, [
            'columns' => ['bom_number', 'base_quantity', 'status']
        ]);
        $headings = $exportBomSubset->headings();
        $this->assertEquals(['BOM Number', 'Base Quantity'], $headings);

        $firstRow = $exportBomSubset->collection()->first();
        $mapped = $exportBomSubset->map($firstRow);
        $this->assertCount(2, $mapped);
        $this->assertEquals('BOM-CUSTOM-01', $mapped[0]);
        $this->assertEquals(2.5, $mapped[1]);

        // 3. Work Center export with single column
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-COL-TEST',
            'name' => 'Column Test Center',
            'status' => 'active',
        ]);
        $exportWcSubset = new WorkCenterExport($this->tenant->id, [
            'columns' => ['name']
        ]);
        $this->assertEquals(['Name'], $exportWcSubset->headings());
        $wcRow = $exportWcSubset->collection()->firstWhere('code', 'WC-COL-TEST');
        $mappedWc = $exportWcSubset->map($wcRow);
        $this->assertEquals(['Column Test Center'], $mappedWc);
    }

    #[Test]
    public function production_order_export_respects_filters_sorting_and_columns(): void
    {
        $order1 = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product1->id,
            'order_number' => 'PO-001',
            'quantity_ordered' => 10,
            'status' => 'draft',
            'production_mode' => 'standard',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-05',
        ]);

        $order2 = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product2->id,
            'order_number' => 'PO-002',
            'quantity_ordered' => 25,
            'status' => 'released',
            'production_mode' => 'batch',
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-15',
        ]);

        // 1. Filter by status
        $exportStatus = new ProductionOrderExport($this->tenant->id, ['status' => 'draft']);
        $rows = $exportStatus->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('PO-001', $rows->first()->order_number);

        // 2. Filter by search
        $exportSearch = new ProductionOrderExport($this->tenant->id, ['search' => 'Desk']);
        $rowsSearch = $exportSearch->collection();
        $this->assertCount(1, $rowsSearch);
        $this->assertEquals('PO-001', $rowsSearch->first()->order_number);

        // 3. Sorting by quantity_ordered asc/desc
        $exportSortAsc = new ProductionOrderExport($this->tenant->id, [
            'sort_by' => 'quantity_ordered',
            'sort_order' => 'asc',
        ]);
        $rowsAsc = $exportSortAsc->collection();
        $this->assertEquals('PO-001', $rowsAsc->first()->order_number);
        $this->assertEquals('PO-002', $rowsAsc->last()->order_number);

        $exportSortDesc = new ProductionOrderExport($this->tenant->id, [
            'sort_by' => 'quantity_ordered',
            'sort_order' => 'desc',
        ]);
        $rowsDesc = $exportSortDesc->collection();
        $this->assertEquals('PO-002', $rowsDesc->first()->order_number);
        $this->assertEquals('PO-001', $rowsDesc->last()->order_number);

        // 4. Custom columns selection
        $exportCustomCols = new ProductionOrderExport($this->tenant->id, [
            'columns' => ['order_number', 'product_sku', 'quantity_ordered', 'status']
        ]);
        $headings = $exportCustomCols->headings();
        $this->assertEquals(['Order Number', 'Product SKU', 'Quantity Ordered', 'Status'], $headings);

        $mapped = $exportCustomCols->map($order1);
        $this->assertEquals(['PO-001', 'DSK-OAK-01', 10.0, 'Draft'], $mapped);
    }

    #[Test]
    public function production_plan_export_respects_filters_sorting_and_columns(): void
    {
        $plan1 = ProductionPlan::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product1->id,
            'plan_number' => 'PLAN-001',
            'name' => 'Oak Desk Q1 Plan',
            'quantity' => 50,
            'status' => 'draft',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $plan2 = ProductionPlan::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product2->id,
            'plan_number' => 'PLAN-002',
            'name' => 'Mesh Chair Q2 Plan',
            'quantity' => 120,
            'status' => 'approved',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ]);

        // 1. Filter by status
        $exportStatus = new ProductionPlanExport($this->tenant->id, ['status' => 'draft']);
        $rows = $exportStatus->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals('PLAN-001', $rows->first()->plan_number);

        // 2. Filter by search
        $exportSearch = new ProductionPlanExport($this->tenant->id, ['search' => 'Chair']);
        $rowsSearch = $exportSearch->collection();
        $this->assertCount(1, $rowsSearch);
        $this->assertEquals('PLAN-002', $rowsSearch->first()->plan_number);

        // 3. Sorting by quantity asc/desc
        $exportSortAsc = new ProductionPlanExport($this->tenant->id, [
            'sort_by' => 'quantity',
            'sort_order' => 'asc',
        ]);
        $rowsAsc = $exportSortAsc->collection();
        $this->assertEquals('PLAN-001', $rowsAsc->first()->plan_number);
        $this->assertEquals('PLAN-002', $rowsAsc->last()->plan_number);

        $exportSortDesc = new ProductionPlanExport($this->tenant->id, [
            'sort_by' => 'quantity',
            'sort_order' => 'desc',
        ]);
        $rowsDesc = $exportSortDesc->collection();
        $this->assertEquals('PLAN-002', $rowsDesc->first()->plan_number);
        $this->assertEquals('PLAN-001', $rowsDesc->last()->plan_number);

        // 4. Custom columns selection
        $exportCustomCols = new ProductionPlanExport($this->tenant->id, [
            'columns' => ['plan_number', 'name', 'product_sku', 'quantity', 'status']
        ]);
        $headings = $exportCustomCols->headings();
        $this->assertEquals(['Plan Number', 'Plan Name', 'Product SKU', 'Planned Quantity', 'Status'], $headings);

        $mapped = $exportCustomCols->map($plan1);
        $this->assertEquals(['PLAN-001', 'Oak Desk Q1 Plan', 'DSK-OAK-01', 50.0, 'Draft'], $mapped);
    }

    #[Test]
    public function wip_export_respects_filters_sorting_and_columns(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product1->id,
            'order_number' => 'PO-WIP-001',
            'quantity_ordered' => 100,
            'status' => 'in_progress',
            'production_mode' => 'batch',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-10',
        ]);

        $workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-WIP-01',
            'name' => 'Assembly Station 1',
            'status' => 'active',
        ]);

        $wip1 = ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'current_work_center_id' => $workCenter->id,
            'quantity' => 50,
            'available_quantity' => 45,
            'completed_quantity' => 5,
            'scrap_quantity' => 2,
            'rework_quantity' => 0,
            'status' => 'active',
            'total_value' => 1500.00,
        ]);

        $wip2 = ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product2->id,
            'current_work_center_id' => $workCenter->id,
            'quantity' => 20,
            'available_quantity' => 18,
            'completed_quantity' => 2,
            'scrap_quantity' => 1,
            'rework_quantity' => 0,
            'status' => 'quality_hold',
            'total_value' => 800.00,
        ]);

        // 1. Filter by status
        $exportStatus = new ProductionWipExport($this->tenant->id, ['status' => 'active']);
        $rows = $exportStatus->collection();
        $this->assertCount(1, $rows);
        $this->assertEquals($wip1->id, $rows->first()->id);

        // 2. Filter by search
        $exportSearch = new ProductionWipExport($this->tenant->id, ['search' => 'Mesh Chair']);
        $rowsSearch = $exportSearch->collection();
        $this->assertCount(1, $rowsSearch);
        $this->assertEquals($wip2->id, $rowsSearch->first()->id);

        // 3. Filter by work center
        $exportWc = new ProductionWipExport($this->tenant->id, ['work_center_id' => $workCenter->id]);
        $rowsWc = $exportWc->collection();
        $this->assertCount(2, $rowsWc);

        // 4. Sorting by available_quantity asc/desc
        $exportSortAsc = new ProductionWipExport($this->tenant->id, [
            'sort_by' => 'available_quantity',
            'sort_order' => 'asc',
        ]);
        $rowsAsc = $exportSortAsc->collection();
        $this->assertEquals($wip2->id, $rowsAsc->first()->id);
        $this->assertEquals($wip1->id, $rowsAsc->last()->id);

        $exportSortDesc = new ProductionWipExport($this->tenant->id, [
            'sort_by' => 'available_quantity',
            'sort_order' => 'desc',
        ]);
        $rowsDesc = $exportSortDesc->collection();
        $this->assertEquals($wip1->id, $rowsDesc->first()->id);
        $this->assertEquals($wip2->id, $rowsDesc->last()->id);

        // 5. Custom column selection
        $exportCustom = new ProductionWipExport($this->tenant->id, [
            'columns' => ['wip_number', 'order_number', 'product_name', 'available_quantity', 'scrap_quantity', 'status']
        ]);
        $headings = $exportCustom->headings();
        $this->assertEquals(['WIP Number', 'Production Order', 'Product Name', 'Available Qty', 'Scrap Qty', 'Status'], $headings);

        $mapped = $exportCustom->map($wip1);
        $this->assertEquals([
            'WIP-#' . str_pad((string) $wip1->id, 5, '0', STR_PAD_LEFT),
            'PO-WIP-001',
            'Executive Wooden Desk',
            45.0,
            2.0,
            'Active'
        ], $mapped);
    }

    #[Test]
    public function schedule_export_respects_filters_sorting_and_columns(): void
    {
        $order1 = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product1->id,
            'order_number' => 'PO-SCHED-001',
            'quantity_ordered' => 100,
            'status' => 'released',
            'production_mode' => 'batch',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-10',
        ]);

        $order2 = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product2->id,
            'order_number' => 'PO-SCHED-002',
            'quantity_ordered' => 50,
            'status' => 'released',
            'production_mode' => 'batch',
            'start_date' => '2026-03-05',
            'end_date' => '2026-03-15',
        ]);

        $sched1 = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'schedule_number' => 'SCH-2026-0001',
            'production_order_id' => $order1->id,
            'scheduling_type' => 'forward',
            'status' => 'scheduled',
            'capacity_utilization' => 75.5,
            'scheduled_at' => '2026-03-01 09:00:00',
        ]);

        $sched2 = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'schedule_number' => 'SCH-2026-0002',
            'production_order_id' => $order2->id,
            'scheduling_type' => 'backward',
            'status' => 'draft',
            'capacity_utilization' => 45.0,
            'scheduled_at' => '2026-03-05 10:00:00',
        ]);

        // 1. Filter by status
        $exportStatus = new ProductionScheduleExport($this->tenant->id, ['status' => 'scheduled']);
        $rowsStatus = $exportStatus->collection();
        $this->assertCount(1, $rowsStatus);
        $this->assertEquals($sched1->id, $rowsStatus->first()->id);

        // 2. Filter by search (order number or schedule number)
        $exportSearch = new ProductionScheduleExport($this->tenant->id, ['search' => 'PO-SCHED-002']);
        $rowsSearch = $exportSearch->collection();
        $this->assertCount(1, $rowsSearch);
        $this->assertEquals($sched2->id, $rowsSearch->first()->id);

        // 3. Filter by scheduling_type
        $exportType = new ProductionScheduleExport($this->tenant->id, ['scheduling_type' => 'forward']);
        $rowsType = $exportType->collection();
        $this->assertCount(1, $rowsType);
        $this->assertEquals($sched1->id, $rowsType->first()->id);

        // 4. Sort by capacity_utilization asc / desc
        $exportSortAsc = new ProductionScheduleExport($this->tenant->id, [
            'sort_by' => 'capacity_utilization',
            'sort_order' => 'asc',
        ]);
        $rowsAsc = $exportSortAsc->collection();
        $this->assertEquals($sched2->id, $rowsAsc->first()->id);
        $this->assertEquals($sched1->id, $rowsAsc->last()->id);

        $exportSortDesc = new ProductionScheduleExport($this->tenant->id, [
            'sort_by' => 'capacity_utilization',
            'sort_order' => 'desc',
        ]);
        $rowsDesc = $exportSortDesc->collection();
        $this->assertEquals($sched1->id, $rowsDesc->first()->id);
        $this->assertEquals($sched2->id, $rowsDesc->last()->id);

        // 5. Custom column selection
        $exportCustom = new ProductionScheduleExport($this->tenant->id, [
            'columns' => ['schedule_number', 'order_number', 'product_name', 'scheduling_type', 'status', 'capacity_utilization']
        ]);
        $headings = $exportCustom->headings();
        $this->assertEquals([
            'Schedule Number',
            'Production Order',
            'Product Name',
            'Scheduling Type',
            'Status',
            'Capacity Utilization (%)'
        ], $headings);

        $mapped = $exportCustom->map($sched1);
        $this->assertEquals([
            'SCH-2026-0001',
            'PO-SCHED-001',
            'Executive Wooden Desk',
            'Forward',
            'Scheduled',
            75.5
        ], $mapped);
    }
}
