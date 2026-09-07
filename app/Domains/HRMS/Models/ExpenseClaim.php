<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseClaim extends Model
{
    protected $table = 'expense_claims';

    protected $fillable = [
        'expense_report_id',
        'expense_category_id',
        'expense_date',
        'amount',
        'tax_amount',
        'merchant',
        'description',
        'receipt_path',
        'status',
        'approved_amount',
        'rejection_reason',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
    ];

    /**
     * Get array of receipt file paths.
     */
    public function getReceiptPathsAttribute(): array
    {
        if (empty($this->receipt_path)) {
            return [];
        }
        if (str_starts_with($this->receipt_path, '[') || str_starts_with($this->receipt_path, '{')) {
            $decoded = json_decode($this->receipt_path, true);
            return is_array($decoded) ? $decoded : [$this->receipt_path];
        }
        return [$this->receipt_path];
    }

    /**
     * Claim belongs to an Expense Report.
     */
    public function report()
    {
        return $this->belongsTo(ExpenseReport::class, 'expense_report_id');
    }

    /**
     * Claim belongs to an Expense Category.
     */
    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
