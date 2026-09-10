<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Repositories\BudgetRepositoryInterface;
use App\Domains\Accounting\Services\BudgetService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetVsActualController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgetService,
        private readonly BudgetRepositoryInterface $budgets,
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.budgets.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $allBudgets = $this->budgets->all()->where('status', '!=', Budget::STATUS_DRAFT)->values();

        $budgetId = $request->filled('budget_id') ? $request->integer('budget_id') : $allBudgets->first()?->id;
        $budget = $budgetId ? $this->budgets->findWithLines($budgetId) : null;

        $rows = $budget ? $this->budgetService->actualVsBudget($budget) : [];

        $summary = [
            BudgetService::STATUS_OK => 0,
            BudgetService::STATUS_WARNING => 0,
            BudgetService::STATUS_OVER => 0,
        ];

        foreach ($rows as $row) {
            if ($row['has_actuals']) {
                $summary[$row['status']]++;
            }
        }

        return view('modules.accounting.reports.budget-vs-actual', [
            'allBudgets' => $allBudgets,
            'budget' => $budget,
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }
}
