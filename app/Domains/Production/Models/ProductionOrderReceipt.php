<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderReceipt extends BaseModel
{
    protected $table = 'production_order_receipts';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'product_id',
        'warehouse_id',
        'inventory_batch_id',  // FK to Inventory::Batch created by StockService (nullOnDelete — see migration)
        'serial_numbers',      // Immutable JSON snapshot of serial strings at receipt time
        'quantity_received',
        'quality_status',
        'received_by',
        'received_at',
        'remarks',
    ];

    protected $casts = [
        'quantity_received' => 'float',
        'received_at'       => 'datetime',
        'serial_numbers'    => 'array',    // Snapshot only; authoritative ledger is inventory serial_numbers table
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * The Inventory Batch record created by StockService::recordInflow().
     * May be null for old receipts or products not using batch tracking.
     * FK is nullOnDelete — deleting the inventory batch does NOT delete this receipt.
     */
    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'inventory_batch_id');
    }
}
