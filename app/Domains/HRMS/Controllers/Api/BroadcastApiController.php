<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Broadcast;
use App\Domains\HRMS\Models\BroadcastComment;
use App\Domains\HRMS\Models\BroadcastReceipt;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Services\BroadcastService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadcastApiController extends Controller
{
    protected BroadcastService $broadcastService;

    public function __construct(BroadcastService $broadcastService)
    {
        $this->broadcastService = $broadcastService;
    }

    private function sendSuccess($data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    private function sendError(string $message = 'An error occurred', int $statusCode = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    private function getTenantId(): int
    {
        return tenant_id() ?? auth()->user()?->tenant_id ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;
    }

    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->hasHrPermission('hrms.broadcasts.manage')
            || $user->hasHrPermission('hrms.communications.manage')
            || $user->hasHrPermission('hr.settings.manage')
            || in_array($user->role, ['admin', 'super_admin', 'company_admin', 'hr_manager']);
    }

    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $tenantId = $this->getTenantId();

        return Employee::where('tenant_id', $tenantId)
            ->where(function ($q) use ($user) {
                if ($user->employee_id) {
                    $q->where('id', $user->employee_id);
                }
                $q->orWhere('user_id', $user->id);
                if ($user->email) {
                    $q->orWhere('office_email', $user->email)
                      ->orWhere('personal_email', $user->email);
                }
            })->first();
    }

    /**
     * GET /api/hrms/broadcasts
     * Target broadcasts feed for logged-in employee (with unread count).
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->broadcastService->processScheduledBroadcasts($tenantId);
        $employee = $this->getAuthenticatedEmployee();

        $query = Broadcast::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now());
            })
            ->with(['creator', 'comments']);

        if ($employee) {
            $query->where(function ($q) use ($employee) {
                $q->where('target_type', 'all')
                  ->orWhereHas('receipts', function ($rq) use ($employee) {
                      $rq->where('employee_id', $employee->id);
                  });
            });
        } elseif (!$this->isHrAdmin()) {
            $query->where('target_type', 'all');
        }

        $broadcasts = $query->latest('published_at')->paginate($request->integer('per_page', 10));

        $isHrAdmin = $this->isHrAdmin();
        $broadcasts->getCollection()->transform(function ($b) use ($isHrAdmin) {
            $b->capabilities = [
                'can_view'        => true,
                'can_comment'     => (bool) ($b->allow_comments ?? true),
                'can_react'       => true,
                'can_acknowledge' => (bool) ($b->is_acknowledgement_required ?? false),
                'can_edit'        => $isHrAdmin,
                'can_delete'      => $isHrAdmin,
            ];
            return $b;
        });

        $unreadCount = 0;
        if ($employee) {
            $unreadCount = BroadcastReceipt::where('tenant_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->whereNull('read_at')
                ->count();
        }

        return $this->sendSuccess([
            'unread_count' => $unreadCount,
            'broadcasts'   => $broadcasts,
        ], 'Employee broadcast feed retrieved successfully.');
    }

    /**
     * GET /api/hrms/broadcasts/management
     * Full broadcast list for HR management.
     */
    public function management(Request $request): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to manage broadcasts.', 403);
        }

        $tenantId = $this->getTenantId();

        $broadcasts = Broadcast::where('tenant_id', $tenantId)
            ->with(['creator', 'receipts'])
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        $isHrAdmin = $this->isHrAdmin();
        $broadcasts->getCollection()->transform(function ($b) use ($isHrAdmin) {
            $b->capabilities = [
                'can_view'        => true,
                'can_comment'     => (bool) ($b->allow_comments ?? true),
                'can_react'       => true,
                'can_acknowledge' => (bool) ($b->is_acknowledgement_required ?? false),
                'can_edit'        => $isHrAdmin,
                'can_delete'      => $isHrAdmin,
            ];
            return $b;
        });

        return $this->sendSuccess($broadcasts, 'Management broadcasts list retrieved successfully.');
    }

    /**
     * POST /api/hrms/broadcasts
     * Create a new broadcast.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to create broadcast.', 403);
        }

        $validated = $request->validate([
            'title'                       => 'required|string|max:255',
            'category'                    => 'required|in:announcement,policy_update,event,emergency,news',
            'priority'                    => 'required|in:normal,important,urgent',
            'content'                     => 'required|string',
            'target_type'                 => 'required|in:all,department,branch,designation,specific_employees',
            'target_ids'                  => 'nullable|array',
            'is_acknowledgement_required' => 'nullable|boolean',
            'allow_comments'              => 'nullable|boolean',
            'send_email'                  => 'nullable|boolean',
            'send_push'                   => 'nullable|boolean',
            'show_banner'                 => 'nullable|boolean',
            'scheduled_at'                => 'nullable|date',
            'expires_at'                  => 'nullable|date|after_or_equal:scheduled_at',
            'status'                      => 'nullable|in:draft,scheduled,published',
            'attachment'                  => 'nullable|file|max:10240',
            'banner_image'                => 'nullable|image|max:5120',
        ]);

        if ($validated['target_type'] !== 'all') {
            $request->validate([
                'target_ids' => 'required|array|min:1',
            ]);
        }

        $tenantId = $this->getTenantId();
        $validated['tenant_id'] = $tenantId;

        $attachment = $request->file('attachment');
        $bannerImage = $request->file('banner_image');

        $broadcast = $this->broadcastService->createBroadcast($validated, $attachment, $bannerImage);

        return $this->sendSuccess($broadcast->load('receipts'), 'Broadcast announcement created successfully.', 201);
    }

    /**
     * GET /api/hrms/broadcasts/{id}
     * Get single broadcast details.
     */
    public function show($id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $broadcast = Broadcast::where('tenant_id', $tenantId)
            ->with(['creator', 'receipts', 'comments.employee', 'comments.replies'])
            ->find($id);

        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin) {
            $hasAccess = $broadcast->target_type === 'all'
                || ($employee && $broadcast->receipts()->where('employee_id', $employee->id)->exists());
            if (!$hasAccess) {
                return $this->sendError('Unauthorized action. You are not an intended recipient of this announcement.', 403);
            }
            // Protect internal recipient audit & delivery telemetry from non-admin viewers
            $broadcast->makeHidden(['receipts']);
        }

        if ($employee) {
            $this->broadcastService->markRead($broadcast, $employee->id);
        }

        $broadcast->capabilities = [
            'can_view'        => true,
            'can_comment'     => (bool) ($broadcast->allow_comments ?? true),
            'can_react'       => true,
            'can_acknowledge' => (bool) ($broadcast->is_acknowledgement_required ?? false),
            'can_edit'        => $isHrAdmin,
            'can_delete'      => $isHrAdmin,
        ];

        return $this->sendSuccess($broadcast, 'Broadcast details retrieved successfully.');
    }

    /**
     * PUT /api/hrms/broadcasts/{id}
     * Update broadcast details.
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to update broadcast.', 403);
        }

        $tenantId = $this->getTenantId();
        $broadcast = Broadcast::where('tenant_id', $tenantId)->find($id);
        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'title'                       => 'required|string|max:255',
            'category'                    => 'required|in:announcement,policy_update,event,emergency,news',
            'priority'                    => 'required|in:normal,important,urgent',
            'content'                     => 'required|string',
            'target_type'                 => 'nullable|in:all,department,branch,designation,specific_employees',
            'target_ids'                  => 'nullable|array',
            'is_acknowledgement_required' => 'nullable|boolean',
            'allow_comments'              => 'nullable|boolean',
            'send_email'                  => 'nullable|boolean',
            'send_push'                   => 'nullable|boolean',
            'show_banner'                 => 'nullable|boolean',
            'expires_at'                  => 'nullable|date',
            'status'                      => 'nullable|in:draft,scheduled,published,expired,archived',
            'attachment'                  => 'nullable|file|max:10240',
            'banner_image'                => 'nullable|image|max:5120',
        ]);

        if (isset($validated['target_type']) && $validated['target_type'] !== 'all') {
            $request->validate([
                'target_ids' => 'required|array|min:1',
            ]);
        }

        $attachment = $request->file('attachment');
        $bannerImage = $request->file('banner_image');

        $updated = $this->broadcastService->updateBroadcast($broadcast, $validated, $attachment, $bannerImage);

        return $this->sendSuccess($updated->load('receipts'), 'Broadcast updated successfully.');
    }

    /**
     * DELETE /api/hrms/broadcasts/{id}
     * Delete broadcast.
     */
    public function destroy($id): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to delete broadcast.', 403);
        }

        $tenantId = $this->getTenantId();
        $broadcast = Broadcast::where('tenant_id', $tenantId)->find($id);
        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        $this->broadcastService->deleteBroadcast($broadcast);

        return $this->sendSuccess(['id' => (int) $id], 'Broadcast deleted successfully.');
    }

    /**
     * POST /api/hrms/broadcasts/{id}/acknowledge
     * Mark broadcast read/acknowledged for logged-in employee.
     */
    public function acknowledge(Request $request, $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $broadcast = Broadcast::where('tenant_id', $tenantId)->find($id);
        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return $this->sendError('Employee record not found for authenticated user.', 404);
        }

        $receipt = $this->broadcastService->acknowledgeBroadcast($broadcast, $employee->id, $request->ip());

        return $this->sendSuccess($receipt, 'Read acknowledgement recorded successfully.');
    }

    /**
     * Standardized helper to format a single broadcast comment object cleanly.
     */
    private function formatComment(BroadcastComment $comment): array
    {
        return [
            'id'           => $comment->id,
            'broadcast_id' => $comment->broadcast_id,
            'parent_id'    => $comment->parent_id,
            'comment_text' => $comment->comment_text,
            'is_pinned'    => (bool) $comment->is_pinned,
            'author'       => $comment->employee ? [
                'id'         => $comment->employee->id,
                'name'       => trim(($comment->employee->first_name ?? '') . ' ' . ($comment->employee->last_name ?? '')) ?: ($comment->employee->full_name ?? null),
                'email'      => $comment->employee->office_email ?? $comment->employee->personal_email ?? null,
                'avatar_url' => $comment->employee->avatar_url ?? null,
            ] : null,
            'replies'      => $comment->replies ? $comment->replies->map(function ($reply) {
                return [
                    'id'           => $reply->id,
                    'broadcast_id' => $reply->broadcast_id,
                    'parent_id'    => $reply->parent_id,
                    'comment_text' => $reply->comment_text,
                    'is_pinned'    => (bool) $reply->is_pinned,
                    'author'       => $reply->employee ? [
                        'id'         => $reply->employee->id,
                        'name'       => trim(($reply->employee->first_name ?? '') . ' ' . ($reply->employee->last_name ?? '')) ?: ($reply->employee->full_name ?? null),
                        'email'      => $reply->employee->office_email ?? $reply->employee->personal_email ?? null,
                        'avatar_url' => $reply->employee->avatar_url ?? null,
                    ] : null,
                    'created_at'   => $reply->created_at ? (is_string($reply->created_at) ? $reply->created_at : $reply->created_at->toIso8601String()) : null,
                ];
            })->values() : [],
            'created_at'   => $comment->created_at ? (is_string($comment->created_at) ? $comment->created_at : $comment->created_at->toIso8601String()) : null,
        ];
    }

    /**
     * GET /api/hrms/broadcasts/{id}/comments
     * Get comments for broadcast.
     */
    public function getComments(Request $request, $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $broadcast = Broadcast::where('tenant_id', $tenantId)->find($id);

        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin) {
            $hasAccess = $broadcast->target_type === 'all'
                || ($employee && $broadcast->receipts()->where('employee_id', $employee->id)->exists());
            if (!$hasAccess) {
                return $this->sendError('Unauthorized action. You are not an intended recipient of this announcement.', 403);
            }
        }

        $comments = BroadcastComment::where('broadcast_id', $broadcast->id)
            ->whereNull('parent_id')
            ->with(['employee', 'replies.employee'])
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->through(fn ($comment) => $this->formatComment($comment));

        return $this->sendSuccess($comments, 'Broadcast comments retrieved successfully.');
    }

    /**
     * POST /api/hrms/broadcasts/{id}/comments
     * Post a comment or reply.
     */
    public function storeComment(Request $request, $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $broadcast = Broadcast::where('tenant_id', $tenantId)->find($id);
        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin) {
            $hasAccess = $broadcast->target_type === 'all'
                || ($employee && $broadcast->receipts()->where('employee_id', $employee->id)->exists());
            if (!$hasAccess) {
                return $this->sendError('Unauthorized action. You are not an intended recipient of this announcement.', 403);
            }
        }

        if (!$broadcast->allow_comments) {
            return $this->sendError('Comments are disabled for this broadcast announcement.', 422);
        }

        $validated = $request->validate([
            'comment_text' => 'required|string',
            'parent_id'    => 'nullable|exists:broadcast_comments,id',
        ]);

        if (!empty($validated['parent_id'])) {
            $parentValid = BroadcastComment::where('id', $validated['parent_id'])
                ->where('broadcast_id', $broadcast->id)
                ->where('tenant_id', $tenantId)
                ->exists();
            if (!$parentValid) {
                return $this->sendError('Invalid parent comment reference for this announcement.', 422);
            }
        }

        if (!$employee) {
            return $this->sendError('Employee record not found for authenticated user.', 404);
        }

        $comment = $this->broadcastService->addComment(
            $broadcast,
            $employee->id,
            $validated['comment_text'],
            $validated['parent_id'] ?? null
        );

        $comment->load(['employee', 'replies.employee']);

        return $this->sendSuccess($this->formatComment($comment), 'Comment posted successfully.', 201);
    }

    /**
     * GET /api/hrms/broadcasts/{id}/analytics
     * Get delivery metrics.
     */
    public function getAnalytics($id): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to view broadcast analytics.', 403);
        }

        $tenantId = $this->getTenantId();
        $broadcast = Broadcast::where('tenant_id', $tenantId)->find($id);
        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        $analytics = $this->broadcastService->getAnalytics($broadcast);

        return $this->sendSuccess($analytics, 'Broadcast delivery analytics retrieved successfully.');
    }
}
