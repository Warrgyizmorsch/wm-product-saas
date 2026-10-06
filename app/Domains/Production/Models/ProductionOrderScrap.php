<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\StockTransaction;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderScrap extends BaseModel
{
    use BelongsToCompany, BelongsToBranch;

    protected $table = 'production_order_scraps';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'ncr_id',
        'production_order_id',
        'production_order_operation_id',
        'production_batch_id',
        'product_id',
        'quantity',
        'reason',
        'scrap_type',
        'measurement_type',
        'length',
        'width',
        'thickness',
        'pieces',
        'weight',
        'weight_unit',
        'scrap_warehouse_id',
        'storage_location',
        'recorded_by',
        'recorded_at',
        'stock_transaction_id', // idempotency guard: set once when stock outflow is posted
    ];

    protected $casts = [
        'quantity'    => 'float',
        'length'      => 'float',
        'width'       => 'float',
        'thickness'   => 'float',
        'pieces'      => 'integer',
        'weight'      => 'float',
        'recorded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function scrapWarehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Warehouse::class, 'scrap_warehouse_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(ProductionNcr::class, 'ncr_id');
    }

    public function getNcrAttribute()
    {
        if ($this->ncr_id && $this->relationLoaded('ncr')) {
            return $this->getRelation('ncr');
        }
        if ($this->ncr_id) {
            return ProductionNcr::find($this->ncr_id);
        }
        return \App\Domains\Production\Models\ProductionNcr::where('production_order_id', $this->production_order_id)
            ->where('production_order_operation_id', $this->production_order_operation_id)
            ->where('disposition_type', 'scrap')
            ->latest()
            ->first();
    }

    public function getDisposalAttribute()
    {
        if ($this->ncr_id) {
            $disposal = ProductionScrapDisposal::where('ncr_id', $this->ncr_id)->first();
            if ($disposal) {
                return $disposal;
            }
        }
        $ncr = $this->ncr_attribute ?? $this->ncr;
        return $ncr ? \App\Domains\Production\Models\ProductionScrapDisposal::where('ncr_id', $ncr->id)->first() : null;
    }

    /**
     * The stock outflow transaction that recorded this scrap.
     * If null, the stock has not yet been posted (or was posted before this column existed).
     */
    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }

    /**
     * Returns true if the inventory stock outflow has already been posted.
     */
    public function isStockPosted(): bool
    {
        return ! is_null($this->stock_transaction_id);
    }
}
