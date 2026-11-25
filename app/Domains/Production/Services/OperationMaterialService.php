<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use Illuminate\Support\Collection;

/**
 * PX-01..PX-06 (docs/production_execution_rules_draft.md) — the ONE place that answers
 * "which materials does this order operation consume, how much, and how much of the order's pooled issue covers it".
 *
 * - Materials come only from ProductionOrderOperation::getExpectedComponents() (PO-12: source BOM + chosen rows).
 * - Required = (op target / BOM base qty) x allocation gross qty (scrap included)   [PX-02 / PO-08]
 * - A product has ONE pooled reservation per order (PO-12); the pooled issued quantity is handed out to the
 *   operations in execution order (child levels first, then sequence) so a shared material is never counted twice [PX-06].
 * - Sub-assembly rows (child_bom_id) that are produced by an operation of the same order are not raw materials [PX-04].
 * - RV-03 (M-27): madeFromSet() says how much of such a sub-assembly its maker operations have produced (counted once all of them are completed);
 *   the Material check adds it to the part issued from the store.
 */
class OperationMaterialService
{
    /** @var array<int, float> */
    private array $bomBaseCache = [];

    public function requiredQty(ProductionOrderOperation $op, RoutingOperationMaterial $row): float
    {
        $order = $op->order;
        $target = (float) ($op->target_produced_qty > 0 ? $op->target_produced_qty : ($order->quantity_ordered ?? 0));
        $base = $this->bomBase((int) ($row->bom_id ?: $op->source_bom_id ?: ($order->bom_id ?? 0)));
        $perBase = (float) ($row->gross_quantity > 0
            ? $row->gross_quantity
            : RoutingOperationMaterial::calculateGrossQuantity((float) $row->quantity, (float) ($row->material_scrap_percentage ?? 0)));

        return round(($target / $base) * $perBase, 4);
    }

    /**
     * Operations of an order in execution order (child BOM levels first, then sequence) — same order as ProductionOrder::operations().
     */
    public function orderedOperations(ProductionOrder $order): Collection
    {
        $ops = $order->relationLoaded('operations')
            ? $order->operations
            : $order->operations()->get();

        // UR-10: planned operations know their execution order (sub-assembly operations first, then the operation that needs them)
        $planned = $ops->isNotEmpty() && $ops->every(fn ($o) => $o->execution_order !== null);
        $ops = $ops->sort(function ($a, $b) use ($planned) {
            if ($planned) {
                return [(int) $a->execution_order, (int) $a->id] <=> [(int) $b->execution_order, (int) $b->id];
            }

            return [(int) $b->bom_level, (int) $a->sequence, (int) $a->id] <=> [(int) $a->bom_level, (int) $b->sequence, (int) $b->id];
        })->values();

        foreach ($ops as $op) {
            if (! $op->relationLoaded('order')) {
                $op->setRelation('order', $order);
            }
        }

        return $ops;
    }

    /**
     * Resolve every operation of the list once: [operationId => resolve() result]. The screens and the readiness check share it, so a page
     * does not resolve the same operation again and again.
     *
     * @return array<int, array{materials: Collection, sub_assemblies: Collection}>
     */
    public function resolveAll(Collection $ops): array
    {
        $map = [];
        foreach ($ops as $op) {
            $map[$op->id] = $this->resolve($op, $ops);
        }

        return $map;
    }

    /**
     * UR-07 — the operations of an order as a TREE for the order screens.
     * The operations that make a sub-assembly (child-BOM material that is short in the store and is made inside this order) are NOT
     * top-level rows: they sit in a drawer of the operation that needs the material — shown ABOVE it, because they are executed first —
     * and again above their own parent when they need a sub-assembly themselves (as deep as the BOMs go).
     *
     * - top:      operations shown as normal rows (everything that is not a "maker" of someone's sub-assembly; orphans stay visible)
     * - children: [operationId => groups], one group per sub-assembly material:
     *             product, required / from stock / to produce, op_ids (all makers), shown_ids (the makers listed in THIS drawer),
     *             done / total (makers completed)
     * - exec:     [operationId => step number] in EXECUTION order: makers first (deepest first), then the operation that needs them
     * - flat:     [['op' => operation, 'depth' => n], ...] in that same execution order
     * - stats:    [operationId => total / done / ready / running] of the operations inside its drawer (all depths)
     * A maker needed by two operations is listed in the drawer of the one that executes first (the deepest one); the other drawer points to it.
     *
     * @return array{top: Collection, byId: Collection, children: array<int, array<int, array>>, claimedBy: array<int, int>, exec: array<int, int>, flat: array<int, array>, depth: array<int, int>, stats: array<int, array<string, int>>}
     */
    public function operationTree(ProductionOrder $order, ?Collection $ops = null, ?array $resolved = null): array
    {
        $ops ??= $this->orderedOperations($order);
        $byId = $ops->keyBy('id');

        // display order of the top-level rows: shallow first, then sequence
        $parentsFirst = $ops->sort(fn ($a, $b) => [(int) $a->bom_level, (int) $a->sequence, (int) $a->id] <=> [(int) $b->bom_level, (int) $b->sequence, (int) $b->id])->values();
        // a maker needed by two operations is listed under the one that EXECUTES first = the DEEPEST one (it needs the sub-assembly before the
        // shallower operation does), so the numbering and the nesting follow the real execution order
        $claimOrder = $ops->sort(fn ($a, $b) => [-(int) $a->bom_level, (int) $a->sequence, (int) $a->id] <=> [-(int) $b->bom_level, (int) $b->sequence, (int) $b->id])->values();

        $claimedBy = [];
        $children = [];
        $finished = [
            ProductionOrderOperation::STATUS_COMPLETED,
            ProductionOrderOperation::STATUS_SKIPPED,
            ProductionOrderOperation::STATUS_CANCELLED,
        ];

        foreach ($claimOrder as $op) {
            $resolvedOp = $resolved[$op->id] ?? $this->resolve($op, $ops);
            foreach ($resolvedOp['sub_assemblies'] as $sub) {
                $makers = $sub['producers']->sort(fn ($a, $b) => [(int) $a->sequence, (int) $a->id] <=> [(int) $b->sequence, (int) $b->id])->values();
                $shown = [];
                foreach ($makers as $maker) {
                    if ($maker->id === $op->id || isset($claimedBy[$maker->id])) {
                        continue;
                    }
                    // UR-10: with a plan made per consuming operation the set hangs from the operation it was made for; an operation that is
                    // served by an EARLIER operation's set ("produce all at one level") only points to it
                    if (($sub['set_key'] ?? null) !== null && ($sub['borrowed'] ?? false)) {
                        continue;
                    }
                    $claimedBy[$maker->id] = $op->id;
                    $shown[] = $maker->id;
                }

                $row = $sub['row'];
                $children[$op->id][] = [
                    'child_bom_id' => $sub['child_bom_id'] ?? null,
                    'set_key' => $sub['set_key'] ?? null,
                    'borrowed' => (bool) ($sub['borrowed'] ?? false),
                    'borrowed_from_op_id' => null,
                    'product_id' => $sub['product_id'],
                    'name' => $row->material?->name ?? ('Product #' . $sub['product_id']),
                    'sku' => $row->material?->sku,
                    'uom' => $row->material?->uom?->code,
                    'required' => $sub['required'],
                    'from_stock' => $sub['from_stock'],
                    'to_produce' => $sub['to_produce'],
                    'op_ids' => $makers->pluck('id')->all(),
                    'shown_ids' => $shown,
                    'done' => $makers->filter(fn ($m) => in_array($m->status, $finished, true))->count(),
                    'total' => $makers->count(),
                ];
            }
        }

        // an operation served by an earlier operation's set: remember whose drawer holds the makers
        foreach ($children as $consumerId => $groups) {
            foreach ($groups as $gi => $group) {
                if ($group['borrowed'] && $group['op_ids'] !== []) {
                    $children[$consumerId][$gi]['borrowed_from_op_id'] = $claimedBy[$group['op_ids'][0]] ?? null;
                }
            }
        }

        $top = $parentsFirst->reject(fn ($o) => isset($claimedBy[$o->id]))->values();

        // execution order: the operations that make a sub-assembly come FIRST (deepest first), then the operation that needs them
        $exec = [];
        $flat = [];
        $depthOf = [];
        $below = [];   // operationId => ids of every operation inside its drawer (all depths)
        $emit = function ($op, int $depth) use (&$emit, &$exec, &$flat, &$depthOf, &$below, $children, $byId): void {
            $inside = [];
            foreach ($children[$op->id] ?? [] as $group) {
                foreach ($group['shown_ids'] as $childId) {
                    $child = $byId->get($childId);
                    if (!$child) {
                        continue;
                    }
                    $emit($child, $depth + 1);
                    $inside[] = (int) $childId;
                    foreach ($below[$childId] ?? [] as $deeper) {
                        $inside[] = $deeper;
                    }
                }
            }
            $exec[$op->id] = count($exec) + 1;
            $flat[] = ['op' => $op, 'depth' => $depth];
            $depthOf[$op->id] = $depth;
            $below[$op->id] = array_values(array_unique($inside));
        };
        foreach ($top as $op) {
            $emit($op, 0);
        }

        $stats = [];
        foreach ($below as $opId => $ids) {
            $s = ['total' => count($ids), 'done' => 0, 'ready' => 0, 'running' => 0];
            foreach ($ids as $id) {
                $status = $byId->get($id)?->status;
                if (in_array($status, $finished, true)) {
                    $s['done']++;
                } elseif (in_array($status, [ProductionOrderOperation::STATUS_RUNNING, ProductionOrderOperation::STATUS_PAUSED], true)) {
                    $s['running']++;
                } elseif ($status === ProductionOrderOperation::STATUS_READY) {
                    $s['ready']++;
                }
            }
            $stats[$opId] = $s;
        }

        return [
            'top' => $top, 'byId' => $byId, 'children' => $children, 'claimedBy' => $claimedBy, 'exec' => $exec, 'flat' => $flat, 'depth' => $depthOf, 'stats' => $stats,
            // UR-10: "produce all at one level" — what each drawer can do (empty for orders created before the per-operation plan)
            'actions' => app(SubAssemblySetService::class)->actions($order, $ops),
        ];
    }

    /**
     * Resolved rows of one operation, split into raw materials and sub-assemblies, with required quantities.
     *
     * @return array{materials: Collection, sub_assemblies: Collection}
     */
    public function resolve(ProductionOrderOperation $op, ?Collection $siblings = null): array
    {
        $order = $op->order;
        $siblings ??= $this->orderedOperations($order);
        // BOM order: the sub-assembly listed first in the BOM comes first (M-23)
        $rows = $op->getExpectedComponents()
            ->sort(fn ($a, $b) => [(int) ($a->bom_item_id ?? 0), (int) ($a->sequence ?? 0), (int) $a->id] <=> [(int) ($b->bom_item_id ?? 0), (int) ($b->sequence ?? 0), (int) $b->id])
            ->values();
        // UR-10: an operation of a plan made per consuming operation finds ITS makers by plan key
        $plan = ($op->plan_key !== null && is_array($order->sub_assembly_plan ?? null)) ? $order->sub_assembly_plan : null;

        $materials = collect();
        $subAssemblies = collect();

        foreach ($rows as $row) {
            $required = $this->requiredQty($op, $row);

            if ($row->child_bom_id) {
                $setKey = null;
                $borrowed = false;
                if ($plan !== null) {
                    $childBomId = (int) $row->child_bom_id;
                    $ownSetKey = $op->plan_key . '>' . $childBomId;
                    $setKey = $this->planSetKey($plan, (string) $op->plan_key, $childBomId);
                    $borrowed = $setKey !== $ownSetKey;
                    $producers = $siblings->filter(fn ($o) => $o->plan_key !== null && str_starts_with((string) $o->plan_key, $setKey . '/'));
                    $demand = $plan['demands'][$childBomId]['by'][$op->plan_key] ?? null;
                    // what THIS operation has to get made (its own share, even when the set also serves a later operation)
                    $toProduce = $demand !== null
                        ? min($required, (float) $demand[2])
                        : min($required, (float) $producers->max('target_produced_qty'));
                } else {
                    $producers = $siblings->filter(fn ($o) => (int) $o->source_product_id === (int) $row->material_id
                        && (int) $o->bom_level > (int) $op->bom_level
                        && (! $row->child_bom_id || (int) $o->source_bom_id === (int) $row->child_bom_id));
                    // every operation of the child BOM has the same target (the quantity to produce) — never add them up
                    $toProduce = min($required, (float) $producers->max('target_produced_qty'));
                }

                if ($producers->isNotEmpty()) {
                    $subAssemblies->push([
                        'row' => $row,
                        'product_id' => (int) $row->material_id,
                        'child_bom_id' => (int) $row->child_bom_id,
                        'consumer_key' => $op->plan_key,
                        'set_key' => $setKey,
                        'borrowed' => $borrowed,
                        'required' => $required,
                        'to_produce' => $toProduce,
                        'from_stock' => max(0.0, round($required - $toProduce, 4)),
                        'producers' => $producers->values(),
                    ]);

                    // The part covered from stock is still a material that has to be issued (PO-12 reservation).
                    $stockPart = max(0.0, round($required - $toProduce, 4));
                    if ($stockPart > 0) {
                        $materials->push(['row' => $row, 'product_id' => (int) $row->material_id, 'required' => $stockPart]);
                    }

                    continue;
                }
            }

            $materials->push(['row' => $row, 'product_id' => (int) $row->material_id, 'required' => $required]);
        }

        // one entry per product per operation (PX-03)
        $merged = $materials->groupBy('product_id')->map(function ($group) {
            $first = $group->first();

            return [
                'row' => $first['row'],
                'product_id' => $first['product_id'],
                'required' => round((float) $group->sum('required'), 4),
                'slots' => $group->count(),
                'is_alternative' => $group->contains(fn ($m) => (bool) $m['row']->is_alternative),
            ];
        })->values();

        return ['materials' => $merged, 'sub_assemblies' => $subAssemblies];
    }

    /**
     * UR-10: the key of the set of makers that supplies a consuming operation with a sub-assembly: its own set
     * ("<its key>><child bom>"), or — when the user chose "produce all at one level" — the set of the earlier operation that serves it.
     *
     * @param array<string, mixed> $plan  production_orders.sub_assembly_plan
     */
    public function planSetKey(array $plan, string $consumerKey, int $childBomId): string
    {
        foreach ((array) ($plan['merges'] ?? []) as $group) {
            if ((int) ($group['bom'] ?? 0) === $childBomId
                && ($group['owner'] ?? null) !== $consumerKey
                && in_array($consumerKey, (array) ($group['serves'] ?? []), true)) {
                return $group['owner'] . '>' . $childBomId;
            }
        }

        return $consumerKey . '>' . $childBomId;
    }

    /**
     * RV-03 (rule A, M-26 / M-27): how much of a sub-assembly its maker operations have PRODUCED for ONE consuming operation.
     *
     * Counted only once ALL the operations of the set are finished (completed / skipped / cancelled — the same test as the "parent waits"
     * rule, ProductionReadinessService::pendingSubAssemblies), because the parent can use nothing of it before that. What is counted is the
     * good quantity of the LAST operation of the set (the one that outputs the sub-assembly: the highest sequence among the set's own
     * operations), cumulative like the issued quantity of a material. When one set serves several operations ("produce all at one level")
     * the output is handed out to them in execution order, each up to its own share of the plan.
     *
     * @param array<string, mixed> $sub  one entry of resolve()['sub_assemblies']
     * @return array{made: float, done: int, total: int}  made = the quantity $consumer can use; done / total = finished / all operations of the set
     */
    public function madeFromSet(ProductionOrder $order, ProductionOrderOperation $consumer, array $sub): array
    {
        $producers = $sub['producers'];
        $total = $producers->count();
        $finished = [
            ProductionOrderOperation::STATUS_COMPLETED,
            ProductionOrderOperation::STATUS_SKIPPED,
            ProductionOrderOperation::STATUS_CANCELLED,
        ];
        $done = $producers->filter(fn ($o) => in_array($o->status, $finished, true))->count();
        if ($total === 0 || $done < $total) {
            return ['made' => 0.0, 'done' => $done, 'total' => $total];
        }

        // the operations of the set itself, not the sets that make ITS sub-assemblies: "<set key>/<routing operation id>"
        $setKey = $sub['set_key'] ?? null;
        $own = $setKey !== null
            ? $producers->filter(fn ($o) => str_starts_with((string) $o->plan_key, $setKey . '/') && ctype_digit(substr((string) $o->plan_key, strlen($setKey) + 1)))
            : $producers;
        $last = $own
            ->filter(fn ($o) => $o->status === ProductionOrderOperation::STATUS_COMPLETED)
            ->sort(fn ($a, $b) => [(int) $b->sequence, (int) $b->id] <=> [(int) $a->sequence, (int) $a->id])
            ->first();
        $output = $last ? max(0.0, (float) $last->quantity_produced) : 0.0;

        $plan = $order->sub_assembly_plan;
        $myKey = (string) $consumer->plan_key;
        $childBomId = (int) ($sub['child_bom_id'] ?? 0);
        if ($setKey !== null && is_array($plan) && $myKey !== '' && $childBomId > 0) {
            $ownerKey = substr($setKey, 0, (int) strrpos($setKey, '>'));
            $group = collect((array) ($plan['merges'] ?? []))->first(fn ($g) => (int) ($g['bom'] ?? 0) === $childBomId && ($g['owner'] ?? null) === $ownerKey);
            if ($group) {
                // one set serves several operations: hand the output out in the execution order recorded in the plan, each up to its share
                $by = (array) ($plan['demands'][$childBomId]['by'] ?? []);
                $position = array_flip((array) ($plan['demands'][$childBomId]['order'] ?? []));
                $served = array_values((array) ($group['serves'] ?? []));
                usort($served, fn ($a, $b) => ($position[$a] ?? PHP_INT_MAX) <=> ($position[$b] ?? PHP_INT_MAX));
                $left = $output;
                foreach ($served as $key) {
                    $give = max(0.0, min((float) ($by[$key][2] ?? 0.0), $left));
                    $left -= $give;
                    if ($key === $myKey) {
                        return ['made' => round($give, 4), 'done' => $done, 'total' => $total];
                    }
                }
            }
        }

        return ['made' => round(max(0.0, min((float) ($sub['to_produce'] ?? 0.0), $output)), 4), 'done' => $done, 'total' => $total];
    }

    /**
     * Pooled issued quantity of every product handed out to the operations in execution order.
     *
     * @return array<int, array<int, array{required: float, issued: float, short: float}>>  [operation_id][product_id]
     */
    public function poolAllocation(ProductionOrder $order, ?Collection $ops = null, ?array $resolvedMap = null): array
    {
        $ops ??= $this->orderedOperations($order);

        $remaining = ProductionOrderReservation::withoutGlobalScopes()
            ->where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->get()
            ->groupBy('product_id')
            ->map(fn ($g) => max(0.0, (float) $g->sum('quantity_issued')))
            ->all();

        $out = [];
        foreach ($ops as $op) {
            $resolved = $resolvedMap[$op->id] ?? $this->resolve($op, $ops);
            foreach ($resolved['materials'] as $m) {
                $pid = $m['product_id'];
                $alloc = min($m['required'], (float) ($remaining[$pid] ?? 0.0));
                $remaining[$pid] = max(0.0, (float) ($remaining[$pid] ?? 0.0) - $alloc);
                $out[$op->id][$pid] = [
                    'required' => $m['required'],
                    'issued' => round($alloc, 4),
                    'short' => max(0.0, round($m['required'] - $alloc, 4)),
                ];
            }
        }

        return $out;
    }

    /**
     * Everything the Operation Tracking drawer needs for one operation (PX-03 / PX-04).
     */
    public function describe(ProductionOrderOperation $op): array
    {
        $order = $op->order;
        $ops = $this->orderedOperations($order);
        $resolved = $this->resolve($op, $ops);
        $pool = $this->poolAllocation($order, $ops);

        $reservations = ProductionOrderReservation::withoutGlobalScopes()
            ->where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->get()
            ->groupBy('product_id');

        $others = [];
        foreach ($ops as $other) {
            if ($other->id === $op->id) {
                continue;
            }
            foreach ($this->resolve($other, $ops)['materials'] as $m) {
                $others[$m['product_id']][] = $other->operation_number ?: ('OP' . $other->sequence);
            }
        }

        $materials = $resolved['materials']->map(function ($m) use ($op, $reservations, $others, $pool) {
            $res = $reservations->get($m['product_id'], collect());
            $planned = (float) $res->sum('quantity_planned') + (float) $res->sum('quantity_additional_requested');
            $issued = (float) $res->sum('quantity_issued');
            $row = $m['row'];

            return [
                'product_id' => $m['product_id'],
                'name' => $row->material?->name ?? ('Product #' . $m['product_id']),
                'sku' => $row->material?->sku,
                'uom' => $row->material?->uom?->code,
                'required' => $m['required'],
                'covered_by_issue' => $pool[$op->id][$m['product_id']]['issued'] ?? 0.0,
                'order_planned' => round($planned, 4),
                'order_issued' => round($issued, 4),
                'order_pending' => max(0.0, round($planned - $issued, 4)),
                'shared_with' => array_values(array_unique($others[$m['product_id']] ?? [])),
                'is_alternative' => $m['is_alternative'],
                'slots' => $m['slots'],
            ];
        })->values();

        $subs = $resolved['sub_assemblies']->map(function ($s) {
            $row = $s['row'];

            return [
                'product_id' => $s['product_id'],
                'name' => $row->material?->name ?? ('Product #' . $s['product_id']),
                'sku' => $row->material?->sku,
                'uom' => $row->material?->uom?->code,
                'required' => $s['required'],
                'from_stock' => $s['from_stock'],
                'to_produce' => $s['to_produce'],
                'produced_by' => $s['producers']->map(fn ($p) => ($p->operation_number ?: 'OP' . $p->sequence) . ' (L' . $p->bom_level . ')')->unique()->values()->all(),
            ];
        })->values();

        return ['materials' => $materials->all(), 'sub_assemblies' => $subs->all()];
    }

    /**
     * describe() for EVERY operation of the order in one go: each operation is resolved once and the reservations are read once
     * (describe() per operation resolved every other operation again — the Component Plan did that for each row).
     *
     * @return array<int, array{materials: array, sub_assemblies: array}>  [operationId => same shape as describe()]
     */
    public function describeAll(ProductionOrder $order, ?Collection $ops = null, ?array $resolved = null): array
    {
        $ops ??= $this->orderedOperations($order);
        $resolved ??= $this->resolveAll($ops);
        $pool = $this->poolAllocation($order, $ops, $resolved);

        $reservations = ProductionOrderReservation::withoutGlobalScopes()
            ->where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->get()
            ->groupBy('product_id');

        // product => [operationId => operation number] of every operation that uses it
        $usedBy = [];
        foreach ($ops as $op) {
            foreach ($resolved[$op->id]['materials'] as $m) {
                $usedBy[$m['product_id']][$op->id] = $op->operation_number ?: ('OP' . $op->sequence);
            }
        }

        $out = [];
        foreach ($ops as $op) {
            $materials = $resolved[$op->id]['materials']->map(function ($m) use ($op, $reservations, $usedBy, $pool) {
                $res = $reservations->get($m['product_id'], collect());
                $planned = (float) $res->sum('quantity_planned') + (float) $res->sum('quantity_additional_requested');
                $issued = (float) $res->sum('quantity_issued');
                $row = $m['row'];

                return [
                    'product_id' => $m['product_id'],
                    'name' => $row->material?->name ?? ('Product #' . $m['product_id']),
                    'sku' => $row->material?->sku,
                    'uom' => $row->material?->uom?->code,
                    'required' => $m['required'],
                    'covered_by_issue' => $pool[$op->id][$m['product_id']]['issued'] ?? 0.0,
                    'order_planned' => round($planned, 4),
                    'order_issued' => round($issued, 4),
                    'order_pending' => max(0.0, round($planned - $issued, 4)),
                    'shared_with' => array_values(array_unique(array_values(array_diff_key($usedBy[$m['product_id']] ?? [], [$op->id => 1])))),
                    'is_alternative' => $m['is_alternative'],
                    'slots' => $m['slots'],
                ];
            })->values();

            $subs = $resolved[$op->id]['sub_assemblies']->map(function ($s) {
                $row = $s['row'];

                return [
                    'product_id' => $s['product_id'],
                    'name' => $row->material?->name ?? ('Product #' . $s['product_id']),
                    'sku' => $row->material?->sku,
                    'uom' => $row->material?->uom?->code,
                    'required' => $s['required'],
                    'from_stock' => $s['from_stock'],
                    'to_produce' => $s['to_produce'],
                    'produced_by' => $s['producers']->map(fn ($p) => ($p->operation_number ?: 'OP' . $p->sequence) . ' (L' . $p->bom_level . ')')->unique()->values()->all(),
                ];
            })->values();

            $out[$op->id] = ['materials' => $materials->all(), 'sub_assemblies' => $subs->all()];
        }

        return $out;
    }

    /**
     * PO-04: sum over the ACTIVE STANDARD warehouses of the order's branch of max(0, quantity - reserved_qty).
     * When the order has no branch, the tenant's active standard warehouses are used.
     */
    public function branchAvailableStock(int $productId, ProductionOrder $order): float
    {
        // The SAME function the Production Order create screen uses (subcontractor / virtual warehouses are the only exclusions),
        // so readiness, MRP and the create screen can never show different stock for one material.
        $map = app(ProductionOrderEngineeringService::class)->getBranchAvailableStockMap(
            (int) $order->tenant_id,
            $order->branch_id ? (int) $order->branch_id : null,
            [$productId]
        );

        return (float) ($map[$productId] ?? 0.0);
    }

    /**
     * Estimated quantity of each product already used by the output produced so far
     * (good + rejected + scrapped of every operation x per-unit material ratio). Used to return only the
     * unconsumed part of the issued material when an order is cancelled (PX-20).
     *
     * @return array<int, float> product_id => consumed
     */
    public function consumedEstimate(ProductionOrder $order): array
    {
        $ops = $this->orderedOperations($order);
        $consumed = [];
        foreach ($ops as $op) {
            $target = (float) ($op->target_produced_qty > 0 ? $op->target_produced_qty : $order->quantity_ordered);
            if ($target <= 0) {
                continue;
            }
            $done = (float) $op->quantity_produced + (float) $op->quantity_rejected + (float) $op->quantity_scrapped;
            if ($done <= 0) {
                continue;
            }
            foreach ($this->resolve($op, $ops)['materials'] as $m) {
                $consumed[$m['product_id']] = ($consumed[$m['product_id']] ?? 0.0) + $m['required'] * min(1.0, $done / $target);
            }
        }

        return array_map(fn ($v) => round($v, 4), $consumed);
    }

    private function bomBase(int $bomId): float
    {
        if ($bomId <= 0) {
            return 1.0;
        }

        return $this->bomBaseCache[$bomId] ??= (function () use ($bomId) {
            $base = (float) (ProductionBom::withoutGlobalScopes()->whereKey($bomId)->value('base_quantity') ?? 1.0);

            return $base > 0 ? $base : 1.0;
        })();
    }
}
