<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\JournalApprovalSettings;
use App\Domains\Accounting\Services\JournalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Maker-checker queue: manual journals and vouchers waiting for a second
 * person, plus the tenant's approval settings. Tenant isolation comes from
 * Journal's global tenant scope (route-model binding included); permissions
 * from JournalPolicy → AccessService.
 */
class JournalApprovalController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly JournalApprovalSettings $settings,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('approve', Journal::class);

        return view('modules.accounting.approvals.index', [
            'pending' => $this->journals->pendingApproval(20),
            'settings' => $this->settings->for(tenant_id()),
            'canConfigure' => $request->user()->can('configureApprovals', Journal::class),
            'currentUserId' => (int) $request->user()->id,
        ]);
    }

    public function approve(Request $request, Journal $journal): RedirectResponse
    {
        $this->authorize('approve', $journal);

        try {
            $approved = $this->journals->approve($journal->id, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$approved->journal_number} approved and posted to the ledger.");
    }

    public function reject(Request $request, Journal $journal): RedirectResponse
    {
        $this->authorize('approve', $journal);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $rejected = $this->journals->reject($journal->id, (int) $request->user()->id, $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$rejected->journal_number} rejected. It will not post.");
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('configureApprovals', Journal::class);

        $validated = $request->validate([
            'threshold' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        $this->settings->update(tenant_id(), [
            'enabled' => $request->boolean('enabled'),
            'threshold' => $validated['threshold'],
            'approvers_post_directly' => $request->boolean('approvers_post_directly'),
        ]);

        return back()->with('success', 'Approval settings saved.');
    }
}
