<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * UR-10 (M-22 .. M-24) — "produce all at one level".
 *
 * When one sub-assembly is needed by several operations, every operation gets its own set of maker operations above it, for its own quantity.
 * Here the user can choose, at any operation except the last one that needs it, to make ALL of it at that level: that operation's set then
 * also makes what the later operations need (quantities added up, the later sets are removed). The choice can be undone, as long as the
 * order is not scheduled (and no work has started).
 *
 * The state lives in production_orders.sub_assembly_plan ("merges": [{bom, owner, serves[]}]) and in the operations (plan_key). A change
 * re-plans the sub-assembly tree with the stock the order was first planned with and updates the operations in place.
 */
class SubAssemblySetService
{
    public function __construct(
        private readonly ProductionOrderEngineeringService $engineering,
        private readonly ProductionOrderService $orders,
    ) {
    }

    /** The order was planned per consuming operation (orders created before that keep their old, shared sets). */
    public function isPlanned(ProductionOrder $order): bool
    {
        return is_array($order->sub_assembly_plan);
    }

    /**
     * Why the choices can no longer be changed — null when they can.
     */
    public function lockReason(ProductionOrder $order, ?Collection $ops = null): ?string
    {
        if (!$this->isPlanned($order)) {
            return __('production.sa_lock_old_order');
        }

        if (!in_array($order->status, [ProductionOrder::STATUS_DRAFT, ProductionOrder::STATUS_LOCKED, ProductionOrder::STATUS_RELEASED], true)) {
            return __('production.sa_lock_status', ['status' => str_replace('_', ' ', (string) $order->status)]);
        }

        $scheduled = $order->relationLoaded('schedules')
            ? $order->schedules->contains(fn ($s) => $s->status !== ProductionSchedule::STATUS_CANCELLED)
            : $order->schedules()->where('status', '!=', ProductionSchedule::STATUS_CANCELLED)->exists();
        if ($scheduled) {
            return __('production.sa_lock_scheduled');
        }

        $ops ??= $order->relationLoaded('operations') ? $order->operations : $order->operations()->get();
        $started = $ops->contains(fn ($o) => in_array($o->status, [
                ProductionOrderOperation::STATUS_RUNNING,
                ProductionOrderOperation::STATUS_PAUSED,
                ProductionOrderOperation::STATUS_COMPLETED,
                ProductionOrderOperation::STATUS_SKIPPED,
            ], true)
            || (float) $o->quantity_produced > 0
            || (float) ($o->quantity_claimed ?? 0) > 0);
        if ($started) {
            return __('production.sa_lock_started');
        }

        return null;
    }

    /**
     * What the drawer of every operation needs to show about "produce all at one level":
     * [consumer operation id => [child BOM id => [can_merge, can_split, later[], serves[], borrowed_from_op_id, lock_reason]]]
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function actions(ProductionOrder $order, Collection $ops): array
    {
        if (!$this->isPlanned($order)) {
            return [];
        }

        $plan = $order->sub_assembly_plan;
        $byKey = $ops->filter(fn ($o) => $o->plan_key !== null)->keyBy('plan_key');
        $locked = $this->lockReason($order, $ops);
        $materials = app(OperationMaterialService::class);

        $label = fn (string $key) => $byKey->get($key)?->contextLabel() ?? $key;
        $out = [];

        foreach ((array) ($plan['demands'] ?? []) as $childBomId => $record) {
            $childBomId = (int) $childBomId;
            $by = (array) ($record['by'] ?? []);
            // the operations that need this sub-assembly made, in execution order
            $consumers = array_values(array_filter((array) ($record['order'] ?? []), fn ($k) => (float) ($by[$k][2] ?? 0) > 0 && $byKey->has($k)));

            foreach ($consumers as $i => $consumerKey) {
                $consumer = $byKey->get($consumerKey);
                $ownSet = $consumerKey . '>' . $childBomId;
                $setKey = $materials->planSetKey($plan, $consumerKey, $childBomId);

                if ($setKey !== $ownSet) {
                    // this operation is served by an earlier operation's set
                    $ownerKey = substr($setKey, 0, (int) strrpos($setKey, '>'));
                    $out[$consumer->id][$childBomId] = [
                        'can_merge' => false,
                        'can_split' => false,
                        'later' => [],
                        'serves' => [],
                        'borrowed_from_op_id' => $byKey->get($ownerKey)?->id,
                        'borrowed_from_label' => $label($ownerKey),
                        'lock_reason' => $locked,
                    ];
                    continue;
                }

                $group = collect((array) ($plan['merges'] ?? []))->first(fn ($g) => (int) ($g['bom'] ?? 0) === $childBomId && ($g['owner'] ?? null) === $consumerKey);
                $served = array_values(array_diff((array) ($group['serves'] ?? []), [$consumerKey]));

                // later operations that still have their OWN set — these are what "produce all here" would take over
                $later = [];
                foreach (array_slice($consumers, $i + 1) as $laterKey) {
                    if ($materials->planSetKey($plan, $laterKey, $childBomId) === $laterKey . '>' . $childBomId && !in_array($laterKey, $served, true)) {
                        $later[] = ['op_id' => $byKey->get($laterKey)->id, 'label' => $label($laterKey), 'qty' => (float) ($by[$laterKey][2] ?? 0)];
                    }
                }

                $out[$consumer->id][$childBomId] = [
                    'can_merge' => $later !== [],
                    'can_split' => $served !== [],
                    'later' => $later,
                    'serves' => array_map(fn ($k) => ['op_id' => $byKey->get($k)?->id, 'label' => $label($k), 'qty' => (float) ($by[$k][2] ?? 0)], $served),
                    'borrowed_from_op_id' => null,
                    'borrowed_from_label' => null,
                    'lock_reason' => $locked,
                ];
            }
        }

        return $out;
    }

    /**
     * "Produce all here": the set of this operation also makes what ALL later operations need.
     */
    public function merge(ProductionOrder $order, int $ownerOperationId, int $childBomId, ?int $userId = null): void
    {
        $this->assertCanChange($order);
        $owner = $this->consumerOperation($order, $ownerOperationId);
        $plan = $order->sub_assembly_plan;
        $record = $plan['demands'][$childBomId] ?? null;
        if (!$record) {
            throw new InvalidArgumentException(__('production.sa_unknown_sub_assembly'));
        }

        $by = (array) ($record['by'] ?? []);
        $consumers = array_values(array_filter((array) ($record['order'] ?? []), fn ($k) => (float) ($by[$k][2] ?? 0) > 0));
        $index = array_search($owner->plan_key, $consumers, true);
        if ($index === false) {
            throw new InvalidArgumentException(__('production.sa_unknown_sub_assembly'));
        }

        $materials = app(OperationMaterialService::class);
        if ($materials->planSetKey($plan, (string) $owner->plan_key, $childBomId) !== $owner->plan_key . '>' . $childBomId) {
            throw new InvalidArgumentException(__('production.sa_not_an_owner'));
        }

        $later = array_slice($consumers, $index + 1);
        if ($later === []) {
            throw new InvalidArgumentException(__('production.sa_nothing_to_merge'));
        }

        // the new group replaces the owner's own group and absorbs the groups of the later operations
        $merges = array_values(array_filter(
            (array) ($plan['merges'] ?? []),
            fn ($g) => (int) ($g['bom'] ?? 0) !== $childBomId || (($g['owner'] ?? null) !== $owner->plan_key && !in_array($g['owner'] ?? null, $later, true))
        ));
        $merges[] = ['bom' => $childBomId, 'owner' => $owner->plan_key, 'serves' => array_merge([$owner->plan_key], $later)];

        $this->replan($order, $merges, $userId);
    }

    /**
     * "Produce separately": every operation that this set served gets its own set again.
     */
    public function split(ProductionOrder $order, int $ownerOperationId, int $childBomId, ?int $userId = null): void
    {
        $this->assertCanChange($order);
        $owner = $this->consumerOperation($order, $ownerOperationId);
        $plan = $order->sub_assembly_plan;

        $merges = array_values(array_filter(
            (array) ($plan['merges'] ?? []),
            fn ($g) => !((int) ($g['bom'] ?? 0) === $childBomId && ($g['owner'] ?? null) === $owner->plan_key)
        ));
        if (count($merges) === count((array) ($plan['merges'] ?? []))) {
            throw new InvalidArgumentException(__('production.sa_nothing_to_split'));
        }

        $this->replan($order, $merges, $userId);
    }

    private function assertCanChange(ProductionOrder $order): void
    {
        $reason = $this->lockReason($order);
        if ($reason !== null) {
            throw new InvalidArgumentException($reason);
        }
    }

    private function consumerOperation(ProductionOrder $order, int $operationId): ProductionOrderOperation
    {
        $op = ProductionOrderOperation::where('production_order_id', $order->id)->find($operationId);
        if (!$op || $op->plan_key === null) {
            throw new InvalidArgumentException(__('production.sa_unknown_sub_assembly'));
        }

        return $op;
    }

    /**
     * Plan the sub-assembly tree again with the choices in $merges (same stock as at creation) and update the operations in place.
     *
     * @param array<int, array{bom: int, owner: string, serves: array<int, string>}> $merges
     */
    private function replan(ProductionOrder $order, array $merges, ?int $userId): void
    {
        $plan = (array) $order->sub_assembly_plan;
        $existing = ProductionOrderOperation::where('production_order_id', $order->id)->whereNotNull('plan_key')->get();
        $rootOps = $existing->filter(fn ($o) => !str_contains((string) $o->plan_key, '>'));

        $bom = $order->bom_id ? ProductionBom::withoutGlobalScopes()->where('tenant_id', $order->tenant_id)->find($order->bom_id) : null;
        if (!$bom || $rootOps->isEmpty()) {
            throw new InvalidArgumentException(__('production.sa_unknown_sub_assembly'));
        }

        $chosenOpIds = $rootOps->pluck('routing_operation_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
        $chosenMaterialIds = $rootOps->flatMap(fn ($o) => (array) $o->selected_routing_material_ids)->map(fn ($v) => (int) $v)->unique()->values()->all();

        $planned = $this->engineering->computeMultiLevelPlan(
            $bom,
            $chosenOpIds,
            $chosenMaterialIds,
            (float) $order->quantity_ordered,
            (int) $order->tenant_id,
            $order->branch_id ? (int) $order->branch_id : null,
            $merges,
            (array) ($plan['stock'] ?? [])
        );

        // a subcontracted operation may already have a vendor document — such sets are not merged away silently
        $newKeys = collect($planned['snapshot_tree_operations'])->pluck('op_key')->all();
        $dropped = $existing->filter(fn ($o) => !in_array($o->plan_key, $newKeys, true));
        if ($dropped->contains(fn ($o) => (bool) $o->is_external)) {
            throw new InvalidArgumentException(__('production.sa_external_involved'));
        }

        DB::transaction(function () use ($order, $planned, $merges, $userId) {
            $this->orders->syncPlannedTree($order, $planned['snapshot_tree_operations'], $userId, [
                'stock' => $planned['stock_snapshot'],
                'demands' => $planned['demands'],
                'merges' => $merges,
            ], true);
        });
    }
}
