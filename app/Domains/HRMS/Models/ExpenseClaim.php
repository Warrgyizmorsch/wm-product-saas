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
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

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
