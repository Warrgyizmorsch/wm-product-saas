<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Models\AssetDisposal;
use App\Domains\Accounting\FixedAssets\Models\AssetRevaluation;
use App\Domains\Accounting\FixedAssets\Models\AssetWriteOff;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends BaseModel
{
    use Loggable;

    protected $table = 'assets';

    // Legacy IT-checkout statuses (kept for backward compatibility).
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ALLOCATED = 'allocated';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_SCRAPPED = 'scrapped';

    // Fixed Asset lifecycle statuses.
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_CAPITALIZATION = 'pending_capitalization';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_IDLE = 'idle';
    public const STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    public const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';
    public const STATUS_DISPOSED = 'disposed';
    public const STATUS_SOLD = 'sold';
    public const STATUS_WRITTEN_OFF = 'written_off';
    public const STATUS_LOST = 'lost';

    public const DEPRECIATION_METHOD_STRAIGHT_LINE = 'straight_line';
    public const DEPRECIATION_METHOD_WDV = 'wdv';

    /**
     * Valid status transitions. Every service that changes an asset's status
     * must go through canTransitionTo() so the map stays the single source
     * of truth instead of being re-derived per workflow.
     */
    private const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PENDING_CAPITALIZATION, self::STATUS_ACTIVE],
        self::STATUS_PENDING_CAPITALIZATION => [self::STATUS_ACTIVE],
        self::STATUS_AVAILABLE => [self::STATUS_ALLOCATED, self::STATUS_ACTIVE, self::STATUS_MAINTENANCE, self::STATUS_UNDER_MAINTENANCE, self::STATUS_SCRAPPED],
        self::STATUS_ALLOCATED => [self::STATUS_AVAILABLE, self::STATUS_ACTIVE, self::STATUS_MAINTENANCE, self::STATUS_UNDER_MAINTENANCE],
        self::STATUS_ACTIVE => [self::STATUS_IDLE, self::STATUS_UNDER_MAINTENANCE, self::STATUS_FULLY_DEPRECIATED, self::STATUS_DISPOSED, self::STATUS_SOLD, self::STATUS_SCRAPPED, self::STATUS_WRITTEN_OFF, self::STATUS_LOST],
        self::STATUS_IDLE => [self::STATUS_ACTIVE, self::STATUS_UNDER_MAINTENANCE, self::STATUS_DISPOSED, self::STATUS_SOLD, self::STATUS_SCRAPPED, self::STATUS_WRITTEN_OFF, self::STATUS_LOST],
        self::STATUS_UNDER_MAINTENANCE => [self::STATUS_ACTIVE, self::STATUS_IDLE],
        self::STATUS_MAINTENANCE => [self::STATUS_AVAILABLE, self::STATUS_ALLOCATED],
        self::STATUS_FULLY_DEPRECIATED => [self::STATUS_DISPOSED, self::STATUS_SOLD, self::STATUS_SCRAPPED, self::STATUS_WRITTEN_OFF, self::STATUS_LOST],
        // Terminal states — no further transitions.
        self::STATUS_DISPOSED => [],
        self::STATUS_SOLD => [],
        self::STATUS_SCRAPPED => [],
        self::STATUS_WRITTEN_OFF => [],
        self::STATUS_LOST => [],
    ];

    protected $fillable = [
        'company_id',
        'branch_id',
        'department_id',
        'asset_category_id',
        'asset_item_id',
        'asset_request_id',
        'goods_receipt_note_item_id',
        'vendor_id',
        'purchase_order_id',
        'purchase_order_item_id',
        'invoice_number',
        'asset_code',
        'name',
        'description',
        'brand',
        'model_number',
        'serial_number',
        'purchase_date',
        'purchase_cost',
        'capitalization_date',
        'commissioning_date',
        'acquisition_cost',
        'directly_attributable_cost',
        'capitalization_cost',
        'recoverable_tax',
        'non_recoverable_tax',
        'residual_value',
        'useful_life_months',
        'depreciation_method',
        'depreciation_start_date',
        'accumulated_depreciation',
        'book_value',
        'status',
        'assigned_employee_id',
        'allocated_at',
        'expected_return_date',
        'location_label',
        'warranty_start_date',
        'warranty_end_date',
        'insurance_start_date',
        'insurance_end_date',
        'barcode',
        'qr_code',
        'notes',
        'condition',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'allocated_at' => 'date',
        'expected_return_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'capitalization_date' => 'date',
        'commissioning_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'directly_attributable_cost' => 'decimal:2',
        'capitalization_cost' => 'decimal:2',
        'recoverable_tax' => 'decimal:2',
        'non_recoverable_tax' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
        'depreciation_start_date' => 'date',
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
        'insurance_start_date' => 'date',
        'insurance_end_date' => 'date',
    ];

    public static function validStatuses(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public function canTransitionTo(string $to): bool
    {
        $from = (string) $this->status;

        if ($from === $to) {
            return true;
        }

        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Get the company owning the asset.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the branch the asset is physically located at.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the department the asset is assigned to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the category of the asset.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * Get the item type model of the asset.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class, 'asset_item_id');
    }

    /**
     * Get the employee currently assigned to this asset.
     */
    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    /**
     * Get the history of allocations for this asset.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(AssetAllocation::class)->orderBy('allocated_at', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Get the request that allocated this asset.
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(AssetRequest::class, 'asset_request_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function depreciationSchedules(): HasMany
    {
        return $this->hasMany(AssetDepreciationSchedule::class)
            ->orderBy('period_year')
            ->orderBy('period_month');
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(AssetDisposal::class);
    }

    public function writeOffs(): HasMany
    {
        return $this->hasMany(AssetWriteOff::class);
    }

    public function revaluations(): HasMany
    {
        return $this->hasMany(AssetRevaluation::class);
    }

    /**
     * The Production machine (if any) commissioned from this asset. Read-only
     * inverse of Machine::asset() — Production owns the write side of the link.
     */
    public function productionMachine(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domains\Production\Models\Machine::class, 'asset_id');
    }
}
