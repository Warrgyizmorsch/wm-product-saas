<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    protected $table = 'stock_reservations';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'product_id',
        'warehouse_id',
        'reference_type', // Sales Order, Transfer, Manufacturing
        'reference_id',
        'reference_item_id',
        'reserved_qty',
        'status', // Active, Completed, Cancelled
        'expires_at',
    ];

    protected $casts = [
        'reserved_qty' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
