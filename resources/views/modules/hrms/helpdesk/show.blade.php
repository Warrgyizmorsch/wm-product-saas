@extends('layouts.duralux')

@section('title', 'Ticket #' . $ticket->ticket_number . ' | Helpdesk')
@section('page-title', 'Ticket Thread: #' . $ticket->ticket_number)
@section('breadcrumb', 'HRMS / Helpdesk / Thread')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-secondary" icon="feather-arrow-left" href="{{ route('hrms.helpdesk.tickets.index') }}" class="fw-semibold">
            Back to Helpdesk
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    .internal-note-box {
        background-color: #fffbeb;
        border: 1px solid #fde68a;
    }
    .internal-note-badge {
        background-color: #f59e0b;
        color: #ffffff;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 4px;
    }
    .avatar-initials-lg {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background-color: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--bs-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 15px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="feather-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Top Header Summary Bar -->
        <div class="border-bottom pb-4 mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold text-dark mb-0">{{ $ticket->subject }}</h4>
                    @if($ticket->is_confidential)
                        <x-ui.badge variant="danger" soft title="Confidential Grievance">
                            <i class="feather-lock me-1"></i> Confidential
                        </x-ui.badge>
                    @endif
                </div>
                <x-ui.badge variant="primary" class="fs-7 fw-bold">#{{ $ticket->ticket_number }}</x-ui.badge>
            </div>

            <div class="d-flex align-items-center gap-3 text-muted small flex-wrap">
                <div><i class="feather-tag me-1 text-primary"></i> Category: <strong class="text-dark">{{ $ticket->category ? $ticket->category->name : 'General' }}</strong></div>
                <div><i class="feather-clock me-1 text-muted"></i> Created: <strong class="text-dark">{{ $ticket->created_at->format('M d, Y H:i') }}</strong></div>
                <div><i class="feather-alert-circle me-1 text-warning"></i> Priority: <strong class="text-dark text-uppercase">{{ $ticket->priority }}</strong></div>
                <div>
                    Status:
                    @if($ticket->status === 'open')
                        <x-ui.badge variant="primary" soft class="fw-bold">OPEN</x-ui.badge>
                    @elseif($ticket->status === 'in_progress')
                        <x-ui.badge variant="warning" soft class="fw-bold">IN PROGRESS</x-ui.badge>
                    @elseif($ticket->status === 'pending_employee')
                        <x-ui.badge variant="info" soft class="fw-bold">PENDING EMPLOYEE</x-ui.badge>
                    @elseif($ticket->status === 'resolved')
                        <x-ui.badge variant="success" soft class="fw-bold">RESOLVED</x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" soft class="fw-bold">CLOSED</x-ui.badge>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Conversation Thread & Description -->
            <div class="col-lg-8">
                <!-- Ticket Description Box -->
                <div class="border rounded-3 p-4 mb-4 bg-light-subtle">
                    <div class="fw-bold text-dark mb-3 pb-2 border-bottom">
                        <i class="feather-align-left me-2 text-primary"></i> Ticket Description
                    </div>
                    <div class="ticket-description text-dark fs-6 lead-snug" style="white-space: pre-line;">
                        {{ $ticket->description }}
                    </div>

                    @if($ticket->attachments->count() > 0)
                        <div class="bg-white p-3 rounded-3 border mt-3">
                            <div class="fw-semibold text-muted small mb-2"><i class="feather-paperclip me-1"></i> Attachments:</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($ticket->attachments as $att)
                                    <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="btn btn-sm btn-light border shadow-none">
                                        <i class="feather-file me-1 text-primary"></i> {{ $att->file_name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Threaded Messages -->
                <h6 class="fw-bold mb-3 text-dark"><i class="feather-message-square me-2 text-primary"></i> Activity & Responses</h6>

                <div class="d-flex flex-column gap-3 mb-4">
                    @forelse($ticket->replies as $reply)
                        @if($reply->is_internal_note)
                            <!-- Internal Note Container -->
                            <div class="border rounded-3 p-3 internal-note-box">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="internal-note-badge"><i class="feather-lock me-1"></i> Internal HR Note</span>
                                        <span class="fw-bold text-dark fs-7">{{ $reply->sender ? ($reply->sender->full_name ?? $reply->sender->first_name . ' ' . $reply->sender->last_name) : 'HR Agent' }}</span>
                                    </div>
                                    <small class="text-muted">{{ $reply->created_at->diffForHumans() }}</small>
                                </div>
                                <div class="text-dark fs-7" style="white-space: pre-line;">{{ $reply->message }}</div>

                                @if($reply->attachments->count() > 0)
                                    <div class="mt-2 pt-2 border-top">
                                        @foreach($reply->attachments as $att)
                                            <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="badge bg-warning text-dark text-decoration-none me-1">
                                                <i class="feather-paperclip"></i> {{ $att->file_name }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            <!-- Public Reply Container -->
                            <div class="border rounded-3 p-4 bg-white">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-initials-lg">
                                            {{ strtoupper(substr($reply->sender->first_name ?? 'U', 0, 1) . substr($reply->sender->last_name ?? '', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-6">{{ $reply->sender ? ($reply->sender->full_name ?? $reply->sender->first_name . ' ' . $reply->sender->last_name) : 'User' }}</div>
                                            <small class="text-muted fs-8">{{ $reply->sender ? ($reply->sender->employee_id ?? $reply->sender->employee_code) : '' }}</small>
                                        </div>
                                    </div>
                                    <small class="text-muted">{{ $reply->created_at->format('M d, Y H:i') }} ({{ $reply->created_at->diffForHumans() }})</small>
                                </div>

                                <div class="text-dark fs-6" style="white-space: pre-line;">{{ $reply->message }}</div>

                                @if($reply->attachments->count() > 0)
                                    <div class="mt-3 pt-2 border-top">
                                        <div class="small text-muted mb-1">Attached files:</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($reply->attachments as $att)
                                                <a href="{{ Storage::url($att->file_path) }}" target="_blank" class="btn btn-sm btn-light border">
                                                    <i class="feather-paperclip me-1 text-primary"></i> {{ $att->file_name }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @empty
                        <div class="text-center py-4 bg-light rounded-3 border text-muted">
                            <i class="feather-message-circle fs-2 d-block mb-1 text-secondary"></i>
                            No responses posted yet.
                        </div>
                    @endforelse
                </div>

                <!-- Reply Box Form -->
                @if(!in_array($ticket->status, ['closed']))
                    <div class="border rounded-3 p-4 bg-white">
                        <div class="fw-bold text-dark mb-3 pb-2 border-bottom">
                            <i class="feather-corner-down-right me-2 text-primary"></i> Post Response
                        </div>
                        <form action="{{ route('hrms.helpdesk.tickets.reply', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <textarea name="message" class="form-control" rows="4" placeholder="Type your reply or update here..." required></textarea>
                            </div>

                            <div class="row align-items-center g-3">
                                <div class="col-md-6">
                                    <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                                </div>
                                <div class="col-md-6 text-end">
                                    @if($canManage)
                                        <div class="d-inline-block me-3 align-middle text-start">
                                            <x-ui.checkbox label="Internal HR Note Only" name="is_internal_note" value="1" id="internalNoteSwitch" />
                                        </div>
                                    @endif
                                    <x-ui.button type="submit" variant="primary" class="fw-bold px-4">
                                        <i class="feather-send me-1"></i> Post Response
                                    </x-ui.button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endif
            </div>

            <!-- Right Column: Sidebar Actions & Metadata -->
            <div class="col-lg-4">
                <!-- SLA Timer Card -->
                <div class="border rounded-3 p-3 text-center mb-4 bg-light-subtle">
                    <div class="text-muted small fw-semibold text-uppercase mb-2">SLA Target Resolution</div>
                    @if(in_array($ticket->status, ['resolved', 'closed']))
                        <x-ui.badge variant="success" soft class="fs-6 px-3 py-2">
                            <i class="feather-check-circle me-1"></i> Resolved {{ $ticket->resolved_at ? $ticket->resolved_at->format('M d, H:i') : '' }}
                        </x-ui.badge>
                    @elseif($ticket->isOverdue())
                        <x-ui.badge variant="danger" class="fs-6 px-3 py-2">
                            <i class="feather-alert-triangle me-1"></i> Overdue Target: {{ $ticket->due_at ? $ticket->due_at->format('M d, H:i') : '' }}
                        </x-ui.badge>
                    @else
                        <x-ui.badge variant="info" soft class="fs-6 px-3 py-2">
                            <i class="feather-clock me-1"></i> Due in {{ $ticket->due_at ? $ticket->due_at->diffForHumans() : 'N/A' }}
                        </x-ui.badge>
                    @endif
                </div>

                <!-- Agent Control Actions Box -->
                @if($canManage)
                    <div class="border rounded-3 p-4 mb-4 bg-white">
                        <div class="fw-bold text-dark mb-3 pb-2 border-bottom">
                            <i class="feather-sliders me-2 text-primary"></i> Ticket Controls
                        </div>
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

                            <x-ui.button type="submit" variant="primary" class="w-100 fw-bold">
                                <i class="feather-save me-1"></i> Update Ticket Settings
                            </x-ui.button>
                        </form>
                    </div>
                @endif

                <!-- Requester Information -->
                <div class="border rounded-3 p-4 mb-4 text-center bg-white">
                    <div class="fw-bold text-dark mb-3 pb-2 border-bottom text-start">
                        <i class="feather-user me-2 text-primary"></i> Requester Profile
                    </div>
                    <div class="avatar-initials-lg mx-auto mb-2" style="width: 56px; height: 56px; font-size: 20px;">
                        {{ strtoupper(substr($ticket->employee->first_name ?? 'E', 0, 1) . substr($ticket->employee->last_name ?? '', 0, 1)) }}
                    </div>
                    <h6 class="fw-bold mb-0 text-dark">{{ $ticket->employee ? ($ticket->employee->full_name ?? $ticket->employee->first_name . ' ' . $ticket->employee->last_name) : 'N/A' }}</h6>
                    <small class="text-muted d-block mb-2">{{ $ticket->employee->designation->name ?? 'Employee' }}</small>
                    <span class="badge bg-light text-dark border px-3 py-1 mb-3">{{ $ticket->employee->employee_id ?? $ticket->employee->employee_code ?? '' }}</span>

                    <div class="text-start border-top pt-3 fs-7">
                        <div class="mb-1"><i class="feather-mail me-2 text-muted"></i>{{ $ticket->employee->office_email ?? $ticket->employee->personal_email ?? 'N/A' }}</div>
                        <div><i class="feather-phone me-2 text-muted"></i>{{ $ticket->employee->personal_mobile_number ?? $ticket->employee->home_phone ?? 'N/A' }}</div>
                    </div>
                </div>

                <!-- CSAT Feedback Box -->
                @if(in_array($ticket->status, ['resolved', 'closed']))
                    <div class="border rounded-3 p-4 bg-white">
                        <div class="fw-bold text-dark mb-3 pb-2 border-bottom">
                            <i class="feather-star me-2 text-warning"></i> Satisfaction Survey (CSAT)
                        </div>
                        <div class="card-body p-0">
                            @if($ticket->rating)
                                <div class="text-center">
                                    <div class="text-warning fs-3 mb-1">
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="feather-star {{ $i <= $ticket->rating->rating ? 'fill-warning' : 'text-muted' }}"></i>
                                        @endfor
                                    </div>
                                    <div class="fw-bold text-dark fs-6">{{ $ticket->rating->rating }} / 5 Stars</div>
                                    @if($ticket->rating->feedback)
                                        <p class="text-muted small mt-2 fst-italic">"{{ $ticket->rating->feedback }}"</p>
                                    @endif
                                </div>
                            @else
                                <form action="{{ route('hrms.helpdesk.tickets.csat', $ticket->id) }}" method="POST">
                                    @csrf
                                    <div class="text-center mb-3">
                                        <x-ui.odoo-form-ui type="select" label="Rate Resolution Quality" name="rating" :required="true">
                                            <option value="5">⭐⭐⭐⭐⭐ 5 - Excellent</option>
                                            <option value="4">⭐⭐⭐⭐ 4 - Good</option>
                                            <option value="3">⭐⭐⭐ 3 - Satisfactory</option>
                                            <option value="2">⭐⭐ 2 - Poor</option>
                                            <option value="1">⭐ 1 - Very Bad</option>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="mb-3">
                                        <textarea name="feedback" class="form-control form-control-sm" rows="2" placeholder="Optional comments..."></textarea>
                                    </div>
                                    <x-ui.button type="submit" variant="warning" class="text-dark w-100 fw-bold">Submit Rating</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
