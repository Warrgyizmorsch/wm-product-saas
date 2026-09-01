<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequisition extends BaseModel
{
    use BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'purchase_requisitions';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'requisition_number',
        'requested_by',
        'requisition_date',
        'expected_date',
        'status', // Draft, Approved, Cancelled
        'rejection_reason',
        'notes',
        'source_type',
        'source_id',
        'requisition_slip_number',
        'reminder_count',
        'last_reminded_at',
    ];

    protected $casts = [
        'requisition_date' => 'date',
        'expected_date' => 'date',
        'last_reminded_at' => 'datetime',
        'requested_by' => 'integer',
        'source_id' => 'integer',
        'reminder_count' => 'integer',
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

    public function reminders(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ApprovalReminder::class, 'remindable')->orderBy('created_at', 'desc');
    }
}
