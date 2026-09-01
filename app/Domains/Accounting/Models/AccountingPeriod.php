<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPeriod extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_LOCKED = 'locked';

    protected $table = 'accounting_periods';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'fiscal_year_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class, 'accounting_period_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function acceptsPostings(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
