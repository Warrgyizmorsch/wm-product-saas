<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerialNumber extends BaseModel
{
    use HasFactory;

    protected $table = 'serial_numbers';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'batch_id',
        'serial_number',
        'status', // Available, Reserved, Sold, Returned, Damaged, In Transit, Scrapped
        'purchase_rate',
        'stock_transaction_id_in',
        'stock_transaction_id_out',
    ];

    protected $casts = [
        'purchase_rate' => 'float',
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

    public function transactionIn(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id_in');
    }

    public function transactionOut(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id_out');
    }
}
