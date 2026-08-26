<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\PurchaseRfq;
use App\Domains\Purchase\Models\PurchaseRfqItem;
use App\Domains\Purchase\Models\PurchaseRfqVendor;
use App\Domains\Purchase\Repositories\PurchaseRequisitionRepository;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseRequisitionService
{
    public function __construct(
        protected PurchaseRequisitionRepository $requisitionRepo
    ) {}

    public function storeRequisition(array $validated, int $tenantId): PurchaseRequisition
    {
        $sourceId = null;
        if ($validated['source_type'] === 'so') {
            $sourceId = $validated['sales_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'mo') {
            $sourceId = $validated['production_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_request') {
            $sourceId = $validated['production_requisition_slip_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_requirement') {
            $sourceId = $validated['material_requirement_id'] ?? null;
        }

        return DB::transaction(function () use ($validated, $sourceId, $tenantId) {
            $year = now()->format('Y');
            $prefix = "PR-{$year}-";
            $lastPr = PurchaseRequisition::where('tenant_id', $tenantId)
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($lastPr) {
                $lastNumStr = str_replace($prefix, '', $lastPr->requisition_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $pr = PurchaseRequisition::create([
                'tenant_id' => $tenantId,
                'requisition_number' => $requisitionNumber,
                'requisition_date' => $validated['requisition_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'status' => 'Draft',
                'source_type' => $validated['source_type'],
                'source_id' => $sourceId,
                'requisition_slip_number' => $validated['requisition_slip_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'requested_by' => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $item) {
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $pr->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'estimated_cost' => $item['estimated_cost'],
                ]);
            }

            return $pr;
        });
    }

    public function updateRequisition(PurchaseRequisition $requisition, array $validated): PurchaseRequisition
    {
        $sourceId = null;
        if ($validated['source_type'] === 'so') {
            $sourceId = $validated['sales_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'mo') {
            $sourceId = $validated['production_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_request') {
            $sourceId = $validated['production_requisition_slip_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_requirement') {
            $sourceId = $validated['material_requirement_id'] ?? null;
        }

        return DB::transaction(function () use ($validated, $sourceId, $requisition) {
            $requisition->update([
                'requisition_date' => $validated['requisition_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'source_type' => $validated['source_type'],
                'source_id' => $sourceId,
                'requisition_slip_number' => $validated['requisition_slip_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $requisition->items()->delete();

            foreach ($validated['items'] as $item) {
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $requisition->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'estimated_cost' => $item['estimated_cost'],
                ]);
            }

            return $requisition;
        });
    }

    public function createPosFromPendingItems(array $selectedItemIds, string $actionType, int $tenantId): array
    {
        if (empty($selectedItemIds)) {
            throw new \InvalidArgumentException('Please select at least one item.');
        }

        return DB::transaction(function () use ($selectedItemIds, $actionType, $tenantId) {
            $prItems = PurchaseRequisitionItem::whereIn('id', $selectedItemIds)
                ->with(['requisition', 'product'])
                ->get();

            $existingPos = PurchaseOrder::where('tenant_id', $tenantId)
                ->whereNotNull('purchase_requisition_id')
                ->where('status', 'Approved')
                ->with('items')
                ->get();

            $itemsWithQty = [];
            foreach ($prItems as $item) {
                $alreadyOrderedQty = (float) $existingPos
                    ->where('purchase_requisition_id', $item->purchase_requisition_id)
                    ->flatMap(fn($po) => $po->items)
                    ->where('product_id', $item->product_id)
                    ->sum('quantity');

                $pendingQty = max(0.0, (float)$item->quantity - $alreadyOrderedQty);
                if ($pendingQty > 0.0001) {
                    $itemsWithQty[] = [
                        'item' => $item,
                        'qty' => $pendingQty
                    ];
                }
            }

            if (empty($itemsWithQty)) {
                throw new \InvalidArgumentException("The selected items have already been fully ordered.");
            }

            $vendorItems = [];
            foreach ($itemsWithQty as $entry) {
                $item = $entry['item'];
                $qty = $entry['qty'];

                $vendorId = null;
                if ($item->product->preferred_vendor_id) {
                    $vendorId = $item->product->preferred_vendor_id;
                } else {
                    $lastPoItem = PurchaseOrderItem::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->whereHas('order', function ($q) {
                            $q->where('status', 'Approved');
                        })
                        ->orderBy('id', 'desc')
                        ->first();
                    $vendorId = $lastPoItem?->order?->vendor_id;
                }

                if (!$vendorId) {
                    throw new \InvalidArgumentException("Product '{$item->product->name}' has no supplier assigned. Please assign a supplier for this item in Product Master before creating PO/RFQ.");
                }

                $vendorItems[$vendorId][] = [
                    'item' => $item,
                    'qty' => $qty
                ];
            }

            if ($actionType === 'po') {
                $poCount = 0;
                foreach ($vendorItems as $vendorId => $list) {
                    $year = now()->format('Y');
                    $prefix = "PO-{$year}-";
                    $latest = PurchaseOrder::where('tenant_id', $tenantId)
                        ->where('purchase_order_number', 'like', "{$prefix}%")
                        ->orderBy('id', 'desc')
                        ->first();
                    $nextNum = 1;
                    if ($latest) {
                        $lastNumStr = str_replace($prefix, '', $latest->purchase_order_number);
                        $nextNum = ((int) $lastNumStr) + 1;
                    }
                    $poNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

                    $firstPrItem = $list[0]['item'];
                    $defaultWarehouse = Warehouse::find($firstPrItem->warehouse_id);
                    $locationName = $defaultWarehouse?->name ?: '';

                    $isSubcontract = false;
                    $moId = null;
                    $opId = null;
                    if ($firstPrItem->requisition && in_array($firstPrItem->requisition->source_type, ['mo', 'ProductionOrder'])) {
                        $moId = $firstPrItem->requisition->source_id;
                        if ($moId) {
                            $extOp = \App\Domains\Production\Models\ProductionOrderOperation::where('production_order_id', $moId)
                                ->where('is_external', true)
                                ->first();

                            if ($extOp || str_contains((string)$firstPrItem->requisition->notes, 'Subcontract Service') || str_contains((string)$firstPrItem->requisition->notes, 'Subcontract PR')) {
                                $isSubcontract = true;
                                $opId = $extOp?->id;
                            }
                        }
                    }

                    $po = PurchaseOrder::create([
                        'tenant_id' => $tenantId,
                        'purchase_order_number' => $poNumber,
                        'purchase_requisition_id' => $firstPrItem->purchase_requisition_id,
                        'source_type' => 'requisition',
                        'is_subcontract' => $isSubcontract,
                        'production_order_id' => $moId,
                        'vendor_id' => $vendorId,
                        'location' => $locationName,
                        'date' => now()->toDateString(),
                        'delivery_date' => $firstPrItem->requisition?->expected_date?->format('Y-m-d'),
                        'discount_type' => 'without_discount',
                        'tax_type' => 'order_wise_tax',
                        'gst_type' => 'cgst_sgst',
                        'status' => 'Draft',
                        'created_by' => auth()->id() ?: 1,
                        'subtotal' => 0,
                        'discount_amount' => 0,
                        'cgst_amount' => 0,
                        'sgst_amount' => 0,
                        'igst_amount' => 0,
                        'tax_amount' => 0,
                        'grand_total' => 0,
                        'notes' => $isSubcontract ? 'Subcontract Service PO generated from Pending Requisitions.' : 'Bulk generated from Pending Requisitions.',
                    ]);

                    $subtotal = 0.0;
                    foreach ($list as $entry) {
                        $item = $entry['item'];
                        $qty = $entry['qty'];
                        $rate = (float)($item->product->unit_cost ?? $item->estimated_cost ?? 0.00);
                        $amount = $qty * $rate;
                        $subtotal += $amount;

                        $itemOpId = $opId;
                        if ($moId && !$itemOpId && $isSubcontract) {
                            $itemOpId = \App\Domains\Production\Models\ProductionOrderOperation::where('production_order_id', $moId)
                                ->where('is_external', true)
                                ->value('id');
                        }

                        PurchaseOrderItem::create([
                            'purchase_order_id' => $po->id,
                            'product_id' => $item->product_id,
                            'production_order_id' => $moId,
                            'production_order_operation_id' => $isSubcontract ? $itemOpId : null,
                            'requisition_item_allocations' => [
                                [
                                    'pr_item_id' => $item->id,
                                    'quantity' => $qty,
                                ]
                            ],
                            'quantity' => $qty,
                            'rate' => $rate,
                            'amount' => $amount,
                            'total_amount' => $amount,
                        ]);
                    }

                    $po->update([
                        'subtotal' => $subtotal,
                        'grand_total' => $subtotal,
                    ]);

                    $poCount++;
                }

                return [
                    'type' => 'po',
                    'count' => $poCount,
                    'message' => "Successfully created {$poCount} Draft Purchase Orders for the selected pending requisition items."
                ];

            } elseif ($actionType === 'rfq') {
                $rfqCount = 0;
                foreach ($vendorItems as $vendorId => $list) {
                    $year = now()->format('Y');
                    $prefix = "RFQ-{$year}-";
                    $latest = PurchaseRfq::where('tenant_id', $tenantId)
                        ->where('rfq_number', 'like', "{$prefix}%")
                        ->orderBy('id', 'desc')
                        ->first();
                    $nextNum = 1;
                    if ($latest) {
                        $lastNumStr = str_replace($prefix, '', $latest->rfq_number);
                        $nextNum = intval($lastNumStr) + 1;
                    }
                    $rfqNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

                    $firstPrItem = $list[0]['item'];

                    $rfq = PurchaseRfq::create([
                        'tenant_id' => $tenantId,
                        'rfq_number' => $rfqNumber,
                        'purchase_requisition_id' => $firstPrItem->purchase_requisition_id,
                        'rfq_date' => now()->toDateString(),
                        'status' => 'Draft',
                        'notes' => 'Bulk generated from Pending Requisitions.',
                        'created_by' => auth()->id() ?: 1,
                    ]);

                    foreach ($list as $entry) {
                        $item = $entry['item'];
                        $qty = $entry['qty'];
                        PurchaseRfqItem::create([
                            'purchase_rfq_id' => $rfq->id,
                            'product_id' => $item->product_id,
                            'quantity' => $qty,
                            'estimated_cost' => $item->estimated_cost ?? $item->product->unit_cost ?? 0.00,
                        ]);
                    }

                    PurchaseRfqVendor::create([
                        'tenant_id' => $tenantId,
                        'purchase_rfq_id' => $rfq->id,
                        'vendor_id' => $vendorId,
                        'token' => Str::random(40),
                        'status' => 'Sent',
                    ]);

                    $rfqCount++;
                }

                return [
                    'type' => 'rfq',
                    'count' => $rfqCount,
                    'message' => "Successfully created {$rfqCount} Draft RFQs for the selected pending requisition items."
                ];
            }

            throw new \InvalidArgumentException("Invalid action type.");
        });
    }
}
