@extends('layouts.duralux')

@section('title', 'Ticket #' . $ticket->ticket_number . ' | Helpdesk')
@section('page-title', 'Ticket #' . $ticket->ticket_number)
@section('breadcrumb', 'HRMS / Helpdesk / Ticket Thread')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.helpdesk.tickets.index') }}" class="border text-dark fw-semibold">
            Back to Helpdesk
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    /* Custom Styling for Helpdesk Ticket Detail Page */
    .ticket-hero-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 16px;
    }

    .ticket-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background-color: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .ticket-card-header {
        border-bottom: 1px solid #f1f5f9;
        padding: 1.1rem 1.5rem;
        background: transparent;
    }

    .ticket-description-body {
        background-color: #f8fafc;
        border-radius: 0 12px 12px 0;
        padding: 1.25rem 1.5rem;
        border: 1px solid #edf2f7;
        border-left: 4px solid var(--bs-primary);
        color: #1e293b;
        font-size: 0.95rem;
        line-height: 1.7;
    }

    .internal-note-card {
        background-color: #fffdf0;
        border: 1px solid #fef08a;
        border-left: 4px solid #f59e0b;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
    }

    .internal-note-pill {
        background-color: #f59e0b;
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 6px;
    }

    .reply-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        background-color: #ffffff;
    }

    .avatar-initials-lg {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--bs-primary) 0%, #1d4ed8 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
        box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
    }

    .avatar-initials-md {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background-color: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--bs-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 15px;
    }

    .hero-meta-label {
        font-size: 12px !important;
        font-weight: 700 !important;
        color: #64748b !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .hero-meta-value {
        font-size: 15px !important;
        font-weight: 700 !important;
        color: #0f172a !important;
    }

    .hero-meta-sub {
        font-size: 13px !important;
        color: #64748b !important;
        font-weight: 500 !important;
    }

    .hero-sla-label {
        font-size: 12px !important;
        font-weight: 700 !important;
        color: #475569 !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .hero-sla-value {
        font-size: 15px !important;
        font-weight: 700 !important;
    }

    .hero-sla-date {
        font-size: 13px !important;
        font-weight: 600 !important;
        color: #475569 !important;
    }

    .hero-title {
        font-size: 1.5rem !important;
        font-weight: 800 !important;
        color: #0f172a !important;
    }

    .attachment-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.84rem;
        color: #334155;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s ease-in-out;
    }

    .attachment-pill:hover {
        background-color: #f1f5f9;
        border-color: #94a3b8;
        color: #0f172a;
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">
    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- Top Ticket Summary Banner Card -->
    <div class="ticket-hero-card p-4 mb-4 shadow-sm border-0 bg-white rounded-4">
        <div class="row align-items-center g-3">
            <!-- Left Side: Ticket Badges & Subject Title -->
            <div class="col-lg-7 col-md-7">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                    <x-ui.badge variant="primary" soft class="fw-bold" style="font-size: 12px;">
                        #{{ $ticket->ticket_number }}
                    </x-ui.badge>
                    <x-ui.status-badge :status="$ticket->status" class="px-3 py-1.5" style="font-size: 12px;" />
                    <x-ui.priority-badge :priority="$ticket->priority" :icon="false" class="px-3 py-1.5" style="font-size: 12px;" />

                    @if($ticket->category)
                        <span class="badge bg-light text-dark border rounded-pill px-3 py-1.5" style="font-size: 12px;">
                            <i class="feather-tag me-1 text-primary"></i> {{ $ticket->category->name }}
                        </span>
                    @endif

                    @if($ticket->is_confidential)
                        <x-ui.badge variant="danger" soft style="font-size: 12px;">
                            <i class="feather-lock me-1"></i> Confidential
                        </x-ui.badge>
                    @endif
                </div>
                <h2 class="hero-title mb-0">{{ $ticket->subject }}</h2>
            </div>

            <!-- Right Side: Quick SLA / Target Status Summary -->
            <div class="col-lg-5 col-md-5">
                <div class="bg-light-subtle rounded-3 p-3 border d-flex align-items-center justify-content-between gap-3 shadow-2xs">
                    <div>
                        <div class="hero-sla-label mb-1">SLA Target Resolution</div>
                        @if(in_array($ticket->status, ['resolved', 'closed']))
                            <div class="hero-sla-value text-success"><i class="feather-check-circle me-1"></i> Resolved</div>
                        @elseif($ticket->isOverdue())
                            <div class="hero-sla-value text-danger"><i class="feather-alert-triangle me-1"></i> Overdue Target</div>
                        @else
                            <div class="hero-sla-value text-primary"><i class="feather-clock me-1"></i> Due {{ $ticket->due_at ? $ticket->due_at->diffForHumans() : 'N/A' }}</div>
                        @endif
                    </div>
                    <div class="text-end">
                        <span class="hero-sla-date d-block">{{ $ticket->due_at ? $ticket->due_at->format('M d, Y H:i') : '' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Meta Details Bar with Explicit Readable Typography -->
        <div class="border-top mt-3 pt-3">
            <div class="row g-3 align-items-center text-dark">
                <div class="col-md-4 col-sm-6">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="avatar-initials-xs bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="feather-calendar fs-5"></i>
                        </div>
                        <div>
                            <span class="hero-meta-label d-block leading-tight">Created Date</span>
                            <span class="hero-meta-value">{{ $ticket->created_at->format('M d, Y H:i') }}</span>
                            <span class="hero-meta-sub ms-1">({{ $ticket->created_at->diffForHumans() }})</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="avatar-initials-xs bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="feather-user fs-5"></i>
                        </div>
                        <div>
                            <span class="hero-meta-label d-block leading-tight">Requested By</span>
                            <div class="hero-meta-value">{{ $ticket->employee ? ($ticket->employee->full_name ?? $ticket->employee->first_name . ' ' . $ticket->employee->last_name) : 'N/A' }}</div>
                            @if($ticket->employee)
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-1" style="font-size: 13px;">
                                    @if($ticket->employee->office_email || $ticket->employee->personal_email)
                                        <a href="mailto:{{ $ticket->employee->office_email ?? $ticket->employee->personal_email }}" class="text-secondary text-decoration-none fw-medium">
                                            <i class="feather-mail me-1 text-primary"></i>{{ $ticket->employee->office_email ?? $ticket->employee->personal_email }}
                                        </a>
                                    @endif
                                    @if($ticket->employee->personal_mobile_number || $ticket->employee->home_phone)
                                        <span class="text-secondary fw-medium">
                                            <i class="feather-phone me-1 text-primary"></i>{{ $ticket->employee->personal_mobile_number ?? $ticket->employee->home_phone }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="avatar-initials-xs bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="feather-headphones fs-5"></i>
                        </div>
                        <div>
                            <span class="hero-meta-label d-block leading-tight">Assigned Agent</span>
                            <span class="hero-meta-value">{{ $ticket->assignedAgent ? ($ticket->assignedAgent->full_name ?? $ticket->assignedAgent->first_name . ' ' . $ticket->assignedAgent->last_name) : 'Unassigned' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Main Column: Description, Timeline, Reply Composer -->
        <div class="col-lg-8">
            <!-- Ticket Description Card -->
            <div class="ticket-card mb-4">
                <div class="ticket-card-header d-flex align-items-center justify-content-between">
                    <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                        <i class="feather-file-text text-primary fs-5"></i>
                        <span>Ticket Description</span>
                    </div>
                    <span class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Submitted {{ $ticket->created_at->format('M d, Y \a\t H:i') }}
                    </span>
                </div>
                <div class="p-4">
                    <div class="ticket-description-body">
                        {{ $ticket->description }}
                    </div>

                    @if($ticket->attachments->count() > 0)
                        <div class="mt-3 pt-3 border-top">
                            <div class="fw-semibold text-dark mb-2" style="font-size: 13px;">
                                <i class="feather-paperclip me-1 text-primary"></i> Attachments ({{ $ticket->attachments->count() }}):
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($ticket->attachments as $att)
                                    <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="attachment-pill">
                                        <i class="feather-file me-1 text-primary"></i>
                                        <span>{{ $att->file_name }}</span>
                                        <i class="feather-download ms-1 text-muted" style="font-size: 11px;"></i>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Activity & Responses Timeline Container -->
            <div class="ticket-card mb-4">
                <div class="ticket-card-header d-flex align-items-center justify-content-between">
                    <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                        <i class="feather-message-square text-primary fs-5"></i>
                        <span>Activity & Responses</span>
                        <x-ui.badge variant="primary" class="fw-bold px-2.5 py-1" style="font-size: 12px;">
                            {{ $ticket->replies->count() }}
                        </x-ui.badge>
                    </div>
                </div>
                <div class="p-4">
                    <div class="d-flex flex-column gap-3">
                        @forelse($ticket->replies as $reply)
                            @if($reply->is_internal_note)
                                <!-- Internal HR Note Card -->
                                <div class="internal-note-card">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="internal-note-pill">
                                                <i class="feather-lock me-1"></i> Internal HR Note
                                            </span>
                                            <span class="fw-bold text-dark" style="font-size: 14px;">
                                                {{ $reply->sender ? ($reply->sender->full_name ?? $reply->sender->first_name . ' ' . $reply->sender->last_name) : 'HR Staff' }}
                                            </span>
                                        </div>
                                        <small class="text-muted" style="font-size: 13px;">{{ $reply->created_at->diffForHumans() }}</small>
                                    </div>
                                    <div class="text-dark" style="font-size: 14px; line-height: 1.6; white-space: pre-line;">{{ $reply->message }}</div>

                                    @if($reply->attachments->count() > 0)
                                        <div class="mt-2 pt-2 border-top border-warning-subtle d-flex flex-wrap gap-2">
                                            @foreach($reply->attachments as $att)
                                                <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="attachment-pill">
                                                    <i class="feather-paperclip me-1 text-warning"></i> {{ $att->file_name }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @else
                                <!-- Standard Public Reply Card -->
                                <div class="reply-card">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-initials-md">
                                                {{ strtoupper(substr($reply->sender->first_name ?? 'U', 0, 1) . substr($reply->sender->last_name ?? '', 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark" style="font-size: 15px;">
                                                    {{ $reply->sender ? ($reply->sender->full_name ?? $reply->sender->first_name . ' ' . $reply->sender->last_name) : 'User' }}
                                                </div>
                                                <small class="text-muted" style="font-size: 12px;">
                                                    {{ $reply->sender ? ($reply->sender->employee_id ?? $reply->sender->employee_code) : '' }}
                                                </small>
                                            </div>
                                        </div>
                                        <small class="text-muted" style="font-size: 13px;">
                                            {{ $reply->created_at->format('M d, Y H:i') }} ({{ $reply->created_at->diffForHumans() }})
                                        </small>
                                    </div>

                                    <div class="text-dark" style="font-size: 14px; line-height: 1.6; white-space: pre-line;">{{ $reply->message }}</div>

                                    @if($reply->attachments->count() > 0)
                                        <div class="mt-3 pt-2 border-top">
                                            <div class="small text-muted mb-2" style="font-size: 12px;">Attached files:</div>
                                            <div class="d-flex flex-wrap gap-2">
                                                @foreach($reply->attachments as $att)
                                                    <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="attachment-pill">
                                                        <i class="feather-paperclip me-1 text-primary"></i> {{ $att->file_name }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @empty
                            <div class="text-center py-5 px-4 rounded-3 bg-light-subtle border border-dashed">
                                <div class="avatar-initials-lg mx-auto mb-3 bg-primary-subtle text-primary" style="width: 56px; height: 56px; font-size: 22px;">
                                    <i class="feather-message-square"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 16px;">No responses posted yet</h6>
                                <p class="text-muted mb-0" style="font-size: 13px;">Use the reply composer below to post a response or update the ticket requester.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Post Response, Ticket Controls & Overview Summary -->
        <div class="col-lg-4">
            <!-- Agent Control Actions Box -->
            @if($canManage)
                <div class="ticket-card mb-4">
                    <div class="ticket-card-header">
                        <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                            <i class="feather-sliders text-primary fs-5"></i>
                            <span>Ticket Management</span>
                        </div>
                    </div>
                    <div class="p-4">
                        <form action="{{ route('hrms.helpdesk.tickets.status', $ticket->id) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="select" label="Update Status" name="status">
                                    <option value="open" {{ $ticket->status == 'open' ? 'selected' : '' }}>Open</option>
                                    <option value="in_progress" {{ $ticket->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                    <option value="pending_employee" {{ $ticket->status == 'pending_employee' ? 'selected' : '' }}>Pending Employee Reply</option>
                                    <option value="resolved" {{ $ticket->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                    <option value="closed" {{ $ticket->status == 'closed' ? 'selected' : '' }}>Closed</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="select" label="Assign Agent" name="assigned_to">
                                    <option value="">-- Unassigned --</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" {{ $ticket->assigned_to == $agent->id ? 'selected' : '' }}>
                                            {{ $agent->full_name ?? trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')) }} ({{ $agent->employee_id ?? $agent->employee_code }})
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="select" label="Priority Level" name="priority">
                                    <option value="low" {{ $ticket->priority == 'low' ? 'selected' : '' }}>Low</option>
                                    <option value="medium" {{ $ticket->priority == 'medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="high" {{ $ticket->priority == 'high' ? 'selected' : '' }}>High</option>
                                    <option value="urgent" {{ $ticket->priority == 'urgent' ? 'selected' : '' }}>Urgent</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <x-ui.button type="submit" variant="primary" icon="feather-save" class="w-100 fw-bold">
                                Save Changes
                            </x-ui.button>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Post Response Composer Card -->
            @if(!in_array($ticket->status, ['closed']))
                <div class="ticket-card mb-4">
                    <div class="ticket-card-header">
                        <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                            <i class="feather-corner-down-right text-primary fs-5"></i>
                            <span>Post Response</span>
                        </div>
                    </div>
                    <div class="p-4">
                        <form action="{{ route('hrms.helpdesk.tickets.reply', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <textarea name="message" class="form-control" rows="4" placeholder="Write your reply, update, or resolution notes here..." required></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-dark fw-semibold mb-1" style="font-size: 13px;">Attach Files (optional)</label>
                                <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                            </div>

                            @if($canManage)
                                <div class="mb-3">
                                    <x-ui.checkbox label="Internal HR Note Only" name="is_internal_note" value="1" id="internalNoteSwitch" />
                                </div>
                            @endif

                            <x-ui.button type="submit" variant="primary" icon="feather-send" class="w-100 fw-bold">
                                Post Reply
                            </x-ui.button>
                        </form>
                    </div>
                </div>
            @endif

            <!-- CSAT Satisfaction Survey Card -->
            @if(in_array($ticket->status, ['resolved', 'closed']))
                <div class="ticket-card mb-4">
                    <div class="ticket-card-header">
                        <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                            <i class="feather-star text-warning fs-5"></i>
                            <span>Satisfaction Survey (CSAT)</span>
                        </div>
                    </div>
                    <div class="p-4">
                        @if($ticket->rating)
                            <div class="text-center py-2">
                                <div class="text-warning fs-3 mb-2">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="feather-star {{ $i <= $ticket->rating->rating ? 'fill-warning text-warning' : 'text-muted' }}"></i>
                                    @endfor
                                </div>
                                <div class="fw-bold text-dark fs-6">{{ $ticket->rating->rating }} / 5 Stars</div>
                                @if($ticket->rating->feedback)
                                    <p class="text-muted small mt-2 fst-italic mb-0">"{{ $ticket->rating->feedback }}"</p>
                                @endif
                            </div>
                        @else
                            <form action="{{ route('hrms.helpdesk.tickets.csat', $ticket->id) }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui type="select" label="Rate Resolution Quality" name="rating" :required="true">
                                        <option value="5">⭐⭐⭐⭐⭐ 5 - Excellent</option>
                                        <option value="4">⭐⭐⭐⭐ 4 - Good</option>
                                        <option value="3">⭐⭐⭐ 3 - Satisfactory</option>
                                        <option value="2">⭐⭐ 2 - Poor</option>
                                        <option value="1">⭐ 1 - Very Bad</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="mb-3">
                                    <textarea name="feedback" class="form-control" rows="2" placeholder="Optional feedback comments..." style="font-size: 13px;"></textarea>
                                </div>
                                <x-ui.button type="submit" variant="warning" class="w-100 fw-bold text-dark">
                                    Submit Rating
                                </x-ui.button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection


