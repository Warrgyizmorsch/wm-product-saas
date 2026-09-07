<?php

namespace App\Domains\Accounting\Controllers\FixedAssets;

use App\Domains\Accounting\FixedAssets\Models\AssetWriteOff;
use App\Domains\Accounting\FixedAssets\Policies\AssetWriteOffPolicy;
use App\Domains\Accounting\FixedAssets\Services\AssetWriteOffService;
use App\Domains\HRMS\Models\Asset;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AssetWriteOffController extends Controller
{
    public function __construct(
        private readonly AssetWriteOffService $writeOffs,
        private readonly AssetWriteOffPolicy $policy,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->policy->viewAny($request->user()), 403);

        $status = $request->query('status');

        $writeOffs = AssetWriteOff::with('asset')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('modules.accounting.fixed-assets.write-offs.index', [
            'writeOffs' => $writeOffs,
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

        return view('modules.accounting.fixed-assets.write-offs.create', ['assets' => $assets]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->policy->create($request->user()), 403);

        $validated = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'write_off_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $asset = Asset::findOrFail($validated['asset_id']);

        try {
            $writeOff = $this->writeOffs->writeOff($asset, $validated, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['asset_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('accounting.fixed-assets.write-offs.index')
            ->with('success', "Write-off request for {$asset->asset_code} submitted for approval.");
    }

    public function approve(Request $request, AssetWriteOff $writeOff): RedirectResponse
    {
        abort_unless($this->policy->approve($request->user(), $writeOff), 403);

        try {
            $this->writeOffs->approve($writeOff, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Write-off approved.');
    }

    public function reject(Request $request, AssetWriteOff $writeOff): RedirectResponse
    {
        abort_unless($this->policy->approve($request->user(), $writeOff), 403);

        try {
            $this->writeOffs->reject($writeOff, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Write-off rejected.');
    }

    public function post(Request $request, AssetWriteOff $writeOff): RedirectResponse
    {
        abort_unless($this->policy->approve($request->user(), $writeOff), 403);

        try {
            $this->writeOffs->post($writeOff, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Write-off posted to the general ledger.');
    }
}
