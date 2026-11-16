<?php

namespace App\Domains\Projects\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Services\SalesInvoiceCreationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProjectBillingService
{
    public function __construct(
        private readonly SalesInvoiceCreationService $salesInvoiceCreationService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * Get unbilled approved time logs eligible for invoicing.
     */
    public function getUnbilledTimeLogs(Project $project): Collection
    {
        // When Fixed budget is set on deliverable-based methods, client pays deliverables, not timesheets
        if ($project->budget_type === Project::BUDGET_TYPE_FIXED && in_array($project->billing_method, [
            Project::BILLING_METHOD_PROJECT_BASED,
            Project::BILLING_METHOD_MILESTONE_BASED,
            Project::BILLING_METHOD_TASK_BASED,
        ], true)) {
            return collect();
        }

        // When budget_type is null and method is milestone or project based, default to deliverable
        if ($project->budget_type === null && in_array($project->billing_method, [
            Project::BILLING_METHOD_PROJECT_BASED,
            Project::BILLING_METHOD_MILESTONE_BASED,
            Project::BILLING_METHOD_TASK_BASED,
        ], true)) {
            return collect();
        }

        return TimeLog::where('project_id', $project->id)
            ->where('is_billable', true)
            ->where('approval_status', TimeLog::STATUS_APPROVED)
            ->where('is_invoiced', false)
            ->with(['task', 'user'])
            ->orderBy('log_date', 'asc')
            ->get();
    }

    /**
     * Get completed unbilled milestones eligible for invoicing.
     */
    public function getUnbilledMilestones(Project $project): Collection
    {
        if (in_array($project->billing_method, [Project::BILLING_METHOD_USER_BASED, Project::BILLING_METHOD_TASK_BASED], true)) {
            return collect();
        }

        // Under Time & Material, client is billed via timesheets rather than fixed milestone deliverables
        if ($project->budget_type === Project::BUDGET_TYPE_TIME_AND_MATERIAL) {
            return collect();
        }

        return Milestone::where('project_id', $project->id)
            ->where(function ($q) {
                $q->where('status', Milestone::STATUS_COMPLETED)
                  ->orWhere('completion_percentage', 100);
            })
            ->where('is_invoiced', false)
            ->where('billing_amount', '>', 0)
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Get completed unbilled fixed-price tasks eligible for invoicing.
     */
    public function getUnbilledTasks(Project $project): Collection
    {
        if ($project->billing_method !== Project::BILLING_METHOD_TASK_BASED || $project->budget_type !== Project::BUDGET_TYPE_FIXED) {
            return collect();
        }

        return Task::where('project_id', $project->id)
            ->where('status', Task::STATUS_COMPLETED)
            ->where('is_invoiced', false)
            ->where('billing_amount', '>', 0)
            ->with(['taskList'])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Calculate financial billing metrics for the project billing tab.
     */
    public function getBillingSummary(Project $project): array
    {
        $invoices = $project->invoices()
            ->where('status', '!=', 'Cancelled')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalInvoiced = (float) $invoices->sum('total_amount');
        $totalPaid     = (float) $invoices->sum('amount_paid');
        $balanceDue    = (float) $invoices->sum('balance_due');

        $unbilledLogs = $this->getUnbilledTimeLogs($project);
        $unbilledTimeAmount = (float) $unbilledLogs->sum(fn($log) => (float) $log->hours * (float) ($log->hourly_rate ?? 0));

        $unbilledMilestones = $this->getUnbilledMilestones($project);
        $unbilledMilestoneAmount = (float) $unbilledMilestones->sum('billing_amount');

        $unbilledTasks = $this->getUnbilledTasks($project);
        $unbilledTaskAmount = (float) $unbilledTasks->sum('billing_amount');

        $unbilledTotal = $unbilledTimeAmount + $unbilledMilestoneAmount + $unbilledTaskAmount;

        return [
            'total_invoiced'             => $totalInvoiced,
            'total_paid'                 => $totalPaid,
            'balance_due'                => $balanceDue,
            'unbilled_time_amount'       => $unbilledTimeAmount,
            'unbilled_milestone_amount'  => $unbilledMilestoneAmount,
            'unbilled_task_amount'       => $unbilledTaskAmount,
            'unbilled_total'             => $unbilledTotal,
            'invoices_count'             => $invoices->count(),
            'unbilled_logs_count'        => $unbilledLogs->count(),
            'unbilled_milestones_count'  => $unbilledMilestones->count(),
            'unbilled_tasks_count'       => $unbilledTasks->count(),
            'invoices'                   => $invoices,
        ];
    }

    /**
     * Resolve active service product from inventory catalog (references existing catalog product, no PM master logic).
     */
    public function resolveServiceProduct(Project $project, ?int $serviceProductId = null): Product
    {
        if ($serviceProductId) {
            $product = Product::where('tenant_id', $project->tenant_id)
                ->where('item_type', 'Service')
                ->find($serviceProductId);

            if (!$product) {
                throw ValidationException::withMessages([
                    'service_product_id' => 'The selected Service Product is invalid or does not exist in inventory.',
                ]);
            }

            return $product;
        }

        $activeService = Product::where('tenant_id', $project->tenant_id)
            ->where('item_type', 'Service')
            ->where(function ($q) {
                $q->whereNull('status')->orWhereIn('status', ['active', 'Active']);
            })
            ->first();

        if ($activeService) {
            return $activeService;
        }

        // Check any service item if no status active found
        $anyService = Product::where('tenant_id', $project->tenant_id)
            ->where('item_type', 'Service')
            ->first();

        if ($anyService) {
            return $anyService;
        }

        throw ValidationException::withMessages([
            'service_product_id' => 'Please configure at least one active Service Product in your Inventory settings before generating project invoices.',
        ]);
    }

    /**
     * Calculate live preview of subtotal, estimated tax, and line items for modal.
     */
    public function previewInvoice(Project $project, array $payload): array
    {
        $timeLogIds = $payload['time_log_ids'] ?? [];
        $milestoneIds = $payload['milestone_ids'] ?? [];
        $taskIds = $payload['task_ids'] ?? [];

        $timeLogs = TimeLog::where('project_id', $project->id)
            ->whereIn('id', $timeLogIds)
            ->where('is_billable', true)
            ->where('approval_status', TimeLog::STATUS_APPROVED)
            ->where('is_invoiced', false)
            ->with(['task', 'user'])
            ->get();

        $milestones = !empty($milestoneIds) && $project->budget_type !== Project::BUDGET_TYPE_TIME_AND_MATERIAL
            ? Milestone::where('project_id', $project->id)
                ->whereIn('id', $milestoneIds)
                ->where(function ($q) {
                    $q->where('status', Milestone::STATUS_COMPLETED)
                      ->orWhere('completion_percentage', 100);
                })
                ->where('is_invoiced', false)
                ->where('billing_amount', '>', 0)
                ->get()
            : collect();

        $tasks = !empty($taskIds) && $project->billing_method === Project::BILLING_METHOD_TASK_BASED && $project->budget_type === Project::BUDGET_TYPE_FIXED
            ? Task::where('project_id', $project->id)
                ->whereIn('id', $taskIds)
                ->where('status', Task::STATUS_COMPLETED)
                ->where('is_invoiced', false)
                ->where('billing_amount', '>', 0)
                ->get()
            : collect();

        $serviceProduct = null;
        try {
            $serviceProduct = $this->resolveServiceProduct($project, $payload['service_product_id'] ?? null);
        } catch (\Throwable $e) {
            // Service product not yet selected/configured
        }

        $items = [];
        $subtotal = 0.0;

        foreach ($timeLogs as $log) {
            $qty = (float) $log->hours;
            $rate = (float) ($log->hourly_rate ?? 0);
            $amount = round($qty * $rate, 2);
            $subtotal += $amount;

            $items[] = [
                'type'        => 'time_log',
                'id'          => $log->id,
                'name'        => "Project Time: " . ($log->task?->title ?? $log->task?->name ?? 'Task Work'),
                'description' => "Logged by {$log->user?->name} on " . ($log->log_date ? $log->log_date->format('Y-m-d') : 'N/A'),
                'quantity'    => $qty,
                'unit_price'  => $rate,
                'subtotal'    => $amount,
            ];
        }

        foreach ($milestones as $milestone) {
            $amount = (float) $milestone->billing_amount;
            $subtotal += $amount;

            $items[] = [
                'type'        => 'milestone',
                'id'          => $milestone->id,
                'name'        => "Milestone: {$milestone->name}",
                'description' => "Completed Milestone Deliverable: " . ($milestone->description ?? $milestone->name),
                'quantity'    => 1,
                'unit_price'  => $amount,
                'subtotal'    => $amount,
            ];
        }

        foreach ($tasks as $task) {
            $amount = (float) $task->billing_amount;
            $subtotal += $amount;

            $taskTitle = $task->title ?? $task->name ?? 'Completed Task';
            $items[] = [
                'type'        => 'task',
                'id'          => $task->id,
                'name'        => "Task Deliverable: {$taskTitle}",
                'description' => "Completed Task Deliverable: " . ($task->description ?? $taskTitle),
                'quantity'    => 1,
                'unit_price'  => $amount,
                'subtotal'    => $amount,
            ];
        }

        $taxRate = (float) ($serviceProduct?->gst_rate ?? 0);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $totalAmount = $subtotal + $taxAmount;

        return [
            'items'         => $items,
            'subtotal'      => $subtotal,
            'tax_rate'      => $taxRate,
            'tax_amount'    => $taxAmount,
            'total_amount'  => $totalAmount,
            'service_sku'   => $serviceProduct?->sku ?? null,
            'service_name'  => $serviceProduct?->name ?? null,
        ];
    }

    /**
     * Transactionally lock work, prepare billing lines, delegate to Sales, and mark work invoiced.
     *
     * @param Project $project
     * @param array $payload [
     *     'invoice_date'       => string,
     *     'due_date'           => string,
     *     'payment_terms'      => string|null,
     *     'time_log_ids'       => array,
     *     'milestone_ids'      => array,
     *     'task_ids'           => array,
     *     'service_product_id' => int|null,
     *     'notes'              => string|null,
     * ]
     * @return Invoice (Created in 'Draft' status)
     */
    public function generateInvoice(Project $project, array $payload): Invoice
    {
        if (empty($project->customer_id)) {
            throw new InvalidArgumentException("The project has no assigned customer. Please assign a customer to the project before generating an invoice.");
        }

        $timeLogIds = $payload['time_log_ids'] ?? [];
        $milestoneIds = $payload['milestone_ids'] ?? [];
        $taskIds = $payload['task_ids'] ?? [];

        if (empty($timeLogIds) && empty($milestoneIds) && empty($taskIds)) {
            throw new InvalidArgumentException("Please select at least one approved time log, completed milestone, or completed task to invoice.");
        }

        return DB::transaction(function () use ($project, $payload, $timeLogIds, $milestoneIds, $taskIds) {
            // 1. Identify and pessimistically lock eligible billable work
            if (!empty($timeLogIds)) {
                if ($project->budget_type === Project::BUDGET_TYPE_FIXED && in_array($project->billing_method, [
                    Project::BILLING_METHOD_PROJECT_BASED,
                    Project::BILLING_METHOD_MILESTONE_BASED,
                    Project::BILLING_METHOD_TASK_BASED,
                ], true)) {
                    throw new InvalidArgumentException("Timesheet invoicing is not applicable for Fixed deliverable projects.");
                }
            }

            $timeLogs = !empty($timeLogIds)
                ? TimeLog::where('project_id', $project->id)
                    ->whereIn('id', $timeLogIds)
                    ->lockForUpdate()
                    ->get()
                : collect();

            foreach ($timeLogs as $log) {
                if (!$log->is_billable) {
                    throw new InvalidArgumentException("Time log #{$log->id} is marked as non-billable and cannot be invoiced.");
                }
                if ($log->approval_status !== TimeLog::STATUS_APPROVED) {
                    throw new InvalidArgumentException("Time log #{$log->id} is not approved and cannot be invoiced.");
                }
                if ($log->is_invoiced) {
                    throw new InvalidArgumentException("Time log #{$log->id} has already been invoiced.");
                }
            }

            if (!empty($milestoneIds)) {
                if ($project->budget_type === Project::BUDGET_TYPE_TIME_AND_MATERIAL) {
                    throw new InvalidArgumentException("Milestone deliverable invoicing is not applicable for Time & Material projects.");
                }
            }

            $milestones = !empty($milestoneIds)
                ? Milestone::where('project_id', $project->id)
                    ->whereIn('id', $milestoneIds)
                    ->lockForUpdate()
                    ->get()
                : collect();

            foreach ($milestones as $milestone) {
                if ($milestone->status !== Milestone::STATUS_COMPLETED && (int)$milestone->completion_percentage !== 100) {
                    throw new InvalidArgumentException("Milestone '{$milestone->name}' is not completed and cannot be invoiced.");
                }
                if ($milestone->is_invoiced) {
                    throw new InvalidArgumentException("Milestone '{$milestone->name}' has already been invoiced.");
                }
                if ((float)$milestone->billing_amount <= 0) {
                    throw new InvalidArgumentException("Milestone '{$milestone->name}' has no positive billing amount configured.");
                }
            }

            if (!empty($taskIds)) {
                if ($project->billing_method !== Project::BILLING_METHOD_TASK_BASED || $project->budget_type !== Project::BUDGET_TYPE_FIXED) {
                    throw new InvalidArgumentException("Task deliverable invoicing is only applicable for Task Based projects with Fixed budget type.");
                }
            }

            $tasks = !empty($taskIds)
                ? Task::where('project_id', $project->id)
                    ->whereIn('id', $taskIds)
                    ->lockForUpdate()
                    ->get()
                : collect();

            foreach ($tasks as $task) {
                if ($task->status !== Task::STATUS_COMPLETED) {
                    throw new InvalidArgumentException("Task '{$task->title}' is not completed and cannot be invoiced.");
                }
                if ($task->is_invoiced) {
                    throw new InvalidArgumentException("Task '{$task->title}' has already been invoiced.");
                }
                if ((float)$task->billing_amount <= 0) {
                    throw new InvalidArgumentException("Task '{$task->title}' has no positive billing amount configured.");
                }
            }

            if ($timeLogs->isEmpty() && $milestones->isEmpty() && $tasks->isEmpty()) {
                throw new InvalidArgumentException("No valid unbilled deliverables were found for the selected IDs.");
            }

            // 2. Resolve Service Product from Inventory catalog (Reference only)
            $serviceProduct = $this->resolveServiceProduct($project, $payload['service_product_id'] ?? null);

            // 3. Prepare billing lines with source traceability
            $items = [];

            foreach ($timeLogs as $log) {
                $logDateStr = $log->log_date ? (is_string($log->log_date) ? $log->log_date : $log->log_date->format('Y-m-d')) : 'N/A';
                $taskName = $log->task?->title ?? $log->task?->name ?? 'Task Work';
                $userName = $log->user?->name ?? 'Team Member';
                $hrs = number_format((float) $log->hours, 2);
                $rate = number_format((float) ($log->hourly_rate ?? 0), 2);

                $desc = "Time log: {$userName} on {$logDateStr} ({$hrs} hrs @ {$rate}/hr)";
                if (!empty($log->description)) {
                    $desc .= " - {$log->description}";
                }

                $items[] = [
                    'product_id'  => $serviceProduct->id,
                    'item_name'   => "Project Time: {$taskName}",
                    'description' => $desc,
                    'quantity'    => (float) $log->hours,
                    'unit_price'  => (float) ($log->hourly_rate ?? 0),
                    'discount'    => 0,
                ];
            }

            foreach ($milestones as $milestone) {
                $desc = "Completed Milestone: {$milestone->name}";
                if (!empty($milestone->description)) {
                    $desc .= " - {$milestone->description}";
                }

                $items[] = [
                    'product_id'  => $serviceProduct->id,
                    'item_name'   => "Milestone: {$milestone->name}",
                    'description' => $desc,
                    'quantity'    => 1,
                    'unit_price'  => (float) $milestone->billing_amount,
                    'discount'    => 0,
                ];
            }

            foreach ($tasks as $task) {
                $taskTitle = $task->title ?? $task->name ?? 'Completed Task';
                $desc = "Completed Task: {$taskTitle}";
                if (!empty($task->description)) {
                    $desc .= " - {$task->description}";
                }

                $items[] = [
                    'product_id'  => $serviceProduct->id,
                    'item_name'   => "Task: {$taskTitle}",
                    'description' => $desc,
                    'quantity'    => 1,
                    'unit_price'  => (float) $task->billing_amount,
                    'discount'    => 0,
                ];
            }

            // 4. Delegate invoice creation to Sales domain
            $invoice = $this->salesInvoiceCreationService->createDraftInvoice([
                'tenant_id'     => $project->tenant_id,
                'company_id'    => $project->company_id,
                'branch_id'     => $project->branch_id,
                'customer_id'   => $project->customer_id,
                'project_id'    => $project->id,
                'sales_order_id'=> $project->sales_order_id,
                'invoice_date'  => $payload['invoice_date'] ?? now()->toDateString(),
                'due_date'      => $payload['due_date'] ?? now()->addDays(30)->toDateString(),
                'payment_terms' => $payload['payment_terms'] ?? null,
                'notes'         => $payload['notes'] ?? ("Generated from Project " . $project->project_code),
                'items'         => $items,
            ]);

            // 5. Mark source records as invoiced
            foreach ($timeLogs as $log) {
                $log->update([
                    'is_invoiced' => true,
                    'invoice_id'  => $invoice->id,
                ]);
            }

            foreach ($milestones as $milestone) {
                $milestone->update([
                    'is_invoiced' => true,
                    'invoice_id'  => $invoice->id,
                ]);
            }

            foreach ($tasks as $task) {
                $task->update([
                    'is_invoiced' => true,
                    'invoice_id'  => $invoice->id,
                ]);
            }

            // 6. Record activity log
            $this->activityLogService->record(
                $project,
                'invoice_generated',
                "Draft Invoice {$invoice->invoice_number} generated",
                "Created Draft Sales Invoice #{$invoice->invoice_number} with " . count($items) . " billing line(s). Total: " . number_format((float)$invoice->total_amount, 2),
                $invoice,
                [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount'   => $invoice->total_amount,
                    'time_log_ids'   => $timeLogs->pluck('id')->all(),
                    'milestone_ids'  => $milestones->pluck('id')->all(),
                    'task_ids'       => $tasks->pluck('id')->all(),
                ]
            );

            return $invoice;
        });
    }
}
