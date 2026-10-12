<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class AccountingPeriodController extends Controller
{
    public function __construct(
        private readonly FiscalPeriodService $periods,
    ) {
    }

    public function close(AccountingPeriod $period): RedirectResponse
    {
        $this->authorize('manage', $period);

        $this->periods->closePeriod($period->id);

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', "Period '{$period->name}' closed.");
    }

    public function lock(AccountingPeriod $period): RedirectResponse
    {
        $this->authorize('manage', $period);

        $this->periods->lockPeriod($period->id);

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', "Period '{$period->name}' locked.");
    }

    public function reopen(AccountingPeriod $period): RedirectResponse
    {
        $this->authorize('reopen', $period);

        try {
            $this->periods->reopenPeriod($period->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return redirect()->route('accounting.fiscal-years.index')
            ->with('success', "Period '{$period->name}' reopened.");
    }
}
