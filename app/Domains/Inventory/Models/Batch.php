<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    protected $table = 'batches';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'product_id',
        'warehouse_id',
        'batch_number',
        'quantity',
        'available_qty',
        'manufacturing_date',
        'expiry_date',
    ];

    protected $casts = [
        'quantity' => 'float',
        'available_qty' => 'float',
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'batch_id');
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'batch_id');
    }
}
