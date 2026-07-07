<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransaction extends BaseModel
{
    use HasFactory;

    protected $table = 'stock_transactions';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'batch_id',
        'type', // IN, OUT
        'reference_type', // Opening Stock, GRN, Invoice, Stock Adjustment, Transfer, Manufacturing
        'reference_id',
        'quantity',
        'unit_cost',
        'total_value',
        'balance_qty',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
        'total_value' => 'float',
        'balance_qty' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function incomingSerials(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'stock_transaction_id_in');
    }

    public function outgoingSerials(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'stock_transaction_id_out');
    }
}
