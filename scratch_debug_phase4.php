<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\GoodsReceiptNote;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionWipService;
use App\Domains\Production\Services\QualityInspectionService;
use App\Domains\Production\Services\SubcontractReceiptOrchestrator;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    $test = new \Tests\Feature\Production\MultiModelPhase4Test('test_full_numerical_hybrid_execution_conservation');
    // Run reflection or setup manually
} catch (\Throwable $e) {
    echo $e->getMessage() . "\n" . $e->getTraceAsString();
} finally {
    DB::rollBack();
}
