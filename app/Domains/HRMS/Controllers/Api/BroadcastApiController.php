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
     * GET /api/hrms/broadcasts
     * Target broadcasts feed for logged-in employee (with unread count).
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $this->broadcastService->processScheduledBroadcasts($tenantId);
        $employee = $this->getAuthenticatedEmployee();

        $query = Broadcast::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->with(['creator', 'comments']);

        if ($employee) {
            $query->where(function ($q) use ($employee) {
                $q->where('target_type', 'all')
                  ->orWhereHas('receipts', function ($rq) use ($employee) {
                      $rq->where('employee_id', $employee->id);
                  });
            });
        }

        $broadcasts = $query->latest('published_at')->paginate($request->integer('per_page', 10));

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
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        $broadcasts = Broadcast::where('tenant_id', $tenantId)
            ->with(['creator', 'receipts'])
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->sendSuccess($broadcasts, 'Management broadcasts list retrieved successfully.');
    }

    /**
     * POST /api/hrms/broadcasts
     * Create a new broadcast.
     */
    public function store(Request $request): JsonResponse
    {
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
        ]);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $validated['tenant_id'] = $tenantId;

        $broadcast = $this->broadcastService->createBroadcast($validated);

        return $this->sendSuccess($broadcast->load('receipts'), 'Broadcast announcement created successfully.', 201);
    }

    /**
     * GET /api/hrms/broadcasts/{id}
     * Get single broadcast details.
     */
    public function show($id): JsonResponse
    {
        $broadcast = Broadcast::with(['creator', 'receipts', 'comments.employee', 'comments.replies'])->find($id);

        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        $employee = $this->getAuthenticatedEmployee();

        if ($employee) {
            $this->broadcastService->markRead($broadcast, $employee->id);
        }

        return $this->sendSuccess($broadcast, 'Broadcast details retrieved successfully.');
    }

    /**
     * PUT /api/hrms/broadcasts/{id}
     * Update broadcast details.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $broadcast = Broadcast::find($id);
        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

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

        $broadcast->update($validated);

        return $this->sendSuccess($broadcast->fresh(), 'Broadcast updated successfully.');
    }

    /**
     * DELETE /api/hrms/broadcasts/{id}
     * Delete broadcast.
     */
    public function destroy($id): JsonResponse
    {
        $broadcast = Broadcast::find($id);
        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        $broadcast->receipts()->delete();
        $broadcast->comments()->delete();
        $broadcast->delete();

        return $this->sendSuccess(null, 'Broadcast deleted successfully.');
    }

    /**
     * POST /api/hrms/broadcasts/{id}/acknowledge
     * Mark broadcast read/acknowledged for logged-in employee.
     */
    public function acknowledge(Request $request, $id): JsonResponse
    {
        $broadcast = Broadcast::find($id);
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
     * GET /api/hrms/broadcasts/{id}/comments
     * Get comments for broadcast.
     */
    public function getComments(Request $request, $id): JsonResponse
    {
        $comments = BroadcastComment::where('broadcast_id', $id)
            ->whereNull('parent_id')
            ->with(['employee', 'replies'])
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->sendSuccess($comments, 'Broadcast comments retrieved successfully.');
    }

    /**
     * POST /api/hrms/broadcasts/{id}/comments
     * Post a comment or reply.
     */
    public function storeComment(Request $request, $id): JsonResponse
    {
        $broadcast = Broadcast::find($id);
        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        if (!$broadcast->allow_comments) {
            return $this->sendError('Comments are disabled for this broadcast announcement.', 422);
        }

        $validated = $request->validate([
            'comment_text' => 'required|string',
            'parent_id'    => 'nullable|exists:broadcast_comments,id',
        ]);

        $employee = $this->getAuthenticatedEmployee();

        if (!$employee) {
            return $this->sendError('Employee record not found for authenticated user.', 404);
        }

        $comment = $this->broadcastService->addComment(
            $broadcast,
            $employee->id,
            $validated['comment_text'],
            $validated['parent_id'] ?? null
        );

        return $this->sendSuccess($comment->load('employee'), 'Comment posted successfully.', 201);
    }

    /**
     * GET /api/hrms/broadcasts/{id}/analytics
     * Get delivery metrics.
     */
    public function getAnalytics($id): JsonResponse
    {
        $broadcast = Broadcast::find($id);
        if (!$broadcast) {
            return $this->sendError("Broadcast record with ID '{$id}' not found.", 404);
        }

        $analytics = $this->broadcastService->getAnalytics($broadcast);

        return $this->sendSuccess($analytics, 'Broadcast delivery analytics retrieved successfully.');
    }
}
