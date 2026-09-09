<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends BaseModel
{
    use HasFactory;

    protected $table = 'bank_statement_lines';

    protected $fillable = [
        'tenant_id',
        'bank_reconciliation_id',
        'transaction_date',
        'description',
        'amount',
        'is_matched',
        'matched_journal_entry_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'float',
        'is_matched' => 'boolean',
    ];

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function matchedJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'matched_journal_entry_id');
    }

    public function scopeUnmatched(Builder $query): void
    {
        $query->where('is_matched', false);
    }
}
