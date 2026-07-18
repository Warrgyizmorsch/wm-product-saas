<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequisition extends BaseModel
{
    use HasFactory;

    protected $table = 'purchase_requisitions';

    protected $fillable = [
        'tenant_id',
        'requisition_number',
        'requested_by',
        'requisition_date',
        'status', // Draft, Approved, Cancelled
        'notes',
        'source_type',
        'source_id',
        'requisition_slip_number',
    ];

    protected $casts = [
        'requisition_date' => 'date',
        'requested_by' => 'integer',
        'source_id' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class, 'purchase_requisition_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function sourceable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }
}
