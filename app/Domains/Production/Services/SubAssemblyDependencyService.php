<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperationDependency;

/**
 * UR-07 / scheduler: an operation that needs a sub-assembly made INSIDE its order waits for ALL the operations that make it —
 * not only for the last one. The order snapshot only stores "last operation of the child BOM -> consuming operation"
 * (dependency type `cross_assembly`, which the WIP / consumption code relies on). This service adds the missing links for the other
 * making operations as type `sub_assembly` (ordering / scheduling only; WIP code ignores that type), so the scheduler, the planner
 * moves, the pre-release check and the shop-floor start gate all see the same, complete dependency list.
 *
 * Idempotent: run it as often as needed (order creation, every schedule generation).
 */
class SubAssemblyDependencyService
{
    public const TYPE = 'sub_assembly';

    /** @return int number of links created */
    public function ensure(ProductionOrder $order): int
    {
        // UR-10: an order planned per consuming operation (sub_assembly_plan) already has exactly the links its plan describes —
        // linking every maker to every consumer here would undo "one set of makers per operation".
        if ($order->sub_assembly_plan !== null) {
            return 0;
        }

        // the order object may hold operations loaded before they were created / changed
        $order->unsetRelation('operations');

        $svc = app(OperationMaterialService::class);
        $tree = $svc->operationTree($order);

        $created = 0;
        foreach ($tree['children'] as $parentId => $groups) {
            foreach ($groups as $group) {
                foreach ($group['op_ids'] as $makerId) {
                    $dependency = ProductionOrderOperationDependency::withoutGlobalScopes()->firstOrCreate(
                        ['operation_id' => (int) $parentId, 'predecessor_operation_id' => (int) $makerId],
                        ['tenant_id' => $order->tenant_id, 'production_order_id' => $order->id, 'dependency_type' => self::TYPE]
                    );
                    if ($dependency->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        }

        if ($created > 0) {
            $order->unsetRelation('operations');
        }

        return $created;
    }
}
