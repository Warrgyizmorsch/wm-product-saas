<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\JournalService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CashBookController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountsService $accounts,
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $cashAccounts = $this->accounts->active()->where('is_cash_or_bank', true)->values();

        $accountId = $request->filled('account_id')
            ? (int) $request->input('account_id')
            : $cashAccounts->first()?->id;

        $account = $accountId ? $cashAccounts->firstWhere('id', $accountId) : null;

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : now();

        $ledger = $account ? $this->journals->forAccount($account, $from, $to) : ['opening' => 0.0, 'entries' => collect(), 'closing' => 0.0];

        return view('modules.accounting.reports.cash-book', [
            'cashAccounts' => $cashAccounts,
            'account' => $account,
            'from' => $from,
            'to' => $to,
            'ledger' => $ledger,
        ]);
    }
}
