<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Batch as InventoryBatch;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionLotTrace;
use App\Domains\Production\Models\ProductionOrder;
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

        if ($direction === 'backward') {
            $traces = ProductionLotTrace::where('tenant_id', $tenantId)
                ->where('target_type', $type)
                ->where('target_id', $id)
                ->get();

            foreach ($traces as $trace) {
                $edges[] = [
                    'source_key' => "{$trace->source_type}_{$trace->source_id}",
                    'target_key' => $key,
                    'quantity'   => (float) $trace->quantity,
                    'remarks'    => $trace->remarks,
                ];
                $this->traverse($tenantId, $trace->source_type, $trace->source_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
            }
        } else {
            $traces = ProductionLotTrace::where('tenant_id', $tenantId)
                ->where('source_type', $type)
                ->where('source_id', $id)
                ->get();

            foreach ($traces as $trace) {
                $edges[] = [
                    'source_key' => $key,
                    'target_key' => "{$trace->target_type}_{$trace->target_id}",
                    'quantity'   => (float) $trace->quantity,
                    'remarks'    => $trace->remarks,
                ];
                $this->traverse($tenantId, $trace->target_type, $trace->target_id, $direction, $maxDepth, $nodes, $edges, $visited, $currentDepth + 1);
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
                    ->with('product')
                    ->where('tenant_id', $tenantId)
                    ->find($id);
                if ($order) {
                    // Forward trace: Customer attribution via verified SalesOrder relationship
                    $customerName = null;
                    if ($order->sales_order_id) {
                        // SalesOrder belongs to tenant via BelongsToTenant scope; use withoutGlobalScopes for safety
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

                    return [
                        'label'    => "Order: {$order->order_number}",
                        'detail'   => $detail,
                        'status'   => $order->status,
                        'date'     => $order->created_at->format('d/m/Y H:i'),
                        'customer' => $customerName,
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

    private function csvVal(string $value): string
    {
        $value = str_replace('"', '""', $value);
        return "\"{$value}\"";
    }
}
