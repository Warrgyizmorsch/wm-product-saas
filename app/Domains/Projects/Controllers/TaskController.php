<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Requests\AssignTaskRequest;
use App\Domains\Projects\Requests\StoreTaskRequest;
use App\Domains\Projects\Requests\UpdateTaskRequest;
use App\Domains\Projects\Requests\UpdateTaskStatusRequest;
use App\Domains\Projects\Services\ActivityLogService;
use App\Domains\Projects\Services\ProjectMemberService;
use App\Domains\Projects\Services\TaskService;
use App\Domains\Projects\Support\TaskDrawerPayload;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Access\AccessService;
use App\Support\InlineEdit\HandlesInlineFieldUpdates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskController extends Controller
{
    use BuildsBackUrl;
    use HandlesInlineFieldUpdates;

    public function __construct(
        private readonly TaskService $tasks,
        private readonly ActivityLogService $activity,
        private readonly ProjectMemberService $members,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $filters = $request->only(['search', 'status', 'priority', 'project_id', 'assignee_id']);

        $query = Task::query()
            ->with(['project', 'taskList', 'milestone', 'assignee', 'reviewer'])
            ->where('tenant_id', require_tenant_id());

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('task_code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (!empty($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        $tasks = $query->latest('id')->paginate(15)->withQueryString();
        $statuses = Task::STATUSES;
        $priorities = Task::PRIORITIES;
        $projects = Project::query()->where('tenant_id', require_tenant_id())->orderBy('name')->get();
        $assignees = User::query()->where('tenant_id', require_tenant_id())->orderBy('name')->get();

        return view('modules.projects.tasks.index', compact('tasks', 'filters', 'statuses', 'priorities', 'projects', 'assignees'));
    }

    /**
     * The Task Workspace: the only primary way to view a task. Row clicks
     * across the module navigate here instead of opening the retired drawer.
     */
    public function show(Project $project, Task $task, Request $request): View
    {
        $this->authorize('view', $task);

        $task->load([
            'taskList',
            'milestone.salesOrderItem.product',
            'assignee',
            'reviewer',
            'subTasks.assignee',
            'dependencies.dependsOn',
            'dependents.task',
            'documents.uploader',
            'productionOrders',
            'purchaseRequisitionItems.requisition',
            'purchaseRequisitionItems.product',
        ]);

        $canManageTask = auth()->user()->can('update', $task);
        $tenantId = require_tenant_id();

        $linkedSalesOrderItem = $task->milestone?->salesOrderItem;
        $linkedProduct = $linkedSalesOrderItem?->product;

        if ($linkedSalesOrderItem && !$linkedProduct && $linkedSalesOrderItem->item_name) {
            $linkedProduct = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)
                ->where('name', $linkedSalesOrderItem->item_name)
                ->first();
        }

        $isManufacturedItem = false;
        $isTradeItem = false;

        if ($linkedProduct) {
            $supplierMethod = strtolower(trim($linkedProduct->supplier_method ?? ''));
            if ($supplierMethod === 'manufacture') {
                $isManufacturedItem = true;
            } else {
                $isTradeItem = true;
            }
        }

        $canViewProduction = $canManageTask && $this->canViewProduction(auth()->user(), $tenantId);
        $availableProductionOrders = collect();
        if ($canViewProduction) {
            $poQuery = \App\Domains\Production\Models\ProductionOrder::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('project_task_id')
                ->whereNotIn('status', [\App\Domains\Production\Models\ProductionOrder::STATUS_CLOSED, \App\Domains\Production\Models\ProductionOrder::STATUS_CANCELLED]);

            if ($project->company_id) {
                $poQuery->where(function ($q) use ($project) {
                    $q->whereNull('company_id')->orWhere('company_id', $project->company_id);
                });
            }
            if ($project->branch_id) {
                $poQuery->where(function ($q) use ($project) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $project->branch_id);
                });
            }

            $availableProductionOrders = $poQuery->latest('id')
                ->limit(30)
                ->get(['id', 'order_number', 'status', 'quantity_ordered', 'quantity_produced']);
        }

        $availablePrItems = collect();
        if ($canManageTask && $this->canViewPurchase(auth()->user(), $tenantId)) {
            $availablePrItems = \App\Domains\Purchase\Models\PurchaseRequisitionItem::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('project_task_id')
                ->with(['requisition', 'product'])
                ->latest('id')
                ->limit(30)
                ->get();
        }

        $nextAction = $this->tasks->deriveNextAction($task);

        $activityPerPage = 15;
        $activityPage = max((int) $request->query('activity_page', 1), 1);
        $allActivities = $this->activity->forTask($task, 200);
        $activityTotal = $allActivities->count();
        $activities = $allActivities->forPage($activityPage, $activityPerPage)->values();

        $backUrl = $task->milestone_id
            ? route('projects.milestones.show', [$project, $task->milestone_id]) . '?tab=tasklists'
            : route('projects.show', $project) . '?tab=tasklists';

        return view('modules.projects.tasks.workspace', [
            'project' => $project,
            'task' => $task,
            'canManageTask' => $canManageTask,
            'nextAction' => $nextAction,
            'legalNextStatuses' => $this->tasks->legalTransitions($task->status),
            'backUrl' => $backUrl,
            'subtasksPayload' => TaskDrawerPayload::subtasks($project, $task),
            'dependenciesPayload' => TaskDrawerPayload::dependencies($project, $task),
            'otherTasks' => TaskDrawerPayload::otherTasks($task, $this->tasks->list($project)->keyBy('id')),
            'taskLists' => $canManageTask ? $this->cloneTaskListOptions($project) : collect(),
            'activeMembers' => $canManageTask ? $this->members->list($project)->where('is_active', true) : collect(),
            'availableProductionOrders' => $availableProductionOrders,
            'canViewProduction' => $canViewProduction,
            'availablePrItems' => $availablePrItems,
            'linkedSalesOrderItem' => $linkedSalesOrderItem,
            'linkedProduct' => $linkedProduct,
            'isManufacturedItem' => $isManufacturedItem,
            'isTradeItem' => $isTradeItem,
            'activities' => $activities,
            'activityPage' => $activityPage,
            'activityPerPage' => $activityPerPage,
            'activityTotal' => $activityTotal,
        ]);
    }

    private function cloneTaskListOptions(Project $project): \Illuminate\Database\Eloquent\Collection
    {
        return $project->taskLists()->orderBy('position')->get(['id', 'name']);
    }

    public function updateField(Request $request, Project $project, Task $task): JsonResponse
    {
        return $this->handleInlineFieldUpdate($request, $task);
    }

    protected function inlineFieldSchema(): array
    {
        $project = request()->route('project');
        $tenantId = require_tenant_id();

        return [
            'title' => [
                'rules' => ['required', 'string', 'max:255'],
                'handler' => fn (Task $task, $value) => $this->tasks->updateField($task, 'title', $value),
            ],
            'description' => [
                'rules' => ['nullable', 'string'],
                'handler' => fn (Task $task, $value) => $this->tasks->updateField($task, 'description', $value),
            ],
            'priority' => [
                'rules' => ['required', Rule::in(Task::PRIORITIES)],
                'handler' => fn (Task $task, $value) => $this->tasks->updateField($task, 'priority', $value),
            ],
            'assignee_id' => [
                'rules' => [
                    'nullable',
                    'integer',
                    Rule::exists('project_members', 'user_id')
                        ->where('tenant_id', $tenantId)
                        ->where('project_id', $project?->id)
                        ->where('is_active', true),
                ],
                'handler' => fn (Task $task, $value) => $this->tasks->updateField($task, 'assignee_id', $value),
            ],
            'reviewer_id' => [
                'rules' => [
                    'nullable',
                    'integer',
                    Rule::exists('project_members', 'user_id')
                        ->where('tenant_id', $tenantId)
                        ->where('project_id', $project?->id)
                        ->where('is_active', true),
                ],
                'handler' => fn (Task $task, $value) => $this->tasks->updateField($task, 'reviewer_id', $value),
            ],
            'due_date' => [
                'rules' => ['nullable', 'date'],
                'handler' => function (Task $task, $value) {
                    if ($value !== null && $task->start_date && Carbon::parse($value)->lt($task->start_date)) {
                        throw ValidationException::withMessages([
                            'value' => 'The due date must be a date after or equal to start date.',
                        ]);
                    }

                    return $this->tasks->updateField($task, 'due_date', $value)?->format('Y-m-d');
                },
            ],
        ];
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [Task::class, $project]);

        $this->tasks->create($project, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.task_added'));
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->tasks->update($task, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.task_updated'));
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $this->tasks->delete($task);

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.task_removed'));
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->tasks->updateStatus($task, $request->validated()['status']);

        // Status transitions are now only triggered from the Task Workspace
        // (the row-level status dropdown was retired), so always return there.
        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('success', __('projects.task_status_updated'));
    }

    public function assign(AssignTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->tasks->assign($task, $request->validated());

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', __('projects.task_assigned'));
    }

    public function linkProductionOrder(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $tenantId = require_tenant_id();
        $user = auth()->user();
        if (!$this->canViewProduction($user, $tenantId)) {
            abort(403, 'Unauthorized to link Production Orders.');
        }

        $targetCompanyId = $task->company_id ?: $project->company_id;
        $targetBranchId = $task->branch_id ?: $project->branch_id;

        $validated = $request->validate([
            'production_order_id' => [
                'required',
                'integer',
                Rule::exists('production_orders', 'id')->where(function ($query) use ($tenantId, $targetCompanyId, $targetBranchId) {
                    $query->where('tenant_id', $tenantId);
                    if ($targetCompanyId) {
                        $query->where(function ($q) use ($targetCompanyId) {
                            $q->whereNull('company_id')->orWhere('company_id', $targetCompanyId);
                        });
                    }
                    if ($targetBranchId) {
                        $query->where(function ($q) use ($targetBranchId) {
                            $q->whereNull('branch_id')->orWhere('branch_id', $targetBranchId);
                        });
                    }
                }),
            ],
        ]);

        $productionOrder = \App\Domains\Production\Models\ProductionOrder::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($validated['production_order_id']);

        if ($targetCompanyId && $productionOrder->company_id && (int) $targetCompanyId !== (int) $productionOrder->company_id) {
            throw ValidationException::withMessages([
                'production_order_id' => __('projects.company_mismatch_error') ?: 'Production order belongs to a different company.',
            ]);
        }

        if ($targetBranchId && $productionOrder->branch_id && (int) $targetBranchId !== (int) $productionOrder->branch_id) {
            throw ValidationException::withMessages([
                'production_order_id' => __('projects.branch_mismatch_error') ?: 'Production order belongs to a different branch.',
            ]);
        }

        $productionOrder->update([
            'project_id' => $project->id,
            'project_milestone_id' => $task->milestone_id,
            'project_task_id' => $task->id,
        ]);

        $this->activity->record(
            $project,
            'production.linked',
            "Production Order {$productionOrder->order_number} linked to task {$task->task_code}",
            null,
            $task,
            ['production_order_id' => $productionOrder->id]
        );

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('success', __('projects.production_order_linked', ['order' => $productionOrder->order_number]) ?: "Production Order {$productionOrder->order_number} linked successfully.");
    }

    public function unlinkProductionOrder(Request $request, Project $project, Task $task, int $productionOrderId): RedirectResponse
    {
        $this->authorize('update', $task);

        $tenantId = require_tenant_id();
        $user = auth()->user();
        if (!$this->canViewProduction($user, $tenantId)) {
            abort(403, 'Unauthorized to unlink Production Orders.');
        }

        $productionOrder = \App\Domains\Production\Models\ProductionOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('project_task_id', $task->id)
            ->findOrFail($productionOrderId);

        $productionOrder->update([
            'project_id' => null,
            'project_milestone_id' => null,
            'project_task_id' => null,
        ]);

        $this->activity->record(
            $project,
            'production.unlinked',
            "Production Order {$productionOrder->order_number} unlinked from task {$task->task_code}",
            null,
            $task,
            ['production_order_id' => $productionOrder->id]
        );

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('success', __('projects.production_order_unlinked', ['order' => $productionOrder->order_number]) ?: "Production Order {$productionOrder->order_number} unlinked successfully.");
    }

    public function linkPurchaseRequisition(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $tenantId = require_tenant_id();
        $user = auth()->user();
        if (!$this->canViewPurchase($user, $tenantId)) {
            abort(403, 'Unauthorized to link Purchase Requisitions.');
        }

        $validated = $request->validate([
            'purchase_requisition_item_id' => [
                'required',
                'integer',
                Rule::exists('purchase_requisition_items', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
        ]);

        $prItem = \App\Domains\Purchase\Models\PurchaseRequisitionItem::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($validated['purchase_requisition_item_id']);

        $prItem->update([
            'project_task_id' => $task->id,
        ]);

        $this->activity->record(
            $project,
            'procurement.linked',
            "Purchase Requisition Item linked to task {$task->task_code}",
            null,
            $task,
            ['purchase_requisition_item_id' => $prItem->id]
        );

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('success', __('projects.pr_item_linked') ?: "Purchase Requisition line linked successfully.");
    }

    public function unlinkPurchaseRequisition(Request $request, Project $project, Task $task, int $requisitionItemId): RedirectResponse
    {
        $this->authorize('update', $task);

        $tenantId = require_tenant_id();
        $user = auth()->user();
        if (!$this->canViewPurchase($user, $tenantId)) {
            abort(403, 'Unauthorized to unlink Purchase Requisitions.');
        }

        $prItem = \App\Domains\Purchase\Models\PurchaseRequisitionItem::query()
            ->where('tenant_id', $tenantId)
            ->where('project_task_id', $task->id)
            ->findOrFail($requisitionItemId);

        $prItem->update([
            'project_task_id' => null,
        ]);

        $this->activity->record(
            $project,
            'procurement.unlinked',
            "Purchase Requisition Item unlinked from task {$task->task_code}",
            null,
            $task,
            ['purchase_requisition_item_id' => $prItem->id]
        );

        return redirect()
            ->route('projects.tasks.show', [$project, $task])
            ->with('success', __('projects.pr_item_unlinked') ?: "Purchase Requisition line unlinked successfully.");
    }

    private function canViewProduction(User $user, int $tenantId): bool
    {
        $access = app(AccessService::class);
        return $access->allows($user, 'production.orders.view', ['tenant_id' => $tenantId])
            || $access->allows($user, 'production.view', ['tenant_id' => $tenantId])
            || $access->hasRole($user, 'tenant_owner', $tenantId)
            || $access->hasRole($user, 'super_admin');
    }

    private function canViewPurchase(User $user, int $tenantId): bool
    {
        $access = app(AccessService::class);
        return $access->allows($user, 'purchase.requisitions.view', ['tenant_id' => $tenantId])
            || $access->allows($user, 'purchase.view', ['tenant_id' => $tenantId])
            || $access->hasRole($user, 'tenant_owner', $tenantId)
            || $access->hasRole($user, 'super_admin');
    }
}
