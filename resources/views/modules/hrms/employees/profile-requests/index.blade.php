@extends('layouts.duralux')

@section('title', __('hrms.employees.profile_requests') . ' | SaaS ERP')
@section('page-title', __('hrms.employees.profile_requests'))
@section('breadcrumb', 'HRMS / ' . __('hrms.employees.title') . ' / ' . __('hrms.employees.profile_requests'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('hrms.employees.index') }}" variant="light" icon="feather-arrow-left">
            {{ __('hrms.employees.back_to_registry') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <style>
        .avatar-initials {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: rgba(var(--bs-primary-rgb), 0.1);
            color: var(--bs-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
        }
    </style>
@endpush

@section('content')
    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-circle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        @php
            $currentParams = request()->except('page');

            $tabs = [
                [
                    'id'         => 'tab-all',
                    'label'      => __('hrms.employees.all_requests'),
                    'icon'       => 'feather-layers',
                    'active'     => $status === 'all' || empty($status),
                    'url'        => route('hrms.employees.profile-requests.index', array_merge($currentParams, ['status' => 'all'])),
                    'badge'      => $counts['all'] ?? null,
                    'badgeClass' => 'bg-secondary-subtle text-secondary',
                ],
                [
                    'id'         => 'tab-pending',
                    'label'      => __('hrms.common.pending'),
                    'icon'       => 'feather-clock',
                    'active'     => $status === 'pending',
                    'url'        => route('hrms.employees.profile-requests.index', array_merge($currentParams, ['status' => 'pending'])),
                    'badge'      => $counts['pending'] ?? null,
                    'badgeClass' => 'bg-warning-subtle text-warning',
                ],
                [
                    'id'         => 'tab-approved',
                    'label'      => __('hrms.employees.status_approved'),
                    'icon'       => 'feather-check-circle',
                    'active'     => $status === 'approved',
                    'url'        => route('hrms.employees.profile-requests.index', array_merge($currentParams, ['status' => 'approved'])),
                    'badge'      => $counts['approved'] ?? null,
                    'badgeClass' => 'bg-success-subtle text-success',
                ],
                [
                    'id'         => 'tab-rejected',
                    'label'      => __('hrms.employees.status_rejected'),
                    'icon'       => 'feather-x-circle',
                    'active'     => $status === 'rejected',
                    'url'        => route('hrms.employees.profile-requests.index', array_merge($currentParams, ['status' => 'rejected'])),
                    'badge'      => $counts['rejected'] ?? null,
                    'badgeClass' => 'bg-danger-subtle text-danger',
                ],
            ];
        @endphp

        <!-- Top Navigation Tabs & Toolbar Container -->
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 pb-2 border-bottom">
            <!-- Left: Tab Navigation Links via Horizontal Tabs Component -->
            <div class="w-auto">
                <x-ui.horizontal-tabs id="profileRequestTabs" :tabs="$tabs" class="border-0 mb-0 p-0" />
            </div>

            <!-- Right: Search, Sort Dropdown & Filter Dropdown (Common UI Components) -->
            <div class="d-flex align-items-center gap-2 flex-wrap ms-lg-auto">
                <!-- Search Form -->
                <form method="GET" action="{{ route('hrms.employees.profile-requests.index') }}" id="profileRequestsSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 36px !important; box-sizing: border-box !important;">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">
                    <input type="hidden" name="department_id" value="{{ $departmentId }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" name="search" id="profile_requests_search_input" value="{{ $search }}" class="w-100 border-0 bg-transparent p-0 fs-13 text-dark" placeholder="{{ __('hrms.employees.search_employees') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'created_at' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.req_date_newest') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'created_at' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.req_date_oldest') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'full_name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'full_name' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.emp_name_az') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'full_name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'full_name' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.emp_name_za') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'reviewed_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'reviewed_at' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>{{ __('hrms.employees.reviewed_date_newest') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <form method="GET" action="{{ route('hrms.employees.profile-requests.index') }}" class="d-inline" id="profileRequestsFilterForm">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">

                    <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                        
                        <!-- Department -->
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_department') }}</label>
                            <x-ui.odoo-form-ui type="select" name="department_id" id="filter_department_id">
                                <option value="">{{ __('hrms.common.all_departments') }}</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ (string)$departmentId === (string)$dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="dropdown-divider my-3"></div>

                        <div class="d-flex gap-2">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.common.apply') }}</x-ui.button>
                            <x-ui.button href="{{ route('hrms.employees.profile-requests.index', array_filter(['status' => $status !== 'all' ? $status : null, 'search' => $search, 'sort_by' => $sortBy, 'sort_order' => $sortOrder])) }}" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') }}</x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Table Container -->
        @if($profileRequests->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-dark">
                    <thead class="table-light fs-11 text-uppercase tracking-wider">
                        <tr>
                            <th class="ps-3 py-3" style="width: 25%;">{{ __('hrms.employees.tbl_employee') }}</th>
                            <th class="py-3" style="width: 22%;">{{ __('hrms.employees.edited_fields') }}</th>
                            <th class="py-3" style="width: 15%;">{{ __('hrms.employees.requested_on') }}</th>
                            <th class="py-3" style="width: 14%;">{{ __('hrms.employees.tbl_status') }}</th>
                            <th class="py-3" style="width: 14%;">{{ __('hrms.employees.reviewed_by') }}</th>
                            <th class="text-end pe-3 py-3" style="width: 10%;">{{ __('hrms.employees.tbl_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($profileRequests as $req)
                            @php
                                $emp = $req->employee;
                                $changes = $req->changes ?? [];
                                $changeCount = count($changes);
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-3">
                                        @if($emp && $emp->photo)
                                            <img src="{{ asset('storage/' . $emp->photo) }}" alt="{{ $emp->full_name }}" class="rounded-circle shadow-sm" style="width: 38px; height: 38px; object-fit: cover; border: 1px solid #e2e8f0;">
                                        @else
                                            <div class="avatar-initials">
                                                {{ strtoupper(substr($emp?->first_name ?? 'E', 0, 1) . substr($emp?->last_name ?? 'M', 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <a href="{{ $emp ? route('hrms.employees.show', $emp->id) : '#' }}" class="fw-bold text-dark text-decoration-none d-block">
                                                {{ $emp?->full_name ?? 'Unknown Employee' }}
                                            </a>
                                            <div class="text-muted fs-11">
                                                <code class="text-primary">{{ $emp?->employee_id ?? 'N/A' }}</code> &bull; {{ $emp?->department?->name ?? 'No Dept' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach(array_slice($changes, 0, 3, true) as $fKey => $fData)
                                            <x-ui.badge variant="light" class="border text-dark fs-11 fw-normal">
                                                {{ $fData['label'] ?? ucwords(str_replace('_', ' ', $fKey)) }}
                                            </x-ui.badge>
                                        @endforeach
                                        @if($changeCount > 3)
                                            <x-ui.badge variant="secondary" soft class="fs-11">
                                                +{{ $changeCount - 3 }} more
                                            </x-ui.badge>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="fs-12 text-dark fw-semibold">
                                        {{ $req->created_at ? $req->created_at->format('d M Y') : 'N/A' }}
                                    </span>
                                    <div class="text-muted fs-11">
                                        {{ $req->created_at ? $req->created_at->format('h:i A') : '' }}
                                    </div>
                                </td>
                                <td>
                                    @if($req->status === 'pending')
                                        <x-ui.badge variant="warning" soft class="border border-warning-subtle px-2.5 py-1 fw-bold fs-11">
                                            <i class="feather-clock me-1"></i>{{ __('hrms.common.pending') }}
                                        </x-ui.badge>
                                    @elseif($req->status === 'approved')
                                        <x-ui.badge variant="success" soft class="border border-success-subtle px-2.5 py-1 fw-bold fs-11">
                                            <i class="feather-check-circle me-1"></i>{{ __('hrms.employees.status_approved') }}
                                        </x-ui.badge>
                                    @elseif($req->status === 'rejected')
                                        <x-ui.badge variant="danger" soft class="border border-danger-subtle px-2.5 py-1 fw-bold fs-11" title="{{ $req->rejection_reason }}">
                                            <i class="feather-x-circle me-1"></i>{{ __('hrms.employees.status_rejected') }}
                                        </x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    @if($req->reviewer)
                                        <span class="fs-12 fw-medium text-dark d-block">{{ $req->reviewer->name }}</span>
                                        <div class="text-muted fs-11">
                                            {{ $req->reviewed_at ? $req->reviewed_at->format('d M Y, h:i A') : '' }}
                                        </div>
                                    @else
                                        <span class="text-muted fs-12">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <x-ui.button type="button" variant="outline-primary" size="sm" icon="feather-eye" data-bs-toggle="modal" data-bs-target="#viewRequestModal_{{ $req->id }}">
                                        {{ __('hrms.employees.review') }}
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- REVIEW MODALS (Placed outside table to prevent DOM corruption and modal blur) -->
            @foreach($profileRequests as $req)
                @php
                    $emp = $req->employee;
                    $changes = $req->changes ?? [];
                @endphp
                <div class="modal fade" id="viewRequestModal_{{ $req->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                            <div class="modal-header bg-light border-bottom px-4 py-3">
                                <div>
                                    <h5 class="modal-title fw-bold mb-0 text-dark">
                                        <i class="feather-user-check me-2 text-primary"></i>{{ __('hrms.employees.review_request') }} #{{ $req->id }}
                                    </h5>
                                    <span class="text-muted fs-12">
                                        {{ __('hrms.employees.submitted_by_on', ['name' => $emp?->full_name, 'date' => $req->created_at ? $req->created_at->format('d M Y, h:i A') : 'N/A']) }}
                                    </span>
                                </div>
                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                @if($req->status === 'rejected' && $req->rejection_reason)
                                    <x-ui.alert variant="danger" class="border-0 rounded-3 mb-4 py-2 px-3 fs-13">
                                        <div class="fw-bold mb-1"><i class="feather-alert-circle me-1"></i>{{ __('hrms.employees.rejection_reason_title') }}:</div>
                                        <div>{{ $req->rejection_reason }}</div>
                                    </x-ui.alert>
                                @endif

                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-layers text-primary me-2"></i>{{ __('hrms.employees.comparison_title') }}
                                </h6>

                                <div class="border rounded-3 overflow-hidden mb-3">
                                    <table class="table table-bordered align-middle mb-0 fs-13" style="table-layout: fixed; width: 100%;">
                                        <thead class="bg-light text-uppercase fs-11 text-muted">
                                            <tr>
                                                <th style="width: 28%;" class="ps-3">{{ __('hrms.employees.field_name') }}</th>
                                                <th style="width: 36%;" class="text-danger bg-soft-danger">{{ __('hrms.employees.current_value') }}</th>
                                                <th style="width: 36%;" class="text-success bg-soft-success">{{ __('hrms.employees.requested_value') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($changes as $fKey => $fData)
                                                <tr>
                                                    <td class="fw-bold text-dark ps-3 text-break" style="white-space: normal; word-break: break-word;">
                                                        {{ $fData['label'] ?? ucwords(str_replace('_', ' ', $fKey)) }}
                                                    </td>
                                                    <td class="text-muted text-break" style="white-space: normal; word-break: break-word;">
                                                        @if(!empty($fData['is_image']))
                                                            @if(!empty($fData['old']) && $fData['old'] !== '—')
                                                                <img src="{{ asset('storage/' . $fData['old']) }}" alt="Old Photo" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px;">
                                                            @else
                                                                <span class="text-muted">{{ __('hrms.employees.no_photo') }}</span>
                                                            @endif
                                                        @else
                                                            @if(empty($fData['old']) || $fData['old'] === '—')
                                                                <span class="text-muted">&mdash;</span>
                                                            @else
                                                                <span class="text-danger text-decoration-line-through d-inline-block" style="line-height: 1.45;">{{ $fData['old'] }}</span>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td class="fw-bold text-success text-break" style="white-space: normal; word-break: break-word;">
                                                        @if(!empty($fData['is_image']))
                                                            <img src="{{ asset('storage/' . $fData['new']) }}" alt="New Photo" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px; border: 2px solid #22c55e;">
                                                        @else
                                                            @if(empty($fData['new']) || $fData['new'] === '—')
                                                                <span class="text-muted">&mdash;</span>
                                                            @else
                                                                <div class="p-2 rounded-2 bg-success-subtle text-success border border-success-subtle fs-13 fw-semibold text-break d-block" style="white-space: normal; word-break: break-word; line-height: 1.45;">{{ $fData['new'] }}</div>
                                                            @endif
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer bg-light border-top px-4 py-3 d-flex justify-content-between">
                                <x-ui.button type="button" variant="light" class="border" data-bs-dismiss="modal">
                                    {{ __('hrms.common.close') }}
                                </x-ui.button>
                                
                                @if($req->status === 'pending')
                                    @php
                                        $authUser = auth()->user();
                                        $canApprove = $authUser && app(\App\Services\Access\AccessService::class)->allows($authUser, 'hrms.employees.update', ['tenant_id' => $authUser->tenant_id]);
                                    @endphp

                                    @if($canApprove)
                                        <div class="d-flex align-items-center gap-2">
                                            <!-- Reject Form Trigger -->
                                            <x-ui.button type="button" variant="danger" icon="feather-x" data-bs-toggle="collapse" data-bs-target="#rejectCollapse_{{ $req->id }}">
                                                {{ __('hrms.common.reject') }}
                                            </x-ui.button>

                                            <!-- Approve Form -->
                                            <form action="{{ route('hrms.employees.profile-requests.approve', $req->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <x-ui.button type="submit" variant="success" icon="feather-check" class="fw-bold">
                                                    {{ __('hrms.employees.approve_and_update') }}
                                                </x-ui.button>
                                            </form>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            <!-- Reject Reason Collapse Section -->
                            @if($req->status === 'pending')
                                <div class="collapse p-4 border-top bg-light" id="rejectCollapse_{{ $req->id }}">
                                    <form action="{{ route('hrms.employees.profile-requests.reject', $req->id) }}" method="POST">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label fs-12 fw-bold text-dark">{{ __('hrms.employees.rejection_reason_label') }}</label>
                                            <textarea name="rejection_reason" class="form-control fs-13 rounded-3" rows="2" placeholder="{{ __('hrms.employees.rejection_reason_placeholder') }}"></textarea>
                                        </div>
                                        <div class="d-flex justify-content-end gap-2">
                                            <x-ui.button type="button" variant="light" size="sm" class="border" data-bs-toggle="collapse" data-bs-target="#rejectCollapse_{{ $req->id }}">
                                                {{ __('hrms.common.cancel') }}
                                            </x-ui.button>
                                            <x-ui.button type="submit" variant="danger" size="sm" class="fw-bold">
                                                {{ __('hrms.employees.confirm_rejection') }}
                                            </x-ui.button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            @if($profileRequests->hasPages())
                <div class="mt-4">
                    <x-ui.pagination 
                        :currentPage="$profileRequests->currentPage()" 
                        :totalPages="$profileRequests->lastPage()" 
                        :totalResults="$profileRequests->total()" 
                        :perPage="$profileRequests->perPage()" 
                    />
                </div>
            @endif
        @else
            <div class="text-center py-5 border rounded bg-light">
                <div class="p-3 d-inline-block rounded-circle bg-soft-primary text-primary mb-3">
                    <i class="feather-user-check fs-24"></i>
                </div>
                <h5 class="fw-bold text-dark">{{ __('hrms.employees.no_profile_requests_found') }}</h5>
                <p class="text-muted fs-13 mb-0">
                    @if($status === 'pending')
                        {{ __('hrms.employees.all_profile_requests_processed') }}
                    @else
                        {{ __('hrms.employees.no_profile_requests_match') }}
                    @endif
                </p>
            </div>
        @endif

    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Move all modals to body root to prevent Bootstrap backdrop overlay / blur issues
        $('.modal').each(function() {
            $(this).appendTo('body');
        });

        const searchInput = document.getElementById('profile_requests_search_input');
        const searchForm = document.getElementById('profileRequestsSearchForm');
        let profileSearchDebounceTimer;

        // Auto-focus search input if search query is present and position cursor at the end
        if (searchInput) {
            if (searchInput.value) {
                searchInput.focus();
                const len = searchInput.value.length;
                searchInput.setSelectionRange(len, len);
            }
        }

        // Auto-search on typing with debounce
        $(document).on('input', '#profile_requests_search_input', function() {
            clearTimeout(profileSearchDebounceTimer);
            profileSearchDebounceTimer = setTimeout(function() {
                $('#profileRequestsSearchForm').submit();
            }, 500);
        });

        // Instant search on pressing Enter key
        $('#profileRequestsSearchForm').on('submit', function() {
            clearTimeout(profileSearchDebounceTimer);
        });

        $('#profile_requests_search_input').on('keydown', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                clearTimeout(profileSearchDebounceTimer);
                $('#profileRequestsSearchForm').submit();
            }
        });
    });
</script>
@endpush
