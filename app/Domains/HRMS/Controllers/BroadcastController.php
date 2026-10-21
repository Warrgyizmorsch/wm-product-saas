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
    public function show(mixed $id): View
    {
        $broadcastId = $id instanceof Broadcast ? $id->id : (int) $id;
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        $data = $this->broadcastRepository->getShowData($broadcastId, $isHrAdmin, $employee, $tenantId);

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
            'category'                    => 'required|in:announcement,policy_update,event,emergency,news',
            'priority'                    => 'required|in:normal,important,urgent',
            'content'                     => 'nullable|string',
            'message'                     => 'nullable|string',
            'target_type'                 => 'required|in:all,department,branch,designation,specific_employees',
            'target_ids'                  => 'nullable|required_unless:target_type,all|array',
            'status'                      => 'nullable|in:draft,published,scheduled',
            'scheduled_at'                => 'nullable|date',
            'expires_at'                  => 'nullable|date',
            'is_acknowledgement_required' => 'nullable|boolean',
            'allow_comments'              => 'nullable|boolean',
            'send_email'                  => 'nullable|boolean',
            'send_push'                   => 'nullable|boolean',
            'show_banner'                 => 'nullable|boolean',
            'attachment'                  => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv|max:10240',
            'banner_image'                => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $validated['content'] = $validated['content'] ?? $validated['message'] ?? '';
        if (empty(trim($validated['content']))) {
            return redirect()->back()->withInput()->with('error', 'Announcement content is required.');
        }

        $validated['is_acknowledgement_required'] = $request->boolean('is_acknowledgement_required');
        $validated['allow_comments'] = $request->has('allow_comments') ? $request->boolean('allow_comments') : true;
        $validated['send_email'] = $request->boolean('send_email');
        $validated['send_push'] = $request->boolean('send_push');
        $validated['show_banner'] = $request->has('show_banner') ? $request->boolean('show_banner') : true;

        if ($request->input('status') === 'draft') {
            $validated['status'] = 'draft';
        } elseif (empty($validated['status'])) {
            if (!empty($validated['scheduled_at']) && \Carbon\Carbon::parse($validated['scheduled_at'])->isFuture()) {
                $validated['status'] = 'scheduled';
            } else {
                $validated['status'] = 'published';
            }
        }

        $broadcast = $this->broadcastRepository->storeBroadcast($validated, $request, $tenantId);

        $targetTab = match($broadcast->status) {
            'published' => 'published',
            default     => 'scheduled',
        };

        $msg = match($broadcast->status) {
            'published' => 'Announcement published successfully.',
            'draft'     => 'Announcement saved as draft.',
            default     => 'Announcement scheduled successfully.',
        };

        return redirect()->route('hrms.broadcasts.index', ['active_tab' => $targetTab])
            ->with('success', $msg);
    }

    /**
     * Update Broadcast.
     */
    public function update(Request $request, mixed $id): RedirectResponse
    {
        $this->authorizeAdmin();

        $broadcastId = $id instanceof Broadcast ? $id->id : (int) $id;
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'title'                       => 'required|string|max:255',
            'category'                    => 'required|in:announcement,policy_update,event,emergency,news',
            'priority'                    => 'required|in:normal,important,urgent',
            'content'                     => 'nullable|string',
            'message'                     => 'nullable|string',
            'target_type'                 => 'required|in:all,department,branch,designation,specific_employees',
            'target_ids'                  => 'nullable|required_unless:target_type,all|array',
            'status'                      => 'nullable|in:draft,published,scheduled',
            'scheduled_at'                => 'nullable|date',
            'expires_at'                  => 'nullable|date',
            'is_acknowledgement_required' => 'nullable|boolean',
            'allow_comments'              => 'nullable|boolean',
            'send_email'                  => 'nullable|boolean',
            'send_push'                   => 'nullable|boolean',
            'show_banner'                 => 'nullable|boolean',
            'attachment'                  => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv|max:10240',
            'banner_image'                => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $validated['content'] = $validated['content'] ?? $validated['message'] ?? '';
        if (empty(trim($validated['content']))) {
            return redirect()->back()->withInput()->with('error', 'Announcement content is required.');
        }

        $validated['is_acknowledgement_required'] = $request->boolean('is_acknowledgement_required');
        $validated['allow_comments'] = $request->has('allow_comments') ? $request->boolean('allow_comments') : true;
        $validated['send_email'] = $request->boolean('send_email');
        $validated['send_push'] = $request->boolean('send_push');
        $validated['show_banner'] = $request->has('show_banner') ? $request->boolean('show_banner') : true;

        if (empty($validated['status'])) {
            if (!empty($validated['scheduled_at']) && \Carbon\Carbon::parse($validated['scheduled_at'])->isFuture()) {
                $validated['status'] = 'scheduled';
            } else {
                $validated['status'] = 'published';
            }
        }

        $this->broadcastRepository->updateBroadcast($broadcastId, $validated, $request, $tenantId);

        return redirect()->back()->with('success', 'Announcement updated successfully.');
    }

    /**
     * Delete Broadcast.
     */
    public function destroy(mixed $id): RedirectResponse
    {
        $this->authorizeAdmin();

        $broadcastId = $id instanceof Broadcast ? $id->id : (int) $id;
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->broadcastRepository->deleteBroadcast($broadcastId, $tenantId);

        return redirect()->route('hrms.broadcasts.index')->with('success', 'Announcement deleted successfully.');
    }

    /**
     * Employee Acknowledges Broadcast.
     */
    public function acknowledge(Request $request, mixed $id): RedirectResponse
    {
        $broadcastId = $id instanceof Broadcast ? $id->id : (int) $id;
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $employee = $this->getAuthenticatedEmployee();

        $this->broadcastRepository->acknowledge($broadcastId, $employee, $request->input('signature_data'), $tenantId);

        return redirect()->back()->with('success', 'Thank you. Your acknowledgement has been recorded.');
    }

    /**
     * Post a Comment on a Broadcast.
     */
    public function storeComment(Request $request, mixed $id): RedirectResponse
    {
        $broadcastId = $id instanceof Broadcast ? $id->id : (int) $id;
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'comment_text' => 'nullable|string|max:2000',
            'comment'      => 'nullable|string|max:2000',
            'parent_id'    => 'nullable|integer|exists:broadcast_comments,id',
        ]);

        if (!empty($validated['parent_id'])) {
            $parentValid = BroadcastComment::where('id', $validated['parent_id'])
                ->where('broadcast_id', $broadcastId)
                ->where('tenant_id', $tenantId)
                ->exists();
            if (!$parentValid) {
                return redirect()->back()->with('error', 'Invalid parent comment reference.');
            }
        }

        $commentText = trim($validated['comment_text'] ?? $validated['comment'] ?? '');
        if ($commentText === '') {
            return redirect()->back()->with('error', 'Comment cannot be empty.');
        }

        $broadcast = Broadcast::where('tenant_id', $tenantId)->findOrFail($broadcastId);

        if (!$broadcast->allow_comments) {
            return redirect()->back()->with('error', 'Comments are disabled for this announcement.');
        }

        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin) {
            $hasAccess = $broadcast->target_type === 'all'
                || ($employee && $broadcast->receipts()->where('employee_id', $employee->id)->exists());
            if (!$hasAccess) {
                abort(403, 'You are not authorized to comment on this targeted announcement.');
            }
        }

        if (!$employee && !$isHrAdmin) {
            return redirect()->back()->with('error', 'An employee profile is required to comment.');
        }

        $this->broadcastRepository->storeComment($broadcastId, $validated, auth()->user(), $employee, $tenantId);

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
                $q->orWhere('user_id', $user->id);
                if ($user->email) {
                    $q->orWhere('office_email', $user->email)
                      ->orWhere('personal_email', $user->email);
                }
            })
            ->first();
    }
}
