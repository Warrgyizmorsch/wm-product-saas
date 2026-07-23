<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionReworkOperation;
use App\Domains\Production\Models\ProductionReworkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReworkService
{
    public function __construct(
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Create a Rework Order and operations.
     */
    public function createReworkOrder(int $tenantId, int $ncrId, array $data): ProductionReworkOrder
    {
        return DB::transaction(function () use ($tenantId, $ncrId, $data) {
            $rework = ProductionReworkOrder::create([
                'tenant_id' => $tenantId,
                'rework_number' => 'RWK-'.strtoupper(uniqid()),
                'ncr_id' => $ncrId,
                'original_production_order_id' => $data['original_production_order_id'],
                'status' => 'draft',
                'cost_estimate' => $data['cost_estimate'] ?? 150.00,
            ]);

            // Add standard default rework operation if operations list empty
            $operations = $data['operations'] ?? [
                ['sequence' => 10, 'name' => 'Disassemble and Inspect Defect', 'work_center_id' => $data['work_center_id']],
                ['sequence' => 20, 'name' => 'Refabricate Defective Section', 'work_center_id' => $data['work_center_id']],
            ];

            foreach ($operations as $op) {
                ProductionReworkOperation::create([
                    'tenant_id' => $tenantId,
                    'rework_order_id' => $rework->id,
                    'sequence' => $op['sequence'],
                    'name' => $op['name'],
                    'work_center_id' => $op['work_center_id'],
                    'machine_id' => $op['machine_id'] ?? null,
                    'status' => 'waiting',
                ]);
            }

            $this->eventService->writeEvent($tenantId, [
                'production_order_id' => $rework->original_production_order_id,
                'event_type'          => 'REWORK_CREATED',
                'title'               => 'Rework Order Created',
                'description'         => "Rework Order {$rework->rework_number} created for NCR #{$ncrId}.",
                'severity'            => 'warning',
                'event_source'        => 'ReworkService',
            ]);

            return $rework;
        });
    }

    /**
     * Start rework operation.
     */
    public function startOperation(int $reworkOpId, ?int $tenantId = null): void
    {
        $op = ProductionReworkOperation::query()
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->findOrFail($reworkOpId);

        if ($op->status === 'completed') {
            throw new \InvalidArgumentException('Completed rework operations cannot be restarted.');
        }

        if ($op->status === 'running') {
            return;
        }

        $op->update([
            'status' => 'running',
            'actual_start' => Carbon::now(),
        ]);

        $rework = $op->reworkOrder;
        if ($rework->status === 'draft') {
            $rework->update(['status' => 'running']);
        }

        $this->eventService->writeEvent($op->tenant_id, [
            'production_order_id' => $rework->original_production_order_id,
            'machine_id' => $op->machine_id,
            'event_type' => 'Rework Started',
            'title' => 'Rework Execution Triggered',
            'description' => "Rework operation {$op->name} has started on order #{$rework->original_production_order_id}.",
            'severity' => 'info',
            'event_source' => 'ReworkService',
        ]);
    }

    /**
     * Complete rework operation and update actual accumulated cost.
     */
    public function completeOperation(int $reworkOpId, array $data, ?int $tenantId = null): void
    {
        DB::transaction(function () use ($reworkOpId, $data, $tenantId) {
            $op = ProductionReworkOperation::query()
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->findOrFail($reworkOpId);

            if ($op->status === 'completed') {
                return;
            }

            if ($op->status !== 'running') {
                throw new \InvalidArgumentException('Only running rework operations can be completed.');
            }

            $start = $op->actual_start ?? Carbon::now()->subMinutes(30);
            $end = Carbon::now();

            $actualMinutes = (float) $start->diffInMinutes($end);
            $hours = $actualMinutes / 60.0;

            $op->update([
                'status' => 'completed',
                'actual_end' => $end,
                'setup_time_actual' => $data['setup_time_actual'] ?? 0.00,
                'processing_time_actual' => $hours,
            ]);

            // Calculate cost increment: labor ($35/hr) + machine ($50/hr)
            $laborRate = 35.00;
            $machineRate = 50.00;

            $addedCost = ($hours * $laborRate) + ($hours * $machineRate);

            $rework = $op->reworkOrder;
            $rework->update([
                'actual_cost' => $rework->actual_cost + $addedCost,
                'labor_hours_actual' => $rework->labor_hours_actual + $hours,
                'machine_hours_actual' => $rework->machine_hours_actual + ($op->machine_id ? $hours : 0.00),
            ]);

            // If all operations are complete, mark rework order as completed
            $incomplete = ProductionReworkOperation::where('rework_order_id', $rework->id)
                ->where('status', '!=', 'completed')
                ->exists();

            if (! $incomplete) {
                $rework->update(['status' => 'completed']);

                // Auto-resolve NCR
                $ncr = $rework->ncr;
                if ($ncr && $ncr->status !== 'closed') {
                    $ncr->update([
                        'status' => 'closed',
                        'closed_by' => auth()->id(),
                        'closed_at' => Carbon::now(),
                        'esignature_closed' => hash('sha256', (auth()->id() ?? 'system').$ncr->id.'closed'.now()->timestamp),
                    ]);

                    $this->eventService->writeEvent($ncr->tenant_id, [
                        'production_order_id' => $ncr->production_order_id,
                        'event_type' => 'NCR Closed',
                        'title' => 'Non-Conformance Resolved',
                        'description' => "NCR {$ncr->ncr_number} automatically closed upon Rework completion.",
                        'severity' => 'success',
                        'event_source' => 'ReworkService',
                    ]);
                }

                // Update original ProductionOrderRework status
                $orderRework = \App\Domains\Production\Models\ProductionOrderRework::where('production_order_id', $rework->original_production_order_id)
                    ->where('production_order_operation_id', $ncr?->production_order_operation_id)
                    ->where('status', '!=', 'completed')
                    ->first();

                if ($orderRework) {
                    $orderRework->update(['status' => 'completed']);
                    $reworkQty = $orderRework->quantity;

                    // Update operation-level counts
                    $originalOp = \App\Domains\Production\Models\ProductionOrderOperation::find($ncr->production_order_operation_id);
                    if ($originalOp) {
                        $originalOp->quantity_rejected = max(0.0000, $originalOp->quantity_rejected - $reworkQty);
                        $originalOp->quantity_produced += $reworkQty;
                        $originalOp->save();
                    }

                    // Update WIP counts
                    $wip = \App\Domains\Production\Models\ProductionWip::where('production_order_id', $rework->original_production_order_id)->first();
                    if ($wip) {
                        $wip->rejected_quantity = max(0.0000, $wip->rejected_quantity - $reworkQty);
                        $wip->completed_quantity += $reworkQty;
                        $wip->save();
                    }
                    
                    // Update original production order's quantity_rejected
                    $originalOrder = $rework->originalOrder;
                    if ($originalOrder) {
                        $originalOrder->quantity_rejected = max(0.0000, $originalOrder->quantity_rejected - $reworkQty);
                        $originalOrder->save();
                    }
                }

                $this->eventService->writeEvent($op->tenant_id, [
                    'production_order_id' => $rework->original_production_order_id,
                    'event_type' => 'Rework Completed',
                    'title' => 'Rework Order Finalized',
                    'description' => "Rework order {$rework->rework_number} completed. Actual Rework Cost: \${$rework->actual_cost}.",
                    'severity' => 'success',
                    'event_source' => 'ReworkService',
                ]);
            }
        });
    }
}
