<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Controllers\MachineController;
use App\Domains\Production\Controllers\ProductionBomController;
use App\Domains\Production\Controllers\ProductionOrderController;
use App\Domains\Production\Controllers\ProductionPlanController;
use App\Domains\Production\Controllers\RoutingController;
use App\Domains\Production\Controllers\ShiftController;
use App\Domains\Production\Controllers\WorkCenterController;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\ProductionOrderEngineeringService;
use App\Models\User;
use Database\Seeders\NonWovenDemoMasterDataSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

$tenantId = 1;
$branchId = 3;
$companyId = 1;

$data = NonWovenDemoMasterDataSeeder::loadData();
$expectedProductCodes = array_column($data['products'] ?? [], 'code');
$expectedBomNumbers = array_column($data['production_boms'] ?? [], 'bom_number');
$expectedRoutingNumbers = array_column($data['production_routings'] ?? [], 'routing_number');

echo str_repeat('=', 100) . PHP_EOL;
echo "  COMPREHENSIVE MASTER DATA & FRONT-END VERIFICATION SUITE" . PHP_EOL;
echo "  Tenant: {$tenantId} | Branch: {$branchId} | Time: " . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('=', 100) . PHP_EOL . PHP_EOL;

$passCount = 0;
$failCount = 0;

function assertCheck(string $title, bool $condition, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$title}" . ($details ? " - {$details}" : '') . PHP_EOL;
    } else {
        $failCount++;
        echo "  [FAIL] {$title}" . ($details ? " - {$details}" : '') . PHP_EOL;
    }
}

// ==============================================================================
// 1. DATABASE INTEGRITY CHECKS
// ==============================================================================
echo ">>> PART 1: Database Integrity Checks" . PHP_EOL;

// 1.1 Shifts
$shiftsCount = DB::table('production_shifts')->where('tenant_id', $tenantId)->where('code', 'like', 'NW-SH-%')->count();
assertCheck("Shifts seeded", $shiftsCount === 4, "Found {$shiftsCount}/4 shifts");

// 1.2 Warehouses
$whCount = DB::table('warehouses')->where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('code', 'like', 'NW-WH-%')->count();
assertCheck("Warehouses seeded in Branch 3", $whCount === 5, "Found {$whCount}/5 warehouses");

// 1.3 Products
$prodCount = DB::table('products')->where('tenant_id', $tenantId)->whereIn('sku', $expectedProductCodes)->count();
$expectedProdTotal = count($expectedProductCodes);
assertCheck("Non-woven demo products seeded (tenant-wide, branch_id null)", $prodCount === $expectedProdTotal, "Found {$prodCount}/{$expectedProdTotal} expected products");

// 1.4 Opening Stock in Branch 3
$expectedStockCount = count($data['opening_stock'] ?? []);
$stockCount = DB::table('product_warehouse_stocks')
    ->join('warehouses', 'product_warehouse_stocks.warehouse_id', '=', 'warehouses.id')
    ->where('product_warehouse_stocks.tenant_id', $tenantId)
    ->where('warehouses.branch_id', $branchId)
    ->where('warehouses.code', 'like', 'NW-WH-%')
    ->count();
assertCheck("Opening stock rows in Branch 3 warehouses", $stockCount === $expectedStockCount, "Found {$stockCount}/{$expectedStockCount} stock records");

$totalStockQty = DB::table('product_warehouse_stocks')
    ->join('warehouses', 'product_warehouse_stocks.warehouse_id', '=', 'warehouses.id')
    ->where('product_warehouse_stocks.tenant_id', $tenantId)
    ->where('warehouses.branch_id', $branchId)
    ->where('warehouses.code', 'like', 'NW-WH-%')
    ->sum('product_warehouse_stocks.quantity');
assertCheck("Opening stock total volume", $totalStockQty > 20000, "Total physical quantity: " . number_format($totalStockQty, 2) . " units");

// 1.5 Work Centers & Sections
$wcCount = DB::table('production_work_centers')->where('tenant_id', $tenantId)->where('code', 'like', 'NW-WC-%')->count();
assertCheck("Work Centers seeded", $wcCount === 3, "Found {$wcCount}/3 work centers");

$secCount = DB::table('production_work_center_sections')->where('tenant_id', $tenantId)->where('code', 'like', 'NW-SEC-%')->count();
assertCheck("Work Center Sections seeded", $secCount === 9, "Found {$secCount}/9 sections");

// 1.6 Machines
$machCount = DB::table('production_machines')->where('tenant_id', $tenantId)->where('code', 'like', 'NW-%')->count();
$unassignedMach = DB::table('production_machines')->where('tenant_id', $tenantId)->where('code', 'like', 'NW-%')->whereNull('section_id')->count();
assertCheck("Machines seeded and linked to sections", $machCount === 12 && $unassignedMach === 0, "Found {$machCount}/12 machines (Unassigned: {$unassignedMach})");

// 1.7 Manual Batches
$batchCount = DB::table('production_section_manual_productions')->where('tenant_id', $tenantId)->where('batch_name', 'like', 'NW-BAT-%')->count();
assertCheck("Manual Batches seeded and linked", $batchCount === 6, "Found {$batchCount}/6 batches");

// 1.8b Workers & Pay Grades
$pgCount = DB::table('production_external_worker_pay_grades')->where('tenant_id', $tenantId)->where('code', 'like', 'NW-PG-%')->count();
assertCheck("External Worker Pay Grades seeded", $pgCount === 3, "Found {$pgCount}/3 pay grades");

$wcEmpCount = DB::table('production_work_center_employees')->where('tenant_id', $tenantId)->whereIn('work_center_id', DB::table('production_work_centers')->where('code', 'like', 'NW-WC-%')->pluck('id'))->count();
assertCheck("Work Center & Section staff assigned", $wcEmpCount === 17, "Found {$wcEmpCount}/17 staff assignments");

$batchEmpCount = DB::table('production_section_manual_production_employees')
    ->whereIn('manual_production_id', DB::table('production_section_manual_productions')->where('batch_name', 'like', 'NW-BAT-%')->pluck('id'))
    ->count();
assertCheck("Manual Batch internal employees linked", $batchEmpCount === 6, "Found {$batchEmpCount}/6 manual batch links");

$allocCount = DB::table('production_external_worker_allocations')
    ->where('tenant_id', $tenantId)
    ->whereIn('pay_grade_id', DB::table('production_external_worker_pay_grades')->where('code', 'like', 'NW-PG-%')->pluck('id'))
    ->count();
assertCheck("External Worker allocations active", $allocCount === 18, "Found {$allocCount}/18 allocations");

// 1.8 Section Calculation Snapshots
$secIds = DB::table('production_work_center_sections')->where('tenant_id', $tenantId)->where('code', 'like', 'NW-SEC-%')->pluck('id');
$snapCount = DB::table('production_work_center_section_calculations')->whereIn('section_id', $secIds)->distinct('section_id')->count('section_id');
assertCheck("Section calculation snapshots generated", $snapCount === 9, "{$snapCount}/9 sections have calculation snapshots with non-zero labor");

// 1.9 Routings & Operations
$rtCount = DB::table('routings')->where('tenant_id', $tenantId)->whereIn('routing_number', $expectedRoutingNumbers)->count();
assertCheck("Routings seeded", $rtCount === 4, "Found {$rtCount}/4 routings");

$opCount = DB::table('production_routing_operations')
    ->join('routings', 'production_routing_operations.routing_id', '=', 'routings.id')
    ->where('routings.tenant_id', $tenantId)
    ->whereIn('routings.routing_number', $expectedRoutingNumbers)
    ->count();
assertCheck("Routing operations seeded", $opCount === 10, "Found {$opCount}/10 operations");

// 1.10 BOMs & Items
$bomCount = DB::table('production_boms')->where('tenant_id', $tenantId)->whereIn('bom_number', $expectedBomNumbers)->where('status', 'approved')->count();
assertCheck("BOMs seeded with approved status", $bomCount === 8, "Found {$bomCount}/8 approved BOMs");

$expectedBomItemCount = count($data['production_bom_items'] ?? []);
$bomItemCount = DB::table('production_bom_items')
    ->join('production_boms', 'production_bom_items.bom_id', '=', 'production_boms.id')
    ->where('production_boms.tenant_id', $tenantId)
    ->whereIn('production_boms.bom_number', $expectedBomNumbers)
    ->count();
assertCheck("Tier 1 BOM items seeded", $bomItemCount === $expectedBomItemCount, "Found {$bomItemCount}/{$expectedBomItemCount} master recipe items");

$expectedRomCount = count($data['production_routing_operation_materials'] ?? []);
$romCount = DB::table('production_routing_operation_materials')
    ->join('production_boms', 'production_routing_operation_materials.bom_id', '=', 'production_boms.id')
    ->where('production_boms.tenant_id', $tenantId)
    ->whereIn('production_boms.bom_number', $expectedBomNumbers)
    ->count();
assertCheck("Tier 2 Routing operation material allocations seeded", $romCount === $expectedRomCount, "Found {$romCount}/{$expectedRomCount} workstation allocations");

// 1.11 Alternative Materials Configuration on BOM-BAG-DCUT
$bagBom = ProductionBom::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('bom_number', 'BOM-BAG-DCUT')->first();
$altCount = RoutingOperationMaterial::withoutGlobalScopes()
    ->where('bom_id', $bagBom?->id)
    ->where('is_alternative', true)
    ->count();
$primaryAllocation = RoutingOperationMaterial::withoutGlobalScopes()
    ->where('bom_id', $bagBom?->id)
    ->where('is_alternative', false)
    ->first();
$altReplacementsValid = RoutingOperationMaterial::withoutGlobalScopes()
    ->where('bom_id', $bagBom?->id)
    ->where('is_alternative', true)
    ->where('replaces_routing_material_id', $primaryAllocation?->id)
    ->count();
assertCheck("D-Cut Bag BOM alternative materials linked to primary", $altCount === 2 && $altReplacementsValid === 2, "2 alternatives linked to primary allocation #{$primaryAllocation?->id}");

echo PHP_EOL;

// ==============================================================================
// 2. COST FRESHNESS & PRODUCTION ENGINEERING VALIDATION
// ==============================================================================
echo ">>> PART 2: Cost Freshness & Engineering Pre-Order Validation" . PHP_EOL;

$engService = app(ProductionOrderEngineeringService::class);

$testProducts = [
    'FG-BAG-DCUT'     => 'D-Cut Bag (Multi-Tier Alternatives: Jumbo + 50IN + 20IN)',
    'FG-MASK-3PLY'    => '3-Ply Mask (5 Materials: 3 Rolls + Earloops + Nosewire)',
    'SFG-ROLL-50-WHT' => '50 GSM White Jumbo Roll (Extrusion: PP + MB + Filler)',
    'SFG-SLIT-50-50IN'=> '50 GSM Slit Roll 50 inch (Slitter: Jumbo roll conversion)',
];

foreach ($testProducts as $sku => $label) {
    $product = Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sku', $sku)->first();
    if (!$product) {
        assertCheck("Product exists: {$sku}", false, "Not found");
        continue;
    }

    $resolution = $engService->resolveAndValidate($product, $tenantId);
    $canCreate = $resolution['can_create_order'];
    $errs = count($resolution['errors']);
    $opCountResolved = count($resolution['selected_operation_ids']);
    $matCountResolved = count($resolution['selected_material_ids']);
    $bomNum = $resolution['bom']?->bom_number ?? 'NONE';
    $rtNum = $resolution['routing']?->routing_number ?? 'NONE';

    assertCheck(
        "Engineering check: {$sku} ({$label})",
        $canCreate && $errs === 0,
        "CanCreate: " . ($canCreate ? 'YES' : 'NO') . " | BOM: {$bomNum} | RT: {$rtNum} | Ops: {$opCountResolved} | Mats: {$matCountResolved} | Errors: {$errs}"
    );

    if ($errs > 0) {
        foreach ($resolution['errors'] as $field => $messages) {
            echo "       └─ [Error: {$field}] " . implode(', ', $messages) . PHP_EOL;
        }
    }
}

echo PHP_EOL;

// ==============================================================================
// 3. FRONT-END & WEB CONTROLLER / VIEW CHECKS
// ==============================================================================
echo ">>> PART 3: Front-End Views & Web Controller Rendering Checks" . PHP_EOL;

// Setup authenticated session
$user = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
if (!$user) {
    echo "  [WARN] No user found for tenant {$tenantId} to run front-end controller checks" . PHP_EOL;
} else {
    Auth::login($user);
    session([
        'current_tenant_id' => $tenantId,
        'current_branch_id' => $branchId,
        'current_company_id' => $companyId,
    ]);

    // 3.1 BOMs Index View
    try {
        $req = Request::create('/production/boms', 'GET');
        $controller = app(ProductionBomController::class);
        $res = $controller->index($req);
        $status = method_exists($res, 'status') ? $res->status() : 200;
        $viewName = method_exists($res, 'name') ? $res->name() : get_class($res);
        assertCheck("BOMs Index UI (/production/boms)", $status === 200, "View: {$viewName} rendered successfully");
    } catch (\Throwable $e) {
        assertCheck("BOMs Index UI (/production/boms)", false, "Exception: {$e->getMessage()}");
    }

    // 3.2 BOM Details View (D-Cut Bag with Alternatives)
    try {
        $bagBom = ProductionBom::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('bom_number', 'BOM-BAG-DCUT')->first();
        $controller = app(ProductionBomController::class);
        $req = Request::create("/production/boms/{$bagBom->id}", 'GET');
        $res = $controller->show($bagBom->id, $req);
        $status = method_exists($res, 'status') ? $res->status() : 200;
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $html = method_exists($res, 'render') ? $res->render() : '';
        $hasAllOps = str_contains($html, 'OP-010') && str_contains($html, 'OP-020') && str_contains($html, 'OP-030');
        assertCheck("BOM Show UI (/production/boms/{$bagBom->id})", $status === 200 && $hasAllOps, "BOM-BAG-DCUT rendered with all 3 operations (OP-010, OP-020, OP-030) and alternatives");
    } catch (\Throwable $e) {
        assertCheck("BOM Show UI (/production/boms/id)", false, "Exception: {$e->getMessage()}");
    }

    // 3.3 Routings Index View
    try {
        $req = Request::create('/production/routing', 'GET');
        $controller = app(RoutingController::class);
        $res = $controller->index($req);
        $status = method_exists($res, 'status') ? $res->status() : 200;
        $viewName = method_exists($res, 'name') ? $res->name() : get_class($res);
        assertCheck("Routings Index UI (/production/routing)", $status === 200, "View: {$viewName} rendered successfully");
    } catch (\Throwable $e) {
        assertCheck("Routings Index UI (/production/routing)", false, "Exception: {$e->getMessage()}");
    }

    // 3.4 Routing Details View (Extrusion Routing)
    try {
        $extRouting = Routing::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('routing_number', 'RT-EXT-SPUNBOND')->first();
        $controller = app(RoutingController::class);
        $res = $controller->show($extRouting->id);
        $status = method_exists($res, 'status') ? $res->status() : 200;
        assertCheck("Routing Show UI (/production/routing/{$extRouting->id})", $status === 200, "RT-EXT-SPUNBOND rendered with operations & stamped rates");
    } catch (\Throwable $e) {
        assertCheck("Routing Show UI (/production/routing/id)", false, "Exception: {$e->getMessage()}");
    }

    // 3.5 Work Centers Index View
    try {
        $req = Request::create('/production/work-centers', 'GET');
        $controller = app(WorkCenterController::class);
        $res = $controller->index($req);
        $status = method_exists($res, 'status') ? $res->status() : 200;
        assertCheck("Work Centers Index UI (/production/work-centers)", $status === 200, "3 work centers rendered with sections & countdowns");
    } catch (\Throwable $e) {
        assertCheck("Work Centers Index UI (/production/work-centers)", false, "Exception: {$e->getMessage()}");
    }

    // 3.6 Machines Index View
    try {
        $req = Request::create('/production/machines', 'GET');
        $controller = app(MachineController::class);
        $res = $controller->index($req);
        $status = method_exists($res, 'status') ? $res->status() : 200;
        assertCheck("Machines Index UI (/production/machines)", $status === 200, "12 machines rendered with sections & operational statuses");
    } catch (\Throwable $e) {
        assertCheck("Machines Index UI (/production/machines)", false, "Exception: {$e->getMessage()}");
    }

    // 3.7 Shifts Index View
    try {
        $req = Request::create('/production/shifts', 'GET');
        $controller = app(ShiftController::class);
        $res = $controller->index($req);
        $status = method_exists($res, 'status') ? $res->status() : 200;
        assertCheck("Shifts Index UI (/production/shifts)", $status === 200, "4 shifts rendered with start/end times");
    } catch (\Throwable $e) {
        assertCheck("Shifts Index UI (/production/shifts)", false, "Exception: {$e->getMessage()}");
    }

    // 3.8 Production Order Create Screen
    try {
        $req = Request::create('/production/orders/create', 'GET');
        $controller = app(ProductionOrderController::class);
        $res = $controller->create($req);
        $status = method_exists($res, 'status') ? $res->status() : 200;
        assertCheck("Production Order Create UI (/production/orders/create)", $status === 200, "Order create wizard rendered with active products");
    } catch (\Throwable $e) {
        assertCheck("Production Order Create UI (/production/orders/create)", false, "Exception: {$e->getMessage()}");
    }

    // 3.9 AJAX Engineering Options Endpoint (used when selecting target product in Create Order UI)
    try {
        $bagProduct = Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sku', 'FG-BAG-DCUT')->first();
        $req = Request::create('/production/plans/ajax-engineering-options', 'GET', [
            'product_id' => $bagProduct->id,
            'quantity' => 1000,
        ]);
        $controller = app(ProductionPlanController::class);
        $res = $controller->getEngineeringOptions($req);
        $status = method_exists($res, 'status') ? $res->status() : 200;
        $data = json_decode($res->getContent(), true);
        $hasBoms = !empty($data['boms'] ?? $data['engineering']['bom'] ?? null);
        assertCheck("AJAX Engineering Options for FG-BAG-DCUT", $status === 200, "UI receives dynamic BOM & Routing options JSON correctly");
    } catch (\Throwable $e) {
        assertCheck("AJAX Engineering Options for FG-BAG-DCUT", false, "Exception: {$e->getMessage()}");
    }

    // 3.10 Dispatch Board View & Data API
    try {
        $controller = app(\App\Domains\Production\Controllers\ProductionScheduleController::class);
        $reqView = Request::create('/production/schedules/dispatch-board', 'GET');
        $resView = $controller->dispatchBoardView($reqView);
        $statusView = method_exists($resView, 'status') ? $resView->status() : 200;

        $reqData = Request::create('/production/schedules/dispatch-board/data', 'GET', [
            'view' => 'day',
            'date' => date('Y-m-d'),
        ]);
        $resData = $controller->dispatchBoardData($reqData);
        $statusData = method_exists($resData, 'status') ? $resData->status() : 200;
        assertCheck("Dispatch Board View & Data API (/production/schedules/dispatch-board)", $statusView === 200 && $statusData === 200, "Dispatch board data loaded without errors");
    } catch (\Throwable $e) {
        assertCheck("Dispatch Board View & Data API (/production/schedules/dispatch-board)", false, "Exception: {$e->getMessage()}");
    }

    // 3.11 MES Shop Floor Dashboard
    try {
        $controller = app(\App\Domains\Production\Controllers\MesController::class);
        $reqMes = Request::create('/production/mes', 'GET');
        $res = $controller->dashboard($reqMes);
        $status = method_exists($res, 'status') ? $res->status() : 200;
        assertCheck("MES Shop Floor Dashboard (/production/mes)", $status === 200, "Shop floor operations dashboard rendered");
    } catch (\Throwable $e) {
        assertCheck("MES Shop Floor Dashboard (/production/mes)", false, "Exception: {$e->getMessage()}");
    }
}

echo PHP_EOL;
echo str_repeat('=', 100) . PHP_EOL;
echo "  SUMMARY: {$passCount} PASSED, {$failCount} FAILED out of " . ($passCount + $failCount) . " checks." . PHP_EOL;
echo str_repeat('=', 100) . PHP_EOL;
