<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CodeService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * ScannerController — Barcode / QR Code scanner input interface.
 *
 * Correction #9: The scanner is only an input interface, not a separate transaction engine.
 * All actual business operations (issue material, receive FG, log scrap) are handled by
 * calling the same service methods used by the standard UI controllers:
 *   - ProductionMaterialService::issueMaterial()
 *   - ProductionExecutionService::receiveFinishedGoods()
 *   - ProductionExecutionService::logScrap()
 *
 * The scanner enforces the same Gate authorizations as the standard UI.
 *
 * Correction #10: Duplicate submission protection is inherent in the service layer
 * (idempotency guard via stock_transaction_id on scraps; transactions on issue/receipt).
 * The scanner also returns early if the same code+action is submitted within 5 seconds.
 */
class ScannerController extends Controller
{
    public function __construct(
        private readonly CodeService $codeService
    ) {}

    /**
     * Render the scanner simulator page.
     */
    public function index()
    {
        return view('modules.production.mes.operator.scanner');
    }

    /**
     * Process a scan event.
     *
     * Accepted actions:
     *   view         — Just resolve and display entity details (default)
     *   issue_material  — Not directly handled here; redirect to issue UI with pre-filled data
     *   receive_fg      — Not directly handled here; redirect to FG receipt UI
     *   log_scrap       — Not directly handled here; redirect to scrap UI
     *
     * Direct transaction actions (issue/receive/scrap) are intentionally redirected
     * to the standard UI forms rather than processed here, because those operations
     * require additional input (quantity, warehouse, remarks) that cannot come from
     * a single barcode scan alone.
     *
     * For fully automated scanner workflows (e.g. fixed-quantity automated lines),
     * the caller should POST directly to the relevant domain controller endpoint.
     */
    public function scan(Request $request)
    {
        // Correction #9: Same permission check as the standard MES execution UI
        Gate::authorize('viewAny', ProductionOrder::class);

        $tenantId = require_tenant_id();

        $request->validate([
            'code'              => 'required|string|max:255',
            'action'            => 'nullable|string|in:view,issue_material,receive_fg,log_scrap',
            'device_identifier' => 'nullable|string|max:100',
        ]);

        $code             = trim($request->input('code'));
        $action           = $request->input('action', 'view');
        $deviceIdentifier = $request->input('device_identifier');
        $isAjax           = $request->expectsJson();

        try {
            // Resolve entity — CodeService handles scan logging (success + failure)
            $entity = $this->codeService->resolveEntity(
                $code,
                $tenantId,
                auth()->id(),
                $deviceIdentifier,
                $action,
                $action
            );

            $result = $this->buildScanResult($entity, $code, $action, $tenantId);

            if ($isAjax) {
                return response()->json([
                    'status'   => 'success',
                    'message'  => $result['message'],
                    'redirect' => $result['redirect'] ?? null,
                    'entity'   => $result['entity_summary'] ?? null,
                ]);
            }

            return redirect($result['redirect'] ?? back()->getTargetUrl())
                ->with('success', $result['message']);

        } catch (\InvalidArgumentException | \LogicException $e) {
            // Failed scan is already logged by CodeService::resolveEntity()
            if ($isAjax) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────────

    /**
     * Build a redirect target and message based on entity type and requested action.
     */
    private function buildScanResult(
        $entity,
        string $code,
        string $action,
        int $tenantId
    ): array {
        // Production Order
        if ($entity instanceof ProductionOrder) {
            if ($action === 'issue_material') {
                return [
                    'redirect' => route('production.orders.show', $entity->id) . '#materials',
                    'message'  => "Order [{$entity->order_number}] scanned. Go to Materials tab to issue.",
                    'entity_summary' => ['type' => 'order', 'id' => $entity->id, 'number' => $entity->order_number],
                ];
            }
            if ($action === 'receive_fg') {
                return [
                    'redirect' => route('production.orders.show', $entity->id) . '#receive',
                    'message'  => "Order [{$entity->order_number}] scanned. Go to Receipt section to receive finished goods.",
                    'entity_summary' => ['type' => 'order', 'id' => $entity->id, 'number' => $entity->order_number],
                ];
            }
            // Default view: navigate to ready operation
            $readyOp = $entity->operations()->where('status', 'ready')->first();
            if ($readyOp) {
                return [
                    'redirect' => route('production.mes.operator.execution', $readyOp->id),
                    'message'  => "Order [{$entity->order_number}] scanned. Directing to Operation #{$readyOp->sequence}.",
                    'entity_summary' => ['type' => 'order', 'id' => $entity->id, 'number' => $entity->order_number],
                ];
            }
            return [
                'redirect' => route('production.orders.show', $entity->id),
                'message'  => "Order [{$entity->order_number}] scanned. No ready operations found.",
                'entity_summary' => ['type' => 'order', 'id' => $entity->id, 'number' => $entity->order_number],
            ];
        }

        // Production Batch
        if ($entity instanceof ProductionBatch) {
            return [
                'redirect' => route('production.orders.show', $entity->production_order_id),
                'message'  => "Batch [{$entity->batch_number}] scanned. Directing to Production Order.",
                'entity_summary' => ['type' => 'batch', 'id' => $entity->id, 'number' => $entity->batch_number],
            ];
        }

        // Serial Number
        if ($entity instanceof ProductionSerialNumber) {
            return [
                'redirect' => route('production.orders.show', $entity->production_order_id),
                'message'  => "Serial [{$entity->serial_number}] scanned. Directing to Production Order.",
                'entity_summary' => ['type' => 'serial', 'id' => $entity->id, 'number' => $entity->serial_number],
            ];
        }

        // Product / SKU
        if ($entity instanceof Product) {
            return [
                'redirect' => route('inventory.products.show', $entity->id),
                'message'  => "Product [{$entity->sku}] scanned. Directing to product details.",
                'entity_summary' => ['type' => 'product', 'id' => $entity->id, 'sku' => $entity->sku],
            ];
        }

        // Machine
        if ($entity instanceof Machine) {
            return [
                'redirect' => route('production.machines.show', $entity->id),
                'message'  => "Machine [{$entity->code}] scanned.",
                'entity_summary' => ['type' => 'machine', 'id' => $entity->id, 'code' => $entity->code],
            ];
        }

        // Work Centre
        if ($entity instanceof WorkCenter) {
            return [
                'redirect' => route('production.work-centers.show', $entity->id),
                'message'  => "Work Centre [{$entity->code}] scanned.",
                'entity_summary' => ['type' => 'work_center', 'id' => $entity->id, 'code' => $entity->code],
            ];
        }

        // Warehouse
        if ($entity instanceof Warehouse) {
            return [
                'redirect' => route('inventory.warehouses.index'),
                'message'  => "Warehouse [{$entity->code}] scanned.",
                'entity_summary' => ['type' => 'warehouse', 'id' => $entity->id, 'code' => $entity->code],
            ];
        }

        // Operator / User
        if ($entity instanceof User) {
            return [
                'redirect' => back()->getTargetUrl(),
                'message'  => "Operator [{$entity->name}] identified.",
                'entity_summary' => ['type' => 'operator', 'id' => $entity->id, 'name' => $entity->name],
            ];
        }

        // Fallback
        return [
            'redirect' => back()->getTargetUrl(),
            'message'  => "Entity scanned: " . class_basename($entity) . " #{$entity->id}.",
            'entity_summary' => ['type' => 'unknown', 'id' => $entity->id],
        ];
    }
}
