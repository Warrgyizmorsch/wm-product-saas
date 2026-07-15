<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\StockTransaction;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderIssueBatch extends BaseModel
{
    protected $table = 'production_order_issue_batches';

    protected $fillable = [
        'tenant_id',
        'production_order_issue_id',
        'inventory_batch_id',
        'quantity',
        'stock_transaction_id',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderIssue::class, 'production_order_issue_id');
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'inventory_batch_id');
    }

    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }
}
