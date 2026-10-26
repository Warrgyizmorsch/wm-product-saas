<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Models\DemoRequest;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DemoRequestController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
    ) {
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        // Only Role ID 1 (Super Admin) or platform.tenants.manage can access
        if ($user->role_id !== 1 && ! $this->access->allows($user, 'platform.tenants.manage')) {
            abort(403, 'Access denied. Only Super Admin can manage demo requests.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin();

        $query = DemoRequest::query()->with('convertedTenant');

        $status = $request->query('status');
        if ($status && in_array($status, array_keys(DemoRequest::statuses()), true)) {
            $query->where('status', $status);
        }

        $teamSize = $request->query('team_size');
        if ($teamSize) {
            $query->where('team_size', $teamSize);
        }

        $search = trim((string) $request->query('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        $sortBy = (string) $request->query('sort_by', 'created_at');
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['created_at', 'full_name', 'company_name', 'status', 'preferred_demo_at'], true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('id');
        }

        $summary = [
            'total' => DemoRequest::count(),
            'new' => DemoRequest::where('status', DemoRequest::STATUS_NEW)->count(),
            'contacted' => DemoRequest::where('status', DemoRequest::STATUS_CONTACTED)->count(),
            'scheduled' => DemoRequest::where('status', DemoRequest::STATUS_SCHEDULED)->count(),
            'completed' => DemoRequest::where('status', DemoRequest::STATUS_COMPLETED)->count(),
        ];

        $statusCounts = [];
        foreach (array_keys(DemoRequest::statuses()) as $stKey) {
            $statusCounts[$stKey] = DemoRequest::where('status', $stKey)->count();
        }

        $demoRequests = $query->paginate(15)->withQueryString();

        return view('modules.platform.demo-requests.index', [
            'demoRequests' => $demoRequests,
            'summary' => $summary,
            'statusCounts' => $statusCounts,
            'currentStatus' => $status,
            'search' => $search,
            'teamSize' => $teamSize,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'allStatuses' => DemoRequest::statuses(),
        ]);
    }

    public function updateStatus(Request $request, DemoRequest $demoRequest): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(DemoRequest::statuses()))],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $demoRequest->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $demoRequest->admin_notes,
        ]);

        return redirect()
            ->back()
            ->with('success', "Demo request for {$demoRequest->full_name} status updated to ".DemoRequest::statuses()[$validated['status']]['label'].'.');
    }

    public function destroy(DemoRequest $demoRequest): RedirectResponse
    {
        $this->authorizeAdmin();

        $name = $demoRequest->full_name;
        $demoRequest->delete();

        return redirect()
            ->back()
            ->with('success', "Demo request from {$name} deleted successfully.");
    }
}
