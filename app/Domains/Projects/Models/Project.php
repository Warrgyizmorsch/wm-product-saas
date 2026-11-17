<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\Models\TimeLog;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected ?string $rbacPermission = 'projects.projects.view';

    public function applyRbacScope(Builder $builder, User $user): void
    {
        $table = $this->getTable();
        $builder->where(function (Builder $sub) use ($user, $table) {
            $sub->where($table . '.owner_id', $user->id)
                ->orWhere($table . '.manager_id', $user->id)
                ->orWhereHas('members', fn (Builder $mq) => 
                    $mq->where('user_id', $user->id)->where('is_active', true)
                );
        });
    }

    public function getRouteKeyName(): string
    {
        return 'project_code';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        if ($field === 'project_code' && is_numeric($value)) {
            $match = $this->withoutGlobalScope('rbac_scope')->where('id', (int) $value)->first();
            if ($match) {
                return $match;
            }
        }

        $branchId = branch_id() ?? (request()->hasSession() ? request()->session()->get('branch_id') : null);

        if ($branchId !== null && in_array($field, ['project_code', 'code'])) {
            $branchMatch = $this->withoutGlobalScope('rbac_scope')->where($field, $value)->where('branch_id', $branchId)->first();
            if ($branchMatch) {
                return $branchMatch;
            }
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_ON_HOLD = 'On Hold';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    // A new project may only start as Draft or Active.
    public const CREATABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
    ];

    public const PRIORITY_LOW = 'Low';
    public const PRIORITY_MEDIUM = 'Medium';
    public const PRIORITY_HIGH = 'High';
    public const PRIORITY_CRITICAL = 'Critical';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    public const BUDGET_TYPE_FIXED = 'Fixed';
    public const BUDGET_TYPE_TIME_AND_MATERIAL = 'Time & Material';

    public const BUDGET_TYPES = [
        self::BUDGET_TYPE_FIXED,
        self::BUDGET_TYPE_TIME_AND_MATERIAL,
    ];

    public const BILLING_METHOD_PROJECT_BASED   = 'Project Based';
    public const BILLING_METHOD_MILESTONE_BASED = 'Milestone Based';
    public const BILLING_METHOD_TASK_BASED      = 'Task Based';
    public const BILLING_METHOD_USER_BASED      = 'User Based';

    public const BILLING_METHODS = [
        self::BILLING_METHOD_PROJECT_BASED,
        self::BILLING_METHOD_MILESTONE_BASED,
        self::BILLING_METHOD_TASK_BASED,
        self::BILLING_METHOD_USER_BASED,
    ];

    public const CLOSURE_STATUS_COMPLETED   = 'Completed';
    public const CLOSURE_STATUS_TERMINATED  = 'Terminated';
    public const CLOSURE_STATUS_HANDED_OVER = 'Handed Over';

    public const CLOSURE_STATUSES = [
        self::CLOSURE_STATUS_COMPLETED,
        self::CLOSURE_STATUS_TERMINATED,
        self::CLOSURE_STATUS_HANDED_OVER,
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'project_code',
        'name',
        'customer_id',
        'sales_order_id',
        'crm_deal_id',
        'client_contact_id',
        'cost_center_id',
        'owner_id',
        'manager_id',
        'start_date',
        'end_date',
        'budget_type',
        'budget_amount',
        'budget_hours',
        'billing_method',
        'priority',
        'status',
        'closure_date',
        'closure_status',
        'client_approval_ref',
        'final_remarks',
        'closed_by',
        'description',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'closure_date'  => 'date',
        'budget_amount' => 'decimal:2',
        'budget_hours'  => 'decimal:2',
    ];

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * The statuses a project may transition to next from its current status,
     * excluding formal closure which must occur exclusively via #modalCloseProject.
     *
     * @return list<string>
     */
    public function getAvailableTransitions(): array
    {
        return app(\App\Domains\Projects\Services\ProjectService::class)->availableStatusTransitions($this);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Whether mandatory setup fields (Customer, Budget Type, Billing Method)
     * are configured so team collaborators may be recruited.
     */
    public function isSetupComplete(): bool
    {
        return !empty($this->customer_id)
            && !empty($this->budget_type)
            && !empty($this->billing_method);
    }

    /**
     * Missing setup fields required before collaborators can be added.
     *
     * @return list<string>
     */
    public function missingSetupFields(): array
    {
        $missing = [];
        if (empty($this->customer_id)) {
            $missing[] = __('projects.client');
        }
        if (empty($this->budget_type)) {
            $missing[] = __('projects.budget_type');
        }
        if (empty($this->billing_method)) {
            $missing[] = __('projects.billing_method');
        }

        return $missing;
    }

    /**
     * Count of active team collaborators beyond the Project Owner and Manager.
     */
    public function activeTeamCollaboratorsCount(): int
    {
        return $this->members()
            ->where('is_active', true)
            ->where('user_id', '!=', $this->owner_id)
            ->when($this->manager_id, fn($q) => $q->where('user_id', '!=', $this->manager_id))
            ->count();
    }

    /**
     * Whether contractual fields (billing_method, budget_type, customer_id, sales_order_id)
     * are locked from inline changes.
     */
    public function isContractLocked(): bool
    {
        // 1. Financial invoices have been issued
        if ($this->invoices()->exists()) {
            return true;
        }

        // 2. Approved timesheets exist
        if ($this->timeLogs()->where('approval_status', TimeLog::STATUS_APPROVED)->exists()) {
            return true;
        }

        // 3. Team collaborators are recruited beyond owner/manager
        if ($this->activeTeamCollaboratorsCount() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Explanation of why contractual fields are locked.
     */
    public function contractLockReason(): ?string
    {
        if ($this->invoices()->exists()) {
            return __('projects.contract_locked_invoices');
        }

        if ($this->timeLogs()->where('approval_status', TimeLog::STATUS_APPROVED)->exists()) {
            return __('projects.contract_locked_timesheets');
        }

        if ($this->activeTeamCollaboratorsCount() > 0) {
            return __('projects.contract_locked_collaborators');
        }

        return null;
    }

    /**
     * Human-readable status label, the single source of truth for how a
     * status renders — used by both the listing UI and the export so the
     * two can never disagree on labels.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn () => __('projects.statuses.' . $this->status));
    }

    /**
     * Human-readable priority label, the single source of truth for how a
     * priority renders — used by both the listing UI and the export so the
     * two can never disagree on labels.
     */
    protected function priorityLabel(): Attribute
    {
        return Attribute::get(fn () => __('projects.priorities.' . $this->priority));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Sales\Models\SalesOrder::class, 'sales_order_id');
    }

    public function crmDeal(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CRM\Models\CrmDeal::class, 'crm_deal_id');
    }

    public function clientContact(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CRM\Models\CrmContact::class, 'client_contact_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Accounting\Models\CostCenter::class, 'cost_center_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(\App\Domains\Production\Models\ProductionOrder::class, 'project_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'project_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'project_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class, 'project_id');
    }

    public function taskLists(): HasMany
    {
        return $this->hasMany(TaskList::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class, 'project_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class, 'project_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class, 'project_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProjectReview::class, 'project_id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'project_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Domains\Sales\Models\Invoice::class, 'project_id');
    }
}

