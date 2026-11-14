<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A TDS section / nature of payment (194C, 194J ...) and how it is deducted. */
class TdsSection extends BaseModel
{
    /** Salary (24Q / Form 16). Deducted by payroll at slab rates; posts to TDS Payable 2140. */
    public const SALARY_CODE = '192';

    public const PERIOD_PER_TRANSACTION = 'per_transaction';
    public const PERIOD_ANNUAL = 'annual';
    public const PERIOD_MONTHLY = 'monthly';

    /** Deductee types taxed at rate_individual; everyone else gets rate_other. */
    public const INDIVIDUAL_TYPES = ['individual', 'huf'];

    public const DEDUCTEE_TYPES = [
        'individual' => 'Individual',
        'huf' => 'HUF',
        'company' => 'Company',
        'firm' => 'Firm / LLP',
        'others' => 'Others (AOP, trust ...)',
    ];

    protected $table = 'tds_sections';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'nature_of_payment',
        'rate_individual', 'rate_other', 'rate_no_pan',
        'single_threshold', 'aggregate_threshold', 'threshold_period', 'deduct_on_excess_only',
        'payable_account_id', 'is_active',
    ];

    protected $casts = [
        'rate_individual' => 'float',
        'rate_other' => 'float',
        'rate_no_pan' => 'float',
        'single_threshold' => 'float',
        'aggregate_threshold' => 'float',
        'deduct_on_excess_only' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payable_account_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(TdsDeduction::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Sections a vendor bill can deduct under (everything except salary). */
    public function scopeNonSalary(Builder $query): void
    {
        $query->where('code', '!=', self::SALARY_CODE);
    }

    public function isSalary(): bool
    {
        return $this->code === self::SALARY_CODE;
    }

    public function rateFor(?string $deducteeType): float
    {
        return in_array($deducteeType, self::INDIVIDUAL_TYPES, true) ? $this->rate_individual : $this->rate_other;
    }

    public function label(): string
    {
        return "{$this->code} - {$this->name}";
    }
}
