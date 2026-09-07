<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Domains\Accounting\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends BaseModel
{
    protected $table = 'asset_categories';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'fixed_asset_account_id',
        'accumulated_depreciation_account_id',
        'depreciation_expense_account_id',
        'gain_on_disposal_account_id',
        'loss_on_disposal_account_id',
        'default_depreciation_method',
        'default_useful_life_months',
        'default_residual_value_percent',
        'capitalization_threshold',
        'status',
        'is_production_machinery',
    ];

    protected $casts = [
        'default_useful_life_months' => 'integer',
        'default_residual_value_percent' => 'decimal:2',
        'capitalization_threshold' => 'decimal:2',
        'is_production_machinery' => 'boolean',
    ];

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'fixed_asset_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_depreciation_account_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_expense_account_id');
    }

    public function gainOnDisposalAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gain_on_disposal_account_id');
    }

    public function lossOnDisposalAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'loss_on_disposal_account_id');
    }

    /**
     * Get the company that owns the category.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the assets belonging to this category.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_category_id');
    }
}
