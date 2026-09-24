<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HelpdeskCategory;
use App\Domains\HRMS\Models\HelpdeskKbArticle;
use App\Domains\HRMS\Models\HelpdeskSatisfactionRating;
use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Domains\HRMS\Models\HelpdeskTicketAttachment;
use App\Domains\HRMS\Models\HelpdeskTicketReply;
use App\Domains\HRMS\Repositories\HelpdeskTicketRepository;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HelpdeskApiController extends Controller
{
    public function __construct(
        private readonly HelpdeskTicketRepository $ticketRepository,
        private readonly AccessService $access
    ) {}

    /**
     * Standardized JSON success response envelope.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Standardized JSON error response envelope.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
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

    /**
     * Helper to check authentication.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();
            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access.', 401);
            }
        }

        return null;
    }

    /**
     * Check if user is HR Admin / Support Manager.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $tenantId = tenant_id() ?? $user->tenant_id ?? 1;

        return (bool) (
            $user->is_admin ||
            $this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId]) ||
            $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId])
        );
    }

    /**
     * Get authenticated employee context.
     */
    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Employee::resolveForUser($user);
    }

    // ==========================================
    // 1. HELPDESK TICKETS APIs
    // ==========================================

    /**
     * GET /api/hrms/helpdesk
     * Paginated list of helpdesk tickets, summary stats, and categories lookup.
     */
    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $indexData = $this->ticketRepository->getIndexData($request->all());
        $ticketsPaginator = $indexData['tickets'];

        $formattedTickets = $ticketsPaginator->through(function ($ticket) {
            return [
                'id'              => $ticket->id,
                'ticket_number'   => $ticket->ticket_number,
                'subject'         => $ticket->subject,
                'description'     => $ticket->description,
                'priority'        => $ticket->priority,
                'status'          => $ticket->status,
                'is_confidential' => (bool) $ticket->is_confidential,
                'due_at'          => $ticket->due_at?->toIso8601String(),
                'resolved_at'     => $ticket->resolved_at?->toIso8601String(),
                'closed_at'       => $ticket->closed_at?->toIso8601String(),
                'category'        => $ticket->category ? [
                    'id'   => $ticket->category->id,
                    'name' => $ticket->category->name,
                ] : null,
                'employee'        => $ticket->employee ? [
                    'id'            => $ticket->employee->id,
                    'employee_code' => $ticket->employee->employee_id ?? null,
                    'name'          => trim(($ticket->employee->first_name ?? '') . ' ' . ($ticket->employee->last_name ?? '')) ?: ($ticket->employee->full_name ?? null),
                    'email'         => $ticket->employee->office_email ?? $ticket->employee->personal_email ?? null,
                ] : null,
                'assigned_agent'  => $ticket->assignedAgent ? [
                    'id'    => $ticket->assignedAgent->id,
                    'name'  => trim(($ticket->assignedAgent->first_name ?? '') . ' ' . ($ticket->assignedAgent->last_name ?? '')) ?: ($ticket->assignedAgent->full_name ?? null),
                    'email' => $ticket->assignedAgent->office_email ?? $ticket->assignedAgent->personal_email ?? null,
                ] : null,
                'rating'          => $ticket->rating ? [
                    'rating'   => $ticket->rating->rating,
                    'feedback' => $ticket->rating->feedback,
                ] : null,
                'created_at'      => $ticket->created_at?->toIso8601String(),
            ];
        });

        $categoriesLookup = $indexData['categories']->map(function ($cat) {
            return [
                'id'   => $cat->id,
                'name' => $cat->name,
                'code' => $cat->code,
            ];
        });

        return $this->sendSuccess([
            'stats' => [
                'total_open'     => $indexData['totalOpen'],
                'total_overdue'  => $indexData['totalOverdue'],
                'total_resolved' => $indexData['totalResolved'],
                'csat_avg'       => $indexData['csatAvg'],
            ],
            'categories' => $categoriesLookup,
            'tickets'    => $formattedTickets,
        ], 'Helpdesk tickets loaded successfully.');
    }

    /**
     * POST /api/hrms/helpdesk/tickets
     * Create a new helpdesk support ticket.
     */
    public function storeTicket(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'category_id'     => 'required|exists:helpdesk_categories,id',
            'subject'         => 'required|string|max:255',
            'description'     => 'required|string',
            'priority'        => 'required|in:low,medium,high,urgent',
            'is_confidential' => 'nullable|boolean',
            'attachments.*'   => 'nullable|file|max:10240',
        ]);

        $files = $request->file('attachments');
        $ticket = $this->ticketRepository->createTicket($validated, $files);

        // Send Notification to HR / Support Admins
        \App\Services\Notification\NotificationService::sendToHrAdmins(
            title: "New Ticket #{$ticket->ticket_number}",
            message: "Ticket created: {$ticket->subject}",
            actionUrl: route('hrms.helpdesk.tickets.show', $ticket->id),
            type: 'helpdesk_ticket',
            iconClass: 'feather-help-circle'
        );

        return $this->sendSuccess($this->formatTicketDetail($ticket), 'Helpdesk ticket created successfully.', 201);
    }

    /**
     * GET /api/hrms/helpdesk/tickets/{id}
     * Get ticket details, conversation history, and attachments.
     */
    public function showTicket(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)
            ->with(['employee', 'category', 'assignedAgent', 'replies.sender', 'replies.attachments', 'attachments', 'rating'])
            ->find($id);

        if (!$ticket) {
            return $this->sendError("Helpdesk ticket with ID '{$id}' not found.", 404);
        }

        $isHrAdmin = $this->isHrAdmin();
        $currentEmployee = $this->getAuthenticatedEmployee();

        if (!$isHrAdmin && $currentEmployee) {
            if ((int)$currentEmployee->id !== (int)$ticket->employee_id && (int)$currentEmployee->id !== (int)$ticket->assigned_to) {
                return $this->sendError('Unauthorized access to this support ticket.', 403);
            }
        }

        return $this->sendSuccess($this->formatTicketDetail($ticket), 'Helpdesk ticket details loaded successfully.');
    }

    /**
     * POST /api/hrms/helpdesk/tickets/{id}/reply
     * Post a reply or internal note on a support ticket.
     */
    public function replyTicket(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)->find($id);

        if (!$ticket) {
            return $this->sendError("Helpdesk ticket with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'message'          => 'required|string',
            'is_internal_note' => 'nullable|boolean',
            'attachments.*'    => 'nullable|file|max:10240',
        ]);

        $isHrAdmin = $this->isHrAdmin();
        if (!empty($validated['is_internal_note']) && !$isHrAdmin) {
            return $this->sendError('Only authorized HR staff can post internal notes.', 403);
        }

        $files = $request->file('attachments');
        $reply = $this->ticketRepository->addReply($ticket, $validated, $files);

        // Notifications
        if (empty($validated['is_internal_note'])) {
            if ($isHrAdmin && $ticket->employee) {
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $ticket->employee,
                    title: "Reply on Ticket #{$ticket->ticket_number}",
                    message: "Support staff replied to your ticket: {$ticket->subject}",
                    actionUrl: route('hrms.helpdesk.tickets.show', $ticket->id),
                    type: 'helpdesk_reply',
                    iconClass: 'feather-message-square'
                );
            } elseif ($ticket->assignedAgent) {
                \App\Services\Notification\NotificationService::sendToEmployee(
                    employee: $ticket->assignedAgent,
                    title: "User replied on Ticket #{$ticket->ticket_number}",
                    message: "New message on ticket {$ticket->ticket_number}",
                    actionUrl: route('hrms.helpdesk.tickets.show', $ticket->id),
                    type: 'helpdesk_reply',
                    iconClass: 'feather-message-square'
                );
            }
        }

        return $this->sendSuccess($this->formatTicketDetail($ticket->fresh()), !empty($validated['is_internal_note']) ? 'Internal note added.' : 'Reply posted successfully.', 201);
    }

    /**
     * PUT /api/hrms/helpdesk/tickets/{id}/status
     * Update status, assign agent, or change priority.
     */
    public function updateTicketStatus(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)->find($id);

        if (!$ticket) {
            return $this->sendError("Helpdesk ticket with ID '{$id}' not found.", 404);
        }

        app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class)->authorizeTicketManagement(auth()->user(), $ticket);

        $validated = $request->validate([
            'status'      => 'required|in:open,in_progress,pending_employee,resolved,closed',
            'assigned_to' => 'nullable|exists:employees,id',
            'priority'    => 'nullable|in:low,medium,high,urgent',
        ]);

        $this->ticketRepository->updateTicketStatus(
            $ticket,
            $validated['status'],
            $validated['assigned_to'] ?? null,
            $validated['priority'] ?? null
        );

        if ($ticket->employee) {
            $statusLabel = ucfirst(str_replace('_', ' ', $validated['status']));
            \App\Services\Notification\NotificationService::sendToEmployee(
                employee: $ticket->employee,
                title: "Ticket #{$ticket->ticket_number} Updated",
                message: "Your ticket status has been updated to {$statusLabel}.",
                actionUrl: route('hrms.helpdesk.tickets.show', $ticket->id),
                type: 'helpdesk_status',
                iconClass: 'feather-check-circle'
            );
        }

        return $this->sendSuccess($this->formatTicketDetail($ticket->fresh()), 'Ticket status updated successfully.');
    }

    /**
     * POST /api/hrms/helpdesk/tickets/{id}/csat
     * Submit satisfaction rating (CSAT).
     */
    public function submitCsat(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)->find($id);

        if (!$ticket) {
            return $this->sendError("Helpdesk ticket with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'rating'   => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $rating = HelpdeskSatisfactionRating::updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'rating'   => $validated['rating'],
                'feedback' => $validated['feedback'] ?? null,
            ]
        );

        return $this->sendSuccess($rating, 'Satisfaction rating submitted successfully.');
    }

    // ==========================================
    // 2. HELPDESK CATEGORIES APIs
    // ==========================================

    /**
     * GET /api/hrms/helpdesk/categories
     */
    public function indexCategories(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        $categories = HelpdeskCategory::where('tenant_id', $tenantId)
            ->with(['defaultAgent'])
            ->withCount('tickets')
            ->orderBy('name')
            ->get()
            ->map(function ($cat) {
                return [
                    'id'                => $cat->id,
                    'name'              => $cat->name,
                    'code'              => $cat->code,
                    'description'       => $cat->description,
                    'default_sla_hours' => $cat->default_sla_hours,
                    'is_confidential'   => (bool) $cat->is_confidential,
                    'is_active'         => (bool) $cat->is_active,
                    'tickets_count'     => $cat->tickets_count ?? 0,
                    'default_agent'     => $cat->defaultAgent ? [
                        'id'   => $cat->defaultAgent->id,
                        'name' => trim(($cat->defaultAgent->first_name ?? '') . ' ' . ($cat->defaultAgent->last_name ?? '')) ?: ($cat->defaultAgent->full_name ?? null),
                    ] : null,
                    'created_at'        => $cat->created_at?->toIso8601String(),
                ];
            });

        return $this->sendSuccess($categories, 'Helpdesk categories retrieved successfully.');
    }

    /**
     * POST /api/hrms/helpdesk/categories
     */
    public function storeCategory(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to create categories.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'default_agent_id'  => 'nullable|exists:employees,id',
            'default_sla_hours' => 'required|integer|min:1',
            'is_confidential'   => 'nullable|boolean',
        ]);

        $code = Str::slug($validated['name'], '_');

        $category = HelpdeskCategory::create([
            'tenant_id'         => $tenantId,
            'name'              => $validated['name'],
            'code'              => $code,
            'description'       => $validated['description'] ?? null,
            'default_agent_id'  => $validated['default_agent_id'] ?? null,
            'default_sla_hours' => $validated['default_sla_hours'],
            'is_confidential'   => $request->boolean('is_confidential'),
            'is_active'         => true,
        ]);

        return $this->sendSuccess($category->load('defaultAgent'), 'Helpdesk category created successfully.', 201);
    }

    /**
     * PUT /api/hrms/helpdesk/categories/{id}
     */
    public function updateCategory(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to update categories.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $category = HelpdeskCategory::where('tenant_id', $tenantId)->find($id);

        if (!$category) {
            return $this->sendError("Helpdesk category with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'default_agent_id'  => 'nullable|exists:employees,id',
            'default_sla_hours' => 'required|integer|min:1',
            'is_confidential'   => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
        ]);

        $category->update([
            'name'              => $validated['name'],
            'description'       => $validated['description'] ?? null,
            'default_agent_id'  => $validated['default_agent_id'] ?? null,
            'default_sla_hours' => $validated['default_sla_hours'],
            'is_confidential'   => $request->boolean('is_confidential'),
            'is_active'         => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active,
        ]);

        return $this->sendSuccess($category->fresh(['defaultAgent']), 'Helpdesk category updated successfully.');
    }

    /**
     * DELETE /api/hrms/helpdesk/categories/{id}
     */
    public function destroyCategory(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to delete categories.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $category = HelpdeskCategory::where('tenant_id', $tenantId)->find($id);

        if (!$category) {
            return $this->sendError("Helpdesk category with ID '{$id}' not found.", 404);
        }

        if ($category->tickets()->exists()) {
            return $this->sendError("Cannot delete category '{$category->name}' because it contains active helpdesk tickets.", 422);
        }

        $category->delete();

        return $this->sendSuccess(['id' => (int) $id], 'Helpdesk category deleted successfully.');
    }

    // ==========================================
    // 3. KNOWLEDGE BASE (KB) APIs
    // ==========================================

    /**
     * GET /api/hrms/helpdesk/kb
     */
    public function indexKb(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $isHrAdmin = $this->isHrAdmin();
        $search = $request->input('search');
        $categoryId = $request->input('category_id');

        $query = HelpdeskKbArticle::where('tenant_id', $tenantId)->with('category');

        if (!$isHrAdmin) {
            $query->where('is_published', true);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $articles = $query->orderBy('view_count', 'desc')
            ->paginate($request->integer('per_page', 12))
            ->through(function ($article) {
                return [
                    'id'           => $article->id,
                    'title'        => $article->title,
                    'slug'         => $article->slug,
                    'content'      => $article->content,
                    'is_published' => (bool) $article->is_published,
                    'view_count'   => (int) $article->view_count,
                    'category'     => $article->category ? [
                        'id'   => $article->category->id,
                        'name' => $article->category->name,
                    ] : null,
                    'created_at'   => $article->created_at?->toIso8601String(),
                ];
            });

        return $this->sendSuccess($articles, 'Knowledge Base articles retrieved successfully.');
    }

    /**
     * GET /api/hrms/helpdesk/kb/suggest
     * Smart search suggestions.
     */
    public function suggestKb(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return $this->sendSuccess([], 'Query string too short.');
        }

        $articles = HelpdeskKbArticle::where('tenant_id', $tenantId)
            ->where('is_published', true)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->take(5)
            ->get(['id', 'title', 'slug']);

        return $this->sendSuccess($articles, 'Knowledge Base suggestions loaded.');
    }

    /**
     * GET /api/hrms/helpdesk/kb/{id}
     */
    public function showKb(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $article = HelpdeskKbArticle::where('tenant_id', $tenantId)
            ->with('category')
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('slug', $id);
            })->first();

        if (!$article) {
            return $this->sendError("Knowledge Base article with ID/slug '{$id}' not found.", 404);
        }

        $article->increment('view_count');

        return $this->sendSuccess([
            'id'           => $article->id,
            'title'        => $article->title,
            'slug'         => $article->slug,
            'content'      => $article->content,
            'is_published' => (bool) $article->is_published,
            'view_count'   => (int) $article->view_count,
            'category'     => $article->category ? [
                'id'   => $article->category->id,
                'name' => $article->category->name,
            ] : null,
            'created_at'   => $article->created_at?->toIso8601String(),
        ], 'Knowledge Base article details loaded.');
    }

    /**
     * POST /api/hrms/helpdesk/kb
     */
    public function storeKb(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'category_id'  => 'nullable|exists:helpdesk_categories,id',
            'content'      => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $slug = Str::slug($validated['title']) . '-' . time();

        $article = HelpdeskKbArticle::create([
            'tenant_id'    => $tenantId,
            'category_id'  => $validated['category_id'] ?? null,
            'title'        => $validated['title'],
            'slug'         => $slug,
            'content'      => $validated['content'],
            'is_published' => $request->boolean('is_published', true),
        ]);

        return $this->sendSuccess($article->load('category'), 'Knowledge Base article created successfully.', 201);
    }

    /**
     * PUT /api/hrms/helpdesk/kb/{id}
     */
    public function updateKb(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $article = HelpdeskKbArticle::where('tenant_id', $tenantId)->find($id);

        if (!$article) {
            return $this->sendError("Knowledge Base article with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'category_id'  => 'nullable|exists:helpdesk_categories,id',
            'content'      => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $article->update([
            'title'        => $validated['title'],
            'category_id'  => $validated['category_id'] ?? null,
            'content'      => $validated['content'],
            'is_published' => $request->has('is_published') ? $request->boolean('is_published') : $article->is_published,
        ]);

        return $this->sendSuccess($article->fresh('category'), 'Knowledge Base article updated successfully.');
    }

    /**
     * DELETE /api/hrms/helpdesk/kb/{id}
     */
    public function destroyKb(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $article = HelpdeskKbArticle::where('tenant_id', $tenantId)->find($id);

        if (!$article) {
            return $this->sendError("Knowledge Base article with ID '{$id}' not found.", 404);
        }

        $article->delete();

        return $this->sendSuccess(['id' => (int) $id], 'Knowledge Base article deleted successfully.');
    }

    /**
     * Format detailed ticket response.
     */
    private function formatTicketDetail(HelpdeskTicket $ticket): array
    {
        $ticket->loadMissing(['employee', 'category', 'assignedAgent', 'replies.sender', 'replies.attachments', 'attachments', 'rating']);

        return [
            'id'              => $ticket->id,
            'ticket_number'   => $ticket->ticket_number,
            'subject'         => $ticket->subject,
            'description'     => $ticket->description,
            'priority'        => $ticket->priority,
            'status'          => $ticket->status,
            'is_confidential' => (bool) $ticket->is_confidential,
            'due_at'          => $ticket->due_at?->toIso8601String(),
            'resolved_at'     => $ticket->resolved_at?->toIso8601String(),
            'closed_at'       => $ticket->closed_at?->toIso8601String(),
            'category'        => $ticket->category ? [
                'id'   => $ticket->category->id,
                'name' => $ticket->category->name,
            ] : null,
            'employee'        => $ticket->employee ? [
                'id'            => $ticket->employee->id,
                'employee_code' => $ticket->employee->employee_id ?? null,
                'name'          => trim(($ticket->employee->first_name ?? '') . ' ' . ($ticket->employee->last_name ?? '')) ?: ($ticket->employee->full_name ?? null),
                'email'         => $ticket->employee->office_email ?? $ticket->employee->personal_email ?? null,
            ] : null,
            'assigned_agent'  => $ticket->assignedAgent ? [
                'id'    => $ticket->assignedAgent->id,
                'name'  => trim(($ticket->assignedAgent->first_name ?? '') . ' ' . ($ticket->assignedAgent->last_name ?? '')) ?: ($ticket->assignedAgent->full_name ?? null),
                'email' => $ticket->assignedAgent->office_email ?? $ticket->assignedAgent->personal_email ?? null,
            ] : null,
            'rating'          => $ticket->rating ? [
                'rating'   => $ticket->rating->rating,
                'feedback' => $ticket->rating->feedback,
            ] : null,
            'attachments'     => $ticket->attachments ? $ticket->attachments->map(function ($att) {
                return [
                    'id'        => $att->id,
                    'file_name' => $att->file_name,
                    'file_type' => $att->file_type,
                    'file_size' => $att->file_size,
                    'file_url'  => asset('storage/' . $att->file_path),
                ];
            }) : [],
            'replies'         => $ticket->replies ? $ticket->replies->map(function ($rep) {
                return [
                    'id'               => $rep->id,
                    'message'          => $rep->message,
                    'is_internal_note' => (bool) $rep->is_internal_note,
                    'sender'           => $rep->sender ? [
                        'id'   => $rep->sender->id,
                        'name' => trim(($rep->sender->first_name ?? '') . ' ' . ($rep->sender->last_name ?? '')) ?: ($rep->sender->full_name ?? null),
                    ] : null,
                    'attachments'      => $rep->attachments ? $rep->attachments->map(function ($att) {
                        return [
                            'id'        => $att->id,
                            'file_name' => $att->file_name,
                            'file_type' => $att->file_type,
                            'file_size' => $att->file_size,
                            'file_url'  => asset('storage/' . $att->file_path),
                        ];
                    }) : [],
                    'created_at'       => $rep->created_at?->toIso8601String(),
                ];
            }) : [],
            'created_at'      => $ticket->created_at?->toIso8601String(),
        ];
    }
}
