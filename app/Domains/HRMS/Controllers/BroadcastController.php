<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Broadcast;
use App\Domains\HRMS\Models\BroadcastComment;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Services\BroadcastService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BroadcastController extends Controller
{
    public function __construct(
        private readonly BroadcastService $broadcastService
    ) {}

    /**
     * Master Broadcasts Dashboard View.
     */
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Auto-publish any scheduled broadcasts whose scheduled_at time has arrived
        $this->broadcastService->processScheduledBroadcasts($tenantId);

        $activeTab = $request->input('active_tab', $request->input('tab', 'published'));
        $search = $request->input('search');
        $priority = $request->input('priority');
        $category = $request->input('category');
        $sort = $request->input('sort', 'newest');

        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        // Base Query
        $baseQuery = Broadcast::query()->where('tenant_id', $tenantId);

        // Stats Computation
        $statsBaseQuery = clone $baseQuery;
        if (!$isHrAdmin) {
            if ($employee) {
                $statsBaseQuery->where(function ($q) use ($employee) {
                    $q->where('target_type', 'all')
                      ->orWhereHas('receipts', fn($rq) => $rq->where('employee_id', $employee->id));
                });
            } else {
                $statsBaseQuery->where('target_type', 'all');
            }
        }

        $totalActive = (clone $statsBaseQuery)->where('status', 'published')->count();
        $publishedThisMonth = (clone $statsBaseQuery)->where('status', 'published')
            ->whereMonth('published_at', now()->month)
            ->whereYear('published_at', now()->year)
            ->count();

        if (!$isHrAdmin && $employee) {
            $pendingAckCount = (clone $statsBaseQuery)->where('status', 'published')
                ->where('is_acknowledgement_required', true)
                ->whereHas('receipts', fn($q) => $q->where('employee_id', $employee->id)->whereNull('acknowledged_at'))
                ->count();
        } else {
            $pendingAckCount = (clone $baseQuery)->where('status', 'published')
                ->where('is_acknowledgement_required', true)
                ->whereHas('receipts', fn($q) => $q->whereNull('acknowledged_at'))
                ->count();
        }

        $scheduledCount = $isHrAdmin ? (clone $baseQuery)->where('status', 'scheduled')->count() : 0;

        // 1. Published Query
        $publishedQuery = (clone $baseQuery)->where('status', 'published');
        if (!$isHrAdmin) {
            if ($employee) {
                $publishedQuery->where(function ($q) use ($employee) {
                    $q->where('target_type', 'all')
                      ->orWhereHas('receipts', fn($rq) => $rq->where('employee_id', $employee->id));
                });
            } else {
                $publishedQuery->where('target_type', 'all');
            }
        }
        $this->applyFilters($publishedQuery, $search, $priority, $category, $sort);
        $publishedBroadcasts = $publishedQuery->paginate(10, ['*'], 'published_page')->appends($request->all());

        // 2. Scheduled & Drafts Query (HR Admin Only)
        if ($isHrAdmin) {
            $scheduledQuery = (clone $baseQuery)->whereIn('status', ['scheduled', 'draft']);
            $this->applyFilters($scheduledQuery, $search, $priority, $category, $sort);
            $scheduledBroadcasts = $scheduledQuery->paginate(10, ['*'], 'scheduled_page')->appends($request->all());
        } else {
            $scheduledBroadcasts = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, 1, [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]);
        }

        // 3. Archived Query
        $archivedQuery = (clone $baseQuery)->whereIn('status', ['expired', 'archived']);
        if (!$isHrAdmin) {
            if ($employee) {
                $archivedQuery->where(function ($q) use ($employee) {
                    $q->where('target_type', 'all')
                      ->orWhereHas('receipts', fn($rq) => $rq->where('employee_id', $employee->id));
                });
            } else {
                $archivedQuery->where('target_type', 'all');
            }
        }
        $this->applyFilters($archivedQuery, $search, $priority, $category, $sort);
        $archivedBroadcasts = $archivedQuery->paginate(10, ['*'], 'archived_page')->appends($request->all());

        // Data for Create Modal
        $departments = Department::where('tenant_id', $tenantId)->get();
        $branches = Branch::where('tenant_id', $tenantId)->get();
        $designations = Designation::where('tenant_id', $tenantId)->get();
        $employees = Employee::where('tenant_id', $tenantId)->where(function($q) {
            $q->where('status', true)->orWhere('status', 1)->orWhereNull('status');
        })->orderBy('full_name')->get();

        return view('modules.hrms.broadcasts.index', compact(
            'activeTab',
            'totalActive',
            'publishedThisMonth',
            'pendingAckCount',
            'scheduledCount',
            'publishedBroadcasts',
            'scheduledBroadcasts',
            'archivedBroadcasts',
            'departments',
            'branches',
            'designations',
            'employees',
            'isHrAdmin'
        ));
    }

    /**
     * Helper to check if current user is HR Admin / Manager.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return (bool) (
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.broadcasts.manage') ||
            $user->hasHrPermission('hrms.communications.manage')
        );
    }

    /**
     * Apply Search, Filters, and Sorting to query.
     */
    private function applyFilters($query, $search, $priority, $category, $sort): void
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('broadcast_number', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($category) {
            $query->where('category', $category);
        }

        match ($sort) {
            'oldest' => $query->oldest('id'),
            'title_asc' => $query->orderBy('title', 'asc'),
            'title_desc' => $query->orderBy('title', 'desc'),
            default => $query->latest('id'),
        };
    }

    /**
     * Store new Broadcast.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isHrAdmin(), 403, 'Unauthorized action. Only HR Admins can create broadcasts.');

        $validated = $request->validate([
            'title'                        => 'required|string|max:255',
            'category'                     => 'required|in:announcement,policy_update,event,emergency,news',
            'priority'                     => 'required|in:normal,important,urgent',
            'content'                      => 'required|string',
            'target_type'                  => 'required|in:all,department,branch,designation,specific_employees',
            'target_ids'                   => 'nullable|array',
            'is_acknowledgement_required' => 'nullable|boolean',
            'allow_comments'              => 'nullable|boolean',
            'send_email'                  => 'nullable|boolean',
            'send_push'                   => 'nullable|boolean',
            'show_banner'                 => 'nullable|boolean',
            'scheduled_at'                => 'nullable|date',
            'expires_at'                  => 'nullable|date|after_or_equal:scheduled_at',
            'banner_image'                => 'nullable|image|max:5120',
            'attachment'                  => 'nullable|file|max:10240',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $validated['tenant_id'] = $tenantId;

        if ($request->hasFile('banner_image')) {
            $validated['banner_image_path'] = $request->file('banner_image')->store("broadcasts/tenant_{$tenantId}/banners", 'public');
        }

        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store("broadcasts/tenant_{$tenantId}/attachments", 'public');
        }

        $validated['is_acknowledgement_required'] = $request->boolean('is_acknowledgement_required');
        $validated['allow_comments'] = $request->boolean('allow_comments', true);
        $validated['send_email'] = $request->boolean('send_email');
        $validated['send_push'] = $request->boolean('send_push');
        $validated['show_banner'] = $request->boolean('show_banner', true);

        $this->broadcastService->createBroadcast($validated);

        return redirect()->route('hrms.broadcasts.index')
            ->with('success', 'Broadcast announcement created successfully.');
    }

    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)->first()
            ?? Employee::where(function ($q) use ($user) {
                $q->where('office_email', $user->email)
                  ->orWhere('personal_email', $user->email);
            })->first();
    }

    /**
     * Show Broadcast detailed workspace.
     */
    public function show(Broadcast $broadcast): View
    {
        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin) {
            if (!in_array($broadcast->status, ['published', 'expired', 'archived'])) {
                abort(403, 'Unauthorized access to broadcast.');
            }
            if ($broadcast->target_type !== 'all') {
                $hasReceipt = $employee && $broadcast->receipts()->where('employee_id', $employee->id)->exists();
                if (!$hasReceipt) {
                    abort(403, 'This broadcast announcement is not targeted to you.');
                }
            }
        }

        $broadcast->load(['creator', 'receipts.employee', 'comments.employee', 'comments.replies']);

        if ($employee) {
            $this->broadcastService->markRead($broadcast, $employee->id);
        }

        $analytics = $this->broadcastService->getAnalytics($broadcast);

        return view('modules.hrms.broadcasts.show', compact('broadcast', 'analytics', 'employee', 'isHrAdmin'));
    }

    /**
     * Update Broadcast.
     */
    public function update(Request $request, Broadcast $broadcast): RedirectResponse
    {
        abort_unless($this->isHrAdmin(), 403, 'Unauthorized action. Only HR Admins can update broadcasts.');

        $validated = $request->validate([
            'title'                        => 'required|string|max:255',
            'category'                     => 'required|in:announcement,policy_update,event,emergency,news',
            'priority'                     => 'required|in:normal,important,urgent',
            'content'                      => 'required|string',
            'is_acknowledgement_required' => 'nullable|boolean',
            'allow_comments'              => 'nullable|boolean',
            'expires_at'                  => 'nullable|date',
            'status'                       => 'required|in:draft,scheduled,published,expired,archived',
        ]);

        $validated['is_acknowledgement_required'] = $request->boolean('is_acknowledgement_required');
        $validated['allow_comments'] = $request->boolean('allow_comments');

        $broadcast->update($validated);

        return redirect()->back()->with('success', 'Broadcast updated successfully.');
    }

    /**
     * Delete Broadcast.
     */
    public function destroy(Broadcast $broadcast): RedirectResponse
    {
        abort_unless($this->isHrAdmin(), 403, 'Unauthorized action. Only HR Admins can delete broadcasts.');

        $broadcast->receipts()->delete();
        $broadcast->comments()->delete();
        $broadcast->delete();

        return redirect()->route('hrms.broadcasts.index')
            ->with('success', 'Broadcast deleted successfully.');
    }

    /**
     * Record employee acknowledgement.
     */
    public function acknowledge(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return redirect()->back()->with('error', 'Employee record not found for your login.');
        }

        $this->broadcastService->acknowledgeBroadcast($broadcast, $employee->id, $request->ip());

        return redirect()->back()->with('success', 'Thank you. Your read acknowledgement has been recorded.');
    }

    /**
     * Add comment to broadcast.
     */
    public function storeComment(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $validated = $request->validate([
            'comment_text' => 'required|string',
            'parent_id'    => 'nullable|exists:broadcast_comments,id',
        ]);

        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return redirect()->back()->with('error', 'Employee record not found for your login.');
        }

        $this->broadcastService->addComment(
            $broadcast,
            $employee->id,
            $validated['comment_text'],
            $validated['parent_id'] ?? null
        );

        return redirect()->back()->with('success', 'Comment posted successfully.');
    }

    /**
     * Toggle pinned status of comment.
     */
    public function togglePinComment(BroadcastComment $comment): RedirectResponse
    {
        abort_unless($this->isHrAdmin(), 403, 'Unauthorized action.');

        $this->broadcastService->togglePinComment($comment);
        return redirect()->back()->with('success', 'Comment pin status toggled.');
    }

    /**
     * Delete comment.
     */
    public function destroyComment(BroadcastComment $comment): RedirectResponse
    {
        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();
        $isOwner = $employee && $comment->employee_id === $employee->id;

        abort_unless($isHrAdmin || $isOwner, 403, 'Unauthorized action.');

        $comment->delete();
        return redirect()->back()->with('success', 'Comment deleted successfully.');
    }
}
