<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionLotTrace;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionSerialNumber;

class LotTraceabilityService
{
    /**
     * Backward Trace: Trace from finished good / batch / serial back to source lots / orders.
     */
    public function backwardTrace(int $tenantId, string $type, int $id, int $depth = 5): array
    {
        $nodes = [];
        $edges = [];
        $visited = [];

        $this->traverse($tenantId, $type, $id, 'backward', $depth, $nodes, $edges, $visited);

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
        ];
    }

    /**
     * Forward Trace: Trace from raw material / batch forward to finished goods.
     */
    public function forwardTrace(int $tenantId, string $type, int $id, int $depth = 5): array
    {
        $nodes = [];
        $edges = [];
        $visited = [];

        $this->traverse($tenantId, $type, $id, 'forward', $depth, $nodes, $edges, $visited);

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
        ];
    }

    /**
     * Build general genealogy tree (combines both backward and forward traces).
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
     * Recursive traversal helper.
     */
    private function traverse(
        int $tenantId,
        string $type,
        int $id,
        string $direction,
        int $maxDepth,
        array &$nodes,
        array &$edges,
        array &$visited,
        int $currentDepth = 0
    ): void {
        if ($currentDepth > $maxDepth) {
            return;
        }

        $key = "{$type}_{$id}";
        if (isset($visited[$key])) {
            return;
        }
        $visited[$key] = true;

        // Resolve node details
        $nodeDetail = $this->resolveNodeDetails($tenantId, $type, $id);
        $nodes[$key] = array_merge([
            'key'   => $key,
            'type'  => $type,
            'id'    => $id,
            'depth' => $currentDepth,
        ], $nodeDetail);

        // Find links
        if ($direction === 'backward') {
            $traces = ProductionLotTrace::where('tenant_id', $tenantId)
                ->where('target_type', $type)
                ->where('target_id', $id)
                ->get();

            foreach ($traces as $trace) {
                $edge = [
                    'source_key' => "{$trace->source_type}_{$trace->source_id}",
                    'target_key' => $key,
                    'quantity'   => (float)$trace->quantity,
                    'remarks'    => $trace->remarks,
                ];
                $edges[] = $edge;

                $this->traverse(
                    $tenantId,
                    $trace->source_type,
                    $trace->source_id,
                    $direction,
                    $maxDepth,
                    $nodes,
                    $edges,
                    $visited,
                    $currentDepth + 1
                );
            }
        } else {
            // Forward direction
            $traces = ProductionLotTrace::where('tenant_id', $tenantId)
                ->where('source_type', $type)
                ->where('source_id', $id)
                ->get();

            foreach ($traces as $trace) {
                $edge = [
                    'source_key' => $key,
                    'target_key' => "{$trace->target_type}_{$trace->target_id}",
                    'quantity'   => (float)$trace->quantity,
                    'remarks'    => $trace->remarks,
                ];
                $edges[] = $edge;

                $this->traverse(
                    $tenantId,
                    $trace->target_type,
                    $trace->target_id,
                    $direction,
                    $maxDepth,
                    $nodes,
                    $edges,
                    $visited,
                    $currentDepth + 1
                );
            }
        }
    }

    /**
     * Resolve descriptive labels and statuses for rendering.
     */
    private function resolveNodeDetails(int $tenantId, string $type, int $id): array
    {
        switch ($type) {
            case 'batch':
                $batch = ProductionBatch::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($id);
                if ($batch) {
                    return [
                        'label'  => "Batch: {$batch->batch_number}",
                        'detail' => "Product: " . ($batch->product?->name ?? '—') . " (Qty: {$batch->planned_quantity})",
                        'status' => $batch->status,
                        'date'   => $batch->created_at->format('d/m/Y H:i'),
                    ];
                }
                break;

            case 'order':
                $order = ProductionOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($id);
                if ($order) {
                    return [
                        'label'  => "Order: {$order->order_number}",
                        'detail' => "Product: " . ($order->product?->name ?? '—') . " (Qty: {$order->quantity_ordered})",
                        'status' => $order->status,
                        'date'   => $order->created_at->format('d/m/Y H:i'),
                    ];
                }
                break;

            case 'serial':
                $serial = ProductionSerialNumber::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($id);
                if ($serial) {
                    return [
                        'label'  => "Serial: {$serial->serial_number}",
                        'detail' => "Product: " . ($serial->product?->name ?? '—'),
                        'status' => $serial->status,
                        'date'   => $serial->created_at->format('d/m/Y H:i'),
                    ];
                }
                break;

            case 'lot':
                // Placeholders for external raw material lots (inventory)
                return [
                    'label'  => "Material Lot #{$id}",
                    'detail' => "Raw Material Allocation",
                    'status' => 'issued',
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
}
