<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransaction extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    protected $table = 'stock_transactions';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
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

    public function getDocumentNumberAttribute(): string
    {
        if (!$this->reference_type || !$this->reference_id) {
            return $this->reference_type ?: 'GRN / Opening Stock';
        }

        return match ($this->reference_type) {
            'SalesReturn' => \App\Domains\Sales\Models\SalesReturn::where('id', $this->reference_id)->value('return_number') ?: "RET-{$this->reference_id}",
            'DispatchOrder', 'Dispatch' => \App\Domains\Sales\Models\DispatchOrder::where('id', $this->reference_id)->value('dispatch_number') ?: "DO-{$this->reference_id}",
            'MaterialRequirement', 'DeliveryOrder' => \App\Domains\Sales\Models\MaterialRequirement::where('id', $this->reference_id)->value('mr_number') ?: "MR-{$this->reference_id}",
            'SalesOrder' => \App\Domains\Sales\Models\SalesOrder::where('id', $this->reference_id)->value('sales_order_number') ?: "SO-{$this->reference_id}",
            'GoodsReceiptNote', 'GRN', 'Purchase Receipt' => \App\Domains\Purchase\Models\GoodsReceiptNote::where('id', $this->reference_id)->value('grn_number') ?: (\App\Domains\Purchase\Models\PurchaseOrder::where('id', $this->reference_id)->value('po_number') ?: "GRN-{$this->reference_id}"),
            'PurchaseOrder' => \App\Domains\Purchase\Models\PurchaseOrder::where('id', $this->reference_id)->value('po_number') ?: "PO-{$this->reference_id}",
            default => "{$this->reference_type} #{$this->reference_id}",
        };
    }
}
