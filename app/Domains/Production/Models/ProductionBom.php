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
use Illuminate\Support\Carbon;

class ProductionBom extends BaseModel
{
    use HasFactory;

    protected $table = 'production_boms';

    public const USAGE_CONTEXTS = ['manufacturing', 'engineering', 'prototype', 'costing'];

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
        'routing_id',
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

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id');
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

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
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

    // Active scope (approved and current date fits effective range)
    public function scopeActive(Builder $query): void
    {
        $today = Carbon::today()->toDateString();
        $query->where('status', 'approved')
            ->where('effective_date', '<=', $today)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', $today);
            });
    }
}
