<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\YearEndClosingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class FiscalYearController extends Controller
{
    public function __construct(
        private readonly FiscalPeriodService $periods,
        private readonly YearEndClosingService $yearEnd,
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

        try {
            $this->periods->createFiscalYearWithMonthlyPeriods($validated + ['created_by' => auth()->id()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['start_date' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', 'Fiscal year created with monthly periods.');
    }

    public function close(FiscalYear $fiscalYear): RedirectResponse
    {
        $this->authorize('close', $fiscalYear);

        try {
            $this->yearEnd->close($fiscalYear, auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['fiscal_year' => $e->getMessage()]);
        }

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', "Fiscal year {$fiscalYear->name} closed. Its profit or loss has been moved to Reserves & Surplus.");
    }

    public function reopen(FiscalYear $fiscalYear): RedirectResponse
    {
        $this->authorize('reopen', $fiscalYear);

        try {
            $this->yearEnd->reopen($fiscalYear, auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['fiscal_year' => $e->getMessage()]);
        }

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', "Fiscal year {$fiscalYear->name} reopened. Reopen the period you need to post into.");
    }
}
