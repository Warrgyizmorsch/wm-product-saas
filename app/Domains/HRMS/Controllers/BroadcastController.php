<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Broadcast;
use App\Domains\HRMS\Models\BroadcastComment;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Repositories\BroadcastRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BroadcastController extends Controller
{
    public function __construct(
        private readonly BroadcastRepositoryInterface $broadcastRepository
    ) {}

    /**
     * Master Broadcasts Dashboard View.
     */
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        $data = $this->broadcastRepository->getIndexData($request->all(), $isHrAdmin, $employee, $tenantId);

        return view('modules.hrms.broadcasts.index', $data);
    }

    /**
     * View Broadcast Details, Receipts, Comments & Attachments.
     */
    public function show(int $id): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        $data = $this->broadcastRepository->getShowData($id, $isHrAdmin, $employee, $tenantId);

        return view('modules.hrms.broadcasts.show', $data);
    }

    /**
     * Store new Broadcast (Publish or Schedule).
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'title'                       => 'required|string|max:255',
            'category'                    => 'required|in:general,policy,event,leadership,celebration,urgent',
            'priority'                    => 'required|in:general,important,urgent',
            'message'                     => 'required|string',
            'target_type'                 => 'required|in:all,department,branch,designation,individual',
            'target_ids'                  => 'nullable|array',
            'status'                      => 'required|in:draft,published,scheduled',
            'scheduled_at'                => 'nullable|required_if:status,scheduled|date|after:now',
            'expires_at'                  => 'nullable|date|after:today',
            'is_pinned'                   => 'nullable|boolean',
            'is_acknowledgement_required' => 'nullable|boolean',
            'allow_comments'              => 'nullable|boolean',
            'attachments.*'               => 'nullable|file|max:10240',
        ]);

        $broadcast = $this->broadcastRepository->storeBroadcast($validated, $request, $tenantId);

        return redirect()->route('hrms.broadcasts.index', ['tab' => $broadcast->status === 'published' ? 'published' : 'scheduled'])
            ->with('success', 'Announcement ' . ($broadcast->status === 'published' ? 'published successfully.' : 'saved successfully.'));
    }

    /**
     * Update Broadcast.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorizeAdmin();

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'title'                       => 'required|string|max:255',
            'category'                    => 'required|in:general,policy,event,leadership,celebration,urgent',
            'priority'                    => 'required|in:general,important,urgent',
            'message'                     => 'required|string',
            'target_type'                 => 'required|in:all,department,branch,designation,individual',
            'target_ids'                  => 'nullable|array',
            'status'                      => 'required|in:draft,published,scheduled',
            'scheduled_at'                => 'nullable|required_if:status,scheduled|date',
            'expires_at'                  => 'nullable|date|after:today',
            'is_pinned'                   => 'nullable|boolean',
            'is_acknowledgement_required' => 'nullable|boolean',
            'allow_comments'              => 'nullable|boolean',
            'attachments.*'               => 'nullable|file|max:10240',
        ]);

        $this->broadcastRepository->updateBroadcast($id, $validated, $request, $tenantId);

        return redirect()->back()->with('success', 'Announcement updated successfully.');
    }

    /**
     * Delete Broadcast.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorizeAdmin();

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->broadcastRepository->deleteBroadcast($id, $tenantId);

        return redirect()->route('hrms.broadcasts.index')->with('success', 'Announcement deleted successfully.');
    }

    /**
     * Employee Acknowledges Broadcast.
     */
    public function acknowledge(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $employee = $this->getAuthenticatedEmployee();

        $this->broadcastRepository->acknowledge($id, $employee, $request->input('signature_data'), $tenantId);

        return redirect()->back()->with('success', 'Thank you. Your acknowledgement has been recorded.');
    }

    /**
     * Post a Comment on a Broadcast.
     */
    public function storeComment(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $employee = $this->getAuthenticatedEmployee();
        $this->broadcastRepository->storeComment($id, $validated, auth()->user(), $employee, $tenantId);

        return redirect()->back()->with('success', 'Comment posted.');
    }

    /**
     * Toggle Pin Comment.
     */
    public function togglePinComment(BroadcastComment $comment): RedirectResponse
    {
        $this->authorizeAdmin();

        $comment->update(['is_pinned' => !$comment->is_pinned]);

        return redirect()->back()->with('success', $comment->is_pinned ? 'Comment pinned.' : 'Comment unpinned.');
    }

    /**
     * Delete a Comment (alias for destroyComment).
     */
    public function destroyComment(BroadcastComment $comment): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $isHrAdmin = $this->isHrAdmin();

        $deleted = $this->broadcastRepository->deleteComment($comment->id, auth()->user(), $isHrAdmin, $tenantId);

        if (!$deleted) {
            abort(403, 'Unauthorized to delete this comment.');
        }

        return redirect()->back()->with('success', 'Comment removed.');
    }

    /**
     * Backward-compatible deleteComment method.
     */
    public function deleteComment(int $commentId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $isHrAdmin = $this->isHrAdmin();

        $deleted = $this->broadcastRepository->deleteComment($commentId, auth()->user(), $isHrAdmin, $tenantId);

        if (!$deleted) {
            abort(403, 'Unauthorized to delete this comment.');
        }

        return redirect()->back()->with('success', 'Comment removed.');
    }

    /**
     * Helper to verify if current user is HR Admin / Super Admin.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $access = app(\App\Services\Access\AccessService::class);
        $context = ['tenant_id' => $user->tenant_id];

        return $access->allows($user, 'hr.settings.manage', $context)
            || $access->allows($user, 'hrms.broadcasts.manage', $context)
            || $access->allows($user, 'hrms.broadcasts.publish', $context)
            || $access->allows($user, 'hrms.employees.view', $context)
            || in_array($user->role, ['admin', 'super_admin', 'company_admin', 'hr_manager']);
    }

    private function authorizeAdmin(): void
    {
        if (!$this->isHrAdmin()) {
            abort(403, 'Only HR Administrators can manage announcements.');
        }
    }

    /**
     * Helper to resolve current authenticated Employee record.
     */
    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Employee::where('tenant_id', tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id())
            ->where(function ($q) use ($user) {
                if ($user->employee_id) {
                    $q->where('id', $user->employee_id);
                }
                if ($user->email) {
                    $q->orWhere('office_email', $user->email)
                      ->orWhere('personal_email', $user->email);
                }
            })
            ->first();
    }
}
