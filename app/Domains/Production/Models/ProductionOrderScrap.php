<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderScrap extends BaseModel
{
    protected $table = 'production_order_scraps';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'production_order_operation_id',
        'product_id',
        'quantity',
        'reason',
        'recorded_by',
        'recorded_at',
        'stock_transaction_id', // idempotency guard: set once when stock outflow is posted
    ];

    protected $casts = [
        'quantity'    => 'float',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getNcrAttribute()
    {
        return \App\Domains\Production\Models\ProductionNcr::where('production_order_id', $this->production_order_id)
            ->where('production_order_operation_id', $this->production_order_operation_id)
            ->where('disposition_type', 'scrap')
            ->first();
    }

    public function getDisposalAttribute()
    {
        $ncr = $this->ncr;
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
