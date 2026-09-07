<?php

namespace App\Domains\Accounting\Controllers\FixedAssets;

use App\Domains\Accounting\FixedAssets\Models\AssetDisposal;
use App\Domains\Accounting\FixedAssets\Policies\AssetDisposalPolicy;
use App\Domains\Accounting\FixedAssets\Services\AssetDisposalService;
use App\Domains\HRMS\Models\Asset;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AssetDisposalController extends Controller
{
    public function __construct(
        private readonly AssetDisposalService $disposals,
        private readonly AssetDisposalPolicy $policy,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->policy->viewAny($request->user()), 403);

        $status = $request->query('status');

        $disposals = AssetDisposal::with('asset')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('modules.accounting.fixed-assets.disposals.index', [
            'disposals' => $disposals,
            'status' => $status,
            'canApprove' => $this->policy->approve($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->policy->create($request->user()), 403);

        $assets = Asset::whereNotIn('status', [
            Asset::STATUS_DISPOSED, Asset::STATUS_SOLD, Asset::STATUS_SCRAPPED,
            Asset::STATUS_WRITTEN_OFF, Asset::STATUS_LOST,
        ])->orderBy('asset_code')->get();

        return view('modules.accounting.fixed-assets.disposals.create', ['assets' => $assets]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->policy->create($request->user()), 403);

        $validated = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'disposal_type' => ['required', 'string', 'in:sale,scrap,lost'],
            'disposal_date' => ['required', 'date'],
            'sale_proceeds' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $asset = Asset::findOrFail($validated['asset_id']);

        try {
            $disposal = $this->disposals->dispose($asset, $validated, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['asset_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.fixed-assets.disposals.index')
            ->with('success', "Disposal request for {$asset->asset_code} submitted for approval.");
    }

    public function approve(Request $request, AssetDisposal $disposal): RedirectResponse
    {
        abort_unless($this->policy->approve($request->user(), $disposal), 403);

        try {
            $this->disposals->approve($disposal, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Disposal approved.');
    }

    public function reject(Request $request, AssetDisposal $disposal): RedirectResponse
    {
        abort_unless($this->policy->approve($request->user(), $disposal), 403);

        try {
            $this->disposals->reject($disposal, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Disposal rejected.');
    }

    public function post(Request $request, AssetDisposal $disposal): RedirectResponse
    {
        abort_unless($this->policy->approve($request->user(), $disposal), 403);

        try {
            $this->disposals->post($disposal, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Disposal posted to the general ledger.');
    }
}
