<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Batch as InventoryBatch;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionLotTrace;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Sales\Models\SalesOrder;

class LotTraceabilityService
{
    /**
     * Backward Trace: from finished good / batch / serial back to source lots / orders.
     */
    public function backwardTrace(int $tenantId, string $type, int $id, int $depth = 5): array
    {
        $nodes   = [];
        $edges   = [];
        $visited = [];

        $this->traverse($tenantId, $type, $id, 'backward', $depth, $nodes, $edges, $visited);

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
        ];
    }

    /**
     * Forward Trace: from raw material / batch forward to finished goods and customer dispatch.
     */
    public function forwardTrace(int $tenantId, string $type, int $id, int $depth = 5): array
    {
        $nodes   = [];
        $edges   = [];
        $visited = [];

        $this->traverse($tenantId, $type, $id, 'forward', $depth, $nodes, $edges, $visited);

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
        ];
    }

    /**
     * Build full genealogy tree (combines both backward and forward traces).
     */
    public function buildGenealogy(int $tenantId, string $type, int $id): array
    {
        $backward = $this->backwardTrace($tenantId, $type, $id);
        $forward  = $this->forwardTrace($tenantId, $type, $id);

        $nodes = [];
        foreach (array_merge($backward['nodes'], $forward['nodes']) as $node) {
            $nodes[$node['key']] = $node;
        }

        $edges = array_unique(
            array_merge($backward['edges'], $forward['edges']),
            SORT_REGULAR
        );

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
        ];
    }

    /**
     * Export a genealogy result as a CSV string.
     * Returns the CSV content as a string for streaming/download.
     */
    public function exportCsv(int $tenantId, string $type, int $id): string
    {
        $genealogy = $this->buildGenealogy($tenantId, $type, $id);

        $lines   = [];
        $lines[] = implode(',', ['Node Key', 'Type', 'Label', 'Status', 'Date', 'Detail']);

        foreach ($genealogy['nodes'] as $node) {
            $lines[] = implode(',', [
                $this->csvVal($node['key']),
                $this->csvVal($node['type']),
                $this->csvVal($node['label']),
                $this->csvVal($node['status'] ?? ''),
                $this->csvVal($node['date'] ?? ''),
                $this->csvVal($node['detail'] ?? ''),
            ]);
        }

        $lines[] = '';
        $lines[] = implode(',', ['Source Key', 'Target Key', 'Quantity', 'Remarks']);
        foreach ($genealogy['edges'] as $edge) {
            $lines[] = implode(',', [
                $this->csvVal($edge['source_key']),
                $this->csvVal($edge['target_key']),
                $this->csvVal((string)($edge['quantity'] ?? '')),
                $this->csvVal($edge['remarks'] ?? ''),
            ]);
        }

        return implode("\n", $lines);
    }

    // ─── Private: Recursive Traversal ────────────────────────────────────────────

    private function traverse(
        int    $tenantId,
        string $type,
        int    $id,
        string $direction,
        int    $maxDepth,
        array  &$nodes,
        array  &$edges,
        array  &$visited,
        int    $currentDepth = 0
    ): void {
        if ($currentDepth > $maxDepth) {
            return;
        }

        $key = "{$type}_{$id}";
        if (isset($visited[$key])) {
            return;
        }
        $visited[$key] = true;

        $nodeDetail  = $this->resolveNodeDetails($tenantId, $type, $id);
        $nodes[$key] = array_merge([
            'key'   => $key,
            'type'  => $type,
            'id'    => $id,
            'depth' => $currentDepth,
        ], $nodeDetail);

        $addEdge = function (string $sourceKey, string $targetKey, float $qty, ?string $remarks = null) use (&$edges) {
            foreach ($edges as $e) {
                if ($e['source_key'] === $sourceKey && $e['target_key'] === $targetKey) {
                    return;
                }
            }
            $edges[] = [
                'source_key' => $sourceKey,
                'target_key' => $targetKey,
                'quantity'   => $qty,
                'remarks'    => $remarks,
            ];
        };

        if ($direction === 'backward') {
            // 1. Explicit trace table entries
            $traces = ProductionLotTrace::where('tenant_id', $tenantId)
                ->where('target_type', $type)
                ->where('target_id', $id)
                ->get();

            foreach ($traces as $trace) {
                $addEdge("{$trace->source_type}_{$trace->source_id}", $key, (float) $trace->quantity, $trace->remarks);
                $this->traverse($tenantId, $trace->source_type, $trace->source_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
            }

            // 2. Direct domain relationships
            if ($type === 'order') {
                $order = ProductionOrder::withoutGlobalScopes()
                    ->with(['salesOrder.customer'])
                    ->where('tenant_id', $tenantId)
                    ->find($id);

                if ($order) {
                    $issues = ProductionOrderIssue::withoutGlobalScopes()
                        ->with(['product', 'warehouse', 'batches'])
                        ->where('tenant_id', $tenantId)
                        ->where('production_order_id', $order->id)
                        ->get();

                    foreach ($issues as $issue) {
                        if ($issue->inventory_batch_id) {
                            $addEdge("lot_{$issue->inventory_batch_id}", $key, (float) $issue->quantity_issued, 'Material Issue (Lot)');
                            $this->traverse($tenantId, 'lot', $issue->inventory_batch_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                        } else {
                            $hasBatchAlloc = false;
                            foreach ($issue->batches as $ib) {
                                if ($ib->inventory_batch_id) {
                                    $hasBatchAlloc = true;
                                    $addEdge("lot_{$ib->inventory_batch_id}", $key, (float) $ib->quantity, 'Material Issue (Allocated Lot)');
                                    $this->traverse($tenantId, 'lot', $ib->inventory_batch_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                                }
                            }
                            if (!$hasBatchAlloc) {
                                $addEdge("material_{$issue->id}", $key, (float) $issue->quantity_issued, 'Raw Material Consumed');
                                $this->traverse($tenantId, 'material', $issue->id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                            }
                        }
                    }

                    if ($order->sales_order_id) {
                        $addEdge("sales_order_{$order->sales_order_id}", $key, (float) $order->quantity_ordered, 'Sales Order Demand');
                        $this->traverse($tenantId, 'sales_order', $order->sales_order_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                    }
                }
            } elseif ($type === 'batch') {
                $batch = ProductionBatch::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($id);
                if ($batch && $batch->production_order_id) {
                    $addEdge("order_{$batch->production_order_id}", $key, (float) ($batch->actual_quantity ?: $batch->planned_quantity), 'Produced by Order');
                    $this->traverse($tenantId, 'order', $batch->production_order_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                }
            } elseif ($type === 'serial') {
                $serial = ProductionSerialNumber::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($id);
                if ($serial) {
                    if ($serial->batch_id) {
                        $addEdge("batch_{$serial->batch_id}", $key, 1.0, 'Part of Batch');
                        $this->traverse($tenantId, 'batch', $serial->batch_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                    } elseif ($serial->production_order_id) {
                        $addEdge("order_{$serial->production_order_id}", $key, 1.0, 'Produced by Order');
                        $this->traverse($tenantId, 'order', $serial->production_order_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                    }
                }
            }
        } else {
            // Forward trace
            // 1. Explicit trace table entries
            $traces = ProductionLotTrace::where('tenant_id', $tenantId)
                ->where('source_type', $type)
                ->where('source_id', $id)
                ->get();

            foreach ($traces as $trace) {
                $addEdge($key, "{$trace->target_type}_{$trace->target_id}", (float) $trace->quantity, $trace->remarks);
                $this->traverse($tenantId, $trace->target_type, $trace->target_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
            }

            // 2. Direct domain relationships
            if ($type === 'lot') {
                $issues = \App\Domains\Production\Models\ProductionOrderIssue::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('inventory_batch_id', $id)
                    ->get();

                foreach ($issues as $issue) {
                    $addEdge($key, "order_{$issue->production_order_id}", (float) $issue->quantity_issued, 'Consumed in Order');
                    $this->traverse($tenantId, 'order', $issue->production_order_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                }

                $issueBatches = \App\Domains\Production\Models\ProductionOrderIssueBatch::withoutGlobalScopes()
                    ->where('inventory_batch_id', $id)
                    ->with('issue')
                    ->get();

                foreach ($issueBatches as $ib) {
                    if ($ib->issue && $ib->issue->tenant_id == $tenantId) {
                        $addEdge($key, "order_{$ib->issue->production_order_id}", (float) $ib->quantity, 'Consumed in Order (Allocated)');
                        $this->traverse($tenantId, 'order', $ib->issue->production_order_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                    }
                }
            } elseif ($type === 'order') {
                $order = ProductionOrder::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->find($id);

                if ($order) {
                    $batches = ProductionBatch::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('production_order_id', $order->id)
                        ->get();

                    foreach ($batches as $batch) {
                        $addEdge($key, "batch_{$batch->id}", (float) ($batch->actual_quantity ?: $batch->planned_quantity), 'Output Batch');
                        $this->traverse($tenantId, 'batch', $batch->id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                    }

                    $receipts = ProductionOrderReceipt::withoutGlobalScopes()
                        ->with('warehouse')
                        ->where('tenant_id', $tenantId)
                        ->where('production_order_id', $order->id)
                        ->get();

                    foreach ($receipts as $receipt) {
                        $batchId = $receipt->inventory_batch_id ?? $receipt->batch_id;
                        if ($batchId) {
                            $addEdge($key, "lot_{$batchId}", (float) $receipt->quantity_received, 'Received FG Lot');
                            $this->traverse($tenantId, 'lot', $batchId, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                        } else {
                            $addEdge($key, "receipt_{$receipt->id}", (float) $receipt->quantity_received, 'Received FG');
                            $this->traverse($tenantId, 'receipt', $receipt->id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                        }
                    }

                    $serials = ProductionSerialNumber::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('production_order_id', $id)
                        ->take(20)
                        ->get();

                    foreach ($serials as $serial) {
                        $addEdge($key, "serial_{$serial->id}", 1.0, 'Output Serial Unit');
                        $this->traverse($tenantId, 'serial', $serial->id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                    }

                    if ($order->sales_order_id) {
                        $addEdge($key, "sales_order_{$order->sales_order_id}", (float) $order->quantity_ordered, 'Fulfills Sales Demand');
                        $this->traverse($tenantId, 'sales_order', $order->sales_order_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                    }
                }
            } elseif ($type === 'batch') {
                $serials = ProductionSerialNumber::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('batch_id', $id)
                    ->take(20)
                    ->get();

                foreach ($serials as $serial) {
                    $addEdge($key, "serial_{$serial->id}", 1.0, 'Output Serial Unit');
                    $this->traverse($tenantId, 'serial', $serial->id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
                }
            }
        }
    }

    // ─── Private: Node Details Resolution ────────────────────────────────────────

    /**
     * Resolve display labels, status and contextual details for each trace node.
     *
     * 'lot' type = Inventory::Batch (raw material lot received into stock).
     *
     * Supplier attribution for 'lot' nodes:
     *  We use the StockTransaction with reference_type='GRN' or 'Opening Stock' for that
     *  batch to identify the receipt source. We do NOT use Product.preferred_vendor_id
     *  as that represents the preferred future supplier, not the actual receipt supplier.
     *  If no purchase transaction exists, the supplier field is left as "N/A".
     *
     * Customer information for 'order' nodes:
     *  The ProductionOrder.sales_order_id is a verified FK to sales_orders.
     *  SalesOrder.customer_id → Customer. This path is used for forward customer attribution.
     *  The relationship: ProductionOrder → SalesOrder (sales_order_id) → Customer (customer_id).
     */
    private function resolveNodeDetails(int $tenantId, string $type, int $id): array
    {
        switch ($type) {
            case 'batch':
                $batch = ProductionBatch::withoutGlobalScopes()
                    ->with('product')
                    ->where('tenant_id', $tenantId)
                    ->find($id);
                if ($batch) {
                    return [
                        'label'  => "Batch: {$batch->batch_number}",
                        'detail' => "Product: " . ($batch->product?->name ?? '—') . " | Planned Qty: {$batch->planned_quantity} | Actual: {$batch->actual_quantity}",
                        'status' => $batch->status,
                        'date'   => $batch->created_at->format('d/m/Y H:i'),
                        'expiry' => $batch->expiry_date?->format('d/m/Y') ?? null,
                    ];
                }
                break;

            case 'order':
                $order = ProductionOrder::withoutGlobalScopes()
                    ->with(['product', 'operations.workCenter', 'operations.machine'])
                    ->where('tenant_id', $tenantId)
                    ->find($id);
                if ($order) {
                    $customerName = null;
                    if ($order->sales_order_id) {
                        $so = SalesOrder::withoutGlobalScopes()
                            ->with('customer')
                            ->where('tenant_id', $tenantId)
                            ->find($order->sales_order_id);
                        $customerName = $so?->customer?->name ?? null;
                    }

                    $detail = "Product: " . ($order->product?->name ?? '—') . " | Qty Ordered: {$order->quantity_ordered} | Produced: {$order->quantity_produced}";
                    if ($customerName) {
                        $detail .= " | Customer: {$customerName}";
                    }

                    $ops = $order->operations->map(function ($op) {
                        return [
                            'sequence'    => $op->sequence,
                            'name'        => $op->name,
                            'status'      => $op->status,
                            'work_center' => $op->workCenter?->name ?? '—',
                            'machine'     => $op->machine?->name ?? '—',
                            'produced'    => (float) $op->quantity_produced,
                            'rejected'    => (float) $op->quantity_rejected,
                        ];
                    })->toArray();

                    $materialsCount = ProductionOrderIssue::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('production_order_id', $order->id)
                        ->count();

                    return [
                        'label'            => "Order: {$order->order_number}",
                        'detail'           => $detail,
                        'status'           => $order->status,
                        'date'             => $order->created_at->format('d/m/Y H:i'),
                        'customer'         => $customerName,
                        'operations'       => $ops,
                        'operations_count' => count($ops),
                        'materials_count'  => $materialsCount,
                        'produced_qty'     => (float) $order->quantity_produced,
                        'ordered_qty'      => (float) $order->quantity_ordered,
                    ];
                }
                break;

            case 'serial':
                $serial = ProductionSerialNumber::withoutGlobalScopes()
                    ->with('product')
                    ->where('tenant_id', $tenantId)
                    ->find($id);
                if ($serial) {
                    return [
                        'label'  => "Serial: {$serial->serial_number}",
                        'detail' => "Product: " . ($serial->product?->name ?? '—') . " | Status: {$serial->status}",
                        'status' => $serial->status,
                        'date'   => $serial->created_at->format('d/m/Y H:i'),
                    ];
                }
                break;

            case 'material':
                $issue = \App\Domains\Production\Models\ProductionOrderIssue::withoutGlobalScopes()
                    ->with(['product', 'warehouse'])
                    ->where('tenant_id', $tenantId)
                    ->find($id);
                if ($issue) {
                    return [
                        'label'  => "Raw Material: " . ($issue->product?->name ?? "Material #{$id}"),
                        'detail' => "Issued Qty: {$issue->quantity_issued} | Warehouse: " . ($issue->warehouse?->name ?? 'Default') . " | SKU: " . ($issue->product?->sku ?? '—'),
                        'status' => 'issued',
                        'date'   => $issue->issued_at?->format('d/m/Y H:i') ?? $issue->created_at->format('d/m/Y H:i'),
                    ];
                }
                break;

            case 'receipt':
                $rc = \App\Domains\Production\Models\ProductionOrderReceipt::withoutGlobalScopes()
                    ->with('warehouse')
                    ->where('tenant_id', $tenantId)
                    ->find($id);
                if ($rc) {
                    return [
                        'label'  => "FG Receipt #" . ($rc->receipt_number ?? $rc->id),
                        'detail' => "Received Qty: {$rc->quantity_received} | Warehouse: " . ($rc->warehouse?->name ?? 'Default'),
                        'status' => $rc->status ?? 'received',
                        'date'   => $rc->received_at?->format('d/m/Y H:i') ?? $rc->created_at->format('d/m/Y H:i'),
                    ];
                }
                break;

            case 'sales_order':
                $so = SalesOrder::withoutGlobalScopes()
                    ->with('customer')
                    ->where('tenant_id', $tenantId)
                    ->find($id);
                if ($so) {
                    return [
                        'label'    => "Sales Order: {$so->order_number}",
                        'detail'   => "Customer: " . ($so->customer?->name ?? 'Direct Customer') . " | Total: " . ($so->total_amount ?? '—'),
                        'status'   => $so->status ?? 'active',
                        'date'     => $so->created_at->format('d/m/Y H:i'),
                        'customer' => $so->customer?->name,
                    ];
                }
                break;

            case 'lot':
                // 'lot' type = Inventory::Batch (raw material stock lot)
                // Correction #13: supplier comes from StockTransaction source, not Product.vendor
                $invBatch = InventoryBatch::withoutGlobalScopes()
                    ->with('product')
                    ->where('tenant_id', $tenantId)
                    ->find($id);

                if ($invBatch) {
                    // Try to find the inbound stock transaction for this batch to get supplier info
                    // reference_type 'GRN' or 'Opening Stock' indicates receipt source
                    $inboundTx = \App\Domains\Inventory\Models\StockTransaction::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('batch_id', $invBatch->id)
                        ->where('type', 'IN')
                        ->orderBy('created_at', 'asc')
                        ->first();

                    $receiptSource = 'N/A';
                    if ($inboundTx) {
                        $receiptSource = $inboundTx->reference_type . ' #' . $inboundTx->reference_id;
                    }

                    return [
                        'label'   => "Inventory Lot: " . $invBatch->batch_number,
                        'detail'  => "Product: " . ($invBatch->product?->name ?? '—')
                            . " | Qty: {$invBatch->quantity} | Receipt Source: {$receiptSource}",
                        'status'  => $invBatch->quantity > 0 ? 'in_stock' : 'consumed',
                        'date'    => $invBatch->created_at->format('d/m/Y H:i'),
                        'expiry'  => $invBatch->expiry_date?->format('d/m/Y') ?? null,
                        'receipt' => $receiptSource,
                    ];
                }

                // Fallback for lot records predating the inventory batch linkage
                return [
                    'label'  => "Material Lot #{$id}",
                    'detail' => "Inventory batch record not found (may be deleted or pre-migration).",
                    'status' => 'unknown',
                    'date'   => 'N/A',
                ];
        }

        return [
            'label'  => "Unknown Node #{$id}",
            'detail' => "Type: {$type}",
            'status' => 'unknown',
            'date'   => 'N/A',
        ];
    }

    /**
     * Compute aggregated lot summary for a Production Order / Lot context across all batches.
     */
    public function getLotSummary(int $tenantId, int $orderId): array
    {
        $order = ProductionOrder::withoutGlobalScopes()
            ->with(['product', 'operations'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($orderId);

        $batches = ProductionBatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->with('currentOperation')
            ->get();

        $plannedTotal = (float) $batches->sum('planned_quantity');
        if ($plannedTotal <= 0) {
            $plannedTotal = (float) $order->quantity_ordered;
        }

        // Unique physical produced: logged output at initial operation
        $firstOp = $order->operations()->orderBy('sequence', 'asc')->first();
        $uniqueProduced = 0.0;
        if ($firstOp) {
            $uniqueProduced = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('operation_id', $firstOp->id)
                ->sum('quantity_produced');
        }

        // Total Operation Throughput (sum across all routing operations)
        $operationThroughput = (float) \App\Domains\Production\Models\ProductionOrderProgressLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->sum('quantity_produced');

        // Total Scrap
        $totalScrap = (float) \App\Domains\Production\Models\ProductionOrderScrap::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->sum('quantity');

        // Total Pending Rework
        $totalReworkPending = (float) \App\Domains\Production\Models\ProductionOrderRework::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where('status', 'pending')
            ->sum('quantity');

        // Total Completed Rework
        $totalReworkRecovered = (float) \App\Domains\Production\Models\ProductionOrderRework::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where('status', 'completed')
            ->sum('quantity');

        // Finished Goods Received Qty
        $totalFinished = (float) \App\Domains\Production\Models\ProductionOrderReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->sum('quantity_received');

        // Active WIP in progress
        $currentWip = max(0.0, $uniqueProduced - $totalFinished - $totalScrap);

        $batchSummaries = $batches->map(function ($b) use ($tenantId) {
            $scrap = (float) \App\Domains\Production\Models\ProductionOrderScrap::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_batch_id', $b->id)
                ->sum('quantity');

            $reworkPending = (float) \App\Domains\Production\Models\ProductionOrderRework::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_batch_id', $b->id)
                ->where('status', 'pending')
                ->sum('quantity');

            $reworkRecovered = (float) \App\Domains\Production\Models\ProductionOrderRework::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_batch_id', $b->id)
                ->where('status', 'completed')
                ->sum('quantity');

            return [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'planned_quantity' => (float) $b->planned_quantity,
                'actual_quantity' => (float) $b->actual_quantity,
                'status' => $b->status,
                'current_operation' => $b->currentOperation?->name ?? 'Initial',
                'current_operation_sequence' => $b->currentOperation?->sequence ?? 10,
                'scrap_quantity' => $scrap,
                'rework_pending' => $reworkPending,
                'rework_recovered' => $reworkRecovered,
            ];
        });

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'product_name' => $order->product?->name ?? '—',
            'planned_total' => $plannedTotal,
            'unique_produced' => $uniqueProduced,
            'operation_throughput' => $operationThroughput,
            'current_wip' => $currentWip,
            'finished_goods' => $totalFinished,
            'scrap_total' => $totalScrap,
            'rework_pending' => $totalReworkPending,
            'rework_recovered' => $totalReworkRecovered,
            'batches' => $batchSummaries->toArray(),
        ];
    }

    private function csvVal(string $value): string
    {
        $value = str_replace('"', '""', $value);
        return "\"{$value}\"";
    }
}
