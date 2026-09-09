<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    protected $table = 'journal_entries';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'journal_id',
        'chart_of_account_id',
        'cost_center_id',
        'debit',
        'credit',
        'description',
        'is_reconciled',
        'reconciled_at',
        'bank_reconciliation_id',
    ];

    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
        'is_reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    /**
     * Signed movement on this line alone, matching a bank statement's sign
     * convention (positive = deposit, negative = withdrawal) when the line
     * sits on a debit-normal cash/bank account.
     */
    public function signedAmount(): float
    {
        return round($this->debit - $this->credit, 2);
    }
}
