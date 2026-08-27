<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMaintenanceWorkOrderSpare extends BaseModel
{
    use HasFactory;

    protected $table = 'production_maintenance_work_order_spares';

    protected $fillable = [
        'tenant_id',
        'maintenance_work_order_id',
        'product_id',
        'warehouse_id',
        'requested_qty',
        'issued_qty',
        'unit_cost',
        'total_cost',
        'stock_transaction_id',
    ];

    protected $casts = [
        'requested_qty' => 'decimal:4',
        'issued_qty'    => 'decimal:4',
        'unit_cost'     => 'decimal:2',
        'total_cost'    => 'decimal:2',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionMaintenanceWorkOrder::class, 'maintenance_work_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
    }
}
