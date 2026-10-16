<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ProductionBom extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_boms';

    public const USAGE_CONTEXTS = ['manufacturing', 'engineering', 'prototype', 'costing'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE = 'approved';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SUPERSEDED = 'inactive';
    public const STATUS_UNDER_REVISION = 'under_revision';

    protected $fillable = [
        'tenant_id',
        'bom_number',
        'bom_name',
        'bom_type', // manufacturing, engineering, sales, phantom, subcontracting
        'usage_context', // manufacturing, engineering, prototype, costing
        'product_id',
        'base_quantity',
        'base_uom_id',
        'version',
        'revision',
        'revision_reason',
        'effective_date',
        'expiry_date',
        'status', // draft, pending_approval, approved, inactive, cancelled, under_revision
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'approved_at' => 'datetime',
        'revision' => 'integer',
        'base_quantity' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    public function routings(): HasMany
    {
        return $this->hasMany(Routing::class, 'bom_id');
    }

    public function routing(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Routing::class, 'bom_id')
            ->whereIn('status', [Routing::STATUS_ACTIVE, Routing::STATUS_SCHEDULED]);
    }

    public function getActiveRoutingAttribute(): ?Routing
    {
        return Routing::where('bom_id', $this->id)->active()->first()
            ?: Routing::where('product_id', $this->product_id)->active()->first();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionBomItem::class, 'bom_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ProductionBomApproval::class, 'bom_id')->orderBy('created_at', 'desc');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Status Helpers
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'scheduled'], true);
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled'
            || (in_array($this->status, ['approved', 'active'], true) && $this->effective_date && $this->effective_date->toDateString() > Carbon::today()->toDateString());
    }

    public function isCurrentlyActive(): bool
    {
        if (!in_array($this->status, ['approved', 'active', 'scheduled'], true)) {
            return false;
        }

        $today = Carbon::today()->toDateString();
        $startDate = $this->effective_date ? $this->effective_date->toDateString() : null;
        $endDate = $this->expiry_date ? $this->expiry_date->toDateString() : null;

        if ($startDate && $startDate > $today) {
            return false;
        }

        if ($endDate && $endDate < $today) {
            return false;
        }

        return true;
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isUnderRevision(): bool
    {
        return $this->status === 'under_revision';
    }

    // Active scope (approved/scheduled and current date fits effective range)
    public function scopeActive(Builder $query): void
    {
        $today = Carbon::today()->toDateString();
        $query->whereIn('status', ['approved', 'active', 'scheduled'])
            ->where('effective_date', '<=', $today)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', $today);
            })
            ->orderBy('effective_date', 'desc')
            ->orderBy('id', 'desc');
    }
}
