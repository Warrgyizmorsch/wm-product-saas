<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How (part of) one Receivable / Payable journal line is explained:
 * opening a bill (new_ref), settling one (against_ref), or money not yet
 * matched to a bill (on_account / advance). Amount is signed like the
 * ledger (+ debit, - credit), so a party's allocations sum to its balance.
 */
class BillAllocation extends BaseModel
{
    public const NEW_REF = 'new_ref';
    public const AGAINST_REF = 'against_ref';
    public const ADVANCE = 'advance';
    public const ON_ACCOUNT = 'on_account';

    public const TYPES = [self::NEW_REF, self::AGAINST_REF, self::ADVANCE, self::ON_ACCOUNT];

    protected $table = 'bill_allocations';

    protected $fillable = [
        'tenant_id',
        'journal_entry_id',
        'bill_reference_id',
        'party_type',
        'party_id',
        'control_account_id',
        'ref_type',
        'amount',
        'allocated_on',
        'settlement_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocated_on' => 'date',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(BillReference::class, 'bill_reference_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
