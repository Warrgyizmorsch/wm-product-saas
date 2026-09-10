<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Repositories\BudgetRepositoryInterface;
use App\Domains\Accounting\Services\BudgetService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\HRMS\Models\Department;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgets,
        private readonly ChartOfAccountsService $accounts,
        private readonly BudgetRepositoryInterface $budgetRepository,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Budget::class);

        return view('modules.accounting.budgets.index', [
            'budgets' => $this->budgetRepository->all(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Budget::class);

        return view('modules.accounting.budgets.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Budget::class);

        $validated = $this->validated($request);

        try {
            $budget = $this->budgets->create([
                'fiscal_year_id' => $validated['fiscal_year_id'],
                'name' => $validated['name'],
                'created_by' => auth()->id(),
            ], $validated['lines']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.budgets.index')
            ->with('success', "Budget '{$budget->name}' created as draft.");
    }

    public function edit(Budget $budget): View
    {
        $this->authorize('update', $budget);

        abort_unless($budget->isEditable(), 403, "Budget '{$budget->name}' is {$budget->status} and can no longer be edited.");

        $budget->load(['lines']);

        return view('modules.accounting.budgets.edit', $this->formData() + ['budget' => $budget]);
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $validated = $this->validated($request);

        try {
            $this->budgets->update($budget, [
                'fiscal_year_id' => $validated['fiscal_year_id'],
                'name' => $validated['name'],
            ], $validated['lines']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.budgets.index')
            ->with('success', "Budget '{$budget->name}' updated.");
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        try {
            $this->budgets->delete($budget);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('accounting.budgets.index')->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.budgets.index')
            ->with('success', 'Budget deleted successfully.');
    }

    public function approve(Budget $budget): RedirectResponse
    {
        $this->authorize('approve', $budget);

        try {
            $this->budgets->approve($budget, auth()->id());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('accounting.budgets.index')->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.budgets.index')
            ->with('success', "Budget '{$budget->name}' approved.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'fiscal_year_id' => ['required', 'integer', 'exists:accounting_fiscal_years,id'],
            'name' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'lines.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'lines.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);
    }

    private function formData(): array
    {
        return [
            'fiscalYears' => FiscalYear::orderByDesc('start_date')->get(),
            'accounts' => $this->accounts->active(),
            'costCenters' => CostCenter::active()->orderBy('code')->get(),
            'departments' => Department::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ];
    }
}
