<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bill in the bill-wise register: an invoice, a vendor bill or an opening
 * due, for one party on one control account (Receivable / Payable). What is
 * still open on it is the sum of its allocations.
 */
class BillReference extends BaseModel
{
    protected $table = 'bill_references';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'party_type',
        'party_id',
        'control_account_id',
        'ref_number',
        'bill_date',
        'due_date',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
    ];

    public function allocations(): HasMany
    {
        return $this->hasMany(BillAllocation::class, 'bill_reference_id');
    }

    public function controlAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'control_account_id');
    }
}
