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

    public const PARTY_CUSTOMER = 'customer';
    public const PARTY_VENDOR = 'vendor';

    protected $table = 'journal_entries';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'journal_id',
        'chart_of_account_id',
        'cost_center_id',
        'party_type',
        'party_id',
        'debit',
        'credit',
        'foreign_debit',
        'foreign_credit',
        'description',
        'is_reconciled',
        'bank_date',
        'reconciled_at',
        'bank_reconciliation_id',
    ];

    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
        'foreign_debit' => 'float',
        'foreign_credit' => 'float',
        'is_reconciled' => 'boolean',
        'bank_date' => 'date',
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
     * The bank statement line that cleared this entry, if it has been reconciled.
     */
    public function statementMatch(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BankStatementMatch::class, 'journal_entry_id');
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
