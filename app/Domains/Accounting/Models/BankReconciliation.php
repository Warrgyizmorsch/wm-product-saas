<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Domains\Accounting\Concerns\LogsAccountingActivity;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch, LogsAccountingActivity;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    protected $table = 'bank_reconciliations';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'chart_of_account_id',
        'statement_from_date',
        'statement_date',
        'opening_balance',
        'closing_balance',
        'book_balance',
        'status',
        'brs_snapshot',
        'notes',
        'completed_by',
        'completed_at',
        'reopened_by',
        'reopened_at',
    ];

    protected $casts = [
        'statement_from_date' => 'date',
        'statement_date' => 'date',
        'opening_balance' => 'float',
        'closing_balance' => 'float',
        'book_balance' => 'float',
        'brs_snapshot' => 'array',
        'completed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'bank_reconciliation_id');
    }

    public function statementUploads(): HasMany
    {
        return $this->hasMany(BankStatementUpload::class, 'bank_reconciliation_id');
    }

    public function matchedJournalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'bank_reconciliation_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankStatementMatch::class, 'bank_reconciliation_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
