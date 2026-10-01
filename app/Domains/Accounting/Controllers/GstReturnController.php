<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\GstReturnFiling;
use App\Domains\Accounting\Services\Gst\Gstr1ReturnService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Upload GST Returns → GSTR-1, modelled on Tally Prime: the vouchers of a
 * return period still to go to the portal, Export (Offline) as the portal's
 * JSON, and the upload status of each voucher.
 */
class GstReturnController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly Gstr1ReturnService $gstr1,
    ) {
    }

    public function gstr1(Request $request): View
    {
        $this->authorizeFor('accounting.gst_returns.view');
        [$from, $to] = $this->period($request);

        return view('modules.accounting.gst-returns.gstr1', [
            'built' => $this->gstr1->build($from, $to),
            'includeUploaded' => $request->boolean('include_uploaded'),
            'canFile' => $this->allows('accounting.gst_returns.file'),
            'filings' => GstReturnFiling::query()
                ->with('creator:id,name')
                ->where('return_type', Gstr1ReturnService::RETURN_TYPE)
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function export(Request $request): Response
    {
        $this->authorizeFor('accounting.gst_returns.file');
        [$from, $to] = $this->period($request);
        $data = $request->validate([
            'keys' => ['nullable', 'array'],
            'keys.*' => ['string'],
            'mark_uploaded' => ['nullable', 'boolean'],
            'scope' => ['nullable', 'in:all,selected'],
        ]);

        $keys = ($data['scope'] ?? 'all') === 'selected' ? ($data['keys'] ?? []) : null;

        if ($keys === []) {
            return back()->with('error', 'Select the vouchers to export, or export all pending vouchers.');
        }

        try {
            $filing = $this->gstr1->export($this->gstr1->build($from, $to), $keys, auth()->id(), $request->boolean('mark_uploaded', true));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->download($filing);
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $this->authorizeFor('accounting.gst_returns.file');
        [$from, $to] = $this->period($request);
        $data = $request->validate([
            'action' => ['required', 'in:mark_uploaded,reset,delete_request,reset_delete'],
            'keys' => ['required', 'array', 'min:1'],
            'keys.*' => ['string'],
        ], ['keys.required' => 'Select at least one voucher.']);

        try {
            $count = match ($data['action']) {
                'mark_uploaded' => $this->gstr1->markUploaded($this->gstr1->build($from, $to), $data['keys'], auth()->id()),
                'reset' => $this->gstr1->resetStatus($data['keys']),
                'delete_request' => $this->gstr1->requestDelete($data['keys']),
                'reset_delete' => $this->gstr1->resetDeleteRequest($data['keys']),
            };
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = match ($data['action']) {
            'mark_uploaded' => "{$count} voucher(s) marked as uploaded.",
            'reset' => "{$count} voucher(s) set back to pending.",
            'delete_request' => "{$count} voucher(s) will be deleted from the portal with the next upload.",
            'reset_delete' => "Delete request withdrawn for {$count} voucher(s).",
        };

        return back()->with($count > 0 ? 'success' : 'error', $count > 0 ? $message : 'None of the selected vouchers could be changed.');
    }

    public function downloadFiling(GstReturnFiling $filing): Response
    {
        $this->authorizeFor('accounting.gst_returns.view');
        abort_if($filing->payload === null, 404);

        return $this->download($filing);
    }

    private function download(GstReturnFiling $filing): Response
    {
        return response($filing->payload, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . ($filing->file_name ?: 'GSTR1.json') . '"',
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Like Tally, default to the previous month: that is the return being filed.
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : now()->subMonthNoOverflow()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : $from->copy()->endOfMonth();

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function authorizeFor(string $permission): void
    {
        abort_unless($this->allows($permission), 403);
    }

    private function allows(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $this->access->allows($user, $permission, [
            'tenant_id' => $user->tenant_id,
            'company_id' => current_company_id(),
            'branch_id' => current_branch_id(),
        ]);
    }
}
