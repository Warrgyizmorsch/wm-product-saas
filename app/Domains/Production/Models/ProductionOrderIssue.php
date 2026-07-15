<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderIssue extends BaseModel
{
    protected $table = 'production_order_issues';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'reservation_id',
        'product_id',
        'warehouse_id',
        'inventory_batch_id', // FK to Inventory::Batch consumed (nullOnDelete — see migration)
        'quantity_issued',
        'issue_type',
        'issued_by',
        'issued_at',
        'remarks',
    ];

    protected $casts = [
        'quantity_issued' => 'float',
        'issued_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderReservation::class, 'reservation_id');
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
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * The Inventory Batch that was consumed during this material issue.
     * Populated by ProductionMaterialService::issueMaterial() when the product
     * uses batch tracking. FK is nullOnDelete — does NOT delete this issue record
     * if the inventory batch is removed.
     */
    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'inventory_batch_id');
    }

    public function batches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductionOrderIssueBatch::class, 'production_order_issue_id');
    }
}
