<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** TDS deposited with the government on one challan (ITNS 281). */
class TdsChallan extends BaseModel
{
    protected $table = 'tds_challans';

    protected $fillable = [
        'tenant_id', 'company_id', 'branch_id', 'challan_number', 'bsr_code', 'cin',
        'deposit_date', 'period_month', 'tax_amount', 'interest', 'late_fee',
        'bank_account_id', 'journal_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'deposit_date' => 'date',
        'tax_amount' => 'float',
        'interest' => 'float',
        'late_fee' => 'float',
    ];

    public function deductions(): HasMany
    {
        return $this->hasMany(TdsDeduction::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'bank_account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalAmount(): float
    {
        return round($this->tax_amount + $this->interest + $this->late_fee, 2);
    }
}
