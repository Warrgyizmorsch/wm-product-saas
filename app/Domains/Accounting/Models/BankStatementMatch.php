<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ledger entry cleared by one bank statement line. A line can have
 * several of these (a deposit slip covering several receipts); a ledger entry
 * can only ever have one (unique journal_entry_id).
 */
class BankStatementMatch extends BaseModel
{
    public const METHOD_REFERENCE = 'reference'; // auto: cheque/UTR + amount
    public const METHOD_AMOUNT = 'amount';       // auto: amount + nearest date
    public const METHOD_RULE = 'rule';           // auto: narration rule posted a voucher
    public const METHOD_MANUAL = 'manual';       // a person picked the entries
    public const METHOD_POSTED = 'posted';       // a person posted a new voucher for the line

    public const AUTOMATIC_METHODS = [self::METHOD_REFERENCE, self::METHOD_AMOUNT, self::METHOD_RULE];

    protected $table = 'bank_statement_matches';

    protected $fillable = [
        'tenant_id',
        'bank_reconciliation_id',
        'bank_statement_line_id',
        'journal_entry_id',
        'amount',
        'method',
        'matched_by',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}
