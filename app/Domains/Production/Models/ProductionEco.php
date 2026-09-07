<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionEco extends BaseModel
{
    use HasFactory;

    protected $table = 'production_ecos';

    public const CHANGE_TYPE_BOM = 'BOM_CHANGE';
    public const CHANGE_TYPE_ROUTING = 'ROUTING_CHANGE';
    public const CHANGE_TYPE_BOM_AND_ROUTING = 'BOM_AND_ROUTING_CHANGE';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RELEASED = 'released';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'eco_number',
        'title',
        'description',
        'reason',
        'change_type',
        'product_id',
        'current_bom_id',
        'proposed_bom_id',
        'current_bom_revision',
        'proposed_bom_revision',
        'current_routing_id',
        'proposed_routing_id',
        'current_routing_revision',
        'proposed_routing_revision',
        'effective_date',
        'status',
        'created_by',
        'approved_by',
        'released_by',
        'approved_at',
        'released_at',
        'closed_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'approved_at' => 'datetime',
        'released_at' => 'datetime',
        'closed_at' => 'datetime',
        'current_bom_revision' => 'integer',
        'proposed_bom_revision' => 'integer',
        'current_routing_revision' => 'integer',
        'proposed_routing_revision' => 'integer',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function currentBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'current_bom_id');
    }

    public function proposedBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'proposed_bom_id');
    }

    public function currentRouting(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'current_routing_id');
    }

    public function proposedRouting(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'proposed_routing_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionEcoItem::class, 'eco_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ProductionEcoApproval::class, 'eco_id')->orderBy('created_at', 'desc');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    // Status Helpers
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
