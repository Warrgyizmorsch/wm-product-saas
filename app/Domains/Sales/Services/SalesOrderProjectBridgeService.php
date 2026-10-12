<?php

namespace App\Domains\Sales\Services;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Services\MilestoneService;
use App\Domains\Projects\Services\ProjectService;
use App\Domains\Projects\Services\TaskListService;
use App\Domains\Projects\Services\TaskService;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesOrderProjectBridgeService
{
    public function __construct(
        protected ProjectService $projectService,
        protected MilestoneService $milestoneService,
        protected TaskListService $taskListService,
        protected TaskService $taskService
    ) {}

    /**
     * Convert a Confirmed Sales Order to a delivery Project with Milestones and Tasks.
     *
     * @param SalesOrder $order
     * @param array $customData
     * @return Project
     * @throws InvalidArgumentException
     */
    public function convertSalesOrderToProject(SalesOrder $order, array $customData = []): Project
    {
        // Enforce canonical status requirement: only Confirmed orders can convert
        if ($order->status !== 'Confirmed') {
            throw new InvalidArgumentException(__('sales.errors.only_confirmed_so_can_convert', [
                'status' => $order->status
            ]) ?: "Only Confirmed Sales Orders can be converted to a Delivery Project. Current status: {$order->status}");
        }

        $order->loadMissing(['items', 'quotation']);

        return DB::transaction(function () use ($order, $customData) {
            $dealId = null;
            $contactId = null;

            if ($order->quotation) {
                $dealId = $order->quotation->deal_id ?? null;
                $contactId = $order->quotation->contact_id ?? null;
            }

            $userId = auth()->id() ?: ($order->sales_person_id ?: 1);

            $projectData = [
                'tenant_id' => $order->tenant_id,
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'customer_id' => $order->customer_id,
                'sales_order_id' => $order->id,
                'crm_deal_id' => $customData['crm_deal_id'] ?? $dealId,
                'client_contact_id' => $customData['client_contact_id'] ?? $contactId,
                'cost_center_id' => $customData['cost_center_id'] ?? null,
                'name' => $customData['name'] ?? ($order->customer ? "{$order->customer->name} - {$order->sales_order_number}" : "Delivery: {$order->sales_order_number}"),
                'budget_amount' => $customData['budget_amount'] ?? $order->total_amount,
                'budget_type' => $customData['budget_type'] ?? 'Fixed',
                'billing_method' => $customData['billing_method'] ?? 'Milestone Based',
                'start_date' => $customData['start_date'] ?? ($order->order_date ?: now()),
                'end_date' => $customData['end_date'] ?? ($order->shipment_date ?: now()->addMonth()),
                'owner_id' => $customData['owner_id'] ?? $userId,
                'manager_id' => $customData['manager_id'] ?? $userId,
                'priority' => $customData['priority'] ?? Project::PRIORITY_MEDIUM,
                'status' => $customData['status'] ?? Project::STATUS_ACTIVE,
                'description' => $customData['description'] ?? "Delivery Project automatically generated from Sales Order {$order->sales_order_number}.",
            ];

            // 1. Create Project
            $project = $this->projectService->create($projectData);

            // 2. Create Default Task List
            $taskList = $this->taskListService->create($project, [
                'name' => 'Fulfillment & Delivery',
                'description' => "Tasks linked to deliverables for Sales Order {$order->sales_order_number}",
                'owner_id' => $project->manager_id ?: $project->owner_id,
            ]);

            // 3. For each SO Item, create Milestone and corresponding Task
            if ($order->items && $order->items->isNotEmpty()) {
                foreach ($order->items as $item) {
                    $milestoneName = $item->item_name ?: "Item #{$item->id}";
                    $milestone = $this->milestoneService->create($project, [
                        'sales_order_item_id' => $item->id,
                        'name' => "Deliver: {$milestoneName}",
                        'description' => $item->description ?: "Fulfillment for {$milestoneName} (Qty: {$item->quantity})",
                        'billing_amount' => $item->amount,
                        'start_date' => $project->start_date,
                        'due_date' => $order->shipment_date ?: $project->end_date,
                        'status' => Milestone::STATUS_ACTIVE,
                        'owner_id' => $project->manager_id ?: $project->owner_id,
                    ]);

                    $this->taskService->create($project, [
                        'milestone_id' => $milestone->id,
                        'task_list_id' => $taskList->id,
                        'title' => "Fulfill {$milestoneName}",
                        'description' => "Produce / procure and prepare delivery for {$item->quantity} units of {$milestoneName}.",
                        'priority' => 'Medium',
                        'status' => Task::STATUS_OPEN,
                        'assignee_id' => $project->manager_id ?: $project->owner_id,
                        'start_date' => $project->start_date,
                        'due_date' => $milestone->due_date,
                    ]);
                }
            }

            return $project;
        });
    }
}
