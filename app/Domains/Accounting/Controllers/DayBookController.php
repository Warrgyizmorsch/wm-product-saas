<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Services\JournalService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DayBookController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : now();

        $journals = $this->journals->forDate($date);

        $totalDebit = $journals->sum('total_debit');
        $totalCredit = $journals->sum('total_credit');

        return view('modules.accounting.reports.day-book', [
            'date' => $date,
            'journals' => $journals,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
        ]);
    }
}
