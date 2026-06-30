<?php

namespace App\Domains\Production\Listeners;

use App\Domains\Production\Events\RoutingApproved;
use Illuminate\Support\Facades\Log;

/**
 * UpdateCapacityPlanning — Stub Listener
 *
 * Fires when a routing is approved.
 * Future: triggers capacity planning recalculation based on new routing operations,
 * work center loads, and machine availability.
 */
class UpdateCapacityPlanning
{
    public function handle(RoutingApproved $event): void
    {
        Log::info(
            "[CAPACITY] Routing approved — capacity planning update pending.",
            [
                'routing_id'     => $event->routing->id,
                'routing_number' => $event->routing->routing_number,
                'product_id'     => $event->routing->product_id,
            ]
        );
        // Future: CapacityPlanningService::recalculate($event->routing)
    }
}
