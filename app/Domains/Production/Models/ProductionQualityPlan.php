<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Quality Plan = reusable checklist template (docs/production_quality_rules.md).
 *
 * QR-07: status flow draft → pending_approval → approved (reject → draft), approved → historical.
 * QR-08: versions of one plan share plan_number / root_plan_id; editing an approved version creates the next one as a draft.
 * QR-02: scope = product | section | process (work_center is no longer offered).
 */
class ProductionQualityPlan extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_quality_plans';

    public const STATUS_DRAFT            = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED         = 'approved';
    public const STATUS_HISTORICAL       = 'historical';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_HISTORICAL,
    ];

    public const SCOPE_PRODUCT = 'product';
    public const SCOPE_SECTION = 'section';
    public const SCOPE_PROCESS = 'process';

    public const SCOPES = [
        self::SCOPE_PRODUCT => 'Product specific',
        self::SCOPE_SECTION => 'Section specific',
        self::SCOPE_PROCESS => 'Process general',
    ];

    protected $fillable = [
        'tenant_id',
        'plan_number',
        'root_plan_id',
        'source_plan_id',
        'name',
        'version',
        'status',
        'type',
        'product_id',
        'product_category_id',
        'work_center_id',
        'section_id',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'historical_at',
        'esignature',
    ];

    protected $casts = [
        'submitted_at'  => 'datetime',
        'approved_at'   => 'datetime',
        'historical_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkCenterSection::class, 'section_id');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(ProductionQualityPlanParameter::class, 'quality_plan_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ProductionQualityPlanApproval::class, 'quality_plan_id')->orderByDesc('id');
    }

    public function routingOperationLinks(): HasMany
    {
        return $this->hasMany(RoutingOperationQualityPlan::class, 'quality_plan_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(ProductionQualityInspection::class, 'quality_plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sourcePlan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_plan_id');
    }

    /** Every version of this plan (same family), newest first. */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'root_plan_id', 'root_plan_id')
            ->orderByRaw('CAST(version AS UNSIGNED) DESC')
            ->orderByDesc('id');
    }

    /** The approved version of this plan's family (null when none is approved). */
    public function latestApprovedVersion(): ?self
    {
        return self::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('root_plan_id', $this->root_plan_id ?: $this->id)
            ->where('status', self::STATUS_APPROVED)
            ->orderByRaw('CAST(version AS UNSIGNED) DESC')
            ->first();
    }

    /** The newest version of this plan's family (any status). */
    public function latestVersion(): ?self
    {
        return self::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('root_plan_id', $this->root_plan_id ?: $this->id)
            ->orderByRaw('CAST(version AS UNSIGNED) DESC')
            ->first();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED);
    }

    // ─── State helpers ────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isHistorical(): bool
    {
        return $this->status === self::STATUS_HISTORICAL;
    }

    /** Draft = edited in place; Approved = "edit" creates the next version. */
    public function isEditable(): bool
    {
        return $this->isDraft() || $this->isApproved();
    }

    public function versionNumber(): int
    {
        return max(1, (int) $this->version);
    }

    public function scopeLabel(): string
    {
        return self::SCOPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT            => 'Draft',
            self::STATUS_PENDING_APPROVAL => 'Pending approval',
            self::STATUS_APPROVED         => 'Approved',
            self::STATUS_HISTORICAL       => 'Historical',
            default                       => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    /** Text shown in pickers: "Name v2 (QP-0007)". */
    public function displayName(): string
    {
        return trim($this->name . ' v' . $this->versionNumber() . ($this->plan_number ? ' (' . $this->plan_number . ')' : ''));
    }
}
