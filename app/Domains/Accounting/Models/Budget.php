<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Domains\Accounting\Concerns\LogsAccountingActivity;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch, LogsAccountingActivity;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_LOCKED = 'locked';

    protected $table = 'budgets';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'fiscal_year_id',
        'name',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'budget_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
