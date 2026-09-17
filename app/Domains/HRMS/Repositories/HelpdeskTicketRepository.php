<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HelpdeskCategory;
use App\Domains\HRMS\Models\HelpdeskKbArticle;
use App\Domains\HRMS\Models\HelpdeskSatisfactionRating;
use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Domains\HRMS\Models\HelpdeskTicketAttachment;
use App\Domains\HRMS\Models\HelpdeskTicketReply;
use App\Services\Access\AccessService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HelpdeskTicketRepository
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function ensureTablesExist(): void
    {
        try {
            if (!Schema::hasTable('helpdesk_tickets')) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Exception $e) {
            // Silently fallback if migration CLI unavailable
        }
    }

    public function getIndexData(array $inputs): array
    {
        $this->ensureTablesExist();

        $user = auth()->user();
        $tenantId = tenant_id();
        $employee = Employee::resolveForUser($user);

        $canManage = $user && ($this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]));

        $query = HelpdeskTicket::query()
            ->with(['employee', 'category', 'assignedAgent', 'rating'])
            ->where('tenant_id', $tenantId);

        // Access Scoping: Non-management users can only view tickets they requested or are assigned to
        if (!$canManage) {
            $empId = $employee ? $employee->id : 0;
            $query->where(function ($q) use ($empId) {
                $q->where('employee_id', $empId)
                  ->orWhere('assigned_to', $empId);
            });
        }

        // Filter tab
        $tab = $inputs['tab'] ?? 'all';
        if ($tab === 'my_tickets' && $employee) {
            $query->where('employee_id', $employee->id);
        } elseif ($tab === 'assigned_to_me' && $employee) {
            $query->where('assigned_to', $employee->id);
        } elseif ($tab === 'unassigned') {
            $query->whereNull('assigned_to')->whereNotIn('status', ['resolved', 'closed']);
        } elseif ($tab === 'overdue') {
            $query->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereNotIn('status', ['resolved', 'closed']);
        } elseif ($tab === 'resolved') {
            $query->whereIn('status', ['resolved', 'closed']);
        } elseif ($tab === 'open') {
            $query->whereNotIn('status', ['resolved', 'closed']);
        }

        // Search
        if (!empty($inputs['search'])) {
            $search = trim($inputs['search']);
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('office_email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('assignedAgent', function ($aq) use ($search) {
                      $aq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('office_email', 'like', "%{$search}%");
                  });
            });
        }

        // Category & Priority filter
        if (!empty($inputs['category_id'])) {
            $query->where('category_id', $inputs['category_id']);
        }
        if (!empty($inputs['priority'])) {
            $query->where('priority', $inputs['priority']);
        }

        // Sorting
        $sort = $inputs['sort'] ?? 'newest';
        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sort === 'due_soon') {
            $query->orderBy('due_at', 'asc');
        } elseif ($sort === 'priority_desc') {
            $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')");
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        $tickets = $query->paginate(15);

        // Stats Computation
        $statsQuery = HelpdeskTicket::query()->where('tenant_id', $tenantId);
        if (!$canManage) {
            $empId = $employee ? $employee->id : 0;
            $statsQuery->where(function ($q) use ($empId) {
                $q->where('employee_id', $empId)
                  ->orWhere('assigned_to', $empId);
            });
        }

        $totalOpen = (clone $statsQuery)->whereNotIn('status', ['resolved', 'closed'])->count();
        $totalOverdue = (clone $statsQuery)->whereNotNull('due_at')->where('due_at', '<', now())->whereNotIn('status', ['resolved', 'closed'])->count();
        $totalResolved = (clone $statsQuery)->whereIn('status', ['resolved', 'closed'])->count();

        $csatAvg = HelpdeskSatisfactionRating::whereHas('ticket', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->avg('rating') ?: 5.0;

        $categories = HelpdeskCategory::where('tenant_id', $tenantId)->where('is_active', true)->get();

        $employees = Employee::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('status', true)->orWhere('status', 1)->orWhere('status', '1');
            })
            ->orderBy('full_name')
            ->get();
        if ($employees->isEmpty()) {
            $employees = Employee::where('tenant_id', $tenantId)->orderBy('full_name')->get();
        }

        return [
            'tickets'       => $tickets,
            'categories'    => $categories,
            'employees'     => $employees,
            'totalOpen'     => $totalOpen,
            'totalOverdue'  => $totalOverdue,
            'totalResolved' => $totalResolved,
            'csatAvg'       => round($csatAvg, 1),
            'canManage'     => $canManage,
            'currentTab'    => $tab,
            'employee'      => $employee,
        ];
    }

    public function createTicket(array $data, ?array $files = null): HelpdeskTicket
    {
        $tenantId = tenant_id();
        $user = auth()->user();
        $employee = Employee::resolveForUser($user);

        $category = HelpdeskCategory::where('tenant_id', $tenantId)->findOrFail($data['category_id']);

        $slaHours = $category->default_sla_hours ?: 24;
        $dueAt = now()->addHours($slaHours);

        $assignedTo = $category->default_agent_id;

        $ticketNumber = HelpdeskTicket::generateTicketNumber($tenantId);

        $ticket = HelpdeskTicket::create([
            'tenant_id'       => $tenantId,
            'ticket_number'   => $ticketNumber,
            'employee_id'     => $employee ? $employee->id : 0,
            'category_id'     => $category->id,
            'priority'        => $data['priority'] ?? 'medium',
            'status'          => 'open',
            'subject'         => $data['subject'],
            'description'     => $data['description'],
            'assigned_to'     => $assignedTo,
            'due_at'          => $dueAt,
            'is_confidential' => $category->is_confidential || !empty($data['is_confidential']),
        ]);

        if ($files) {
            foreach ($files as $file) {
                $path = $file->store("helpdesk/{$tenantId}/tickets/{$ticket->id}", 'public');
                HelpdeskTicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return $ticket;
    }

    public function addReply(HelpdeskTicket $ticket, array $data, ?array $files = null): HelpdeskTicketReply
    {
        $user = auth()->user();
        $employee = Employee::resolveForUser($user);

        $reply = HelpdeskTicketReply::create([
            'tenant_id'        => $ticket->tenant_id,
            'ticket_id'        => $ticket->id,
            'sender_id'        => $employee ? $employee->id : 0,
            'message'          => $data['message'],
            'is_internal_note' => !empty($data['is_internal_note']),
        ]);

        // If not internal note, update ticket status to in_progress or pending_employee
        if (empty($data['is_internal_note'])) {
            if ($employee && (int)$employee->id === (int)$ticket->employee_id) {
                $ticket->update(['status' => 'in_progress']);
            } else {
                $ticket->update(['status' => 'pending_employee']);
            }
        }

        if ($files) {
            foreach ($files as $file) {
                $path = $file->store("helpdesk/{$ticket->tenant_id}/tickets/{$ticket->id}", 'public');
                HelpdeskTicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'reply_id'  => $reply->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return $reply;
    }

    public function updateTicketStatus(HelpdeskTicket $ticket, string $status, ?int $assignedTo = null, ?string $priority = null): void
    {
        $updates = ['status' => $status];

        if ($status === 'resolved' && !$ticket->resolved_at) {
            $updates['resolved_at'] = now();
        } elseif ($status === 'closed' && !$ticket->closed_at) {
            $updates['closed_at'] = now();
        }

        if ($assignedTo !== null) {
            $updates['assigned_to'] = $assignedTo ?: null;
        }

        if ($priority !== null) {
            $updates['priority'] = $priority;
        }

        $ticket->update($updates);
    }
}
