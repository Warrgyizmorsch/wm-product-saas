<?php

namespace App\Domains\Accounting\Controllers\FixedAssets;

use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Policies\AssetDepreciationPolicy;
use App\Domains\Accounting\FixedAssets\Services\AssetDepreciationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AssetDepreciationController extends Controller
{
    public function __construct(
        private readonly AssetDepreciationService $depreciation,
        private readonly AssetDepreciationPolicy $policy,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->policy->viewAny($request->user()), 403);

        $year = (int) ($request->query('year') ?: now()->year);
        $month = (int) ($request->query('month') ?: now()->month);
        $status = $request->query('status');

        $schedules = AssetDepreciationSchedule::with('asset')
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('modules.accounting.fixed-assets.depreciation.index', [
            'schedules' => $schedules,
            'year' => $year,
            'month' => $month,
            'status' => $status,
            'canGenerate' => $this->policy->generate($request->user()),
            'canPost' => $this->policy->post($request->user()),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($this->policy->generate($request->user()), 403);

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $generated = $this->depreciation->generateForPeriod(
            $request->user()->tenant_id,
            $validated['year'],
            $validated['month'],
            null,
            $request->user()->id
        );

        return redirect()->route('accounting.fixed-assets.depreciation.index', ['year' => $validated['year'], 'month' => $validated['month']])
            ->with('success', "Generated {$generated->count()} draft depreciation schedule(s) for {$validated['month']}/{$validated['year']}.");
    }

    public function review(Request $request, AssetDepreciationSchedule $schedule): RedirectResponse
    {
        abort_unless($this->policy->generate($request->user(), $schedule), 403);

        try {
            $this->depreciation->review($schedule, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Depreciation schedule marked as reviewed.');
    }

    public function approve(Request $request, AssetDepreciationSchedule $schedule): RedirectResponse
    {
        abort_unless($this->policy->generate($request->user(), $schedule), 403);

        try {
            $this->depreciation->approve($schedule, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Depreciation schedule approved.');
    }

    public function post(Request $request, AssetDepreciationSchedule $schedule): RedirectResponse
    {
        abort_unless($this->policy->post($request->user(), $schedule), 403);

        try {
            $this->depreciation->post($schedule, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Depreciation posted to the general ledger.');
    }
}
