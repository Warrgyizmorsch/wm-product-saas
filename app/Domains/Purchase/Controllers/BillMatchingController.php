<?php

namespace App\Domains\Purchase\Controllers;

use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Services\ThreeWayMatchService;
use App\Domains\Purchase\Services\ThreeWayMatchSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * 3-way match screen: tenant settings, bills on hold, and bills flagged with
 * exceptions. Tenant isolation via VendorBill's global scope (route binding
 * included); permissions via VendorBillPolicy → AccessService.
 */
class BillMatchingController extends Controller
{
    public function __construct(
        private readonly ThreeWayMatchService $matcher,
        private readonly ThreeWayMatchSettings $settings,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VendorBill::class);

        $bills = VendorBill::query()
            ->with(['vendor', 'creator'])
            ->where(fn ($q) => $q
                ->where('status', VendorBill::STATUS_ON_HOLD)
                ->orWhere('match_status', VendorBill::MATCH_EXCEPTION))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [VendorBill::STATUS_ON_HOLD])
            ->latest('bill_date')
            ->latest('id')
            ->paginate(20);

        return view('modules.purchase.bill-matching.index', [
            'bills' => $bills,
            'settings' => $this->settings->for(tenant_id()),
            'modes' => [
                ThreeWayMatchSettings::MODE_OFF => 'Off — don\'t check bills',
                ThreeWayMatchSettings::MODE_WARN => 'Warn — flag mismatches, post as usual',
                ThreeWayMatchSettings::MODE_HOLD => 'Hold — keep mismatched bills out of the ledger until released',
            ],
            'canConfigure' => $request->user()->can('configureMatching', VendorBill::class),
            'canRelease' => $request->user()->can('releaseHold', VendorBill::class),
            'onHoldCount' => VendorBill::query()->where('status', VendorBill::STATUS_ON_HOLD)->count(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('configureMatching', VendorBill::class);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(ThreeWayMatchSettings::MODES)],
            'qty_tolerance_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'price_tolerance_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->settings->update(tenant_id(), $validated);

        return back()->with('success', 'Bill matching settings saved.');
    }

    public function release(Request $request, VendorBill $bill): RedirectResponse
    {
        $this->authorize('releaseHold', $bill);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $released = $this->matcher->release($bill, (int) $request->user()->id, $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$released->bill_number} released. It is posted to the ledger and can now be paid.");
    }
}
