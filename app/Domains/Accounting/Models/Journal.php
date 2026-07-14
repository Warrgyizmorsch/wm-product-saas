<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Journal extends BaseModel
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SALES = 'sales';
    public const SOURCE_PURCHASE = 'purchase';
    public const SOURCE_INVENTORY = 'inventory';
    public const SOURCE_PRODUCTION = 'production';
    public const SOURCE_PAYROLL = 'payroll';

    protected $table = 'journals';

    protected $fillable = [
        'tenant_id',
        'accounting_period_id',
        'journal_number',
        'journal_date',
        'source',
        'reference_type',
        'reference_id',
        'memo',
        'status',
        'reversed_journal_id',
        'total_debit',
        'total_credit',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'total_debit' => 'float',
        'total_credit' => 'float',
        'posted_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'journal_id');
    }

    public function reversedJournal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_journal_id');
    }

    /**
     * The source business document this journal was posted for (invoice, bill, etc.),
     * resolved via reference_type/reference_id rather than a real DB-level morph
     * relation, since the source lives in another module's table.
     */
    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function isBalanced(): bool
    {
        return round($this->total_debit, 2) === round($this->total_credit, 2);
    }

    public function scopePosted(Builder $query): void
    {
        $query->where('status', self::STATUS_POSTED);
    }
}
