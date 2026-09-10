@extends('layouts.duralux')

@section('title', 'Broadcast: ' . $broadcast->broadcast_number . ' | HRMS')
@section('page-title', 'Broadcast Announcement: ' . $broadcast->broadcast_number)
@section('breadcrumb', 'HRMS / Broadcasts / Workspace')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.broadcasts.index') }}" class="border text-dark fw-semibold">
            Back to Broadcasts
        </x-ui.button>
        <x-ui.button variant="outline-danger" icon="feather-trash-2" href="javascript:void(0);" onclick="confirmAction('Are you sure you want to delete broadcast {{ $broadcast->broadcast_number }}? This will remove all receipts and comments.', function() { document.getElementById('adminDeleteBcForm').submit(); }, { title: 'Delete Broadcast', confirmText: 'Yes, Delete', variant: 'danger' });" class="fw-semibold">
            Delete Broadcast
        </x-ui.button>
        <form id="adminDeleteBcForm" action="{{ route('hrms.broadcasts.destroy', $broadcast->id) }}" method="POST" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    </div>
@endsection

@push('styles')
<style>
    .avatar-initials-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--bs-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
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

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- HEADER BANNER CARD -->
        <div class="p-4 bg-light rounded border mb-4 position-relative">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="fw-bold text-primary fs-14">{{ $broadcast->broadcast_number }}</span>
                        @php
                            $prioVariant = match($broadcast->priority) {
                                'urgent' => 'danger',
                                'important' => 'warning',
                                default => 'info'
                            };
                        @endphp
                        <x-ui.badge soft variant="{{ $prioVariant }}" class="text-capitalize">
                            {{ $broadcast->priority }} Priority
                        </x-ui.badge>
                        <x-ui.badge soft variant="secondary" class="text-capitalize">
                            <i class="feather-tag me-1"></i> {{ str_replace('_', ' ', $broadcast->category) }}
                        </x-ui.badge>
                        <x-ui.badge soft variant="success" class="text-capitalize">
                            {{ $broadcast->status }}
                        </x-ui.badge>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">{{ $broadcast->title }}</h3>
                    <div class="text-muted fs-12 mt-1">
                        Published by <strong class="text-dark">{{ $broadcast->creator?->name ?? 'HR Department' }}</strong> &bull;
                        {{ $broadcast->published_at ? $broadcast->published_at->format('M d, Y h:i A') : 'N/A' }}
                    </div>
                </div>

                <!-- MANDATORY READ ACKNOWLEDGEMENT ACTION FOR LOGGED-IN EMPLOYEE -->
                @php
                    $viewerRole = strtolower(auth()->user()->role ?? '');
                    $isAdminViewer = in_array($viewerRole, ['admin', 'company admin', 'hr', 'hr manager', 'super admin', '1']);
                @endphp
                @if($broadcast->is_acknowledgement_required && $employee && !$isAdminViewer)
                    @php
                        $userReceipt = $broadcast->receipts->where('employee_id', $employee->id)->first();
                        $isAck = $userReceipt && $userReceipt->acknowledged_at;
                    @endphp
                    <div class="p-3 bg-white rounded border text-end">
                        <span class="fs-11 text-muted fw-bold text-uppercase d-block mb-1">Compliance Acknowledgement</span>
                        @if($isAck)
                            <x-ui.badge soft variant="success" class="fs-12 py-1.5 px-3">
                                <i class="feather-check-circle me-1"></i> Acknowledged on {{ $userReceipt->acknowledged_at->format('M d, Y') }}
                            </x-ui.badge>
                        @else
                            <form action="{{ route('hrms.broadcasts.acknowledge', $broadcast->id) }}" method="POST">
                                @csrf
                                <x-ui.button type="submit" variant="primary" icon="feather-check-square" size="sm" class="fw-bold px-3">
                                    I Acknowledge & Read
                                </x-ui.button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </div>



        <!-- BROADCAST ANNOUNCEMENT CONTENT -->
        <div class="p-4 bg-white rounded border mb-4">
            <h6 class="fw-bold text-dark mb-3 fs-14 border-bottom pb-2"><i class="feather-align-left me-1.5 text-primary"></i> Announcement Details</h6>
            <div class="fs-13 text-secondary mb-3 leading-relaxed" style="white-space: pre-line;">
                {{ $broadcast->content }}
            </div>

            @if($broadcast->attachment_path)
                <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between bg-light p-3 rounded">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-paperclip fs-18 text-primary"></i>
                        <div>
                            <div class="fw-semibold text-dark fs-13">Attached Document</div>
                            <small class="text-muted fs-11">Download official document for review</small>
                        </div>
                    </div>
                    <x-ui.button href="{{ asset('storage/' . $broadcast->attachment_path) }}" target="_blank" variant="outline-primary" icon="feather-download" size="sm" class="fw-bold">
                        Download Attachment
                    </x-ui.button>
                </div>
            @endif
        </div>

        <!-- DELIVERY & ACKNOWLEDGEMENT ANALYTICS SUMMARY -->
        <div class="p-3 bg-light rounded border mb-4">
            <h6 class="fw-bold text-dark mb-3 fs-13"><i class="feather-pie-chart me-1.5 text-primary"></i> Read & Acknowledgement Delivery Metrics</h6>
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-white rounded border">
                        <small class="text-muted fs-11 text-uppercase fw-semibold">Total Targeted</small>
                        <h4 class="fw-bold text-dark mb-0 mt-1">{{ $analytics['total_targeted'] }}</h4>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-white rounded border">
                        <small class="text-muted fs-11 text-uppercase fw-semibold">Delivered</small>
                        <h4 class="fw-bold text-info mb-0 mt-1">{{ $analytics['delivered'] }}</h4>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-white rounded border">
                        <small class="text-muted fs-11 text-uppercase fw-semibold">Read Rate</small>
                        <h4 class="fw-bold text-success mb-0 mt-1">{{ $analytics['read_percentage'] }}% ({{ $analytics['read'] }})</h4>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-white rounded border">
                        <small class="text-muted fs-11 text-uppercase fw-semibold">Acknowledged</small>
                        <h4 class="fw-bold text-primary mb-0 mt-1">{{ $analytics['ack_percentage'] }}% ({{ $analytics['acknowledged'] }})</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- COMMENTS & Q&A THREAD SECTION -->
        @if($broadcast->allow_comments)
            <div class="p-4 bg-white rounded border mb-4">
                <h6 class="fw-bold text-dark mb-3 fs-14 border-bottom pb-2">
                    <i class="feather-message-square me-1.5 text-primary"></i> Comments & Clarifications ({{ $broadcast->comments->count() }})
                </h6>

                <!-- ADD COMMENT FORM -->
                <form action="{{ route('hrms.broadcasts.comment.store', $broadcast->id) }}" method="POST" class="mb-4">
                    @csrf
                    <div class="d-flex gap-2">
                        <x-ui.odoo-form-ui type="textarea" name="comment_text" rows="2" placeholder="Write a comment or ask a question..." :required="true" class="flex-grow-1 mb-0" />
                        <x-ui.button type="submit" variant="primary" icon="feather-send" class="align-self-end fw-bold px-3">
                            Post
                        </x-ui.button>
                    </div>
                </form>

                <!-- COMMENTS LIST -->
                <div class="d-flex flex-column gap-3">
                    @forelse($broadcast->comments->where('parent_id', null) as $comm)
                        <div class="p-3 rounded border bg-light position-relative {{ $comm->is_pinned ? 'border-primary' : '' }}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-initials-sm">
                                        {{ strtoupper(substr($comm->employee->full_name ?? 'E', 0, 2)) }}
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark fs-13">{{ $comm->employee->full_name ?? 'Employee' }}</span>
                                        <small class="text-muted fs-11 ms-2">{{ $comm->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    @if($comm->is_pinned)
                                        <x-ui.badge soft variant="primary" class="fs-10"><i class="feather-pin me-1"></i> Pinned Answer</x-ui.badge>
                                    @endif
                                    <form action="{{ route('hrms.broadcasts.comment.pin', $comm->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <x-ui.icon-btn type="submit" variant="soft-secondary" icon="feather-pin" title="Toggle Pin" />
                                    </form>
                                    <form action="{{ route('hrms.broadcasts.comment.destroy', $comm->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="submit" variant="soft-danger" icon="feather-trash-2" title="Delete Comment" />
                                    </form>
                                </div>
                            </div>
                            <p class="fs-13 text-secondary mb-0 ps-4.5">{{ $comm->comment_text }}</p>

                            <!-- REPLIES LIST -->
                            @if($comm->replies && $comm->replies->count() > 0)
                                <div class="mt-3 pt-2 border-top ps-4 d-flex flex-column gap-2">
                                    @foreach($comm->replies as $reply)
                                        <div class="p-2 rounded bg-white border">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <span class="fw-bold text-dark fs-12">{{ $reply->employee->full_name ?? 'User' }}</span>
                                                <small class="text-muted fs-10">{{ $reply->created_at->diffForHumans() }}</small>
                                            </div>
                                            <p class="fs-12 text-secondary mb-0">{{ $reply->comment_text }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-3 text-muted fs-12">No comments yet. Be the first to ask a question or comment.</div>
                    @endforelse
                </div>
            </div>
        @endif

        <!-- AUDIT TRAIL RECEIPT TABLE -->
        <div class="p-3 bg-light rounded border">
            <h6 class="fw-bold text-dark mb-3 fs-13"><i class="feather-shield me-1.5 text-primary"></i> Read & Acknowledgement Audit Trail Log</h6>
            <div class="table-responsive bg-white rounded border">
                <table class="table table-hover align-middle mb-0 fs-12">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-2.5 text-muted text-uppercase fs-10">Employee</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10">Department</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10">Read At</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10">Acknowledged At</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($broadcast->receipts as $rc)
                            <tr>
                                <td class="ps-3 fw-bold text-dark">{{ $rc->employee->full_name ?? 'N/A' }}</td>
                                <td class="text-muted">{{ $rc->employee->department?->name ?? 'N/A' }}</td>
                                <td>
                                    @if($rc->read_at)
                                        <x-ui.badge soft variant="info" class="fs-10">{{ $rc->read_at->format('M d, H:i') }}</x-ui.badge>
                                    @else
                                        <span class="text-muted fs-11">Unread</span>
                                    @endif
                                </td>
                                <td>
                                    @if($rc->acknowledged_at)
                                        <x-ui.badge soft variant="success" class="fs-10">{{ $rc->acknowledged_at->format('M d, H:i') }}</x-ui.badge>
                                    @else
                                        <span class="text-muted fs-11">Pending</span>
                                    @endif
                                </td>
                                <td class="text-muted fs-11"><code>{{ $rc->ip_address ?? '—' }}</code></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted fs-12">No delivery audit records.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
