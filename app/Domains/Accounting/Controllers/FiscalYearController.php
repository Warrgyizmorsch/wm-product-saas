<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FiscalYearController extends Controller
{
    public function __construct(
        private readonly FiscalPeriodService $periods,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', FiscalYear::class);

        $fiscalYears = FiscalYear::with('periods')->orderByDesc('start_date')->get();

        return view('modules.accounting.fiscal-years.index', [
            'fiscalYears' => $fiscalYears,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', FiscalYear::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $this->periods->createFiscalYearWithMonthlyPeriods($validated + ['created_by' => auth()->id()]);

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', 'Fiscal year created with monthly periods.');
    }

    public function close(FiscalYear $fiscalYear): RedirectResponse
    {
        $this->authorize('close', $fiscalYear);

        $this->periods->closeFiscalYear($fiscalYear->id);

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', 'Fiscal year closed.');
    }
}
