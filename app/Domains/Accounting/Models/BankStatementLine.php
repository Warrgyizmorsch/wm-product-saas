<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementLine extends BaseModel
{
    use HasFactory;

    protected $table = 'bank_statement_lines';

    protected $fillable = [
        'tenant_id',
        'bank_reconciliation_id',
        'bank_statement_upload_id',
        'transaction_date',
        'description',
        'reference',
        'suggested_ledger',
        'amount',
        'balance',
        'import_hash',
        'is_matched',
        'matched_journal_entry_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'float',
        'balance' => 'float',
        'is_matched' => 'boolean',
    ];

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function bankStatementUpload(): BelongsTo
    {
        return $this->belongsTo(BankStatementUpload::class, 'bank_statement_upload_id');
    }

    public function matchedJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'matched_journal_entry_id');
    }

    /**
     * Every ledger entry this line cleared — one for a plain match, several
     * when one deposit covers several receipts.
     */
    public function matches(): HasMany
    {
        return $this->hasMany(BankStatementMatch::class, 'bank_statement_line_id');
    }

    public function isDeposit(): bool
    {
        return (float) $this->amount > 0;
    }

    public function scopeMatched(Builder $query): void
    {
        $query->where('is_matched', true);
    }

    public function scopeUnmatched(Builder $query): void
    {
        $query->where('is_matched', false);
    }
}
