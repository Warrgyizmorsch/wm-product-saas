@extends('layouts.duralux')

@section('title', __('visitor.my_approvals') . ' | SaaS ERP')
@section('page-title', __('visitor.my_approvals'))
@section('breadcrumb', __('visitor.visitor_management') . ' > ' . __('visitor.my_approvals'))

@push('styles')
<style>
    .visitor-avatar {
        width: 36px;
        height: 36px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid #e2e8f0;
    }
    .visitor-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .btn-soft-success {
        background-color: #d1fae5 !important;
        color: #065f46 !important;
        border: 1px solid #a7f3d0 !important;
        transition: all 0.2s ease;
    }
    .btn-soft-success:hover {
        background-color: #10b981 !important;
        color: #ffffff !important;
        border-color: #10b981 !important;
    }
    .btn-soft-danger {
        background-color: #fee2e2 !important;
        color: #991b1b !important;
        border: 1px solid #fecaca !important;
        transition: all 0.2s ease;
    }
    .btn-soft-danger:hover {
        background-color: #ef4444 !important;
        color: #ffffff !important;
        border-color: #ef4444 !important;
    }
    .btn-soft-info {
        background-color: #e0f2fe !important;
        color: #0369a1 !important;
        border: 1px solid #bae6fd !important;
        transition: all 0.2s ease;
    }
    .btn-soft-info:hover {
        background-color: #0284c7 !important;
        color: #ffffff !important;
        border-color: #0284c7 !important;
    }
    .btn-soft-warning {
        background-color: #fef3c7 !important;
        color: #92400e !important;
        border: 1px solid #fde68a !important;
        transition: all 0.2s ease;
    }
    .btn-soft-warning:hover {
        background-color: #f59e0b !important;
        color: #ffffff !important;
        border-color: #f59e0b !important;
    }
</style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('visitor.index') }}" variant="secondary" icon="feather-arrow-left">
            {{ __('visitor.back_to_list') }}
        </x-ui.button>
    </div>
@endsection

@section('content')

    <!-- Flash Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center" role="alert">
            <i class="feather-check-circle fs-18 text-success me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center" role="alert">
            <i class="feather-alert-triangle fs-18 text-danger me-2"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- 1. Stats Widgets (Common x-ui.stat-widget) -->
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-widget 
                title="{{ __('visitor.pending_approvals') }}" 
                value="{{ $stats['waiting_approval'] ?? 0 }}" 
                icon="feather-clock" 
                color="warning" 
                variant="compact" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-widget 
                title="{{ __('visitor.approved_requests') }}" 
                value="{{ $stats['approved'] ?? 0 }}" 
                icon="feather-check-circle" 
                color="success" 
                variant="compact" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-widget 
                title="{{ __('visitor.rejected_requests') }}" 
                value="{{ $stats['rejected'] ?? 0 }}" 
                icon="feather-x-circle" 
                color="danger" 
                variant="compact" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-widget 
                title="{{ __('visitor.total_requests') }}" 
                value="{{ $stats['total'] ?? 0 }}" 
                icon="feather-users" 
                color="primary" 
                variant="compact" />
        </div>
    </div>

    <!-- 2. Main Panel (Common ERP Panel) -->
    <div class="erp-single-panel">
        
        {{-- Header: Title & Actions --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('visitor.approvals_dashboard') }}</h5>
            
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box (CRM/HRMS Common Style) -->
                <form method="GET" action="{{ route('visitor.approvals.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 250px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('visitor.search_placeholder') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('visitor.approvals.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <!-- Common Filter Component -->
                <form method="GET" action="{{ route('visitor.approvals.index') }}" class="d-inline">
                    @if(request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    <x-ui.filter :label="__('crm.filter') ?? 'Filter'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') ?? 'Filter Options' }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('visitor.purpose_of_visit') }}</label>
                            <x-ui.odoo-form-ui type="select" name="purpose">
                                <option value="">— All Purposes —</option>
                                <option value="Meeting" {{ request('purpose') === 'Meeting' ? 'selected' : '' }}>{{ __('visitor.purposes.Meeting') }}</option>
                                <option value="Interview" {{ request('purpose') === 'Interview' ? 'selected' : '' }}>{{ __('visitor.purposes.Interview') }}</option>
                                <option value="Vendor" {{ request('purpose') === 'Vendor' ? 'selected' : '' }}>{{ __('visitor.purposes.Vendor') }}</option>
                                <option value="Delivery" {{ request('purpose') === 'Delivery' ? 'selected' : '' }}>{{ __('visitor.purposes.Delivery') }}</option>
                                <option value="Audit" {{ request('purpose') === 'Audit' ? 'selected' : '' }}>{{ __('visitor.purposes.Audit') }}</option>
                                <option value="Personal" {{ request('purpose') === 'Personal' ? 'selected' : '' }}>{{ __('visitor.purposes.Personal') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_from') ?? 'Date From' }}</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') }}" />
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_to') ?? 'Date To' }}</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') }}" />
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('visitor.approvals.index') }}" class="btn btn-sm btn-light border">{{ __('crm.reset') ?? 'Reset' }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') ?? 'Apply Filters' }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Status Tabs (Using Common Horizontal Tabs Component) --}}
        @php
            $activeStatus = request('status', 'Waiting Approval');
            $approvalTabs = [
                [
                    'id' => 'tab-pending',
                    'label' => __('visitor.pending_approvals') . ' (' . ($stats['waiting_approval'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Waiting Approval', 'page' => null]),
                    'active' => $activeStatus === 'Waiting Approval',
                ],
                [
                    'id' => 'tab-approved',
                    'label' => __('visitor.approved_requests') . ' (' . ($stats['approved'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Approved', 'page' => null]),
                    'active' => $activeStatus === 'Approved',
                ],
                [
                    'id' => 'tab-rejected',
                    'label' => __('visitor.rejected_requests') . ' (' . ($stats['rejected'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Rejected', 'page' => null]),
                    'active' => $activeStatus === 'Rejected',
                ],
                [
                    'id' => 'tab-all',
                    'label' => __('visitor.all_approvals') . ' (' . ($stats['total'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'all', 'page' => null]),
                    'active' => $activeStatus === 'all',
                ],
            ];
        @endphp
        <x-ui.horizontal-tabs id="approvalStatusTabs" class="mb-3" :tabs="$approvalTabs" />

        <!-- Passes Data Table (Using Common x-ui.odoo-form-ui table) -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="approvalTable" class="mb-0">
                <thead>
                    <tr>
                        <th style="width: 14%;">{{ __('visitor.pass_number') }}</th>
                        <th style="width: 18%;">{{ __('visitor.visitor_name') }}</th>
                        <th style="width: 13%;">{{ __('visitor.company') }}</th>
                        <th style="width: 13%;">{{ __('visitor.purpose_of_visit') }}</th>
                        <th style="width: 12%;">{{ __('visitor.expected_time') }}</th>
                        <th style="width: 9%;">{{ __('visitor.status') }}</th>
                        <th style="width: 21%;" class="text-end pe-3">{{ __('visitor.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($passes as $pass)
                        <tr>
                            <td>
                                <a href="{{ route('visitor.passes.show', $pass->id) }}" class="fw-bold text-primary text-decoration-none">
                                    {{ $pass->pass_number }}
                                </a>
                                @if($pass->gate_number)
                                    <div class="fs-11 text-muted"><i class="feather-map-pin me-1"></i>{{ $pass->gate_number }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if(!empty($pass->visitor?->photo_url))
                                        <img src="{{ $pass->visitor->photo_url }}" alt="{{ $pass->visitor->full_name }}" class="visitor-avatar">
                                    @else
                                        <div class="visitor-avatar-placeholder">
                                            {{ strtoupper(substr($pass->visitor?->full_name ?? 'V', 0, 1)) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $pass->visitor?->full_name ?? '—' }}</div>
                                        <div class="fs-11 text-muted">{{ $pass->visitor?->phone ?? '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark">{{ $pass->visitor?->company_name ?? '—' }}</div>
                                @if($pass->visitor?->designation)
                                    <div class="fs-11 text-muted">{{ $pass->visitor->designation }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ __('visitor.purposes.' . $pass->purpose) ?? $pass->purpose }}
                                </span>
                                @if($pass->belongings && $pass->belongings->count() > 0)
                                    <div class="fs-11 text-muted mt-0.5">
                                        <i class="feather-package text-secondary"></i> {{ $pass->belongings->pluck('item_type')->join(', ') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($pass->expected_arrival_at)
                                    <span class="fs-12 text-dark">{{ $pass->expected_arrival_at->format('d M, Y h:i A') }}</span>
                                @else
                                    <span class="fs-12 text-muted">{{ $pass->created_at->format('d M, Y h:i A') }}</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusKey = match($pass->status) {
                                        'Checked-In'       => 'approved',
                                        'Checked-Out'      => 'completed',
                                        'Approved'         => 'active',
                                        'Waiting Approval' => 'pending_approval',
                                        'Rejected'         => 'rejected',
                                        default            => 'in_progress',
                                    };
                                @endphp
                                <x-ui.status-badge :status="$statusKey" :label="__('visitor.statuses.' . $pass->status, [], null) ?? $pass->status" dot />
                                @if($pass->status === 'Rejected' && $pass->rejection_reason)
                                    <div class="fs-11 text-danger mt-1 text-truncate" style="max-width: 140px;" title="{{ $pass->rejection_reason }}">
                                        <em>{{ $pass->rejection_reason }}</em>
                                    </div>
                                @endif
                            </td>
                            <!-- Actions Column (Common Component x-ui.button with Spacing) -->
                            <td class="text-end pe-3" style="white-space: nowrap;">
                                <div class="hstack gap-2 justify-content-end flex-nowrap">
                                    @if($pass->status === 'Waiting Approval')
                                        <!-- Approve Button Form -->
                                        <form method="POST" action="{{ route('visitor.approvals.approve', $pass->id) }}" class="d-inline-flex m-0 p-0">
                                            @csrf
                                            <x-ui.button variant="success" size="sm" type="submit" icon="feather-check">
                                                {{ __('visitor.approve') }}
                                            </x-ui.button>
                                        </form>

                                        <!-- Reject Button -->
                                        <x-ui.button variant="danger" size="sm" type="button" icon="feather-x" onclick="openRejectModal({{ $pass->id }}, '{{ addslashes($pass->visitor?->full_name ?? '') }}', '{{ $pass->pass_number }}')">
                                            {{ __('visitor.reject') }}
                                        </x-ui.button>
                                    @elseif($pass->status === 'Checked-In')
                                        <!-- Start Meeting Button -->
                                        <form method="POST" action="{{ route('visitor.passes.start-meeting', $pass->id) }}" class="d-inline-flex m-0 p-0">
                                            @csrf
                                            <x-ui.button variant="info" size="sm" type="submit" icon="feather-users" class="text-white">
                                                {{ __('visitor.start_meeting') }}
                                            </x-ui.button>
                                        </form>

                                        <!-- Extend Stay Button -->
                                        <x-ui.button variant="warning" size="sm" type="button" icon="feather-clock" class="text-dark" onclick="openExtendModal({{ $pass->id }}, '{{ $pass->pass_number }}', {{ $pass->expected_duration_minutes ?: 60 }})">
                                            +Time
                                        </x-ui.button>
                                    @elseif($pass->status === 'Meeting in Progress')
                                        <!-- Extend Stay Button -->
                                        <x-ui.button variant="warning" size="sm" type="button" icon="feather-clock" class="text-dark" onclick="openExtendModal({{ $pass->id }}, '{{ $pass->pass_number }}', {{ $pass->expected_duration_minutes ?: 60 }})">
                                            {{ __('visitor.extend_visit') }}
                                        </x-ui.button>
                                    @endif

                                    <!-- View Pass Details Button -->
                                    <x-ui.button variant="light" size="sm" href="{{ route('visitor.passes.show', $pass->id) }}" icon="feather-eye" title="{{ __('visitor.view_pass') }}" class="border">
                                    </x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="feather-inbox fs-32 text-secondary mb-2 d-block"></i>
                                    <p class="mb-0 fs-13">{{ __('visitor.no_visitors_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        {{-- Pagination --}}
        <div class="pt-3">
            <x-ui.pagination
                :currentPage="$passes->currentPage()"
                :totalPages="$passes->lastPage()"
                :totalResults="$passes->total()"
                :perPage="$passes->perPage()" />
        </div>

    </div>

    <!-- Reject Confirmation Modal -->
    <x-ui.modal 
        id="rejectPassModal" 
        title="<i class='feather-alert-octagon text-danger me-2'></i> {{ __('visitor.reject_pass') }}" 
        centered 
        :showFooter="false">
        <form id="rejectPassForm" method="POST" action="">
            @csrf
            <div class="mb-3">
                <p class="fs-13 text-dark mb-1">
                    Visitor: <strong id="rejectVisitorName" class="text-dark"></strong> (<span id="rejectPassNumber" class="text-muted"></span>)
                </p>
                <p class="fs-12 text-muted">
                    {{ __('visitor.enter_rejection_reason') }}
                </p>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.rejection_reason') }} <span class="text-danger">*</span></label>
                <textarea name="rejection_reason" id="rejection_reason_input" class="form-control fs-13" rows="3" placeholder="{{ __('visitor.enter_rejection_reason') }}" required></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">{{ __('crm.cancel') ?? 'Cancel' }}</button>
                <button type="submit" class="btn btn-danger px-3 fw-semibold">
                    <i class="feather-x me-1"></i> {{ __('visitor.reject') }}
                </button>
            </div>
        </form>
    </x-ui.modal>

    <!-- Extend Visit Modal for Host -->
    <x-ui.modal 
        id="extendVisitModal" 
        title="<i class='feather-clock text-warning me-2'></i> {{ __('visitor.extend_visit') }} - <span id='extendPassNumber'></span>" 
        centered 
        :showFooter="false">
        <form id="extendVisitForm" method="POST" action="">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.extend_minutes') }} <span class="text-danger">*</span></label>
                <select name="extend_minutes" class="form-select fs-13" required>
                    <option value="30">+30 Minutes</option>
                    <option value="60" selected>+60 Minutes (1 Hour)</option>
                    <option value="120">+120 Minutes (2 Hours)</option>
                    <option value="240">+240 Minutes (4 Hours)</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">Reason / Remarks</label>
                <textarea name="extend_notes" class="form-control fs-13" rows="2" placeholder="Meeting prolonged, additional discussion..."></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">{{ __('crm.cancel') ?? 'Cancel' }}</button>
                <button type="submit" class="btn btn-warning px-3 fw-semibold">
                    <i class="feather-clock me-1"></i> Extend Stay
                </button>
            </div>
        </form>
    </x-ui.modal>

@endsection

@push('scripts')
<script>
    function openRejectModal(passId, visitorName, passNumber) {
        const form = document.getElementById('rejectPassForm');
        form.action = `/visitor/approvals/${passId}/reject`;
        document.getElementById('rejectVisitorName').textContent = visitorName;
        document.getElementById('rejectPassNumber').textContent = passNumber;
        document.getElementById('rejection_reason_input').value = '';
        
        const modal = new bootstrap.Modal(document.getElementById('rejectPassModal'));
        modal.show();
    }

    function openExtendModal(id, passNo, currentDuration) {
        document.getElementById('extendVisitForm').action = '/visitor/passes/' + id + '/extend';
        document.getElementById('extendPassNumber').textContent = passNo;
        new bootstrap.Modal(document.getElementById('extendVisitModal')).show();
    }
</script>
@endpush
