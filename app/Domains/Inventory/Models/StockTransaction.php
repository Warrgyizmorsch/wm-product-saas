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
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withoutGlobalScopes();
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
        if (!$this->reference_id) {
            return $this->reference_type ?: 'Opening Stock';
        }

        return match ($this->reference_type) {
            'GoodsReceiptNote', 'GRN', 'Purchase Receipt' => 
                \App\Domains\Purchase\Models\GoodsReceiptNote::where('id', $this->reference_id)->value('grn_number')
                ?: (\App\Domains\Purchase\Models\PurchaseOrder::where('id', $this->reference_id)->value('po_number') ?: "GRN-{$this->reference_id}"),

            'DispatchOrder', 'Dispatch' => 
                \App\Domains\Sales\Models\DispatchOrder::where('id', $this->reference_id)->value('dispatch_number') ?: "DO-{$this->reference_id}",

            'MaterialRequirement', 'DeliveryOrder' => 
                \App\Domains\Sales\Models\MaterialRequirement::where('id', $this->reference_id)->value('mr_number') ?: "MR-{$this->reference_id}",

            'MaterialRequest' => 
                \App\Domains\Sales\Models\MaterialRequest::where('id', $this->reference_id)->value('request_number') ?: "REQ-{$this->reference_id}",

            'StockTransfer', 'Transfer' => 
                \App\Domains\Inventory\Models\StockTransfer::where('id', $this->reference_id)->value('transfer_number') ?: "TRF-{$this->reference_id}",

            'StockAdjustment', 'Adjustment' => 
                \App\Domains\Inventory\Models\StockAdjustment::where('id', $this->reference_id)->value('adjustment_number') ?: "ADJ-{$this->reference_id}",

            'SalesOrder' => 
                \App\Domains\Sales\Models\SalesOrder::where('id', $this->reference_id)->value('sales_order_number') ?: "SO-{$this->reference_id}",

            'Invoice' => 
                \App\Domains\Sales\Models\Invoice::where('id', $this->reference_id)->value('invoice_number') ?: "INV-{$this->reference_id}",

            'SalesReturn' => 
                \App\Domains\Sales\Models\SalesReturn::where('id', $this->reference_id)->value('return_number') ?: "RET-{$this->reference_id}",

            'PurchaseOrder' => 
                \App\Domains\Purchase\Models\PurchaseOrder::where('id', $this->reference_id)->value('po_number') ?: "PO-{$this->reference_id}",

            'PurchaseReturn' => 
                \App\Domains\Purchase\Models\PurchaseReturn::where('id', $this->reference_id)->value('return_number') ?: "PRET-{$this->reference_id}",

            'ProductionOrder', 'WorkOrder', 'Manufacturing', 'Production', 'ProductionMaterial' => 
                \App\Domains\Production\Models\ProductionOrder::where('id', $this->reference_id)->value('work_order_number') ?: "WO-{$this->reference_id}",

            'MaintenanceWorkOrder', 'MaintenanceSpare' => 
                \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::where('id', $this->reference_id)->value('wo_number') ?: "MWO-{$this->reference_id}",

            default => "{$this->reference_type} #{$this->reference_id}",
        };
    }

    public function getDocumentUrlAttribute(): ?string
    {
        if (!$this->reference_id) {
            if ($this->reference_type === 'Opening Stock' && $this->product_id) {
                return \Route::has('inventory.products.show') ? route('inventory.products.show', $this->product_id) : null;
            }
            return null;
        }

        try {
            return match ($this->reference_type) {
                'GoodsReceiptNote', 'GRN', 'Purchase Receipt' => 
                    \Route::has('grns.show') ? route('grns.show', $this->reference_id) : (\Route::has('purchase.grns.show') ? route('purchase.grns.show', $this->reference_id) : url('/grns/' . $this->reference_id)),

                'DispatchOrder', 'Dispatch' => 
                    \Route::has('inventory.dispatches.show') ? route('inventory.dispatches.show', $this->reference_id) : (\Route::has('sales.dispatches.show') ? route('sales.dispatches.show', $this->reference_id) : url('/inventory/dispatches/' . $this->reference_id)),

                'MaterialRequirement', 'DeliveryOrder' => 
                    \Route::has('inventory.material-requirements.show') ? route('inventory.material-requirements.show', $this->reference_id) : (\Route::has('sales.material-requirements.show') ? route('sales.material-requirements.show', $this->reference_id) : url('/inventory/material-requirements/' . $this->reference_id)),

                'MaterialRequest' => 
                    \Route::has('inventory.material-requests.show') ? route('inventory.material-requests.show', $this->reference_id) : url('/inventory/material-requests/' . $this->reference_id),

                'StockTransfer', 'Transfer' => 
                    \Route::has('inventory.transfers.show') ? route('inventory.transfers.show', $this->reference_id) : url('/inventory/transfers/' . $this->reference_id),

                'StockAdjustment', 'Adjustment' => 
                    \Route::has('inventory.adjustments.show') ? route('inventory.adjustments.show', $this->reference_id) : url('/inventory/adjustments/' . $this->reference_id),

                'SalesOrder' => 
                    \Route::has('sales.orders.show') ? route('sales.orders.show', $this->reference_id) : url('/sales/orders/' . $this->reference_id),

                'Invoice' => 
                    \Route::has('sales.invoices.show') ? route('sales.invoices.show', $this->reference_id) : url('/sales/invoices/' . $this->reference_id),

                'SalesReturn' => 
                    \Route::has('sales.returns.show') ? route('sales.returns.show', $this->reference_id) : url('/sales/returns/' . $this->reference_id),

                'PurchaseOrder' => 
                    \Route::has('purchase.orders.show') ? route('purchase.orders.show', $this->reference_id) : url('/purchase/orders/' . $this->reference_id),

                'PurchaseReturn' => 
                    \Route::has('purchase.returns.show') ? route('purchase.returns.show', $this->reference_id) : url('/purchase/returns/' . $this->reference_id),

                'ProductionOrder', 'WorkOrder', 'Manufacturing', 'Production', 'ProductionMaterial' => 
                    \Route::has('production.orders.show') ? route('production.orders.show', $this->reference_id) : url('/production/orders/' . $this->reference_id),

                'MaintenanceWorkOrder', 'MaintenanceSpare' => 
                    \Route::has('production.maintenance.work-orders.show') ? route('production.maintenance.work-orders.show', $this->reference_id) : url('/production/maintenance/work-orders/' . $this->reference_id),

                default => null,
            };
        } catch (\Throwable $e) {
            return null;
        }
    }
}
