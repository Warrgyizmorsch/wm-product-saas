<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\JournalService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BankBookController extends Controller
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

        // ChartOfAccount doesn't carry a separate "cash" vs "bank" subtype, only
        // the shared is_cash_or_bank flag — so every cash/bank ledger is offered
        // here and the user picks which one this Bank Book statement is for
        // (unlike Cash Book, which is normally a single Cash-in-Hand account).
        $bankAccounts = $this->accounts->active()->where('is_cash_or_bank', true)->values();

        $accountId = $request->filled('account_id')
            ? (int) $request->input('account_id')
            : $bankAccounts->first()?->id;

        $account = $accountId ? $bankAccounts->firstWhere('id', $accountId) : null;

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : now();

        $ledger = $account ? $this->journals->forAccount($account, $from, $to) : ['opening' => 0.0, 'entries' => collect(), 'closing' => 0.0];

        return view('modules.accounting.reports.bank-book', [
            'bankAccounts' => $bankAccounts,
            'account' => $account,
            'from' => $from,
            'to' => $to,
            'ledger' => $ledger,
        ]);
    }
}
