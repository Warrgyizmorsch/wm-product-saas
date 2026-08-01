<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionBatch extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_batches';

    public const STATUS_PLANNED       = 'planned';
    public const STATUS_IN_PROGRESS   = 'in_progress';
    public const STATUS_IN_PRODUCTION = self::STATUS_IN_PROGRESS;
    public const STATUS_COMPLETED     = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';
    public const STATUS_CONSUMED    = 'consumed';
    public const STATUS_BLOCKED     = 'blocked';
    public const STATUS_QUARANTINE  = 'quarantine';

    protected $fillable = [
        'tenant_id',
        'batch_number',
        'production_order_id',
        'product_id',
        'planned_quantity',
        'actual_quantity',
        'manufactured_at',
        'expiry_date',
        'status',
        'current_operation_id',
        'source_operation_id',
        'remarks',
        'barcode',
        'qr_code',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'actual_quantity'  => 'decimal:4',
        'manufactured_at'  => 'datetime',
        'expiry_date'      => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(ProductionSerialNumber::class, 'batch_id');
    }

    public function parentGenealogies(): HasMany
    {
        return $this->hasMany(ProductionBatchGenealogy::class, 'child_batch_id');
    }

    public function childGenealogies(): HasMany
    {
        return $this->hasMany(ProductionBatchGenealogy::class, 'parent_batch_id');
    }

    public function currentOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'current_operation_id');
    }

    public function sourceOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'source_operation_id');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ProductionOrderProgressLog::class, 'production_batch_id');
    }

    public function scraps(): HasMany
    {
        return $this->hasMany(ProductionOrderScrap::class, 'production_batch_id');
    }

    public function reworks(): HasMany
    {
        return $this->hasMany(ProductionOrderRework::class, 'production_batch_id');
    }

    public function wips(): HasMany
    {
        return $this->hasMany(ProductionWip::class, 'production_batch_id');
    }
}
