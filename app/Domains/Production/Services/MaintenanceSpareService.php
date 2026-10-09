<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderSpare;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionRequisitionSlipItem;
use App\Domains\Production\Repositories\MaintenanceRepositoryInterface;
use App\Domains\Sales\Services\MaterialRequestService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaintenanceSpareService
{
    public function __construct(
        private readonly MaintenanceRepositoryInterface $repository,
        private readonly ProductionEventService $eventService,
        private readonly DowntimeService $downtimeService,
        private readonly MaintenanceWorkOrderLogService $logService
    ) {}

    /**
     * Request a spare part on a Maintenance Work Order and generate/append to Store Material Request.
     */
    public function addSpareRequest(
        int $workOrderId,
        int $tenantId,
        int $productId,
        ?int $warehouseId = null,
        float $requestedQty = 1.0
    ): ProductionMaintenanceWorkOrderSpare {
        if ($requestedQty <= 0) {
            throw new InvalidArgumentException("Requested quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($workOrderId, $tenantId, $productId, $warehouseId, $requestedQty) {
            $wo = $this->repository->findWorkOrderForLock($workOrderId, $tenantId);
            if (!$wo) {
                throw new InvalidArgumentException("Work Order #{$workOrderId} not found.");
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_COMPLETED || $wo->status === ProductionMaintenanceWorkOrder::STATUS_CANCELLED) {
                throw new InvalidArgumentException("Cannot add spare parts to a completed or cancelled Work Order.");
            }

            // 1. Find an open requisition slip for this MWO, or create a new one
            $slip = ProductionRequisitionSlip::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('maintenance_work_order_id', $workOrderId)
                ->whereIn('status', ['pending', 'partial', 'Pending', 'Partially Issued'])
                ->latest('id')
                ->first();

            if (!$slip) {
                $branchId = $wo->branch_id ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id();
                $companyId = $wo->company_id ?? company_id() ?? app(\App\Core\Company\CompanyContext::class)->id();
                $year = now()->format('Y');
                $prefix = "MR-{$year}-";
                $lastSlip = ProductionRequisitionSlip::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
                    ->where('requisition_number', 'like', "{$prefix}%")
                    ->orderBy('id', 'desc')
                    ->first();

                $nextNum = 1;
                if ($lastSlip) {
                    $lastNumStr = str_replace($prefix, '', $lastSlip->requisition_number);
                    $nextNum = ((int) $lastNumStr) + 1;
                }
                $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
                while (ProductionRequisitionSlip::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
                    ->where('requisition_number', $reqNumber)
                    ->exists()) {
                    $nextNum++;
                    $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
                }

                $slip = ProductionRequisitionSlip::create([
                    'tenant_id'                 => $tenantId,
                    'company_id'                => $companyId,
                    'branch_id'                 => $branchId,
                    'production_order_id'       => null,
                    'maintenance_work_order_id' => $workOrderId,
                    'source_type'               => ProductionRequisitionSlip::SOURCE_TYPE_MAINTENANCE_WORK_ORDER,
                    'requisition_number'        => $reqNumber,
                    'status'                    => 'pending',
                    'requested_by'              => auth()->id() ?: $wo->created_by,
                    'requisition_date'          => now()->toDateString(),
                    'notes'                     => "Spare parts requisition for Maintenance Work Order {$wo->work_order_number}",
                ]);
            }

            // 2. Create Requisition Slip Item
            $product = Product::withoutGlobalScopes()->find($productId);
            $uomId = $product?->uom_id;
            if (!$uomId || !\Illuminate\Support\Facades\DB::table('uoms')->where('id', $uomId)->exists()) {
                $existingUom = \Illuminate\Support\Facades\DB::table('uoms')->where('tenant_id', $tenantId)->first();
                if ($existingUom) {
                    $uomId = $existingUom->id;
                } else {
                    $uomId = \Illuminate\Support\Facades\DB::table('uoms')->insertGetId([
                        'tenant_id'  => $tenantId,
                        'name'       => 'Piece',
                        'code'       => 'PCS',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $slipItem = ProductionRequisitionSlipItem::create([
                'tenant_id'                      => $tenantId,
                'production_requisition_slip_id' => $slip->id,
                'product_id'                     => $productId,
                'warehouse_id'                   => $warehouseId,
                'quantity_planned'               => $requestedQty,
                'quantity_reserved'              => 0.0000,
                'quantity_issued'                => 0.0000,
                'uom_id'                         => $uomId,
            ]);

            // 3. Create Maintenance Work Order Spare record linked to slip
            $spare = $this->repository->addWorkOrderSpare([
                'tenant_id'                           => $tenantId,
                'maintenance_work_order_id'           => $workOrderId,
                'production_requisition_slip_id'      => $slip->id,
                'production_requisition_slip_item_id' => $slipItem->id,
                'product_id'                          => $productId,
                'warehouse_id'                        => $warehouseId,
                'requested_qty'                       => $requestedQty,
                'issued_qty'                          => 0.0000,
                'unit_cost'                           => 0.00,
                'total_cost'                          => 0.00,
            ]);

            $this->logService->recordSpareRequested($wo, $productId, $warehouseId, $requestedQty, auth()->id());

            return $spare;
        });
    }

    /**
     * Issue a requested spare part for a Maintenance Work Order using StockService::recordOutflow().
     *
     * Validates stock availability, prevents negative stock, prevents duplicate issue,
     * calls StockService::recordOutflow(), records transaction, and updates WO spare costs.
     */
    public function issueSparePart(
        int $spareId,
        int $tenantId,
        float $issueQty,
        ?int $userId = null
    ): ProductionMaintenanceWorkOrderSpare {
        if ($issueQty <= 0) {
            throw new InvalidArgumentException("Issue quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($spareId, $tenantId, $issueQty, $userId) {
            $spare = ProductionMaintenanceWorkOrderSpare::where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($spareId);

            $wo = $this->repository->findWorkOrderForLock($spare->maintenance_work_order_id, $tenantId);
            if (!$wo) {
                throw new InvalidArgumentException("Associated Work Order not found.");
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_COMPLETED || $wo->status === ProductionMaintenanceWorkOrder::STATUS_CANCELLED) {
                throw new InvalidArgumentException("Cannot issue spares for a completed or cancelled Work Order.");
            }

            // Prevent duplicate issue / over-issue
            $remainingToIssue = max(0.0, (float) $spare->requested_qty - (float) $spare->issued_qty);
            if ($remainingToIssue <= 0) {
                throw new InvalidArgumentException("This spare part request has already been fully issued.");
            }

            $actualIssueQty = min($issueQty, $remainingToIssue);

            // Verify physical stock availability via StockService
            $availableStock = StockService::getAvailableStock($spare->product_id, $spare->warehouse_id);
            if ($availableStock < $actualIssueQty) {
                throw new InvalidArgumentException("Cannot issue spare part: Insufficient stock available. Required: {$actualIssueQty}, Available: {$availableStock}.");
            }

            // Record outflow strictly via StockService
            $stockTxn = StockService::recordOutflow(
                $tenantId,
                $spare->product_id,
                $spare->warehouse_id,
                $actualIssueQty,
                'MaintenanceWorkOrder',
                $wo->id
            );

            $unitCost  = (float) $stockTxn->unit_cost;
            $totalCost = (float) ($stockTxn->total_cost ?? $stockTxn->total_value);

            $newIssuedQty = (float) $spare->issued_qty + $actualIssueQty;
            $newTotalCost = (float) $spare->total_cost + $totalCost;
            $newUnitCost  = $newIssuedQty > 0 ? round($newTotalCost / $newIssuedQty, 2) : $unitCost;

            $spare->update([
                'issued_qty'           => $newIssuedQty,
                'unit_cost'            => $newUnitCost,
                'total_cost'           => $newTotalCost,
                'stock_transaction_id' => $stockTxn->id,
            ]);

            // Sync linked Store Requisition Slip Item if present
            if ($spare->production_requisition_slip_item_id) {
                $slipItem = ProductionRequisitionSlipItem::find($spare->production_requisition_slip_item_id);
                if ($slipItem) {
                    $slipItem->increment('quantity_issued', $actualIssueQty);
                    if ($slipItem->slip) {
                        app(MaterialRequestService::class)->updateSlipStatus($slipItem->slip);
                    }
                }
            }

            // Rollup spare parts total on Maintenance Work Order
            $sumSparesCost = (float) ProductionMaintenanceWorkOrderSpare::where('tenant_id', $tenantId)
                ->where('maintenance_work_order_id', $wo->id)
                ->sum('total_cost');

            $wo->update([
                'spare_parts_cost' => $sumSparesCost,
                'total_cost'       => round((float) $wo->mechanic_cost + $sumSparesCost + (float)($wo->additional_cost ?? 0.0), 2),
            ]);

            $this->logService->recordSpareIssued($wo, $spare->product_id, $spare->warehouse_id, $actualIssueQty, $totalCost, $userId);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Spare Part Issued',
                'title'        => 'Maintenance Spare Issued',
                'description'  => "Issued {$actualIssueQty} of product #{$spare->product_id} for Work Order [{$wo->work_order_number}]. Cost: \${$totalCost}.",
                'severity'     => 'info',
                'event_source' => 'MaintenanceSpareService',
            ]);

            return $spare->fresh(['product', 'warehouse']);
        });
    }
}
