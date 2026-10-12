<?php

namespace App\Domains\Projects\Listeners;

use App\Domains\Production\Events\ProductionOrderCompleted;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Projects\Models\ActivityLog;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Services\MilestoneService;

class SyncProductionTaskCompletionListener
{
    public function __construct(
        protected MilestoneService $milestoneService
    ) {}

    public function handle(ProductionOrderCompleted $event): void
    {
        $order = $event->order;

        // Constraint 4: Production Orders remain fully functional when no PM links exist
        if (! $order->project_task_id) {
            return;
        }

        /** @var Task|null $task */
        $task = Task::query()
            ->where('tenant_id', $order->tenant_id)
            ->find($order->project_task_id);

        if (! $task || $task->status === Task::STATUS_COMPLETED || $task->status === Task::STATUS_CANCELLED) {
            return;
        }

        // Constraint 3: Consume Production's authoritative completion/disposition rules
        $quantityProduced = (float) ($order->quantity_produced ?? 0);
        $quantityOrdered = (float) ($order->quantity_ordered ?? 0);
        $isQuantitySatisfied = ($quantityOrdered <= 0) || ($quantityProduced >= ($quantityOrdered - 0.0001));

        // Check if there are other Production Orders for the same task that are not yet completed
        $uncompletedSiblingsExist = ProductionOrder::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('project_task_id', $task->id)
            ->where('id', '!=', $order->id)
            ->where('status', '!=', ProductionOrder::STATUS_COMPLETED)
            ->exists();

        if ($isQuantitySatisfied && ! $uncompletedSiblingsExist) {
            $task->status = Task::STATUS_COMPLETED;
            $task->completed_at = now();
            $task->save();

            // Recalculate milestone progress if task belongs to a milestone
            if ($task->milestone_id) {
                $this->milestoneService->recalculateProgress($task->milestone_id);
            }

            // Log activity
            ActivityLog::create([
                'tenant_id' => $task->tenant_id,
                'company_id' => $task->company_id,
                'branch_id' => $task->branch_id,
                'project_id' => $task->project_id,
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'event_type' => 'production.completed',
                'title' => 'Task Completed via Production Order',
                'description' => "Task '{$task->title}' automatically completed following successful completion of Production Order {$order->order_number} ({$quantityProduced}/{$quantityOrdered} units).",
                'triggered_by' => $order->completed_by ?: ($task->assignee_id ?: $task->project?->owner_id),
                'metadata' => [
                    'production_order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'quantity_ordered' => $quantityOrdered,
                    'quantity_produced' => $quantityProduced,
                ],
            ]);
        } else {
            // Shortfall or uncompleted sibling orders: task remains in progress, log warning note
            $reason = ! $isQuantitySatisfied
                ? "quantity shortfall ({$quantityProduced} produced vs {$quantityOrdered} ordered)"
                : "pending sibling production orders for this task";

            ActivityLog::create([
                'tenant_id' => $task->tenant_id,
                'company_id' => $task->company_id,
                'branch_id' => $task->branch_id,
                'project_id' => $task->project_id,
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'event_type' => 'production.shortfall_warning',
                'title' => 'Production Order Completed with Incomplete Task Fulfillment',
                'description' => "Production Order {$order->order_number} was completed, but Task '{$task->title}' remains In Progress due to {$reason}.",
                'triggered_by' => $order->completed_by ?: ($task->assignee_id ?: $task->project?->owner_id),
                'metadata' => [
                    'production_order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'quantity_ordered' => $quantityOrdered,
                    'quantity_produced' => $quantityProduced,
                    'uncompleted_siblings' => $uncompletedSiblingsExist,
                ],
            ]);
        }
    }
}
