<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\AccountingPostingFailure;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingPostingFailureController extends Controller
{
    private const SORTABLE = ['occurred_at', 'model_class'];

    public function __construct(
        private readonly PostingFailureRecorder $recorder,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Journal::class);

        $filters = $request->only(['search', 'model_class', 'sort', 'direction']);

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'occurred_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $failures = AccountingPostingFailure::query()
            ->unresolved()
            ->when($filters['model_class'] ?? null, fn ($q, $modelClass) => $q->where('model_class', $modelClass))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")->orWhere('model_class', 'like', "%{$search}%");
            }))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $modelClasses = AccountingPostingFailure::query()
            ->unresolved()
            ->distinct()
            ->orderBy('model_class')
            ->pluck('model_class');

        return view('modules.accounting.posting-failures.index', [
            'failures' => $failures,
            'filters' => $filters,
            'modelClasses' => $modelClasses,
        ]);
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
