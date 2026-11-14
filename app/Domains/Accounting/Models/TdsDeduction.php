<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the TDS register. Bills below the threshold are kept too (with
 * tds_amount 0, reason below_threshold) so a vendor's yearly aggregate is
 * always known when a later bill crosses the limit.
 */
class TdsDeduction extends BaseModel
{
    public const REASON_NORMAL = 'normal';
    public const REASON_NO_PAN = 'no_pan';
    public const REASON_LOWER_CERT = 'lower_cert';
    public const REASON_BELOW_THRESHOLD = 'below_threshold';

    public const SOURCE_VENDOR_BILL = 'vendor_bill';
    public const SOURCE_JOURNAL = 'journal';
    // Salary TDS: one row per employee per payroll run (source_id = run id).
    public const SOURCE_PAYROLL_RUN = 'PayrollRun';

    protected $table = 'tds_deductions';

    protected $fillable = [
        'tenant_id', 'company_id', 'branch_id', 'tds_section_id',
        'party_type', 'party_id', 'party_name', 'pan', 'deductee_type',
        'source_type', 'source_id', 'source_number', 'journal_id',
        'deduction_date', 'base_amount', 'catchup_base', 'rate', 'tds_amount', 'reason',
        'fiscal_year', 'quarter', 'tds_challan_id', 'is_void',
    ];

    protected $casts = [
        'deduction_date' => 'date',
        'base_amount' => 'float',
        'catchup_base' => 'float',
        'rate' => 'float',
        'tds_amount' => 'float',
        'quarter' => 'integer',
        'is_void' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(TdsSection::class, 'tds_section_id');
    }

    public function challan(): BelongsTo
    {
        return $this->belongsTo(TdsChallan::class, 'tds_challan_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /** Not voided, and its journal (if any) has not been reversed. */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_void', false)->where(function (Builder $q) {
            $q->whereNull('journal_id')
                ->orWhereHas('journal', fn (Builder $j) => $j->where('status', '!=', Journal::STATUS_REVERSED));
        });
    }

    /** Deductions with tax actually withheld and not yet deposited. */
    public function scopeUndeposited(Builder $query): void
    {
        $query->live()->where('tds_amount', '>', 0)->whereNull('tds_challan_id');
    }

    /** "2026-27" for any date in that April-March year. */
    public static function fiscalYearFor(\DateTimeInterface|string $date): string
    {
        $date = \Illuminate\Support\Carbon::parse($date);
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return $start . '-' . substr((string) ($start + 1), -2);
    }

    /** TDS quarter: Apr-Jun = 1 ... Jan-Mar = 4. */
    public static function quarterFor(\DateTimeInterface|string $date): int
    {
        $month = \Illuminate\Support\Carbon::parse($date)->month;

        return $month >= 4 ? intdiv($month - 4, 3) + 1 : 4;
    }
}
