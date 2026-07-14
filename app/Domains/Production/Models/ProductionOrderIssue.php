<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
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
}
