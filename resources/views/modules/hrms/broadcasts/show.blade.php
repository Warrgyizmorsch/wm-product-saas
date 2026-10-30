@extends('layouts.duralux')

@section('title', __('hrms.broadcasts.title_show', ['code' => $broadcast->broadcast_number]))
@section('page-title', __('hrms.broadcasts.page_title_show', ['code' => $broadcast->broadcast_number]))
@section('breadcrumb', __('hrms.broadcasts.breadcrumb_show', ['code' => $broadcast->broadcast_number]))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.broadcasts.index') }}" class="border text-dark fw-semibold">
            {{ __('hrms.broadcasts.btn_back_to_broadcasts') }}
        </x-ui.button>
        @if($isHrAdmin)
        <x-ui.button variant="primary" icon="feather-edit" data-bs-toggle="modal" data-bs-target="#editBroadcastModal" class="fw-semibold">
            {{ __('hrms.broadcasts.btn_edit_broadcast') }}
        </x-ui.button>
        <x-ui.icon-btn type="button" variant="soft-danger" icon="feather-trash-2" onclick="confirmAction(@json(__('hrms.broadcasts.confirm_delete_broadcast', ['code' => $broadcast->broadcast_number])), function() { document.getElementById('adminDeleteBcForm').submit(); }, { title: @json(__('hrms.broadcasts.delete_broadcast_modal_title')), confirmText: @json(__('hrms.broadcasts.btn_yes_delete')), variant: 'danger' });" :title="__('hrms.broadcasts.btn_delete_broadcast')" style="width: 36px; height: 36px;" />
        <form id="adminDeleteBcForm" action="{{ route('hrms.broadcasts.destroy', $broadcast->id) }}" method="POST" class="d-none">
            @csrf
            @method('DELETE')
        </form>
        @endif
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
    .broadcast-body-content {
        line-height: 1.65;
        color: #1e293b;
        font-size: 13.5px;
    }
    .broadcast-body-content p {
        margin-bottom: 0.65rem;
    }
    .broadcast-body-content p:last-child {
        margin-bottom: 0;
    }
    .broadcast-body-content ul, .broadcast-body-content ol {
        padding-left: 1.25rem;
        margin-bottom: 0.65rem;
    }
    #editBroadcastModal .modal-dialog {
        max-width: 860px !important;
    }
    #editBroadcastModal .odoo-form-label {
        width: auto !important;
        min-width: 120px !important;
        max-width: 215px !important;
        flex-shrink: 0 !important;
        white-space: nowrap !important;
        margin-right: 8px !important;
    }
    .bi-pin-angle, .bi-pin-angle-fill {
        font-size: 13.5px;
        line-height: 1;
        display: inline-block;
    }
    .feather-pin:before {
        content: "\f4e8" !important;
        font-family: bootstrap-icons !important;
        font-style: normal;
        display: inline-block;
    }
    .comment-pin-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 32px !important;
        height: 32px !important;
        padding: 0 !important;
        border-radius: 6px !important;
        border: 1px solid #e2e8f0 !important;
        background-color: #f8fafc !important;
        color: #475569 !important;
        transition: all 0.15s ease-in-out !important;
    }
    .comment-pin-btn i,
    .comment-pin-btn .bi-pin-angle,
    .comment-pin-btn .bi-pin-angle-fill {
        font-size: 14px !important;
        line-height: 1 !important;
        color: #475569 !important;
        display: inline-block !important;
        transition: color 0.15s ease-in-out !important;
    }
    .comment-pin-btn:hover,
    .comment-pin-btn:focus,
    .comment-pin-btn:active {
        background-color: #eff6ff !important;
        border-color: #93c5fd !important;
        color: #2563eb !important;
    }
    .comment-pin-btn:hover i,
    .comment-pin-btn:focus i,
    .comment-pin-btn:active i,
    .comment-pin-btn:hover *,
    .comment-pin-btn:focus *,
    .comment-pin-btn:active *,
    .comment-pin-btn:hover .bi-pin-angle,
    .comment-pin-btn:focus .bi-pin-angle,
    .comment-pin-btn:active .bi-pin-angle {
        color: #2563eb !important;
    }
    .comment-pin-btn.is-pinned {
        background-color: #eff6ff !important;
        border-color: #60a5fa !important;
        color: #2563eb !important;
    }
    .comment-pin-btn.is-pinned i,
    .comment-pin-btn.is-pinned *,
    .comment-pin-btn.is-pinned .bi-pin-angle-fill {
        color: #2563eb !important;
    }
    .comment-pin-btn.is-pinned:hover,
    .comment-pin-btn.is-pinned:focus,
    .comment-pin-btn.is-pinned:active {
        background-color: #dbeafe !important;
        border-color: #3b82f6 !important;
        color: #1d4ed8 !important;
    }
    .comment-pin-btn.is-pinned:hover i,
    .comment-pin-btn.is-pinned:focus i,
    .comment-pin-btn.is-pinned:active i,
    .comment-pin-btn.is-pinned:hover *,
    .comment-pin-btn.is-pinned:focus *,
    .comment-pin-btn.is-pinned:active *,
    .comment-pin-btn.is-pinned:hover .bi-pin-angle-fill,
    .comment-pin-btn.is-pinned:focus .bi-pin-angle-fill,
    .comment-pin-btn.is-pinned:active .bi-pin-angle-fill {
        color: #1d4ed8 !important;
    }

    /* Dark mode */
    html.app-skin-dark .comment-pin-btn {
        background-color: #1e293b !important;
        border-color: #334155 !important;
        color: #94a3b8 !important;
    }
    html.app-skin-dark .comment-pin-btn i {
        color: #94a3b8 !important;
    }
    html.app-skin-dark .comment-pin-btn:hover,
    html.app-skin-dark .comment-pin-btn:focus {
        background-color: #334155 !important;
        border-color: #64748b !important;
        color: #f1f5f9 !important;
    }
    html.app-skin-dark .comment-pin-btn:hover i,
    html.app-skin-dark .comment-pin-btn:focus i,
    html.app-skin-dark .comment-pin-btn:hover *,
    html.app-skin-dark .comment-pin-btn:focus * {
        color: #f1f5f9 !important;
    }
    html.app-skin-dark .comment-pin-btn.is-pinned {
        background-color: rgba(37, 99, 235, 0.2) !important;
        border-color: #2563eb !important;
        color: #60a5fa !important;
    }
    html.app-skin-dark .comment-pin-btn.is-pinned i,
    html.app-skin-dark .comment-pin-btn.is-pinned * {
        color: #60a5fa !important;
    }
    html.app-skin-dark .comment-pin-btn.is-pinned:hover {
        background-color: rgba(37, 99, 235, 0.35) !important;
        color: #93c5fd !important;
    }
    html.app-skin-dark .comment-pin-btn.is-pinned:hover i,
    html.app-skin-dark .comment-pin-btn.is-pinned:hover * {
        color: #93c5fd !important;
    }
</style>
@endpush

@section('content')
@php
    $categoryLabels = [
        'announcement'  => __('hrms.broadcasts.category_announcement'),
        'policy_update' => __('hrms.broadcasts.category_policy_update'),
        'event'         => __('hrms.broadcasts.category_event'),
        'emergency'     => __('hrms.broadcasts.category_emergency'),
        'news'          => __('hrms.broadcasts.category_news'),
    ];
    $priorityLabels = [
        'normal'    => __('hrms.broadcasts.priority_normal'),
        'important' => __('hrms.broadcasts.priority_important'),
        'urgent'    => __('hrms.broadcasts.priority_urgent'),
    ];
    $audienceLabels = [
        'all'                => __('hrms.broadcasts.audience_all'),
        'department'         => __('hrms.broadcasts.audience_department'),
        'branch'             => __('hrms.broadcasts.audience_branch'),
        'designation'        => __('hrms.broadcasts.audience_designation'),
        'specific_employees' => __('hrms.broadcasts.audience_specific_employees'),
    ];
    $statusLabels = [
        'published' => __('hrms.broadcasts.status_published'),
        'scheduled' => __('hrms.broadcasts.status_scheduled'),
        'draft'     => __('hrms.broadcasts.status_draft'),
        'archived'  => __('hrms.broadcasts.status_archived'),
    ];
@endphp

<div class="container-fluid p-0">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-3">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-3">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-3 p-md-4 shadow-sm rounded-3 border-0 text-dark">

        <!-- HEADER BANNER CARD -->
        <div class="p-3 p-md-3.5 bg-light rounded-3 border mb-3 position-relative">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1.5">
                        <span class="fw-bold text-primary fs-13">{{ $broadcast->broadcast_number }}</span>
                        @php
                            $prioVariant = match($broadcast->priority) {
                                'urgent' => 'danger',
                                'important' => 'warning',
                                default => 'info'
                            };
                        @endphp
                        <x-ui.badge soft variant="{{ $prioVariant }}" class="fs-11">
                            {{ __('hrms.broadcasts.priority_badge_suffix', ['priority' => $priorityLabels[$broadcast->priority] ?? $broadcast->priority]) }}
                        </x-ui.badge>
                        <x-ui.badge soft variant="secondary" class="fs-11">
                            <i class="feather-tag me-1"></i> {{ $categoryLabels[$broadcast->category] ?? str_replace('_', ' ', $broadcast->category) }}
                        </x-ui.badge>
                        <x-ui.badge soft variant="success" class="fs-11">
                            {{ $statusLabels[$broadcast->status] ?? $broadcast->status }}
                        </x-ui.badge>
                    </div>
                    <h4 class="fw-bold text-dark mb-1 fs-18">{{ $broadcast->title }}</h4>
                    <div class="text-muted fs-12 mt-0.5">
                        {!! __('hrms.broadcasts.published_by', [
                            'name' => '<strong class="text-dark">' . e($broadcast->creator?->name ?? __('hrms.broadcasts.hr_department')) . '</strong>',
                            'date' => $broadcast->published_at ? $broadcast->published_at->format('M d, Y h:i A') : 'N/A'
                        ]) !!}
                    </div>
                </div>

                <!-- MANDATORY READ ACKNOWLEDGEMENT ACTION FOR LOGGED-IN EMPLOYEE -->
                @if($broadcast->is_acknowledgement_required && $employee && !$isHrAdmin)
                    @php
                        $userReceipt = $broadcast->receipts->where('employee_id', $employee->id)->first();
                        $isAck = $userReceipt && $userReceipt->acknowledged_at;
                    @endphp
                    <div class="p-2.5 bg-white rounded border text-end">
                        <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">{{ __('hrms.broadcasts.compliance_acknowledgement') }}</span>
                        @if($isAck)
                            <x-ui.badge soft variant="success" class="fs-11 py-1.5 px-3">
                                <i class="feather-check-circle me-1"></i> {{ __('hrms.broadcasts.acknowledged_on', ['date' => $userReceipt->acknowledged_at->format('M d, Y')]) }}
                            </x-ui.badge>
                        @else
                            <form action="{{ route('hrms.broadcasts.acknowledge', $broadcast->id) }}" method="POST">
                                @csrf
                                <x-ui.button type="submit" variant="primary" icon="feather-check-square" size="sm" class="fw-bold px-3">
                                    {{ __('hrms.broadcasts.btn_acknowledge_read') }}
                                </x-ui.button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- BROADCAST ANNOUNCEMENT CONTENT -->
        <div class="p-3 p-md-3.5 bg-white rounded-3 border mb-3">
            <h6 class="fw-bold text-dark mb-2.5 fs-13 border-bottom pb-2">
                <i class="feather-align-left me-1.5 text-primary"></i> {{ __('hrms.broadcasts.announcement_details') }}
            </h6>
            <div class="broadcast-body-content text-secondary mb-2">
                {!! $broadcast->content !!}
            </div>

            @if($broadcast->attachment_path)
                <div class="mt-3 pt-2.5 border-top d-flex align-items-center justify-content-between bg-light p-2.5 rounded">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-paperclip fs-16 text-primary"></i>
                        <div>
                            <div class="fw-semibold text-dark fs-12">{{ __('hrms.broadcasts.attached_document') }}</div>
                            <small class="text-muted fs-11">{{ __('hrms.broadcasts.attached_document_desc') }}</small>
                        </div>
                    </div>
                    <x-ui.button href="{{ asset('storage/' . $broadcast->attachment_path) }}" target="_blank" variant="outline-primary" icon="feather-download" size="sm" class="fw-bold fs-11">
                        {{ __('hrms.broadcasts.btn_download_attachment') }}
                    </x-ui.button>
                </div>
            @endif
        </div>

        @if($isHrAdmin)
        <!-- DELIVERY & ACKNOWLEDGEMENT ANALYTICS SUMMARY -->
        <div class="p-3 bg-light rounded-3 border mb-3">
            <h6 class="fw-bold text-dark mb-2.5 fs-13">
                <i class="feather-pie-chart me-1.5 text-primary"></i> {{ __('hrms.broadcasts.analytics_title') }}
            </h6>
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-white rounded border">
                        <small class="text-muted fs-11 text-uppercase fw-semibold">{{ __('hrms.broadcasts.stat_total_targeted') }}</small>
                        <h4 class="fw-bold text-dark mb-0 mt-1">{{ $analytics['total_targeted'] }}</h4>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-white rounded border">
                        <small class="text-muted fs-11 text-uppercase fw-semibold">{{ __('hrms.broadcasts.stat_delivered') }}</small>
                        <h4 class="fw-bold text-info mb-0 mt-1">{{ $analytics['delivered'] }}</h4>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-white rounded border">
                        <small class="text-muted fs-11 text-uppercase fw-semibold">{{ __('hrms.broadcasts.stat_read_rate') }}</small>
                        <h4 class="fw-bold text-success mb-0 mt-1">{{ $analytics['read_percentage'] }}% ({{ $analytics['read'] }})</h4>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-white rounded border">
                        <small class="text-muted fs-11 text-uppercase fw-semibold">{{ __('hrms.broadcasts.stat_acknowledged') }}</small>
                        <h4 class="fw-bold text-primary mb-0 mt-1">{{ $analytics['ack_percentage'] }}% ({{ $analytics['acknowledged'] }})</h4>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- COMMENTS & Q&A THREAD SECTION -->
        @if($broadcast->allow_comments)
            <div class="p-3 p-md-3.5 bg-white rounded-3 border mb-3">
                <h6 class="fw-bold text-dark mb-2.5 fs-13 border-bottom pb-2">
                    <i class="feather-message-square me-1.5 text-primary"></i> {{ __('hrms.broadcasts.comments_title', ['count' => $broadcast->comments->count()]) }}
                </h6>

                <!-- ADD COMMENT FORM -->
                <form action="{{ route('hrms.broadcasts.comment.store', $broadcast->id) }}" method="POST" class="mb-3">
                    @csrf
                    <div class="d-flex gap-2">
                        <x-ui.odoo-form-ui type="textarea" name="comment_text" rows="2" :placeholder="__('hrms.broadcasts.placeholder_comment')" :required="true" class="flex-grow-1 mb-0" />
                        <x-ui.button type="submit" variant="primary" icon="feather-send" class="align-self-end fw-bold px-3">
                            {{ __('hrms.broadcasts.btn_post_comment') }}
                        </x-ui.button>
                    </div>
                </form>

                <!-- COMMENTS LIST -->
                <div class="d-flex flex-column gap-2.5">
                    @forelse($broadcast->comments->where('parent_id', null) as $comm)
                        <div class="p-3 rounded border bg-light position-relative {{ $comm->is_pinned ? 'border-primary' : '' }}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-initials-sm">
                                        {{ strtoupper(substr($comm->employee->full_name ?? 'E', 0, 2)) }}
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark fs-13">{{ $comm->employee->full_name ?? __('hrms.broadcasts.th_employee') }}</span>
                                        <small class="text-muted fs-11 ms-2">{{ $comm->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    @if($comm->is_pinned)
                                        <x-ui.badge soft variant="primary" class="fs-10"><i class="bi bi-pin-angle-fill me-1"></i> {{ __('hrms.broadcasts.pinned_answer') }}</x-ui.badge>
                                    @endif
                                    @if($isHrAdmin)
                                    <form action="{{ route('hrms.broadcasts.comment.pin', $comm->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <x-ui.icon-btn type="submit" variant="{{ $comm->is_pinned ? 'soft-primary' : 'soft-secondary' }}" icon="bi bi-pin-angle{{ $comm->is_pinned ? '-fill' : '' }}" :title="__('hrms.broadcasts.btn_toggle_pin')" class="comment-pin-btn {{ $comm->is_pinned ? 'is-pinned' : '' }}" />
                                    </form>
                                    @endif
                                    @if($isHrAdmin || ($employee && $comm->employee_id === $employee->id))
                                    <form action="{{ route('hrms.broadcasts.comment.destroy', $comm->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="submit" variant="soft-danger" icon="feather-trash-2" :title="__('hrms.broadcasts.btn_delete_comment')" />
                                    </form>
                                    @endif
                                </div>
                            </div>
                            <p class="fs-13 text-secondary mb-0 ps-4.5">{{ $comm->comment_text }}</p>

                            <!-- REPLIES LIST -->
                            @if($comm->replies && $comm->replies->count() > 0)
                                <div class="mt-2.5 pt-2 border-top ps-4 d-flex flex-column gap-2">
                                    @foreach($comm->replies as $reply)
                                        <div class="p-2 rounded bg-white border">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <span class="fw-bold text-dark fs-12">{{ $reply->employee->full_name ?? __('hrms.broadcasts.th_employee') }}</span>
                                                <small class="text-muted fs-10">{{ $reply->created_at->diffForHumans() }}</small>
                                            </div>
                                            <p class="fs-12 text-secondary mb-0">{{ $reply->comment_text }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-3 text-muted fs-12">{{ __('hrms.broadcasts.no_comments_yet') }}</div>
                    @endforelse
                </div>
            </div>
        @endif

        @if($isHrAdmin)
        <!-- AUDIT TRAIL RECEIPT TABLE -->
        <div class="p-3 bg-light rounded-3 border">
            <h6 class="fw-bold text-dark mb-2.5 fs-13">
                <i class="feather-shield me-1.5 text-primary"></i> {{ __('hrms.broadcasts.audit_trail_title') }}
            </h6>
            <div class="table-responsive bg-white rounded border">
                <table class="table table-hover align-middle mb-0 fs-12">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-2.5 text-muted text-uppercase fs-10">{{ __('hrms.broadcasts.th_employee') }}</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10">{{ __('hrms.broadcasts.th_department') }}</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10">{{ __('hrms.broadcasts.th_read_at') }}</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10">{{ __('hrms.broadcasts.th_acknowledged_at') }}</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10">{{ __('hrms.broadcasts.th_ip_address') }}</th>
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
                                        <span class="text-muted fs-11">{{ __('hrms.broadcasts.unread') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($rc->acknowledged_at)
                                        <x-ui.badge soft variant="success" class="fs-10">{{ $rc->acknowledged_at->format('M d, Y H:i') }}</x-ui.badge>
                                    @else
                                        <span class="text-muted fs-11">{{ __('hrms.broadcasts.pending') }}</span>
                                    @endif
                                </td>
                                <td class="text-muted fs-11"><code>{{ $rc->ip_address ?? '—' }}</code></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted fs-12">{{ __('hrms.broadcasts.no_audit_records') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
</div>

@if($isHrAdmin)
<!-- EDIT BROADCAST MODAL -->
<x-ui.modal
    id="editBroadcastModal"
    :title="'<i class=\'feather-edit me-1.5 text-primary\'></i> ' . __('hrms.broadcasts.modal_edit_title', ['code' => $broadcast->broadcast_number])"
    size="lg"
    :centered="true"
    :showFooter="false"
>
    <form action="{{ route('hrms.broadcasts.update', $broadcast->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.broadcasts.field_title')" name="title" :value="$broadcast->title" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.broadcasts.field_category')" name="category" :required="true">
                    <option value="announcement" @selected($broadcast->category === 'announcement')>{{ __('hrms.broadcasts.category_announcement') }}</option>
                    <option value="policy_update" @selected($broadcast->category === 'policy_update')>{{ __('hrms.broadcasts.category_policy_update') }}</option>
                    <option value="event" @selected($broadcast->category === 'event')>{{ __('hrms.broadcasts.category_event') }}</option>
                    <option value="emergency" @selected($broadcast->category === 'emergency')>{{ __('hrms.broadcasts.category_emergency') }}</option>
                    <option value="news" @selected($broadcast->category === 'news')>{{ __('hrms.broadcasts.category_news') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.broadcasts.field_priority')" name="priority" :required="true">
                    <option value="normal" @selected($broadcast->priority === 'normal')>{{ __('hrms.broadcasts.priority_normal_opt') }}</option>
                    <option value="important" @selected($broadcast->priority === 'important')>{{ __('hrms.broadcasts.priority_important_opt') }}</option>
                    <option value="urgent" @selected($broadcast->priority === 'urgent')>{{ __('hrms.broadcasts.priority_urgent_opt') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.broadcasts.field_target_scope')" name="target_type" id="editTargetTypeSelect" :required="true">
                    <option value="all" @selected($broadcast->target_type === 'all')>{{ __('hrms.broadcasts.audience_all') }}</option>
                    <option value="department" @selected($broadcast->target_type === 'department')>{{ __('hrms.broadcasts.audience_department') }}</option>
                    <option value="branch" @selected($broadcast->target_type === 'branch')>{{ __('hrms.broadcasts.audience_branch') }}</option>
                    <option value="designation" @selected($broadcast->target_type === 'designation')>{{ __('hrms.broadcasts.audience_designation') }}</option>
                    <option value="specific_employees" @selected($broadcast->target_type === 'specific_employees')>{{ __('hrms.broadcasts.audience_specific_employees') }}</option>
                </x-ui.odoo-form-ui>
            </div>

            <!-- Dynamic Target Selection Container -->
            <div class="col-12 {{ ($broadcast->target_type === 'all' || empty($broadcast->target_type)) ? 'd-none' : '' }}" id="editTargetSelectionContainer">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.broadcasts.field_target_items')" name="target_ids[]" id="editTargetIdsSelect" :multiple="true" :searchable="true">
                    <!-- JS Populated -->
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.broadcasts.field_content')" name="content" rows="4" :value="$broadcast->content" :required="true" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="file" :label="__('hrms.broadcasts.field_replace_attachment')" name="attachment" />
                @if($broadcast->attachment_path)
                    <small class="text-muted fs-11 mt-1 d-block">{{ __('hrms.broadcasts.lbl_current', ['file' => basename($broadcast->attachment_path)]) }}</small>
                @endif
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="file" :label="__('hrms.broadcasts.field_replace_banner')" name="banner_image" />
                @if($broadcast->banner_image_path)
                    <small class="text-muted fs-11 mt-1 d-block">{{ __('hrms.broadcasts.lbl_current', ['file' => basename($broadcast->banner_image_path)]) }}</small>
                @endif
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="datetime-local" :label="__('hrms.broadcasts.field_scheduled_at_edit')" name="scheduled_at" :value="$broadcast->scheduled_at ? $broadcast->scheduled_at->format('Y-m-d\TH:i') : ''" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.broadcasts.field_expires_at_edit')" name="expires_at" :value="$broadcast->expires_at ? $broadcast->expires_at->format('Y-m-d') : ''" />
            </div>

            <div class="col-12 border-top pt-3 mt-2">
                <h6 class="fw-bold text-dark fs-12 mb-2">{{ __('hrms.broadcasts.section_delivery_flags') }}</h6>
                <div class="d-flex flex-wrap gap-4">
                    <x-ui.checkbox :label="__('hrms.broadcasts.flag_require_ack')" name="is_acknowledgement_required" :checked="(bool)$broadcast->is_acknowledgement_required" />
                    <x-ui.checkbox :label="__('hrms.broadcasts.flag_allow_comments')" name="allow_comments" :checked="(bool)$broadcast->allow_comments" />
                    <x-ui.checkbox :label="__('hrms.broadcasts.flag_show_banner')" name="show_banner" :checked="(bool)$broadcast->show_banner" />
                    <x-ui.checkbox :label="__('hrms.broadcasts.flag_send_email')" name="send_email" :checked="(bool)$broadcast->send_email" />
                </div>
            </div>
        </div>

        <div class="modal-footer border-top bg-light py-2.5 px-0 mt-4 mb-n3 mx-n3">
            <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.broadcasts.btn_cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">{{ __('hrms.broadcasts.btn_update_broadcast') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endif

<x-ui.confirmation-modal />

@if($isHrAdmin)
@push('scripts')
<script>
    $(document).ready(function() {
        if ($('#editBroadcastModal').parent().get(0) !== document.body) {
            $('#editBroadcastModal').appendTo(document.body);
        }

        const departments = @json($departments ?? []);
        const branches = @json($branches ?? []);
        const designations = @json($designations ?? []);
        const employees = @json($employees ?? []);
        const currentTargetIds = @json(array_map('strval', (array)($broadcast->target_ids ?? [])));

        function updateEditTargetSelection(initialLoad = false) {
            const val = $('#editTargetTypeSelect').val();
            const container = $('#editTargetSelectionContainer');
            const select = $('#editTargetIdsSelect');
            const labelEl = container.find('.odoo-form-label');

            select.empty();

            if (val === 'all') {
                container.addClass('d-none');
            } else {
                container.removeClass('d-none');
                let labelText = @json(__('hrms.broadcasts.field_target_items'));
                if (val === 'department') {
                    labelText = @json(__('hrms.broadcasts.target_departments'));
                    departments.forEach(d => {
                        const sel = initialLoad && currentTargetIds.includes(String(d.id)) ? 'selected' : '';
                        select.append(`<option value="${d.id}" ${sel}>${d.name}</option>`);
                    });
                } else if (val === 'branch') {
                    labelText = @json(__('hrms.broadcasts.target_branches'));
                    branches.forEach(b => {
                        const sel = initialLoad && currentTargetIds.includes(String(b.id)) ? 'selected' : '';
                        select.append(`<option value="${b.id}" ${sel}>${b.name}</option>`);
                    });
                } else if (val === 'designation') {
                    labelText = @json(__('hrms.broadcasts.target_designations'));
                    designations.forEach(ds => {
                        const sel = initialLoad && currentTargetIds.includes(String(ds.id)) ? 'selected' : '';
                        select.append(`<option value="${ds.id}" ${sel}>${ds.name}</option>`);
                    });
                } else if (val === 'specific_employees') {
                    labelText = @json(__('hrms.broadcasts.target_employees'));
                    employees.forEach(e => {
                        const sel = initialLoad && currentTargetIds.includes(String(e.id)) ? 'selected' : '';
                        select.append(`<option value="${e.id}" ${sel}>${e.full_name} (${e.employee_id})</option>`);
                    });
                }

                if (labelEl.length) {
                    labelEl.html(labelText + ' <span class="text-danger">*</span>');
                }

                if ($.fn.select2) {
                    if (!select.hasClass('select2-hidden-accessible')) {
                        select.select2({
                            theme: "bootstrap-5",
                            width: "100%",
                            dropdownParent: $('#editBroadcastModal')
                        });
                    } else {
                        select.trigger('change.select2');
                    }
                }
            }
        }

        updateEditTargetSelection(true);
        $(document).on('change', '#editTargetTypeSelect', function() {
            updateEditTargetSelection(false);
        });
    });
</script>
@endpush
@endif
@endsection
