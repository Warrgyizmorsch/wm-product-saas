<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    protected $table = 'budget_lines';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'budget_id',
        'chart_of_account_id',
        'cost_center_id',
        'department_id',
        'project_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    /**
     * The dimension this line is scoped to, if any — a budget line carries
     * at most one of cost_center_id/department_id/project_id (enforced by
     * BudgetService, not the schema).
     *
     * @return array{type: string, id: int}|null
     */
    public function dimension(): ?array
    {
        return match (true) {
            $this->cost_center_id !== null => ['type' => 'cost_center', 'id' => $this->cost_center_id],
            $this->department_id !== null => ['type' => 'department', 'id' => $this->department_id],
            $this->project_id !== null => ['type' => 'project', 'id' => $this->project_id],
            default => null,
        };
    }
}
