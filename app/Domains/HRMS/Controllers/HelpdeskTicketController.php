<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HelpdeskCategory;
use App\Domains\HRMS\Models\HelpdeskSatisfactionRating;
use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Domains\HRMS\Repositories\HelpdeskTicketRepository;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpdeskTicketController extends Controller
{
    public function __construct(
        private readonly HelpdeskTicketRepository $ticketRepository,
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
        $request->validate([
            'category_id'   => 'required|exists:helpdesk_categories,id',
            'subject'       => 'required|string|max:255',
            'description'   => 'required|string',
            'priority'      => 'required|in:low,medium,high,urgent',
            'attachments.*' => 'nullable|file|max:10240',
        ]);

        $files = $request->file('attachments');
        $ticket = $this->ticketRepository->createTicket($request->all(), $files);

        return redirect()->route('hrms.helpdesk.tickets.show', $ticket->id)
            ->with('success', "Helpdesk Ticket #{$ticket->ticket_number} created successfully.");
    }

    public function show(int $id): View
    {
        $tenantId = tenant_id();
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)
            ->with(['employee', 'category', 'assignedAgent', 'replies.sender', 'replies.attachments', 'attachments', 'rating'])
            ->findOrFail($id);

        $user = auth()->user();
        $currentEmployee = Employee::resolveForUser($user);

        $canManage = $user && ($this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        if (!$canManage && $currentEmployee) {
            if ((int)$currentEmployee->id !== (int)$ticket->employee_id && (int)$currentEmployee->id !== (int)$ticket->assigned_to) {
                abort(403, 'Unauthorized access to this support ticket.');
            }
        }

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

        return view('modules.hrms.helpdesk.show', compact('ticket', 'canManage', 'currentEmployee', 'agents', 'categories'));
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'message'          => 'required|string',
            'is_internal_note' => 'nullable|boolean',
            'attachments.*'    => 'nullable|file|max:10240',
        ]);

        $tenantId = tenant_id();
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $user = auth()->user();
        $canManage = $user && ($this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        if ($request->boolean('is_internal_note') && !$canManage) {
            return redirect()->back()->with('error', 'Only authorized HR staff can post internal notes.');
        }

        $files = $request->file('attachments');
        $this->ticketRepository->addReply($ticket, $request->all(), $files);

        return redirect()->route('hrms.helpdesk.tickets.show', $ticket->id)
            ->with('success', $request->boolean('is_internal_note') ? 'Internal note added.' : 'Reply posted successfully.');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'status'      => 'required|in:open,in_progress,pending_employee,resolved,closed',
            'assigned_to' => 'nullable|exists:employees,id',
            'priority'    => 'nullable|in:low,medium,high,urgent',
        ]);

        $tenantId = tenant_id();
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $this->ticketRepository->updateTicketStatus(
            $ticket,
            $request->input('status'),
            $request->input('assigned_to'),
            $request->input('priority')
        );

        return redirect()->route('hrms.helpdesk.tickets.show', $ticket->id)
            ->with('success', 'Ticket updated successfully.');
    }

    public function submitCsat(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'rating'   => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $tenantId = tenant_id();
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)->findOrFail($id);

        HelpdeskSatisfactionRating::updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'rating'   => $request->input('rating'),
                'feedback' => $request->input('feedback'),
            ]
        );

        return redirect()->route('hrms.helpdesk.tickets.show', $ticket->id)
            ->with('success', 'Thank you for your feedback!');
    }
}
