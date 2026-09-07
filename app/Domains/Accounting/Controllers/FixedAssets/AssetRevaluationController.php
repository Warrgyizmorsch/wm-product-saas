<?php

namespace App\Domains\Accounting\Controllers\FixedAssets;

use App\Domains\Accounting\FixedAssets\Models\AssetRevaluation;
use App\Domains\Accounting\FixedAssets\Policies\AssetRevaluationPolicy;
use App\Domains\Accounting\FixedAssets\Services\AssetRevaluationService;
use App\Domains\HRMS\Models\Asset;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AssetRevaluationController extends Controller
{
    public function __construct(
        private readonly AssetRevaluationService $revaluations,
        private readonly AssetRevaluationPolicy $policy,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->policy->viewAny($request->user()), 403);

        $status = $request->query('status');

        $revaluations = AssetRevaluation::with('asset')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('modules.accounting.fixed-assets.revaluations.index', [
            'revaluations' => $revaluations,
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
        ])->whereNotNull('capitalization_cost')->orderBy('asset_code')->get();

        return view('modules.accounting.fixed-assets.revaluations.create', ['assets' => $assets]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->policy->create($request->user()), 403);

        $validated = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'revaluation_date' => ['required', 'date'],
            'revalued_amount' => ['required', 'numeric', 'min:0.01'],
            'revised_useful_life_months' => ['nullable', 'integer', 'min:1'],
            'revised_residual_value' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $asset = Asset::findOrFail($validated['asset_id']);

        try {
            $revaluation = $this->revaluations->revalue($asset, $validated, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['asset_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.fixed-assets.revaluations.index')
            ->with('success', "Revaluation request for {$asset->asset_code} submitted for approval.");
    }

    public function approve(Request $request, AssetRevaluation $revaluation): RedirectResponse
    {
        abort_unless($this->policy->approve($request->user(), $revaluation), 403);

        try {
            $this->revaluations->approve($revaluation, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Revaluation approved.');
    }

    public function reject(Request $request, AssetRevaluation $revaluation): RedirectResponse
    {
        abort_unless($this->policy->approve($request->user(), $revaluation), 403);

        try {
            $this->revaluations->reject($revaluation, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Revaluation rejected.');
    }

    public function post(Request $request, AssetRevaluation $revaluation): RedirectResponse
    {
        abort_unless($this->policy->approve($request->user(), $revaluation), 403);

        try {
            $this->revaluations->post($revaluation, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Revaluation posted to the general ledger.');
    }
}
