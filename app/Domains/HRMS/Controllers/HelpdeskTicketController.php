<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HelpdeskCategory;
use App\Domains\HRMS\Models\HelpdeskSatisfactionRating;
use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Domains\HRMS\Models\HelpdeskTicketAttachment;
use App\Domains\HRMS\Repositories\HelpdeskTicketRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HelpdeskTicketController extends Controller
{
    public function __construct(
        private readonly HelpdeskTicketRepositoryInterface $ticketRepository,
        private readonly AccessService $access
    ) {
    }

    public function index(Request $request): View
    {
        $data = $this->ticketRepository->getIndexData($request->all());
        return view('modules.hrms.helpdesk.index', $data);
    }

    public function create(): View
    {
        $tenantId = tenant_id();
        $categories = HelpdeskCategory::where('tenant_id', $tenantId)->where('is_active', true)->get();

        return view('modules.hrms.helpdesk.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id();
        $request->validate([
            'category_id'   => ['required', Rule::exists('helpdesk_categories', 'id')->where('tenant_id', $tenantId)],
            'subject'       => 'required|string|max:255',
            'description'   => 'required|string',
            'priority'      => 'required|in:low,medium,high,urgent',
            'attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv|max:10240',
        ]);

        $files = $request->file('attachments');
        $ticket = $this->ticketRepository->createTicket($request->all(), $files);

        // Send Notification to HR / Support Admins
        \App\Services\Notification\NotificationService::sendToHrAdmins(
            title: "New Ticket #{$ticket->ticket_number}",
            message: "Ticket created: {$ticket->subject}",
            actionUrl: route('hrms.helpdesk.tickets.show', $ticket->id),
            type: 'helpdesk_ticket',
            iconClass: 'feather-help-circle'
        );

        try {
            \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.ticket.created', [
                'tenant_id'     => $ticket->tenant_id,
                'ticket_no'     => $ticket->ticket_number ?? ('#' . $ticket->id),
                'subject'       => $ticket->subject,
                'category'      => $ticket->category?->name ?? 'General',
                'priority'      => ucfirst($ticket->priority),
                'employee_name' => $ticket->employee?->full_name ?? (auth()->user()?->name ?? 'Employee'),
                'action_url'    => route('hrms.helpdesk.tickets.show', $ticket->id),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("NotificationRule trigger failed: " . $e->getMessage());
        }

        return redirect()->route('hrms.helpdesk.tickets.show', $ticket->id)
            ->with('success', __('hrms.helpdesk.flash_ticket_created', ['number' => $ticket->ticket_number]));
    }

    public function show(int $id): View
    {
        $tenantId = tenant_id();
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)
            ->with(['employee', 'category', 'assignedAgent', 'replies.sender', 'replies.attachments', 'attachments', 'rating'])
            ->findOrFail($id);

        $user = auth()->user();
        $currentEmployee = Employee::resolveForUser($user);

        $isSuperAdmin = $user && (strtolower($user->role ?? '') === 'super_admin'
            || strtolower($user->role ?? '') === 'super-admin'
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
            || $user->is_admin);

        $canManage = $user && ($user->is_admin
            || $this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        $isAssignedAgent = $currentEmployee && $ticket->assigned_to && (int)$currentEmployee->id === (int)$ticket->assigned_to;
        $isOwnTicket = $currentEmployee && (int)$currentEmployee->id === (int)$ticket->employee_id;

        if (!$canManage) {
            if (!$currentEmployee || (!$isOwnTicket && !$isAssignedAgent)) {
                abort(403, 'Unauthorized access to this support ticket.');
            }
            // Defend internal notes from ticket owners and assigned agents
            $ticket->setRelation(
                'replies',
                $ticket->replies->filter(fn ($r) => !$r->is_internal_note)->values()
            );
        }

        // Anti-conflict-of-interest check: ticket creator cannot manage/update their own ticket unless Super Admin
        $canUpdate = ($canManage || $isAssignedAgent) && (!$isOwnTicket || $isSuperAdmin);

        $agents = Employee::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('status', true)->orWhere('status', 1)->orWhere('status', '1');
            })
            ->orderBy('full_name')
            ->get();
        if ($agents->isEmpty()) {
            $agents = Employee::where('tenant_id', $tenantId)->orderBy('full_name')->get();
        }
        $categories = HelpdeskCategory::where('tenant_id', $tenantId)->where('is_active', true)->get();

        return view('modules.hrms.helpdesk.show', compact('ticket', 'canManage', 'canUpdate', 'currentEmployee', 'isOwnTicket', 'agents', 'categories', 'isSuperAdmin'));
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'message'          => 'required|string',
            'is_internal_note' => 'nullable|boolean',
            'attachments.*'    => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv|max:10240',
        ]);

        $tenantId = tenant_id();
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)->findOrFail($id);

        if ($ticket->status === 'closed') {
            return redirect()->back()->with('error', __('hrms.helpdesk.flash_cannot_reply_closed'));
        }

        $user = auth()->user();
        $currentEmployee = Employee::resolveForUser($user);
        $canManage = $user && ($user->is_admin
            || $this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        $isAssignedAgent = $currentEmployee && $ticket->assigned_to && (int)$currentEmployee->id === (int)$ticket->assigned_to;
        $isOwnTicket = $currentEmployee && (int)$currentEmployee->id === (int)$ticket->employee_id;

        if (!$canManage) {
            if (!$currentEmployee || (!$isOwnTicket && !$isAssignedAgent)) {
                abort(403, 'Unauthorized to reply to this support ticket.');
            }
        }

        if ($request->boolean('is_internal_note') && !$canManage) {
            return redirect()->back()->with('error', __('hrms.helpdesk.flash_internal_note_unauthorized'));
        }

        $files = $request->file('attachments');
        $this->ticketRepository->addReply($ticket, $request->all(), $files);

        // Trigger Notification on Reply
        if (!$request->boolean('is_internal_note')) {
            if ($canManage && $ticket->employee) {
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

        return redirect()->route('hrms.helpdesk.tickets.show', $ticket->id)
            ->with('success', $request->boolean('is_internal_note') ? __('hrms.helpdesk.flash_internal_note_added') : __('hrms.helpdesk.flash_reply_posted'));
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id();
        $request->validate([
            'status'      => 'required|in:open,in_progress,pending_employee,resolved,closed',
            'assigned_to' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'priority'    => 'nullable|in:low,medium,high,urgent',
        ]);

        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $user = auth()->user();
        $isSuperAdmin = $user && (strtolower($user->role ?? '') === 'super_admin'
            || strtolower($user->role ?? '') === 'super-admin'
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
            || $user->is_admin);

        $wasClosed = $ticket->status === 'closed';

        // Option 3: Lock on Closed - only Super Admin can reopen or modify a closed ticket
        if ($wasClosed && !$isSuperAdmin) {
            return redirect()->back()->with('error', __('hrms.helpdesk.flash_ticket_closed_locked'));
        }

        app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class)->authorizeTicketManagement($user, $ticket);

        $newStatus = $request->input('status');
        $this->ticketRepository->updateTicketStatus(
            $ticket,
            $newStatus,
            $request->input('assigned_to'),
            $request->input('priority')
        );

        // Notify employee on status change
        if ($ticket->employee) {
            $statusLabel = ucfirst(str_replace('_', ' ', $newStatus));
            \App\Services\Notification\NotificationService::sendToEmployee(
                employee: $ticket->employee,
                title: "Ticket #{$ticket->ticket_number} Updated",
                message: "Your ticket status has been updated to {$statusLabel}.",
                actionUrl: route('hrms.helpdesk.tickets.show', $ticket->id),
                type: 'helpdesk_status',
                iconClass: 'feather-check-circle'
            );
        }

        if ($newStatus === 'resolved') {
            try {
                \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.ticket.resolved', [
                    'tenant_id'   => $ticket->tenant_id,
                    'ticket_no'   => $ticket->ticket_number ?? ('#' . $ticket->id),
                    'subject'     => $ticket->subject,
                    'resolved_by' => auth()->user()?->name ?? 'Support Agent',
                    'action_url'  => route('hrms.helpdesk.tickets.show', $ticket->id),
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("NotificationRule trigger failed: " . $e->getMessage());
            }
        }

        $isReopened = $wasClosed && $newStatus !== 'closed';

        return redirect()->route('hrms.helpdesk.tickets.show', $ticket->id)
            ->with('success', $isReopened ? __('hrms.helpdesk.flash_ticket_reopened') : __('hrms.helpdesk.flash_ticket_updated'));
    }

    public function submitCsat(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'rating'   => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $tenantId = tenant_id();
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)->findOrFail($id);

        if (!in_array($ticket->status, ['resolved', 'closed'])) {
            return redirect()->back()->with('error', __('hrms.helpdesk.flash_csat_resolved_only'));
        }

        $employee = Employee::resolveForUser(auth()->user());
        if (!$employee || (int)$employee->id !== (int)$ticket->employee_id) {
            return redirect()->back()->with('error', __('hrms.helpdesk.flash_csat_owner_only'));
        }

        HelpdeskSatisfactionRating::updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'rating'   => $request->input('rating'),
                'feedback' => $request->input('feedback'),
            ]
        );

        return redirect()->route('hrms.helpdesk.tickets.show', $ticket->id)
            ->with('success', __('hrms.helpdesk.flash_csat_thank_you'));
    }

    public function downloadAttachment(int $ticketId, int $attachmentId)
    {
        $tenantId = tenant_id();
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)->findOrFail($ticketId);

        $user = auth()->user();
        $currentEmployee = Employee::resolveForUser($user);

        $canManage = $user && ($user->is_admin
            || $this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        $isAssignedAgent = $currentEmployee && $ticket->assigned_to && (int)$currentEmployee->id === (int)$ticket->assigned_to;
        $isOwnTicket = $currentEmployee && (int)$currentEmployee->id === (int)$ticket->employee_id;

        if (!$canManage && (!$currentEmployee || (!$isOwnTicket && !$isAssignedAgent))) {
            abort(403, 'Unauthorized access to this support ticket.');
        }

        $attachment = HelpdeskTicketAttachment::where('ticket_id', $ticket->id)->findOrFail($attachmentId);

        if ($attachment->reply && $attachment->reply->is_internal_note && !$canManage) {
            abort(403, 'Unauthorized access to internal note attachments.');
        }

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            abort(404, 'Attachment file not found on disk.');
        }

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }
}
