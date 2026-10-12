@extends('layouts.duralux')

@section('title', __('hrms.exits.page_title') . ' | SaaS ERP')
@section('page-title', __('hrms.exits.page_title'))
@section('breadcrumb', __('hrms.exits.breadcrumb'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-user-minus" data-bs-toggle="modal" data-bs-target="#initiateExitModal" class="fw-bold shadow-sm">
            {{ __('hrms.exits.btn_initiate_exit') }}
        </x-ui.button>
        <x-ui.button variant="light" icon="feather-users" href="{{ route('hrms.employees.index') }}" class="border text-dark fw-semibold">
            {{ __('hrms.exits.btn_back_to_registry') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .avatar-initials {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 50%;
            background-color: rgba(var(--bs-danger-rgb), 0.1);
            color: var(--bs-danger);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
        }
        .table-responsive {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        .table-responsive::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        .clearance-item-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            background: #ffffff;
            transition: all 0.2s;
        }
        .clearance-item-box:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }
    </style>
@endpush

@section('content')
    @if(session('success'))
        <x-ui.alert variant="success" :dismissible="true" icon="feather-check-circle" class="mb-4 shadow-sm border-0">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" :dismissible="true" icon="feather-alert-circle" class="mb-4 shadow-sm border-0">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        @php
            $currentParams = [
                'department_id' => $departmentId,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'status' => $statusFilter,
            ];

            $tabs = [
                [
                    'id'     => 'tab-exit-cases',
                    'label'  => __('hrms.exits.tab_exit_cases'),
                    'icon'   => 'feather-list',
                    'active' => $activeTab === 'exits' || empty($activeTab),
                    'href'   => route('hrms.exits.index', array_merge($currentParams, ['tab' => 'exits'])),
                ],
                [
                    'id'     => 'tab-clearances',
                    'label'  => __('hrms.exits.tab_clearance_nocs'),
                    'icon'   => 'feather-check-square',
                    'active' => $activeTab === 'clearances',
                    'href'   => route('hrms.exits.index', array_merge($currentParams, ['tab' => 'clearances'])),
                ],
                [
                    'id'     => 'tab-fnf',
                    'label'  => __('hrms.exits.tab_fnf_settlements'),
                    'icon'   => 'feather-credit-card',
                    'active' => $activeTab === 'fnf',
                    'href'   => route('hrms.exits.index', array_merge($currentParams, ['tab' => 'fnf'])),
                ],
                [
                    'id'     => 'tab-documents',
                    'label'  => __('hrms.exits.tab_relieving_certificates'),
                    'icon'   => 'feather-file-text',
                    'active' => $activeTab === 'documents',
                    'href'   => route('hrms.exits.index', array_merge($currentParams, ['tab' => 'documents'])),
                ],
            ];

            $tabTitles = [
                'exits' => __('hrms.exits.tab_exit_cases'),
                'clearances' => __('hrms.exits.tab_clearance_nocs'),
                'fnf' => __('hrms.exits.tab_fnf_settlements'),
                'documents' => __('hrms.exits.tab_relieving_certificates'),
            ];
            $currentTabTitle = $tabTitles[$activeTab] ?? __('hrms.exits.tab_exit_cases');
        @endphp

        {{-- Tabs header navigation using standard x-ui.horizontal-tabs (matching Expense Master) --}}
        <div id="exitTabsWrapper">
            <x-ui.horizontal-tabs id="exitMainTabs" :tabs="$tabs" />
        </div>

        {{-- Sub-Toolbar Row (Category/Tab title on Left, Search + Sort + Filter on Right) --}}
        <div id="exitToolbarWrapper" class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            {{-- Left: Table / Tab Title --}}
            <h5 class="fw-bold text-dark mb-0 fs-15">{{ $currentTabTitle }}</h5>

            {{-- Right: Actions Toolbar (Search, Sort, Filter) --}}
            <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                {{-- Search Form --}}
                <form method="GET" action="{{ route('hrms.exits.index') }}" class="m-0" id="exitSearchForm" style="width: 220px;">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <input type="hidden" name="status" value="{{ $statusFilter }}">
                    <input type="hidden" name="department_id" value="{{ $departmentId }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">
                    
                    <x-ui.icon-input
                        icon="feather-search"
                        name="search"
                        id="exit_search_input"
                        :value="$search"
                        placeholder="{{ __('hrms.exits.search_placeholder') }}"
                        autocomplete="off"
                    />
                </form>

                {{-- Sort Dropdown --}}
                <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" :active="$sortBy === 'created_at' && $sortOrder === 'desc'">
                        {{ __('hrms.exits.sort_created_newest') }}
                    </x-ui.dropdown-item>
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" :active="$sortBy === 'created_at' && $sortOrder === 'asc'">
                        {{ __('hrms.exits.sort_created_oldest') }}
                    </x-ui.dropdown-item>
                    <div class="dropdown-divider"></div>
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'lwd', 'sort_order' => 'asc']) }}" :active="$sortBy === 'lwd' && $sortOrder === 'asc'">
                        {{ __('hrms.exits.sort_lwd_nearest') }}
                    </x-ui.dropdown-item>
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'lwd', 'sort_order' => 'desc']) }}" :active="$sortBy === 'lwd' && $sortOrder === 'desc'">
                        {{ __('hrms.exits.sort_lwd_furthest') }}
                    </x-ui.dropdown-item>
                    <div class="dropdown-divider"></div>
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'employee', 'sort_order' => 'asc']) }}" :active="$sortBy === 'employee' && $sortOrder === 'asc'">
                        {{ __('hrms.exits.sort_name_az') }}
                    </x-ui.dropdown-item>
                </x-ui.sort-dropdown>

                {{-- Filter Dropdown --}}
                <form method="GET" action="{{ route('hrms.exits.index') }}" class="d-inline m-0">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">

                    <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.common.department') }}</label>
                            <x-ui.odoo-form-ui type="select" name="department_id">
                                <option value="">{{ __('hrms.common.all_departments') }}</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected($departmentId == $dept->id)>{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.common.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                <option value="in_clearance" @selected($statusFilter === 'in_clearance')>{{ __('hrms.exits.status_in_clearance') }}</option>
                                <option value="approved" @selected($statusFilter === 'approved')>{{ __('hrms.exits.status_approved') }}</option>
                                <option value="settled" @selected($statusFilter === 'settled')>{{ __('hrms.exits.status_settled_exited') }}</option>
                                <option value="rejected" @selected($statusFilter === 'rejected')>{{ __('hrms.exits.status_rejected') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <x-ui.button variant="light" size="sm" href="{{ route('hrms.exits.index', ['tab' => $activeTab]) }}" class="border">{{ __('hrms.common.reset') }}</x-ui.button>
                            <x-ui.button variant="primary" size="sm" type="submit">{{ __('hrms.common.apply') }}</x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- TAB CONTENT AREA -->
        <div id="exitTabContentContainer">
            @if($activeTab === 'exits')
                <!-- TAB 1: EXIT CASES LIST -->
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle mb-0 text-dark w-100">
                        <thead class="table-light fs-11 text-uppercase tracking-wider">
                            <tr>
                                <th class="ps-3 py-2.5">{{ __('hrms.exits.tbl_employee_details') }}</th>
                                <th class="py-2.5">{{ __('hrms.exits.tbl_separation_type') }}</th>
                                <th class="py-2.5 text-nowrap">{{ __('hrms.exits.tbl_resignation_date') }}</th>
                                <th class="py-2.5 text-nowrap">{{ __('hrms.exits.tbl_lwd') }}</th>
                                <th class="py-2.5 text-nowrap">{{ __('hrms.exits.tbl_clearance_status') }}</th>
                                <th class="py-2.5 text-nowrap">{{ __('hrms.exits.tbl_exit_status') }}</th>
                                <th class="text-end pe-4 py-2.5 text-nowrap">{{ __('hrms.exits.tbl_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($exits as $exit)
                                @php
                                    $emp = $exit->employee;
                                    $progress = $exit->getClearanceProgressPercentage();
                                @endphp
                                <tr>
                                    <td class="ps-3 py-2.5">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-initials flex-shrink-0">
                                                {{ strtoupper(substr($emp->full_name ?? 'E', 0, 2)) }}
                                            </div>
                                            <div class="text-break">
                                                <a href="{{ route('hrms.employees.show', $emp->id) }}" class="fw-bold text-dark text-decoration-none d-block fs-13 lh-sm mb-1">
                                                    {{ $emp->full_name }}
                                                </a>
                                                <div class="text-dark fw-medium fs-12 lh-sm mb-0.5">
                                                    {{ $emp->designation->name ?? 'N/A' }}
                                                </div>
                                                <div class="text-muted fs-11 lh-sm">
                                                    {{ $emp->employee_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5">
                                        <x-ui.badge soft variant="secondary" class="text-uppercase fs-11">
                                            {{ ucfirst(str_replace('_', ' ', $exit->separation_type)) }}
                                        </x-ui.badge>
                                        <div class="text-muted fs-11 mt-1 lh-sm text-break">{{ $exit->reason_category }}</div>
                                    </td>
                                    <td class="py-2.5">
                                        <div class="fw-medium text-dark fs-12">{{ $exit->resignation_date ? \Carbon\Carbon::parse($exit->resignation_date)->format('d M, Y') : 'N/A' }}</div>
                                        <span class="text-muted fs-11 d-block">{{ __('hrms.exits.notice') }}: {{ $exit->notice_period_days }} {{ __('hrms.exits.days') }}</span>
                                    </td>
                                    <td class="py-2.5">
                                        <div class="fw-bold text-dark fs-12 d-flex align-items-center gap-1">
                                            <span>{{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : __('hrms.exits.pending_decision') }}</span>
                                            @if($exit->status !== 'settled' && $exit->status !== 'rejected')
                                                <x-ui.icon-btn variant="transparent-primary" size="xs" icon="feather-edit-2" data-bs-toggle="modal" data-bs-target="#approveExitModal_{{ $exit->id }}" title="{{ __('hrms.exits.modal_lwd_edit_title') }}" />
                                            @endif
                                        </div>
                                        @if($exit->approved_lwd)
                                            <span class="text-success fs-11 d-block"><i class="feather-check me-1"></i> {{ __('hrms.exits.approved_lwd') }}</span>
                                        @else
                                            <span class="text-warning fs-11 d-block"><i class="feather-calendar me-1"></i> {{ __('hrms.exits.pref_lwd') }}: {{ $exit->preferred_lwd ? \Carbon\Carbon::parse($exit->preferred_lwd)->format('d M, Y') : __('hrms.common.none') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5">
                                        <div class="d-flex align-items-center justify-content-between fs-11 mb-1">
                                            <span class="fw-semibold">{{ __('hrms.exits.noc_signoffs') }}</span>
                                            <span class="fw-bold {{ $progress === 100 ? 'text-success' : 'text-primary' }}">{{ $progress }}%</span>
                                        </div>
                                        <x-ui.progress-bar :value="$progress" height="5px" :color="$progress === 100 ? 'success' : 'primary'" />
                                    </td>
                                    <td class="py-2.5">
                                        @if($exit->status === 'settled')
                                            <x-ui.badge soft variant="success" class="fs-11">
                                                <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.status_settled_exited') }}
                                            </x-ui.badge>
                                        @elseif($exit->status === 'in_clearance')
                                            <x-ui.badge soft variant="warning" class="fs-11">
                                                <i class="feather-shield me-1"></i> {{ __('hrms.exits.status_in_clearance') }}
                                            </x-ui.badge>
                                        @elseif($exit->status === 'rejected')
                                            <x-ui.badge soft variant="danger" class="fs-11">
                                                <i class="feather-x-circle me-1"></i> {{ __('hrms.exits.status_rejected') }}
                                            </x-ui.badge>
                                        @else
                                            <x-ui.badge soft variant="info" class="fs-11">
                                                {{ ucfirst(str_replace('_', ' ', $exit->status)) }}
                                            </x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4 py-2.5">
                                        <x-ui.button variant="primary" size="sm" icon="feather-check-square" href="{{ route('hrms.exits.index', ['tab' => 'clearances']) }}" class="fw-semibold py-1 px-3 fs-11 text-nowrap">
                                            {{ __('hrms.exits.btn_noc_hub') }}
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-user-minus fs-24"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1 text-dark">{{ __('hrms.exits.empty_exits_title') }}</h6>
                                        <p class="fs-13 mb-0 text-muted">{{ __('hrms.exits.empty_exits_desc') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($activeTab === 'clearances')
                <!-- TAB 2: MULTI-DEPARTMENT CLEARANCE HUB -->
                <div class="row g-4">
                    @forelse($exits->where('status', '!=', 'rejected') as $exit)
                        @php
                            $emp = $exit->employee;
                            $progress = $exit->getClearanceProgressPercentage();
                            $deptClearances = $exit->clearances->groupBy('department');
                        @endphp
                        <div class="col-xl-6">
                            <div class="card border rounded-4 shadow-sm h-100 mb-0">
                                <div class="card-header bg-light border-bottom p-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0">{{ $emp->full_name }} ({{ $emp->employee_id }})</h6>
                                        <span class="text-muted fs-12">{{ $emp->designation->name ?? 'N/A' }} &bull; {{ __('hrms.exits.tbl_lwd') }}: <strong>{{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : 'TBD' }}</strong></span>
                                    </div>
                                    <div class="text-end">
                                        @if($progress === 100)
                                            <x-ui.badge soft variant="success" class="px-3 py-1.5 fw-bold fs-12">
                                                <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.cleared_100') }}
                                            </x-ui.badge>
                                        @elseif($progress > 0)
                                            <x-ui.badge soft variant="primary" class="px-3 py-1.5 fw-bold fs-12">
                                                {{ $progress }}% Cleared
                                            </x-ui.badge>
                                        @else
                                            <x-ui.badge soft variant="secondary" class="px-3 py-1.5 fw-semibold fs-12">
                                                0% Cleared
                                            </x-ui.badge>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row g-2">
                                        @php
                                            $allCategories = $deptClearances->keys();
                                            if ($allCategories->isEmpty()) {
                                                $allCategories = collect(['it_assets', 'facilities_admin', 'finance_payroll', 'hr_operations', 'reporting_manager']);
                                            }
                                        @endphp
                                        @foreach($allCategories as $catKey)
                                            @php
                                                $items = $deptClearances->get($catKey, collect());
                                                $meta = \App\Domains\HRMS\Models\ExitClearanceTemplate::getCategoryMetadata($catKey);
                                                $clearedOrWaived = $items->whereIn('status', ['cleared', 'waived'])->count();
                                                $issuesCount = $items->where('status', 'issues_found')->count();
                                                $allCleared = $items->count() > 0 && $clearedOrWaived === $items->count();
                                            @endphp
                                            <div class="col-12">
                                                <div class="clearance-item-box d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="avatar-text avatar-sm bg-soft-{{ $allCleared ? 'success' : ($issuesCount > 0 ? 'danger' : $meta['color']) }} text-{{ $allCleared ? 'success' : ($issuesCount > 0 ? 'danger' : $meta['color']) }} rounded-circle d-flex align-items-center justify-content-center">
                                                            <i class="{{ $allCleared ? 'feather-check' : ($issuesCount > 0 ? 'feather-alert-triangle' : $meta['icon']) }} fs-14"></i>
                                                        </div>
                                                        <div>
                                                            <span class="fw-bold text-dark fs-13">{{ $meta['name'] }}</span>
                                                            <span class="text-muted fs-11 d-block">
                                                                @if($issuesCount > 0)
                                                                    <span class="text-danger fw-semibold"><i class="feather-alert-circle me-1"></i>{{ __('hrms.exits.status_issues_flagged', ['count' => $issuesCount]) }}</span> &bull; {{ __('hrms.exits.status_resolved', ['cleared' => $clearedOrWaived, 'total' => $items->count()]) }}
                                                                @else
                                                                    ({{ __('hrms.exits.status_completed', ['cleared' => $clearedOrWaived, 'total' => $items->count()]) }})
                                                                @endif
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        @if($allCleared)
                                                            <x-ui.badge soft variant="success" class="px-3 py-2 fw-semibold fs-12">
                                                                <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.status_category_approved') }}
                                                            </x-ui.badge>
                                                        @elseif($issuesCount > 0)
                                                            <x-ui.badge soft variant="danger" class="px-2.5 py-1.5 fw-semibold fs-11">
                                                                {{ __('hrms.exits.status_issues_dues') }}
                                                            </x-ui.badge>
                                                            <x-ui.button variant="outline-primary" size="sm" icon="feather-edit-2" data-bs-toggle="modal" data-bs-target="#clearanceModal_{{ $exit->id }}_{{ $catKey }}" class="fw-bold px-3">
                                                                {{ __('hrms.common.edit') }}
                                                            </x-ui.button>
                                                        @else
                                                            <x-ui.button variant="primary" size="sm" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#clearanceModal_{{ $exit->id }}_{{ $catKey }}" class="fw-bold px-3">
                                                                {{ __('hrms.exits.btn_noc_hub') }}
                                                            </x-ui.button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="card-footer bg-light border-top p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <x-ui.button variant="primary" size="sm" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#allClearanceModal_{{ $exit->id }}" class="fw-bold shadow-sm">
                                        Review All Checklists
                                    </x-ui.button>
                                    @if($progress === 100)
                                        <span class="text-success fw-bold fs-13"><i class="feather-check-circle me-1"></i> {{ __('hrms.exits.status_ready_settle') }}</span>
                                    @else
                                        <span class="text-warning fw-semibold fs-12"><i class="feather-alert-circle me-1"></i> {{ __('hrms.exits.status_in_clearance') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center py-5 text-muted">
                            <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                <i class="feather-check-square fs-24"></i>
                            </div>
                            <h6 class="fw-bold mb-1 text-dark">{{ __('hrms.exits.empty_clearances_title') }}</h6>
                            <p class="fs-13 mb-0 text-muted">{{ __('hrms.exits.empty_exits_desc') }}</p>
                        </div>
                    @endforelse
                </div>

            @elseif($activeTab === 'fnf')
                <!-- TAB 3: FULL & FINAL (FnF) SETTLEMENTS -->
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle mb-0 text-dark w-100">
                        <thead class="table-light fs-11 text-uppercase tracking-wider">
                            <tr>
                                <th class="ps-3 py-2.5" style="width: 24%;">{{ __('hrms.exits.tbl_employee_details') }}</th>
                                <th class="py-2.5" style="width: 12%;">{{ __('hrms.exits.tbl_lwd') }}</th>
                                <th class="py-2.5" style="width: 22%;">{{ __('hrms.exits.tbl_gross_earnings') }}</th>
                                <th class="py-2.5" style="width: 16%;">{{ __('hrms.exits.tbl_total_deductions') }}</th>
                                <th class="py-2.5" style="width: 12%;">{{ __('hrms.exits.tbl_net_payout') }}</th>
                                <th class="py-2.5" style="width: 14%;">{{ __('hrms.exits.tbl_status') }}</th>
                                <th class="text-end pe-3 py-2.5" style="min-width: 120px;">{{ __('hrms.exits.tbl_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($exits as $exit)
                                @php
                                    $fnf = $exit->fnfSettlement;
                                    $emp = $exit->employee;
                                @endphp
                                <tr>
                                    <td class="ps-3 py-2.5">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-initials flex-shrink-0">
                                                {{ strtoupper(substr($emp->full_name ?? 'E', 0, 2)) }}
                                            </div>
                                            <div class="text-break">
                                                <a href="{{ route('hrms.employees.show', $emp->id) }}" class="fw-bold text-dark text-decoration-none d-block fs-13 lh-sm mb-1">
                                                    {{ $emp->full_name }}
                                                </a>
                                                <div class="text-dark fw-medium fs-12 lh-sm mb-0.5">
                                                    {{ $emp->designation->name ?? 'N/A' }}
                                                </div>
                                                <div class="text-muted fs-11 lh-sm">
                                                    {{ $emp->employee_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5">
                                        <div class="fw-semibold text-dark fs-12">{{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : 'TBD' }}</div>
                                    </td>
                                    <td class="py-2.5">
                                        <div class="fw-bold text-success fs-13">${{ number_format($fnf->total_earnings ?? 0, 2) }}</div>
                                        <div class="text-muted fs-11 lh-sm mt-0.5 text-break">
                                            @php
                                                $salaryDays = floatval($fnf->unpaid_salary_days ?? 0);
                                                $leaveDays = floatval($fnf->leave_encashment_days ?? 0);
                                            @endphp
                                            <div>{{ __('hrms.exits.salary') }}: {{ $salaryDays == intval($salaryDays) ? intval($salaryDays) : number_format($salaryDays, 1) }} {{ $salaryDays == 1 ? __('hrms.exits.day') : __('hrms.exits.days') }} (${{ number_format($fnf->unpaid_salary_amount ?? 0, 2) }})</div>
                                            @if(($fnf->leave_encashment_amount ?? 0) > 0)
                                                <div>{{ __('hrms.exits.leave') }}: {{ $leaveDays == intval($leaveDays) ? intval($leaveDays) : number_format($leaveDays, 1) }} {{ $leaveDays == 1 ? __('hrms.exits.day') : __('hrms.exits.days') }} (${{ number_format($fnf->leave_encashment_amount ?? 0, 2) }})</div>
                                            @endif
                                            @if(($fnf->gratuity_amount ?? 0) > 0)
                                                <div>{{ __('hrms.exits.gratuity') }}: ${{ number_format($fnf->gratuity_amount, 2) }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-2.5">
                                        <div class="fw-bold text-danger fs-13">${{ number_format($fnf->total_deductions ?? 0, 2) }}</div>
                                        <div class="text-muted fs-11 lh-sm mt-0.5 text-break">
                                            @if(($fnf->asset_damage_recovery ?? 0) > 0)
                                                <div class="text-danger fw-semibold">{{ __('hrms.exits.asset_dues') }}: ${{ number_format($fnf->asset_damage_recovery, 2) }}</div>
                                            @endif
                                            @if(($fnf->unsettled_advances_recovery ?? 0) > 0)
                                                <div>{{ __('hrms.exits.advances') }}: ${{ number_format($fnf->unsettled_advances_recovery, 2) }}</div>
                                            @endif
                                            @if(($fnf->notice_shortfall_recovery ?? 0) > 0)
                                                <div>{{ __('hrms.exits.shortfall') }}: ${{ number_format($fnf->notice_shortfall_recovery, 2) }}</div>
                                            @endif
                                            @if(($fnf->total_deductions ?? 0) == 0)
                                                <span class="text-muted">{{ __('hrms.exits.no_deductions') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-2.5">
                                        <h6 class="fw-bold text-primary mb-0 fs-13">${{ number_format($fnf->net_payable_amount ?? 0, 2) }}</h6>
                                    </td>
                                    <td class="py-2.5">
                                        @php $fnfProgress = $exit->getClearanceProgressPercentage(); @endphp
                                        @if(($fnf->status ?? '') === 'paid')
                                            <x-ui.badge soft variant="success" class="fs-11 text-nowrap">
                                                <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.status_paid') }}
                                            </x-ui.badge>
                                        @elseif($fnfProgress === 100)
                                            <x-ui.badge soft variant="primary" class="fs-11 text-nowrap">
                                                <i class="feather-check me-1"></i> {{ __('hrms.exits.status_ready_settle') }}
                                            </x-ui.badge>
                                        @else
                                            <x-ui.badge soft variant="warning" class="fs-11 text-nowrap" title="{{ __('hrms.exits.status_noc_pending') }}">
                                                <i class="feather-clock me-1"></i> {{ __('hrms.exits.status_noc_pending') }} ({{ $fnfProgress }}%)
                                            </x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3 py-2.5">
                                        <div class="d-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                            <form method="POST" action="{{ route('hrms.exits.fnf.recalculate', $exit->id) }}" class="d-inline m-0">
                                                @csrf
                                                <x-ui.icon-btn variant="transparent-dark" size="sm" icon="feather-refresh-cw" type="submit" title="{{ __('hrms.exits.btn_recalculate') }}" />
                                            </form>
                                            <x-ui.icon-btn variant="transparent-dark" size="sm" icon="feather-printer" href="{{ route('hrms.exits.fnf-statement.view', $exit->id) }}" target="_blank" title="{{ __('hrms.exits.btn_view_statement') }}" />
                                            @if(($fnf->status ?? '') !== 'paid')
                                                <x-ui.button variant="{{ $fnfProgress === 100 ? 'success' : 'outline-primary' }}" size="sm" icon="feather-check" data-bs-toggle="modal" data-bs-target="#finalizeFnfModal_{{ $exit->id }}" class="fw-semibold fs-11 py-1 px-2.5 text-nowrap">
                                                    {{ __('hrms.exits.btn_pay') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <div class="avatar-text avatar-lg bg-soft-info text-info rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-credit-card fs-24"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1 text-dark">{{ __('hrms.exits.empty_fnf_title') }}</h6>
                                        <p class="fs-13 mb-0 text-muted">{{ __('hrms.exits.empty_fnf_desc') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($activeTab === 'documents')
                <!-- TAB 5: EXIT CERTIFICATES & LETTERS (GROUPED BY EMPLOYEE) -->
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle mb-0 text-dark w-100">
                        <thead class="table-light fs-11 text-uppercase tracking-wider">
                            <tr>
                                <th class="ps-3 py-2.5" style="width: 24%;">{{ __('hrms.exits.tbl_exiting_employee') }}</th>
                                <th class="py-2.5 text-nowrap" style="width: 13%;">{{ __('hrms.exits.tbl_lwd') }}</th>
                                <th class="py-2.5 text-nowrap" style="width: 13%;">{{ __('hrms.exits.tbl_status') }}</th>
                                <th class="py-2.5 text-start pe-3" style="width: 50%;">{{ __('hrms.exits.tbl_generated_certificates') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($exits as $exit)
                                @php
                                    $emp = $exit->employee;
                                    $progress = $exit->getClearanceProgressPercentage();
                                    $isSettled = $exit->status === 'settled' || ($exit->fnfSettlement->status ?? '') === 'paid';
                                @endphp
                                <tr>
                                    <td class="ps-3 py-2.5">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-initials flex-shrink-0">
                                                {{ strtoupper(substr($emp->full_name ?? 'E', 0, 2)) }}
                                            </div>
                                            <div class="text-break">
                                                <a href="{{ route('hrms.employees.show', $emp->id) }}" class="fw-bold text-dark text-decoration-none d-block fs-13 lh-sm mb-1">
                                                    {{ $emp->full_name }}
                                                </a>
                                                <div class="text-dark fw-medium fs-12 lh-sm mb-0.5">
                                                    {{ $emp->designation->name ?? 'N/A' }}
                                                </div>
                                                <div class="text-muted fs-11 lh-sm">
                                                    {{ $emp->employee_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5">
                                        <div class="fw-semibold text-dark fs-12">{{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : 'TBD' }}</div>
                                        <span class="text-muted fs-11">{{ ucfirst(str_replace('_', ' ', $exit->separation_type)) }}</span>
                                    </td>
                                    <td class="py-2.5">
                                        @if($isSettled)
                                            <x-ui.badge soft variant="success" class="fs-11 text-nowrap">
                                                <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.settled_issued') }}
                                            </x-ui.badge>
                                        @elseif($progress === 100)
                                            <x-ui.badge soft variant="primary" class="fs-11 text-nowrap">
                                                <i class="feather-check me-1"></i> {{ __('hrms.exits.cleared_100') }}
                                            </x-ui.badge>
                                        @else
                                            <x-ui.badge soft variant="warning" class="fs-11 text-nowrap">
                                                {{ __('hrms.exits.tab_clearance_nocs') }} ({{ $progress }}%)
                                            </x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="py-2.5 pe-3">
                                        <div class="d-flex align-items-center gap-1.5 flex-nowrap overflow-visible">
                                            <!-- 1. Relieving Letter -->
                                            <x-ui.button variant="light" size="sm" icon="feather-file-text" href="{{ route('hrms.exits.relieving-letter.view', $exit->id) }}" target="_blank" class="border text-dark fw-medium fs-12 shadow-2xs text-nowrap py-1 px-2.5">
                                                {{ __('hrms.exits.relieving_letter') }}
                                            </x-ui.button>

                                            <!-- 2. Experience Certificate -->
                                            <x-ui.button variant="light" size="sm" icon="feather-award" href="{{ route('hrms.exits.experience-certificate.view', $exit->id) }}" target="_blank" class="border text-dark fw-medium fs-12 shadow-2xs text-nowrap py-1 px-2.5">
                                                {{ __('hrms.exits.experience_certificate') }}
                                            </x-ui.button>

                                            <!-- 3. NOC Certificate -->
                                            <x-ui.button variant="light" size="sm" icon="feather-shield" href="{{ route('hrms.exits.noc-certificate.view', $exit->id) }}" target="_blank" class="border text-dark fw-medium fs-12 shadow-2xs text-nowrap py-1 px-2.5">
                                                {{ __('hrms.exits.noc_certificate') }}
                                            </x-ui.button>

                                            <!-- 4. FnF Statement -->
                                            <x-ui.button variant="light" size="sm" icon="feather-dollar-sign" href="{{ route('hrms.exits.fnf-statement.view', $exit->id) }}" target="_blank" class="border text-dark fw-medium fs-12 shadow-2xs text-nowrap py-1 px-2.5">
                                                {{ __('hrms.exits.fnf_statement') }}
                                            </x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text fs-24"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1 text-dark">{{ __('hrms.exits.empty_docs_title') }}</h6>
                                        <p class="fs-13 mb-0 text-muted">{{ __('hrms.exits.empty_docs_desc') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            @if($exits->hasPages())
                <div class="mt-2" id="exitPaginationWrapper">
                    <x-ui.pagination :paginator="$exits" />
                </div>
            @endif
        </div>
    </div>

    <!-- ── MODALS SECTION (Appended to body via jQuery) ── -->
    <div id="exitModalsWrapper">
        <!-- 1. Initiate Exit / Resignation Modal -->
        <x-ui.modal 
            id="initiateExitModal" 
            title="<i class='feather-user-minus text-primary me-2'></i>{{ __('hrms.exits.modal_initiate_title') }}" 
            size="lg" 
            formAction="{{ route('hrms.exits.initiate') }}" 
            formMethod="POST"
            :showFooter="true"
        >
            <div class="row g-3">
                <div class="col-md-6">
                    <x-ui.modal-form-ui type="select" name="employee_id" :label="__('hrms.exits.select_employee')" :required="true" :searchable="true">
                        <option value="">{{ __('hrms.exits.choose_employee') }}</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }}) - {{ $emp->designation->name ?? 'Employee' }}</option>
                        @endforeach
                    </x-ui.modal-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.modal-form-ui type="select" name="initiated_by" :label="__('hrms.exits.initiated_by')" :required="true" :searchable="false">
                        <option value="employer" selected>{{ __('hrms.exits.init_employer') }}</option>
                        <option value="employee">{{ __('hrms.exits.init_employee') }}</option>
                    </x-ui.modal-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.modal-form-ui type="select" name="separation_type" :label="__('hrms.exits.separation_type')" :required="true" :searchable="false">
                        <option value="resignation" selected>{{ __('hrms.exits.type_resignation') }}</option>
                        <option value="termination">{{ __('hrms.exits.type_termination') }}</option>
                        <option value="retirement">{{ __('hrms.exits.type_retirement') }}</option>
                        <option value="absconding">{{ __('hrms.exits.type_absconding') }}</option>
                        <option value="death">{{ __('hrms.exits.type_death') }}</option>
                    </x-ui.modal-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.modal-form-ui type="select" name="reason_category" :label="__('hrms.exits.reason_category')" :required="true" :searchable="false">
                        <option value="Career Growth / Better Opportunity" selected>{{ __('hrms.exits.cat_career_growth') }}</option>
                        <option value="Higher Education / Relocation">{{ __('hrms.exits.cat_higher_education') }}</option>
                        <option value="Health / Personal Reasons">{{ __('hrms.exits.cat_health_personal') }}</option>
                        <option value="Compensation & Benefits">{{ __('hrms.exits.cat_compensation') }}</option>
                        <option value="Company Restructuring">{{ __('hrms.exits.cat_restructuring') }}</option>
                        <option value="Performance / Disciplinary">{{ __('hrms.exits.cat_performance') }}</option>
                        <option value="Retirement">{{ __('hrms.exits.cat_retirement') }}</option>
                        <option value="Other">{{ __('hrms.exits.cat_other') }}</option>
                    </x-ui.modal-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.modal-form-ui type="input" inputType="date" name="resignation_date" :label="__('hrms.exits.resignation_date')" :value="date('Y-m-d')" :required="true" />
                </div>
                <div class="col-md-4">
                    <x-ui.modal-form-ui type="input" inputType="number" name="notice_period_days" :label="__('hrms.exits.notice_period_days')" value="30" />
                </div>
                <div class="col-md-4">
                    <x-ui.modal-form-ui type="input" inputType="date" name="preferred_lwd" :label="__('hrms.exits.preferred_lwd')" :value="date('Y-m-d', strtotime('+30 days'))" />
                </div>
                <div class="col-md-12">
                    <x-ui.modal-form-ui type="textarea" name="reason_details" :label="__('hrms.exits.reason_details_note')" rows="2" placeholder="{{ __('hrms.exits.reason_placeholder') }}" />
                </div>
                <div class="col-md-12">
                    <x-ui.modal-form-ui type="textarea" name="feedback_text" :label="__('hrms.exits.exit_interview_feedback')" rows="2" placeholder="{{ __('hrms.exits.feedback_placeholder') }}" />
                </div>
            </div>

            <x-slot:footer>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">{{ __('hrms.exits.btn_cancel') }}</x-ui.button>
                    <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                        <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.btn_submit_initiate') }}
                    </x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.modal>

    <!-- Dynamic Modals for Clearance Sign-offs, LWD Decisions, and FnF Payouts -->
    @foreach($exits as $exit)
        <!-- 2. Approve / Edit LWD Modal -->
        @if($exit->status !== 'settled' && $exit->status !== 'rejected')
            <x-ui.modal 
                id="approveExitModal_{{ $exit->id }}" 
                title="<i class='feather-calendar text-primary me-2'></i>{{ $exit->approved_lwd ? __('hrms.exits.modal_lwd_edit_title') : __('hrms.exits.modal_lwd_approve_title') }} - {{ $exit->employee->full_name }}" 
                size="md" 
                formAction="{{ route('hrms.exits.approve', $exit->id) }}" 
                formMethod="POST"
                :showFooter="true"
            >
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">{{ __('hrms.exits.requested_lwd') }}</span>
                            <strong class="text-dark fs-13">{{ $exit->preferred_lwd ? \Carbon\Carbon::parse($exit->preferred_lwd)->format('d M, Y') : __('hrms.exits.no_pref_specified') }}</strong>
                        </div>
                        <div class="text-end">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">{{ __('hrms.exits.notice_policy') }}</span>
                            <strong class="text-primary fs-13">{{ $exit->notice_period_days ?? 30 }} {{ __('hrms.exits.days') }}</strong>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <x-ui.modal-form-ui type="input" inputType="date" name="approved_lwd" :label="__('hrms.exits.approved_official_lwd')" :value="$exit->approved_lwd ?: ($exit->preferred_lwd ?: date('Y-m-d', strtotime('+30 days')))" :required="true" :helperText="__('hrms.exits.lwd_recalc_help')" />
                </div>
                <div class="mb-3">
                    <x-ui.modal-form-ui type="select" name="notice_action" :label="__('hrms.exits.notice_action')" :required="true" :searchable="false">
                        <option value="serve" @selected(($exit->notice_action ?? 'serve') === 'serve')>{{ __('hrms.exits.action_serve') }}</option>
                        <option value="waive" @selected(($exit->notice_action ?? '') === 'waive')>{{ __('hrms.exits.action_waive') }}</option>
                        <option value="recover" @selected(($exit->notice_action ?? '') === 'recover')>{{ __('hrms.exits.action_recover') }}</option>
                    </x-ui.modal-form-ui>
                </div>
                <div class="mb-3">
                    <x-ui.modal-form-ui type="input" inputType="number" name="notice_shortfall_days" :label="__('hrms.exits.shortfall_days')" value="{{ $exit->notice_shortfall_days ?? 0 }}" />
                </div>

                <x-slot:footer>
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">{{ __('hrms.exits.btn_cancel') }}</x-ui.button>
                        <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                            <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.btn_save_recalculate') }}
                        </x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.modal>
        @endif

        <!-- 2.5 Unified All-in-One Clearance Checklist Sign-off Modal -->
        <x-ui.modal 
            id="allClearanceModal_{{ $exit->id }}" 
            title="<div class='d-flex align-items-center gap-2'><div class='avatar-text avatar-xs bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center'><i class='feather-shield fs-12'></i></div><div><span class='fw-bold text-dark d-block fs-14'>Clearance & NOC Checklist Sign-Off</span><span class='text-muted fs-11 fw-normal'>{{ __('hrms.exits.employee_label') }}: <strong>{{ $exit->employee->full_name }}</strong> ({{ $exit->employee->employee_id }})</span></div></div>" 
            size="xl" 
            formAction="{{ route('hrms.exits.clearances.batch-update-all', $exit->id) }}" 
            formMethod="POST"
            :showFooter="true"
        >
            <!-- Toolbar -->
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom flex-wrap gap-2">
                <span class="text-muted fs-12"><i class="feather-info me-1"></i> Review and sign off verification points across all departments. Flags with penalty amounts auto-sync to FnF settlement.</span>
                <div class="d-flex gap-2">
                    <x-ui.button type="button" variant="outline-success" size="xs" class="fw-semibold px-2 py-1 fs-11" onclick="setClearanceBatchStatus('allClearanceModal_{{ $exit->id }}', 'cleared')">
                        <i class="feather-check-circle me-1"></i> Mark All Cleared
                    </x-ui.button>
                    <x-ui.button type="button" variant="outline-secondary" size="xs" class="fw-semibold px-2 py-1 fs-11" onclick="setClearanceBatchStatus('allClearanceModal_{{ $exit->id }}', 'pending')">
                        <i class="feather-rotate-ccw me-1"></i> Reset to Pending
                    </x-ui.button>
                </div>
            </div>

            <div class="table-responsive border rounded-3 bg-white mb-3">
                <table class="table table-hover align-middle mb-0 text-dark w-100">
                    <thead class="table-light fs-11 text-uppercase tracking-wider">
                        <tr>
                            <th class="ps-3 py-2" style="width: 18%;">Department</th>
                            <th class="py-2" style="width: 32%;">Checklist Point</th>
                            <th class="py-2" style="width: 20%;">Sign-off Status</th>
                            <th class="py-2" style="width: 13%;">Recovery / Penalty (₹)</th>
                            <th class="pe-3 py-2" style="width: 17%;">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="fs-12">
                        @forelse($exit->clearances as $cItem)
                            @php
                                $catMeta = $cItem->getCategoryMeta();
                                $isIssue = $cItem->status === 'issues_found';
                            @endphp
                            <tr>
                                <td class="ps-3 py-2.5">
                                    <x-ui.badge soft variant="{{ $catMeta['color'] }}" class="fs-11 px-2 py-1">
                                        <i class="{{ $catMeta['icon'] }} me-1"></i> {{ $catMeta['name'] }}
                                    </x-ui.badge>
                                </td>
                                <td class="py-2.5">
                                    <strong class="text-dark">{{ $cItem->item_name }}</strong>
                                </td>
                                <td class="py-2.5">
                                    <select name="clearances[{{ $cItem->id }}][status]" class="form-select form-select-sm clearance-status-select" data-item-id="{{ $cItem->id }}" onchange="var p = document.getElementById('unified_penalty_{{ $cItem->id }}'); if (this.value === 'issues_found') { p.style.display = 'block'; } else { p.style.display = 'none'; p.value = '0.00'; }">
                                        <option value="pending" @selected($cItem->status === 'pending')>Pending Sign-off</option>
                                        <option value="cleared" @selected($cItem->status === 'cleared')>Cleared & Handed Over</option>
                                        <option value="issues_found" @selected($cItem->status === 'issues_found')>Issues / Recovery Due</option>
                                        <option value="waived" @selected($cItem->status === 'waived')>Waived by Authority</option>
                                    </select>
                                </td>
                                <td class="py-2.5">
                                    <input type="number" step="0.01" name="clearances[{{ $cItem->id }}][deduction_amount]" id="unified_penalty_{{ $cItem->id }}" class="form-control form-control-sm text-danger fw-bold" placeholder="0.00" value="{{ $cItem->deduction_amount > 0 ? $cItem->deduction_amount : '0.00' }}" style="{{ $isIssue ? '' : 'display: none;' }}">
                                </td>
                                <td class="pe-3 py-2.5">
                                    <input type="text" name="clearances[{{ $cItem->id }}][remarks]" class="form-control form-control-sm" placeholder="Remarks..." value="{{ $cItem->remarks }}">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No clearance items found for this exit.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Ad-Hoc Item Quick Form inside unified modal -->
            <div class="border-top p-3 bg-white mt-3 rounded-bottom">
                <x-ui.button variant="link" size="sm" icon="feather-plus-circle" data-bs-toggle="collapse" href="#adhocCollapseUnified_{{ $exit->id }}" class="text-primary p-0 fw-semibold fs-12 text-decoration-none">
                    Add Custom Checklist Point for {{ $exit->employee->first_name }}
                </x-ui.button>
                <div class="collapse mt-2" id="adhocCollapseUnified_{{ $exit->id }}">
                    <form method="POST" action="{{ route('hrms.exits.clearances.adhoc.store', $exit->id) }}" class="p-3 bg-light rounded-3 border">
                        @csrf
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <x-ui.modal-form-ui type="select" name="clearance_category" label="Department" :searchable="false">
                                    <option value="it_assets">IT & Systems</option>
                                    <option value="facilities_admin">Facilities & Admin</option>
                                    <option value="finance_payroll">Finance & Payroll</option>
                                    <option value="hr_operations" selected>HR & Operations</option>
                                    <option value="reporting_manager">Reporting Manager</option>
                                    <option value="legal_compliance">Legal & Compliance</option>
                                </x-ui.modal-form-ui>
                            </div>
                            <div class="col-md-4">
                                <x-ui.modal-form-ui type="input" name="item_name" :label="__('hrms.exits.adhoc_title')" placeholder="{{ __('hrms.exits.adhoc_placeholder') }}" :required="true" />
                            </div>
                            <div class="col-md-3">
                                <x-ui.modal-form-ui type="input" name="remarks" :label="__('hrms.exits.adhoc_remarks')" placeholder="{{ __('hrms.exits.adhoc_remarks_placeholder') }}" />
                            </div>
                            <div class="col-md-2 text-end mb-3">
                                <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold w-100" icon="feather-plus">
                                    Add Point
                                </x-ui.button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <x-slot:footer>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">{{ __('hrms.exits.btn_cancel') }}</x-ui.button>
                    <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                        <i class="feather-check-circle me-1"></i> Save All Clearances
                    </x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.modal>

        <!-- 3. Clearance Sign-off Modals per Category (Dynamic Batch Form with Quick Actions & Ad-Hoc Points) -->
        @php
            $modalCategories = $exit->clearances->pluck('department')->unique();
            if ($modalCategories->isEmpty()) {
                $modalCategories = collect(['it_assets', 'facilities_admin', 'finance_payroll', 'hr_operations', 'reporting_manager']);
            }
        @endphp
        @foreach($modalCategories as $deptKey)
            @php 
                $deptItems = $exit->clearances->where('department', $deptKey); 
                $meta = \App\Domains\HRMS\Models\ExitClearanceTemplate::getCategoryMetadata($deptKey);
            @endphp
            <x-ui.modal 
                id="clearanceModal_{{ $exit->id }}_{{ $deptKey }}" 
                title="<div class='d-flex align-items-center gap-2'><div class='avatar-text avatar-xs bg-soft-{{ $meta['color'] }} text-{{ $meta['color'] }} rounded-circle d-flex align-items-center justify-content-center'><i class='{{ $meta['icon'] }} fs-12'></i></div><div><span class='fw-bold text-dark d-block fs-14'>{{ __('hrms.exits.clearance_checklist_title', ['department' => $meta['name']]) }}</span><span class='text-muted fs-11 fw-normal'>{{ __('hrms.exits.employee_label') }}: <strong>{{ $exit->employee->full_name }}</strong> ({{ $exit->employee->employee_id }})</span></div></div>" 
                size="lg" 
                formAction="{{ route('hrms.exits.clearances.batch-update', ['exit' => $exit->id, 'department' => $deptKey]) }}" 
                formMethod="POST"
                :showFooter="true"
            >
                <!-- Physical Asset Custody Sub-section for IT & Systems -->
                @if(in_array($deptKey, ['it', 'it_assets']))
                    @if($exit->employee && $exit->employee->assets && $exit->employee->assets->count() > 0)
                        <div class="p-3 bg-white rounded-3 border mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                <span class="fs-12 fw-bold text-dark text-uppercase"><i class="feather-cpu text-primary me-1"></i> Assigned Company Hardware Assets ({{ $exit->employee->assets->count() }})</span>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                @foreach($exit->employee->assets as $ast)
                                    @php
                                        $isReturned = empty($ast->assigned_employee_id) || in_array($ast->status, ['available', 'returned', 'scrapped']);
                                    @endphp
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border border-opacity-50">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="feather-hard-drive text-muted fs-14"></i>
                                            <div>
                                                <div class="fs-12 fw-bold text-dark">{{ $ast->name }}</div>
                                                <div class="fs-11 text-muted">
                                                    <code>{{ $ast->asset_code ?? 'AST-#' . $ast->id }}</code>
                                                    @if($ast->serial_number)
                                                        &bull; SN: <span class="font-monospace">{{ $ast->serial_number }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            @if($isReturned)
                                                <x-ui.badge soft variant="success" class="fs-11"><i class="feather-check me-1"></i> Returned</x-ui.badge>
                                            @else
                                                <x-ui.button type="button" variant="primary" size="sm" icon="feather-corner-down-left" data-bs-toggle="modal" data-bs-target="#assetReturnModal_{{ $exit->id }}_{{ $ast->id }}" class="fw-semibold fs-11 py-1 px-2.5">
                                                    Return Asset
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="alert alert-info border border-info border-opacity-25 rounded-3 py-2 px-3 mb-3 fs-12 text-dark d-flex align-items-center gap-2">
                            <i class="feather-info text-info fs-16"></i>
                            <div>
                                <strong>BYOD / Digital Only:</strong> This employee has 0 physical company assets assigned. Hardware clearance is automatically verified.
                            </div>
                        </div>
                    @endif
                @endif

                <!-- Quick Batch Actions Toolbar -->
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom flex-wrap gap-2">
                    <span class="text-muted fs-12"><i class="feather-info me-1"></i> {{ __('hrms.exits.checklist_help') }}</span>
                    <div class="d-flex gap-2">
                        <x-ui.button type="button" variant="outline-success" size="xs" class="fw-semibold px-2 py-1 fs-11" onclick="setClearanceBatchStatus('clearanceModal_{{ $exit->id }}_{{ $deptKey }}', 'cleared')">
                            <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.btn_mark_all_cleared') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="outline-secondary" size="xs" class="fw-semibold px-2 py-1 fs-11" onclick="setClearanceBatchStatus('clearanceModal_{{ $exit->id }}_{{ $deptKey }}', 'pending')">
                            <i class="feather-rotate-ccw me-1"></i> {{ __('hrms.exits.btn_reset_pending') }}
                        </x-ui.button>
                    </div>
                </div>

                <div class="d-flex flex-column gap-3">
                    @forelse($deptItems as $cItem)
                        @php $isIssue = $cItem->status === 'issues_found'; @endphp
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="feather-check-circle text-muted fs-14"></i>
                                    <span class="fw-bold text-dark fs-13">{{ $cItem->item_name }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <x-ui.badge soft variant="{{ $cItem->status === 'cleared' ? 'success' : ($cItem->status === 'issues_found' ? 'danger' : ($cItem->status === 'waived' ? 'warning' : 'secondary')) }}" class="text-uppercase fw-bold">
                                        {{ strtoupper(str_replace('_', ' ', $cItem->status)) }}
                                    </x-ui.badge>
                                </div>
                            </div>
                            <div class="row g-2 align-items-end mt-1">
                                <div class="col-md-4">
                                    <x-ui.modal-form-ui type="select" name="clearances[{{ $cItem->id }}][status]" :label="__('hrms.exits.clearance_status')" :searchable="false" class="clearance-status-select" data-item-id="{{ $cItem->id }}">
                                        <option value="pending" @selected($cItem->status === 'pending')>{{ __('hrms.exits.status_pending_signoff') }}</option>
                                        <option value="cleared" @selected($cItem->status === 'cleared')>{{ __('hrms.exits.status_clear_handed') }}</option>
                                        <option value="issues_found" @selected($cItem->status === 'issues_found')>{{ __('hrms.exits.status_issues_dues') }}</option>
                                        <option value="waived" @selected($cItem->status === 'waived')>{{ __('hrms.exits.status_waived_manager') }}</option>
                                    </x-ui.modal-form-ui>
                                </div>
                                <div class="col-md-3 penalty-field-col" id="penalty_col_{{ $cItem->id }}" style="{{ $isIssue ? '' : 'display: none;' }}">
                                    <x-ui.modal-form-ui type="input" inputType="number" name="clearances[{{ $cItem->id }}][deduction_amount]" :label="__('hrms.exits.due_penalty')" placeholder="0.00" value="{{ $cItem->deduction_amount > 0 ? $cItem->deduction_amount : '0.00' }}" />
                                </div>
                                <div class="{{ $isIssue ? 'col-md-5' : 'col-md-8' }} remarks-field-col" id="remarks_col_{{ $cItem->id }}">
                                    <x-ui.modal-form-ui type="input" name="clearances[{{ $cItem->id }}][remarks]" :label="__('hrms.exits.signoff_remarks')" placeholder="{{ __('hrms.exits.remarks_placeholder') }}" value="{{ $cItem->remarks }}" />
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="feather-info text-muted fs-20 d-block mb-1"></i>
                            <span class="fs-12">{{ __('hrms.exits.no_default_items') }}</span>
                        </div>
                    @endforelse
                </div>

                <!-- Ad-Hoc Item Quick Form -->
                <div class="border-top p-3 bg-white mt-3 rounded-bottom">
                    <x-ui.button variant="link" size="sm" icon="feather-plus-circle" data-bs-toggle="collapse" href="#adhocCollapse_{{ $exit->id }}_{{ $deptKey }}" class="text-primary p-0 fw-semibold fs-12 text-decoration-none">
                        {{ __('hrms.exits.add_adhoc_item', ['name' => $exit->employee->first_name]) }}
                    </x-ui.button>
                    <div class="collapse mt-2" id="adhocCollapse_{{ $exit->id }}_{{ $deptKey }}">
                        <form method="POST" action="{{ route('hrms.exits.clearances.adhoc.store', $exit->id) }}" class="p-3 bg-light rounded-3 border">
                            @csrf
                            <input type="hidden" name="clearance_category" value="{{ $deptKey }}">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <x-ui.modal-form-ui type="input" name="item_name" :label="__('hrms.exits.adhoc_title')" placeholder="{{ __('hrms.exits.adhoc_placeholder') }}" :required="true" />
                                </div>
                                <div class="col-md-4">
                                    <x-ui.modal-form-ui type="input" name="remarks" :label="__('hrms.exits.adhoc_remarks')" placeholder="{{ __('hrms.exits.adhoc_remarks_placeholder') }}" />
                                </div>
                                <div class="col-md-3 text-end mb-3">
                                    <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold w-100" icon="feather-plus">
                                        {{ __('hrms.exits.btn_add_item') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <x-slot:footer>
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">{{ __('hrms.exits.btn_cancel') }}</x-ui.button>
                        <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                            <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.btn_save_clearances') }}
                        </x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.modal>
        @endforeach

        <!-- 4. Asset Return Modal -->
        @if($exit->employee && $exit->employee->assets)
            @foreach($exit->employee->assets as $asset)
                @if($asset->assigned_employee_id === $exit->employee_id)
                    <x-ui.modal 
                        id="assetReturnModal_{{ $exit->id }}_{{ $asset->id }}" 
                        title="<i class='feather-package text-primary me-2'></i>{{ __('hrms.exits.modal_asset_return_title') }}" 
                        size="md" 
                        formAction="{{ route('hrms.exits.assets.return', ['exit' => $exit->id, 'asset' => $asset->id]) }}" 
                        formMethod="POST"
                        :showFooter="true"
                    >
                        <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="fw-bold text-dark">{{ $asset->name }}</div>
                            <div class="text-muted fs-12">{{ __('hrms.exits.code_label') }}: {{ $asset->asset_code ?? $asset->asset_tag ?? 'TAG-AUTO' }} &bull; {{ __('hrms.exits.serial_number_label') }}: {{ $asset->serial_number ?: 'N/A' }}</div>
                        </div>
                        <div class="mb-3">
                            <x-ui.modal-form-ui type="select" name="condition" :label="__('hrms.exits.physical_condition')" :required="true" :searchable="false" onchange="var p = document.getElementById('asset_deduction_wrap_{{ $exit->id }}_{{ $asset->id }}'); if (this.value === 'damaged' || this.value === 'lost') { p.style.display = 'block'; } else { p.style.display = 'none'; p.querySelector('input').value = '0.00'; }">
                                <option value="good" selected>{{ __('hrms.exits.condition_good') }}</option>
                                <option value="fair">{{ __('hrms.exits.condition_fair') }}</option>
                                <option value="damaged">{{ __('hrms.exits.condition_damaged') }}</option>
                                <option value="lost">{{ __('hrms.exits.condition_lost') }}</option>
                            </x-ui.modal-form-ui>
                        </div>
                        <div class="mb-3" id="asset_deduction_wrap_{{ $exit->id }}_{{ $asset->id }}" style="display: none;">
                            <x-ui.modal-form-ui type="input" inputType="number" name="damage_deduction" :label="__('hrms.exits.damage_deduction_amount')" value="0.00" placeholder="0.00" :helperText="__('hrms.exits.damage_deduction_help')" />
                        </div>
                        <div class="mb-3">
                            <x-ui.modal-form-ui type="textarea" name="remarks" :label="__('hrms.exits.return_inspection_remarks')" rows="2" placeholder="{{ __('hrms.exits.inspection_remarks_placeholder') }}" />
                        </div>

                        <x-slot:footer>
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">{{ __('hrms.exits.btn_cancel') }}</x-ui.button>
                                <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                                    <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.btn_confirm_return') }}
                                </x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.modal>
                @endif
            @endforeach
        @endif

        <!-- 5. Finalize FnF Settlement Modal (Standard Enterprise Clearance Gate) -->
        @if(($exit->fnfSettlement->status ?? '') !== 'paid')
            @php
                $progress = $exit->getClearanceProgressPercentage();
                $isIncomplete = $exit->clearances->where('status', 'pending')->count() > 0;
                $pendingDepts = [];
                foreach(['it' => 'IT & Systems', 'admin' => 'Facilities & Admin', 'finance' => 'Finance & Payroll', 'hr' => 'HR & Operations', 'manager' => 'Reporting Manager'] as $deptKey => $deptLabel) {
                    $pCount = $exit->clearances->where('department', $deptKey)->where('status', 'pending')->count();
                    if ($pCount > 0) {
                        $pendingDepts[] = "$deptLabel ($pCount " . __('hrms.exits.unreviewed_count', ['count' => '']) . ")";
                    }
                }
                $clearanceDues = $exit->fnfSettlement->asset_damage_recovery ?? 0;
            @endphp
            <x-ui.modal 
                id="finalizeFnfModal_{{ $exit->id }}" 
                title="<i class='feather-check-circle text-success me-2'></i>{{ __('hrms.exits.modal_finalize_fnf_title') }} - {{ $exit->employee->full_name }}" 
                size="lg" 
                formAction="{{ route('hrms.exits.fnf.finalize', $exit->id) }}" 
                formMethod="POST"
                :showFooter="true"
            >
                @if($isIncomplete)
                    <div class="alert alert-warning border border-warning border-opacity-25 rounded-3 p-3 mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="feather-alert-triangle text-warning fs-18"></i>
                            <h6 class="fw-bold text-dark mb-0 fs-13">{{ __('hrms.exits.unreviewed_clearances_title', ['progress' => $progress]) }}</h6>
                        </div>
                        <p class="fs-12 text-muted mb-2">
                            {{ __('hrms.exits.unreviewed_clearances_desc') }}
                        </p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach($pendingDepts as $pDept)
                                <x-ui.badge soft variant="warning" class="border border-warning border-opacity-25 fs-11">{{ $pDept }}</x-ui.badge>
                            @endforeach
                        </div>
                        <div class="mb-2">
                            <x-ui.checkbox 
                                name="override_clearances" 
                                id="override_chk_{{ $exit->id }}" 
                                value="1" 
                                :required="true" 
                                :label="__('hrms.exits.override_clearance_label')" 
                            />
                        </div>
                        <div class="mt-2">
                            <x-ui.modal-form-ui type="input" name="override_reason" :label="__('hrms.exits.override_reason')" :required="true" placeholder="{{ __('hrms.exits.override_reason_placeholder') }}" />
                        </div>
                    </div>
                @elseif($clearanceDues > 0)
                    <div class="alert alert-success border border-success border-opacity-25 rounded-3 p-3 mb-3 fs-12 text-dark">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="feather-check-circle text-success fs-18"></i>
                            <h6 class="fw-bold text-dark mb-0 fs-13">{{ __('hrms.exits.all_clearances_signed_title') }}</h6>
                        </div>
                        <p class="fs-12 text-muted mb-0">
                            {{ __('hrms.exits.all_clearances_signed_dues_desc', ['amount' => '$' . number_format($clearanceDues, 2)]) }}
                        </p>
                    </div>
                @else
                    <div class="alert alert-success border border-success border-opacity-25 rounded-3 py-2.5 px-3 mb-3 fs-12 text-dark">
                        <i class="feather-check-circle text-success me-1"></i> {{ __('hrms.exits.all_clearances_ready_desc') }}
                    </div>
                @endif

                <!-- Settlement Financial Breakdown Summary -->
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="row g-2 text-center text-md-start align-items-center">
                        <div class="col-md-4 border-end">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">{{ __('hrms.exits.gross_earnings') }}</span>
                            <h6 class="fw-bold text-success mb-0 fs-14">+${{ number_format($exit->fnfSettlement->total_earnings ?? 0, 2) }}</h6>
                            <span class="text-muted fs-11">{{ __('hrms.exits.salary_and_encashment') }}</span>
                        </div>
                        <div class="col-md-4 border-end">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">{{ __('hrms.exits.total_deductions') }}</span>
                            <h6 class="fw-bold text-danger mb-0 fs-14">-${{ number_format($exit->fnfSettlement->total_deductions ?? 0, 2) }}</h6>
                            @if(($exit->fnfSettlement->asset_damage_recovery ?? 0) > 0)
                                <span class="text-danger fs-11 fw-bold"><i class="feather-alert-triangle me-0.5"></i> {{ __('hrms.exits.incl_clearance_dues', ['amount' => '$' . number_format($exit->fnfSettlement->asset_damage_recovery, 2)]) }}</span>
                            @else
                                <span class="text-muted fs-11">{{ __('hrms.exits.advances_penalties') }}</span>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">{{ __('hrms.exits.net_payable_amount') }}</span>
                            <h5 class="fw-bold text-primary mb-0 fs-16">${{ number_format($exit->fnfSettlement->net_payable_amount ?? 0, 2) }}</h5>
                            <span class="text-muted fs-11">{{ __('hrms.exits.final_payout') }}</span>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.modal-form-ui type="select" name="settlement_channel" :label="__('hrms.exits.payout_channel')" :searchable="false">
                            <option value="monthly_payroll">{{ __('hrms.exits.channel_monthly_payroll') }}</option>
                            <option value="off_cycle">{{ __('hrms.exits.channel_off_cycle') }}</option>
                        </x-ui.modal-form-ui>
                    </div>
                    <div class="col-md-6">
                        <x-ui.modal-form-ui type="select" name="payment_method" :label="__('hrms.exits.payment_mode')" :searchable="false">
                            <option value="Bank Transfer">{{ __('hrms.exits.mode_bank_transfer') }}</option>
                            <option value="Cheque">{{ __('hrms.exits.mode_cheque') }}</option>
                            <option value="UPI / Online">{{ __('hrms.exits.mode_upi') }}</option>
                        </x-ui.modal-form-ui>
                    </div>
                    <div class="col-md-12">
                        <x-ui.modal-form-ui type="input" name="payment_reference" :label="__('hrms.exits.payment_reference')" placeholder="{{ __('hrms.exits.reference_placeholder') }}" />
                    </div>
                    <div class="col-md-12">
                        <x-ui.modal-form-ui type="textarea" name="notes" :label="__('hrms.exits.settlement_notes')" rows="2" placeholder="{{ __('hrms.exits.notes_placeholder') }}" />
                    </div>
                </div>

                <x-slot:footer>
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">{{ __('hrms.exits.btn_cancel') }}</x-ui.button>
                        <x-ui.button variant="success" type="submit" class="px-4 fw-bold">
                            <i class="feather-check-circle me-1"></i> {{ __('hrms.exits.btn_disburse_complete') }}
                        </x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.modal>
        @endif
    @endforeach
    </div>
@endsection

@push('scripts')
<script>
    function togglePenaltyField($select) {
        var itemId = $select.data('item-id');
        if (!itemId) {
            var nameAttr = $select.attr('name') || '';
            var match = nameAttr.match(/\[(\d+)\]/);
            if (match) itemId = match[1];
        }
        if (!itemId) return;

        var status = $select.val();
        var $penaltyCol = $('#penalty_col_' + itemId);
        var $remarksCol = $('#remarks_col_' + itemId);

        if (status === 'issues_found') {
            $penaltyCol.stop(true, true).fadeIn(150);
            $remarksCol.removeClass('col-md-8').addClass('col-md-5');
        } else {
            $penaltyCol.stop(true, true).hide();
            $penaltyCol.find('input').val('0.00');
            $remarksCol.removeClass('col-md-5').addClass('col-md-8');
        }
    }

    $(document).on('change', '.clearance-status-select, select[name^="clearances"]', function() {
        togglePenaltyField($(this));
    });

    function setClearanceBatchStatus(modalId, statusVal) {
        var $modal = $('#' + modalId);
        $modal.find('select[name^="clearances"]').each(function() {
            $(this).val(statusVal);
            $(this).trigger('change');
            if ($(this).hasClass('select2-hidden-accessible') || $(this).data('select2')) {
                $(this).trigger('change.select2');
            }
            togglePenaltyField($(this));
        });
    }

    function moveExitModalsToBody() {
        $('#exitModalsWrapper .modal, .modal[id^="finalizeFnfModal_"], .modal[id^="approveExitModal_"], .modal[id^="clearanceModal_"], .modal[id^="addClearanceModal_"]').each(function() {
            if ($(this).parent().get(0) !== document.body) {
                $(this).appendTo(document.body);
            }
        });
    }

    $(document).ready(function() {
        moveExitModalsToBody();
    });

    $(document).on('show.bs.modal', '.modal', function () {
        if ($(this).parent().get(0) !== document.body) {
            $(this).appendTo(document.body);
        }
    });

    let exitSearchTimeout = null;
    let activeExitRequest = null;

    function refreshExitList(targetUrl) {
        if (activeExitRequest) {
            activeExitRequest.abort();
        }
        const controller = new AbortController();
        activeExitRequest = controller;

        const contentContainer = document.getElementById('exitTabContentContainer');
        if (contentContainer) {
            contentContainer.style.opacity = '0.5';
        }

        fetch(targetUrl.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to refresh list.');
            }
            return response.text();
        })
        .then(function (html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');

            const newContent = doc.getElementById('exitTabContentContainer');
            const oldContent = document.getElementById('exitTabContentContainer');
            if (newContent && oldContent) {
                oldContent.innerHTML = newContent.innerHTML;
            }

            const newTabs = doc.getElementById('exitTabsWrapper');
            const oldTabs = document.getElementById('exitTabsWrapper');
            if (newTabs && oldTabs) {
                oldTabs.innerHTML = newTabs.innerHTML;
            }

            const newToolbar = doc.getElementById('exitToolbarWrapper');
            const oldToolbar = document.getElementById('exitToolbarWrapper');
            if (newToolbar && oldToolbar) {
                oldToolbar.innerHTML = newToolbar.innerHTML;
            }

            const newPagination = doc.getElementById('exitPaginationWrapper');
            const oldPagination = document.getElementById('exitPaginationWrapper');
            if (newPagination && oldPagination) {
                oldPagination.innerHTML = newPagination.innerHTML;
            }

            const newModals = doc.getElementById('exitModalsWrapper');
            const oldModals = document.getElementById('exitModalsWrapper');
            if (newModals && oldModals) {
                oldModals.innerHTML = newModals.innerHTML;
                moveExitModalsToBody();
            }

            // Sync hidden search inputs in filter dropdowns
            const searchVal = targetUrl.searchParams.get('search') || '';
            $('input[name="search"]').not('#exit_search_input').val(searchVal);

            // Update browser URL without full reload
            history.pushState(null, '', targetUrl.toString());
        })
        .catch(function (error) {
            if (error.name !== 'AbortError') {
                window.location.href = targetUrl.toString();
            }
        })
        .finally(function () {
            if (activeExitRequest === controller) {
                if (contentContainer) {
                    contentContainer.style.opacity = '1';
                }
                activeExitRequest = null;
            }
        });
    }

    // 1. Debounced live search on typing (works automatically as you write without clicking Enter)
    $(document).on('input', '#exit_search_input', function () {
        const form = this.closest('form');
        if (!form) return;
        const url = new URL(form.action || window.location.href);
        
        const formData = new FormData(form);
        for (const [key, val] of formData.entries()) {
            url.searchParams.set(key, val);
        }
        url.searchParams.delete('page');

        clearTimeout(exitSearchTimeout);
        exitSearchTimeout = setTimeout(function () {
            refreshExitList(url);
        }, 250);
    });

    // 2. Prevent page reload on form submit
    $(document).on('submit', '#exitSearchForm', function (e) {
        e.preventDefault();
        const url = new URL(this.action || window.location.href);
        const formData = new FormData(this);
        for (const [key, val] of formData.entries()) {
            url.searchParams.set(key, val);
        }
        url.searchParams.delete('page');
        clearTimeout(exitSearchTimeout);
        refreshExitList(url);
    });

    // 3. Intercept pagination links for instant seamless changes
    $(document).on('click', '#exitPaginationWrapper a.page-link', function (e) {
        e.preventDefault();
        const href = this.getAttribute('href');
        if (href && href !== '#' && !href.startsWith('javascript:')) {
            const url = new URL(href, window.location.origin);
            refreshExitList(url);
        }
    });
</script>
@endpush
