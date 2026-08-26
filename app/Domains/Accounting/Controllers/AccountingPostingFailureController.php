<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\AccountingPostingFailure;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountingPostingFailureController extends Controller
{
    public function __construct(
        private readonly PostingFailureRecorder $recorder,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Journal::class);

        $failures = AccountingPostingFailure::query()
            ->unresolved()
            ->latest('occurred_at')
            ->get();

        return view('modules.accounting.posting-failures.index', compact('failures'));
    }

    public function retry(AccountingPostingFailure $failure): RedirectResponse
    {
        $this->authorize('post', Journal::class);

        try {
            $succeeded = $this->recorder->retry($failure);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['retry' => $e->getMessage()]);
        }

        return $succeeded
            ? back()->with('success', 'Posting retried successfully — the journal is now on the ledger.')
            : back()->withErrors(['retry' => 'Retry failed again — the underlying issue (e.g. a closed period or missing account) still needs fixing.']);
    }

    public function dismiss(AccountingPostingFailure $failure): RedirectResponse
    {
        $this->authorize('post', Journal::class);

        $failure->update([
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        return back()->with('success', 'Marked resolved. This does not create a journal — use it only if you already corrected the books manually.');
    }
}
