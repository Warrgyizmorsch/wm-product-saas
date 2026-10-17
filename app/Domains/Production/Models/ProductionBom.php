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
        'has_warning',
        'warning_reason',
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
        'has_warning' => 'boolean',
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

    /**
     * Inspect component sub-BOMs used in this BOM.
     * Generates warnings if:
     * 1. A connected sub-BOM has been cancelled or deactivated.
     * 2. A newer approved/active version of a component BOM has been released.
     */
    public function getComponentWarnings(): array
    {
        // Cancelled or inactive parent BOMs do not need warnings
        if (in_array($this->status, ['cancelled', 'inactive'], true)) {
            return [];
        }

        $warnings = [];
        $this->loadMissing(['items.material', 'items.childBom']);

        $today = Carbon::today()->toDateString();

        foreach ($this->items as $item) {
            if (!$item->material_id) {
                continue;
            }

            $materialName = $item->material?->name ?? 'Component';
            $materialSku = $item->material?->sku ?? '';

            // Check if item has an explicit child_bom_id
            if ($item->child_bom_id) {
                $childBom = $item->childBom ?? self::withoutGlobalScopes()->find($item->child_bom_id);

                if ($childBom) {
                    $childStatusStr = ucfirst($childBom->status);
                    $isChildCancelled = ($childBom->status === 'cancelled');
                    $isChildInactive = in_array($childBom->status, ['inactive', 'superseded'], true) 
                        || ($childBom->expiry_date && Carbon::parse($childBom->expiry_date)->toDateString() < $today);

                    // Check if a newer approved version exists for this component product
                    $latestApprovedBom = self::withoutGlobalScopes()
                        ->where('tenant_id', $this->tenant_id)
                        ->where('product_id', $item->material_id)
                        ->whereIn('status', ['approved', 'active'])
                        ->where(function ($q) use ($today) {
                            $q->whereNull('expiry_date')
                              ->orWhere('expiry_date', '>=', $today);
                        })
                        ->where('id', '!=', $childBom->id)
                        ->orderByDesc('id')
                        ->first();

                    $hasNewerVersion = ($latestApprovedBom && $latestApprovedBom->id > $childBom->id);

                    if ($isChildCancelled) {
                        $message = $hasNewerVersion
                            ? "Sub-BOM {$childBom->bom_number} v{$childBom->version} [Status: Cancelled] for '{$materialName}' was cancelled and a newer approved version v{$latestApprovedBom->version} [Status: Active] ({$latestApprovedBom->bom_number}) is available."
                            : "Sub-BOM {$childBom->bom_number} v{$childBom->version} [Status: Cancelled] for '{$materialName}' was cancelled and has no active replacement.";

                        $warnings[] = [
                            'type'                => 'cancelled',
                            'item_id'             => $item->id,
                            'material_name'       => $materialName,
                            'material_sku'        => $materialSku,
                            'child_bom_id'        => $childBom->id,
                            'child_bom_number'    => $childBom->bom_number,
                            'child_bom_version'   => $childBom->version,
                            'child_bom_status'    => $childBom->status,
                            'latest_bom_id'       => $latestApprovedBom?->id,
                            'latest_bom_number'   => $latestApprovedBom?->bom_number,
                            'latest_bom_version'  => $latestApprovedBom?->version,
                            'latest_bom_status'   => $latestApprovedBom?->status,
                            'message'             => $message,
                        ];
                    } elseif ($isChildInactive) {
                        $message = $hasNewerVersion
                            ? "Sub-BOM {$childBom->bom_number} v{$childBom->version} [Status: {$childStatusStr}] for '{$materialName}' is inactive/expired and a newer approved version v{$latestApprovedBom->version} [Status: Active] ({$latestApprovedBom->bom_number}) is available."
                            : "Sub-BOM {$childBom->bom_number} v{$childBom->version} [Status: {$childStatusStr}] for '{$materialName}' is inactive/expired and has no active replacement.";

                        $warnings[] = [
                            'type'                => 'inactive',
                            'item_id'             => $item->id,
                            'material_name'       => $materialName,
                            'material_sku'        => $materialSku,
                            'child_bom_id'        => $childBom->id,
                            'child_bom_number'    => $childBom->bom_number,
                            'child_bom_version'   => $childBom->version,
                            'child_bom_status'    => $childBom->status,
                            'latest_bom_id'       => $latestApprovedBom?->id,
                            'latest_bom_number'   => $latestApprovedBom?->bom_number,
                            'latest_bom_version'  => $latestApprovedBom?->version,
                            'latest_bom_status'   => $latestApprovedBom?->status,
                            'message'             => $message,
                        ];
                    } elseif ($hasNewerVersion) {
                        $warnings[] = [
                            'type'                => 'version_updated',
                            'item_id'             => $item->id,
                            'material_name'       => $materialName,
                            'material_sku'        => $materialSku,
                            'child_bom_id'        => $childBom->id,
                            'child_bom_number'    => $childBom->bom_number,
                            'child_bom_version'   => $childBom->version,
                            'child_bom_status'    => $childBom->status,
                            'latest_bom_id'       => $latestApprovedBom->id,
                            'latest_bom_number'   => $latestApprovedBom->bom_number,
                            'latest_bom_version'  => $latestApprovedBom->version,
                            'latest_bom_status'   => $latestApprovedBom->status,
                            'message'             => "Sub-BOM {$childBom->bom_number} v{$childBom->version} [Status: {$childStatusStr}] for '{$materialName}' has a newer approved version v{$latestApprovedBom->version} [Status: Active] ({$latestApprovedBom->bom_number}).",
                        ];
                    }
                }
            } else {
                // If material has BOMs, but child_bom_id was not explicitly set on this item
                $activeChildBom = self::withoutGlobalScopes()
                    ->where('tenant_id', $this->tenant_id)
                    ->where('product_id', $item->material_id)
                    ->whereIn('status', ['approved', 'active'])
                    ->orderByDesc('id')
                    ->first();

                $oldChildBom = self::withoutGlobalScopes()
                    ->where('tenant_id', $this->tenant_id)
                    ->where('product_id', $item->material_id)
                    ->whereIn('status', ['cancelled', 'inactive', 'superseded'])
                    ->where(function ($q) use ($activeChildBom) {
                        if ($activeChildBom) {
                            $q->where('id', '<', $activeChildBom->id);
                        }
                    })
                    ->latest('id')
                    ->first();

                if ($oldChildBom && $activeChildBom) {
                    $oldStatusStr = ucfirst($oldChildBom->status);
                    $warnings[] = [
                        'type'                => 'version_updated',
                        'item_id'             => $item->id,
                        'material_name'       => $materialName,
                        'material_sku'        => $materialSku,
                        'child_bom_id'        => $oldChildBom->id,
                        'child_bom_number'    => $oldChildBom->bom_number,
                        'child_bom_version'   => $oldChildBom->version,
                        'child_bom_status'    => $oldChildBom->status,
                        'latest_bom_id'       => $activeChildBom->id,
                        'latest_bom_number'   => $activeChildBom->bom_number,
                        'latest_bom_version'  => $activeChildBom->version,
                        'latest_bom_status'   => $activeChildBom->status,
                        'message'             => "Sub-BOM {$oldChildBom->bom_number} v{$oldChildBom->version} [Status: {$oldStatusStr}] for '{$materialName}' has a newer approved version v{$activeChildBom->version} [Status: Active] ({$activeChildBom->bom_number}).",
                    ];
                } elseif ($oldChildBom && !$activeChildBom) {
                    $oldStatusStr = ucfirst($oldChildBom->status);
                    $warnings[] = [
                        'type'              => 'cancelled',
                        'item_id'           => $item->id,
                        'material_name'     => $materialName,
                        'material_sku'      => $materialSku,
                        'child_bom_id'      => $oldChildBom->id,
                        'child_bom_number'  => $oldChildBom->bom_number,
                        'child_bom_version' => $oldChildBom->version,
                        'child_bom_status'  => $oldChildBom->status,
                        'message'           => "Sub-BOM {$oldChildBom->bom_number} v{$oldChildBom->version} [Status: {$oldStatusStr}] for '{$materialName}' was " . strtolower($oldStatusStr) . " and has no active replacement.",
                    ];
                }
            }
        }

        return $warnings;
    }

    public function hasComponentWarning(): bool
    {
        if (in_array($this->status, ['cancelled', 'inactive'], true)) {
            return false;
        }

        return !empty($this->getComponentWarnings());
    }

    public function getComponentWarningSummary(): string
    {
        if (in_array($this->status, ['cancelled', 'inactive'], true)) {
            return '';
        }

        $warnings = $this->getComponentWarnings();
        if (empty($warnings)) {
            return '';
        }

        return implode('; ', array_column($warnings, 'message'));
    }

    public function refreshWarningStatus(): bool
    {
        if (in_array($this->status, ['cancelled', 'inactive'], true)) {
            if ($this->has_warning || $this->warning_reason !== null) {
                $this->update([
                    'has_warning'    => false,
                    'warning_reason' => null,
                ]);
            }
            return false;
        }

        $warnings = $this->getComponentWarnings();
        $hasWarning = !empty($warnings);
        $reason = $hasWarning ? $this->getComponentWarningSummary() : null;

        $this->update([
            'has_warning'    => $hasWarning,
            'warning_reason' => $reason,
        ]);

        return $hasWarning;
    }
}
