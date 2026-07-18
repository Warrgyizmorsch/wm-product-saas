<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRfq extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_rfqs';

    protected $fillable = [
        'tenant_id',
        'rfq_number',
        'purchase_requisition_id',
        'rfq_date',
        'status', // Draft, Sent, Received, Confirmed, Cancelled
        'notes',
        'created_by',
    ];

    protected $casts = [
        'rfq_date' => 'date',
        'purchase_requisition_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRfqItem::class, 'purchase_rfq_id');
    }

    public function rfqVendors(): HasMany
    {
        return $this->hasMany(PurchaseRfqVendor::class, 'purchase_rfq_id');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
