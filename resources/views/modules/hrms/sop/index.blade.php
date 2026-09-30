@extends('layouts.duralux')

@section('title', 'Standard Operating Procedures (SOP) | HRMS')
@section('page-title', 'Standard Operating Procedures (SOP)')
@section('breadcrumb', 'HRMS / Operations / SOP Management')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .avatar-initials {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: rgba(var(--bs-primary-rgb), 0.1);
            color: var(--bs-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }

        .step-badge {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: var(--bs-primary);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 11px;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        @if($isHrOrAdmin)
            <x-ui.button variant="light" icon="feather-folder-plus" data-bs-toggle="modal" data-bs-target="#createCategoryModal" class="border fw-semibold">
                New Category
            </x-ui.button>
            <x-ui.button variant="primary" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#createSopModal" class="fw-bold">
                Create New SOP
            </x-ui.button>
        @endif
    </div>
@endsection

@section('content')
<div class="container-fluid p-0">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        @php
            $sopTabs = [];
            if ($isHrOrAdmin) {
                $sopTabs[] = [
                    'id' => 'tab-directory',
                    'label' => 'SOP Directory',
                    'active' => ($activeTab === 'directory'),
                    'icon' => 'feather-folder'
                ];
            }
            $sopTabs[] = [
                'id' => 'tab-mysops',
                'label' => 'My SOPs',
                'active' => ($activeTab === 'my_sops'),
                'icon' => 'feather-user-check',
                'badge' => $myPendingAssignments->count(),
                'badgeClass' => 'bg-danger text-white'
            ];
            if ($isHrOrAdmin) {
                $sopTabs[] = [
                    'id' => 'tab-categories',
                    'label' => 'Categories Master',
                    'active' => ($activeTab === 'categories'),
                    'icon' => 'feather-tag'
                ];
            }
        @endphp

        <!-- Navigation Tabs Bar with Common UI Component -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 border-bottom pb-2">
            <div class="flex-grow-1" style="min-width: 0;">
                <x-ui.horizontal-tabs id="sopTabs" :tabs="$sopTabs" />
            </div>
        </div>

        <!-- TAB CONTENT CONTAINER -->
        <div class="tab-content pt-2" id="sopTabContent">

            <!-- TAB 1: SOP DIRECTORY (ADMIN / HR) -->
            @if($isHrOrAdmin)
                <div class="tab-pane fade {{ $activeTab === 'directory' ? 'show active' : '' }}" id="tab-directory" role="tabpanel">
                    
                    <!-- TOP TOOLBAR: TITLE + SEARCH, SORT, FILTER (RIGHT CORNER) -->
                    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-0"><i class="feather-book-open me-2 text-primary"></i> Standard Operating Procedures</h5>
                            <p class="text-muted fs-12 mb-0">Browse and manage company operational standards and compliance</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 ms-auto">
                            <!-- Live Instant Search Input (No enter click needed, seamless typing) -->
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="search" id="sop_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search code, title, objective..." value="{{ request('search') }}" style="box-shadow: none; height: 32px;" autocomplete="off">
                            </div>

                            <!-- Sort Dropdown -->
                            <x-ui.sort-dropdown label="Sort">
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setSopSort('newest', this)">
                                    <span>Newest First</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'oldest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setSopSort('oldest', this)">
                                    <span>Oldest First</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'title_asc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setSopSort('title_asc', this)">
                                    <span>Title (A-Z)</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'title_desc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setSopSort('title_desc', this)">
                                    <span>Title (Z-A)</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') === 'code_asc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setSopSort('code_asc', this)">
                                    <span>Code (A-Z)</span>
                                </a>
                            </x-ui.sort-dropdown>

                            <!-- Filter Dropdown -->
                            <x-ui.filter label="Filter" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                <form method="GET" action="javascript:void(0);" id="sopFilterForm">
                                    <input type="hidden" name="sort" id="sop_sort_input" value="{{ request('sort', 'newest') }}">

                                    <div class="mb-3" style="min-width: 250px;">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category</label>
                                        <x-ui.odoo-form-ui type="select" name="category_id" id="filter_sop_category_id">
                                            <option value="">All Categories</option>
                                            @foreach($categories as $cat)
                                                <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </div>

                                    <div class="mb-3" style="min-width: 250px;">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Department</label>
                                        <x-ui.odoo-form-ui type="select" name="department_id" id="filter_sop_department_id">
                                            <option value="">All Departments</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </div>

                                    <div class="mb-3" style="min-width: 250px;">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                        <x-ui.odoo-form-ui type="select" name="status" id="filter_sop_status">
                                            <option value="">All Statuses</option>
                                            <option value="published" {{ request('status') == 'published' ? 'selected' : '' }}>Published & Active</option>
                                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                            <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                                        </x-ui.odoo-form-ui>
                                    </div>

                                    <div class="dropdown-divider my-3"></div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                        <button type="button" id="btnResetSopFilter" class="btn btn-light btn-sm border flex-grow-1 text-center">Reset</button>
                                    </div>
                                </form>
                            </x-ui.filter>
                        </div>
                    </div>

                    <!-- DIRECTORY TABLE -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="sopDirectoryTable" style="width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="min-width: 260px;">SOP Details</th>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="min-width: 170px;">Category & Department</th>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="min-width: 130px;">Version / Level</th>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="min-width: 110px;">Status</th>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="min-width: 140px;">Compliance</th>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="min-width: 110px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sops as $sop)
                                    @php
                                        $totalAssigned = $sop->assignments_count ?? 0;
                                        if ($totalAssigned === 0 && isset($employees) && $employees->count() > 0) {
                                            if ($sop->target_audience_type === 'department' && $sop->department_id) {
                                                $totalAssigned = $employees->where('department_id', $sop->department_id)->count();
                                            } else {
                                                $totalAssigned = $employees->count();
                                            }
                                        }
                                        $acknowledged = $sop->acknowledged_count ?? 0;
                                        $rate = $totalAssigned > 0 ? round(($acknowledged / $totalAssigned) * 100) : 0;
                                    @endphp
                                    <tr class="sop-row">
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                <x-ui.badge variant="light" class="border font-monospace fw-bold fs-11">{{ $sop->code }}</x-ui.badge>
                                                <a href="{{ route('hrms.sop.show', $sop->id) }}" class="fw-bold text-primary text-decoration-none fs-13">
                                                    {{ $sop->title }}
                                                </a>
                                            </div>
                                            @if($sop->summary || $sop->objective)
                                                <div class="text-muted fs-12 text-truncate" style="max-width: 380px;">
                                                    {{ $sop->summary ?: Str::limit($sop->objective, 75) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="mb-1">
                                                @if($sop->category)
                                                    <x-ui.badge variant="primary" soft class="fs-11">
                                                        <i class="{{ $sop->category->icon ?? 'feather-tag' }} me-1"></i>{{ $sop->category->name }}
                                                    </x-ui.badge>
                                                @else
                                                    <span class="text-muted fs-11">Uncategorized</span>
                                                @endif
                                            </div>
                                            <div class="text-muted fs-11">
                                                <i class="feather-briefcase me-1 text-muted"></i>{{ $sop->department->name ?? 'All Departments' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <x-ui.badge variant="secondary" soft class="fs-11">v{{ $sop->version }}</x-ui.badge>
                                                @if($sop->criticality === 'critical')
                                                    <x-ui.badge variant="danger" class="fs-11">Critical</x-ui.badge>
                                                @elseif($sop->criticality === 'high')
                                                    <x-ui.badge variant="warning" class="fs-11">High</x-ui.badge>
                                                @elseif($sop->criticality === 'medium')
                                                    <x-ui.badge variant="info" soft class="fs-11">Medium</x-ui.badge>
                                                @else
                                                    <x-ui.badge variant="light" class="border fs-11">Low</x-ui.badge>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @if($sop->status === 'published')
                                                <x-ui.badge variant="success" soft class="fs-11"><i class="feather-check-circle me-1"></i>Active</x-ui.badge>
                                            @elseif($sop->status === 'archived')
                                                <x-ui.badge variant="light" class="border fs-11">Archived</x-ui.badge>
                                            @else
                                                <x-ui.badge variant="secondary" soft class="fs-11">Draft</x-ui.badge>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <div class="flex-grow-1" style="min-width: 60px;">
                                                    <x-ui.progress-bar :value="$rate" :max="100" :color="$totalAssigned > 0 ? 'auto' : 'secondary'" height="6px" />
                                                </div>
                                                <span class="fw-bold fs-11 text-muted">{{ $rate }}%</span>
                                            </div>
                                            <div class="text-muted fs-11">{{ $acknowledged }}/{{ $totalAssigned }} staff</div>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-flex align-items-center justify-content-end gap-1">
                                                <x-ui.icon-btn href="{{ route('hrms.sop.show', $sop->id) }}" icon="feather-eye" variant="soft-primary" size="sm" title="View & Read SOP" />

                                                @if($sop->status === 'draft')
                                                    <x-ui.icon-btn type="button" icon="feather-check-circle" variant="success" size="sm" title="Publish SOP" data-bs-toggle="modal" data-bs-target="#publishSopModal" data-sop-id="{{ $sop->id }}" data-sop-code="{{ $sop->code }}" data-sop-title="{{ $sop->title }}" data-sop-audience="{{ $sop->target_audience_type === 'all' ? 'All Organization Staff' : ($sop->target_audience_type === 'department' ? 'Department Only' : 'Designated Staff') }}" data-sop-days="{{ $sop->acknowledgment_days_limit ?? 7 }}" class="btn-publish-trigger" />
                                                @endif

                                                <form method="POST" action="{{ route('hrms.sop.destroy', $sop->id) }}" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this SOP?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.icon-btn type="submit" icon="feather-trash-2" variant="danger" size="sm" title="Delete SOP" />
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="emptyTableRow">
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-book-open fs-36 d-block mb-2 text-muted opacity-50"></i>
                                            <p class="mb-0 fw-medium">No Standard Operating Procedures found.</p>
                                        </td>
                                    </tr>
                                @endforelse
                                <tr id="noSearchMatchRow" class="d-none">
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="feather-search fs-24 d-block mb-1 opacity-50"></i>
                                        No matching SOPs found for your search query.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="sop_pagination_container" class="mt-4">
                        @if($sops->hasPages())
                            <x-ui.pagination :currentPage="$sops->currentPage()" :totalPages="$sops->lastPage()" :totalResults="$sops->total()" :perPage="$sops->perPage()" pageParam="sop_page" />
                        @endif
                    </div>
                </div>
            @endif

            <!-- TAB 2: MY SOPS (EMPLOYEE WORKSPACE) -->
            <div class="tab-pane fade {{ ($activeTab === 'my_sops' || (!$isHrOrAdmin && $activeTab === 'directory')) ? 'show active' : '' }}" id="tab-mysops" role="tabpanel">
                <div class="row g-4">
                    <!-- PENDING ACTION REQUIRED -->
                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <span class="p-1 rounded bg-danger-subtle text-danger"><i class="feather-alert-circle"></i></span>
                                Mandatory Action Required
                            </h6>
                            <x-ui.badge variant="danger" soft>{{ $myPendingAssignments->count() }} Pending</x-ui.badge>
                        </div>

                        @if($myPendingAssignments->count() > 0)
                            <div class="row g-3">
                                @foreach($myPendingAssignments as $assignment)
                                    @php
                                        $sop = $assignment->document;
                                        $isOverdue = $assignment->due_date && $assignment->due_date->isPast();
                                    @endphp
                                    <div class="col-md-6 col-xl-4">
                                        <x-ui.card class="h-100 border shadow-sm rounded-3 overflow-hidden position-relative" style="border-left: 4px solid var({{ $isOverdue ? '--bs-danger' : '--bs-warning' }}) !important;">
                                            <div class="d-flex flex-column justify-content-between h-100">
                                                <div>
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <x-ui.badge variant="light" class="border font-monospace">{{ $sop->code }}</x-ui.badge>
                                                        @if($isOverdue)
                                                            <x-ui.badge variant="danger" soft><i class="feather-clock me-1"></i>Overdue</x-ui.badge>
                                                        @else
                                                            <x-ui.badge variant="warning" soft><i class="feather-clock me-1"></i>Due {{ $assignment->due_date ? $assignment->due_date->format('M d') : 'Soon' }}</x-ui.badge>
                                                        @endif
                                                    </div>
                                                    <h6 class="fw-bold text-dark mb-1">{{ $sop->title }}</h6>
                                                    <p class="text-muted fs-12 mb-3 text-truncate-2">
                                                        {{ $sop->summary ?: Str::limit($sop->objective, 90) }}
                                                    </p>
                                                </div>
                                                <div class="pt-2 border-top d-flex align-items-center justify-content-between mt-auto">
                                                    <small class="text-muted fs-11">
                                                        {{ $sop->sections->count() }} Steps &bull; v{{ $assignment->version_assigned }}
                                                    </small>
                                                    <x-ui.button variant="primary" size="sm" icon="feather-book-open" href="{{ route('hrms.sop.show', $sop->id) }}" class="fw-bold">
                                                        Review & Sign
                                                    </x-ui.button>
                                                </div>
                                            </div>
                                        </x-ui.card>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="bg-light p-4 rounded-3 text-center text-muted">
                                <i class="feather-check-circle fs-32 text-success d-block mb-2"></i>
                                <p class="mb-0 fw-medium text-dark">You are 100% up to date!</p>
                                <small>No pending SOP sign-offs or actions required at this time.</small>
                            </div>
                        @endif
                    </div>

                    <!-- COMPLETED / ACKNOWLEDGED SOPS -->
                    <div class="col-12 mt-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <span class="p-1 rounded bg-success-subtle text-success"><i class="feather-check-circle"></i></span>
                                Acknowledged & Completed SOPs
                            </h6>
                            <x-ui.badge variant="success" soft>{{ $myCompletedAssignments->count() }} Completed</x-ui.badge>
                        </div>

                        @if($myCompletedAssignments->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 120px;">Code</th>
                                            <th>SOP Title</th>
                                            <th style="width: 150px;">Category</th>
                                            <th style="width: 90px;">Version</th>
                                            <th style="width: 160px;">Signed On</th>
                                            <th style="width: 110px;" class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($myCompletedAssignments as $assignment)
                                            @php $sop = $assignment->document; @endphp
                                            <tr>
                                                <td>
                                                    <x-ui.badge variant="light" class="border font-monospace">{{ $sop->code }}</x-ui.badge>
                                                </td>
                                                <td>
                                                    <a href="{{ route('hrms.sop.show', $sop->id) }}" class="fw-bold text-dark text-decoration-none">
                                                        {{ $sop->title }}
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="text-muted fs-12">{{ $sop->category->name ?? 'General' }}</span>
                                                </td>
                                                <td>
                                                    <x-ui.badge variant="light" class="border">v{{ $assignment->version_assigned }}</x-ui.badge>
                                                </td>
                                                <td>
                                                    <span class="text-success fw-medium fs-12">
                                                        <i class="feather-check-circle me-1"></i>{{ $assignment->acknowledged_at ? $assignment->acknowledged_at->format('M d, Y H:i') : 'Signed' }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <x-ui.icon-btn variant="soft-primary" size="sm" icon="feather-eye" href="{{ route('hrms.sop.show', $sop->id) }}" title="Reference SOP" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted fs-13">No completed SOP acknowledgments yet.</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- TAB 3: CATEGORIES MASTER (ADMIN / HR) -->
            @if($isHrOrAdmin)
                <div class="tab-pane fade {{ $activeTab === 'categories' ? 'show active' : '' }}" id="tab-categories" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h5 class="fw-bold text-dark mb-0"><i class="feather-tag me-2 text-primary"></i> SOP Categories & Domains</h5>
                            <p class="text-muted fs-12 mb-0">Functional classifications for company standard operating procedures</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        @forelse($categories as $cat)
                            <div class="col-md-4 col-sm-6">
                                <x-ui.card class="h-100 position-relative" style="border-left: 4px solid var(--bs-primary) !important;">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <x-ui.badge variant="primary" soft>
                                            <i class="{{ $cat->icon ?? 'feather-tag' }} me-1"></i>{{ $cat->code ?? 'CAT' }}
                                        </x-ui.badge>
                                        <form method="POST" action="{{ route('hrms.sop.category.destroy', $cat->id) }}" class="d-inline m-0" onsubmit="return confirm('Delete this category?')">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="submit" icon="feather-trash-2" variant="danger" size="sm" title="Delete Category" />
                                        </form>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">{{ $cat->name }}</h6>
                                    <p class="text-muted fs-12 mb-2">{{ $cat->description ?: 'No description provided.' }}</p>
                                    <small class="text-muted fs-11">
                                        <i class="feather-file-text me-1"></i>{{ $cat->documents()->count() }} SOPs linked
                                    </small>
                                </x-ui.card>
                            </div>
                        @empty
                            <div class="col-12 text-center py-5 text-muted">
                                <i class="feather-tag fs-36 d-block mb-2 text-muted opacity-50"></i>
                                <p class="mb-0 fw-medium">No categories defined yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif

        </div>

    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: CREATE NEW SOP DOCUMENT USING X-UI.MODAL & X-UI.ODOO-FORM-UI -->
<!-- ========================================================================= -->
<x-ui.modal id="createSopModal" title="<i class='feather-file-plus text-primary me-2'></i> Create Standard Operating Procedure (SOP)" size="xl" :showFooter="false" :centered="true" :scrollable="true">
    <form method="POST" action="{{ route('hrms.sop.store') }}" enctype="multipart/form-data" id="createSopForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <!-- SECTION 1: HEADER & CLASSIFICATION -->
            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                <span class="step-badge">1</span>
                <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase">General Information & Scope</h6>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="SOP Code" name="code" placeholder="e.g. SOP-IT-001 (Auto if empty)" />
                    <x-ui.odoo-form-ui type="input" label="SOP Title" name="title" placeholder="e.g. Production Server Deployment Guidelines" :required="true" />
                    <x-ui.odoo-form-ui type="select" label="Category" name="sop_category_id">
                        <option value="">Choose category...</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="select" label="Department" name="department_id">
                        <option value="">Organization-Wide (All)</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Criticality" name="criticality">
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="critical">Critical / Regulatory</option>
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="select" label="Initial Status" name="status">
                        <option value="published" selected>Published & Active (Dispatch to Staff Immediately)</option>
                        <option value="draft">Save as Draft</option>
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Review Cycle" name="review_interval_months" value="12" helperText="Review frequency in months" />
                    <x-ui.odoo-form-ui type="file" label="Attachment PDF" name="attachment" helperText="Attach policy PDF or checklist" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Summary" name="summary" placeholder="Brief 1-2 sentence overview of this standard operating procedure..." rows="2" />
                </div>
            </div>

            <!-- SECTION 2: PURPOSE, OBJECTIVE & PREREQUISITES -->
            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                <span class="step-badge">2</span>
                <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase">Purpose, Scope & Prerequisites</h6>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="textarea" label="Objective" name="objective" placeholder="Why this SOP exists and what standard it establishes..." rows="3" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="textarea" label="Scope" name="scope" placeholder="Who must follow this procedure and when it applies..." rows="3" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Prerequisites" name="prerequisites" placeholder="Access permissions, PPE, software tools, or approvals required..." rows="2" />
                </div>
            </div>

            <!-- SECTION 3: TARGET AUDIENCE & COMPLIANCE SETTINGS -->
            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                <span class="step-badge">3</span>
                <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase">Target Audience & Compliance Settings</h6>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Audience Scope" name="target_audience_type" id="targetAudienceSelect">
                        <option value="all">Entire Company (All Employees)</option>
                        <option value="department">Specific Department(s)</option>
                        <option value="designation">Specific Designation(s)</option>
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Sign-off SLA" name="acknowledgment_days_limit" value="7" helperText="Days limit for employee sign-off" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="checkbox" label="Mandatory Sign-off" name="is_mandatory" value="1" checked>
                        Requires mandatory employee acknowledgment
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="checkbox" label="Auto-Assign" name="auto_assign_new_hires" value="1" checked>
                        Automatically assign to new employees upon onboarding
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- SECTION 4: STEP-BY-STEP PROCEDURE BUILDER -->
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="step-badge">4</span>
                    <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase">Step-by-Step Procedure & Checklist Items</h6>
                </div>
                <x-ui.button type="button" variant="outline-primary" size="sm" icon="feather-plus" id="addStepBtn">
                    Add Step
                </x-ui.button>
            </div>

            <div id="stepsContainer">
                <!-- Step 1 (Default) -->
                <div class="step-card bg-light p-3 rounded-3 mb-3 border">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-bold fs-13 text-dark d-flex align-items-center gap-2">
                            <span class="step-badge">1</span> Step 1: Procedure Title & Instructions
                        </span>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Step Title" name="sections[0][title]" placeholder="e.g. Pre-Deployment Verification" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Step Content" name="sections[0][content]" :rows="3" placeholder="Detailed step instructions, commands, or protocol..." :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Checklist Items" name="sections[0][checklist_items]" :rows="2" placeholder="One item per line (e.g. Confirm backup is taken)" />
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.odoo-form-ui>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top mt-3">
            <x-ui.button type="button" variant="light" size="sm" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-check" class="fw-bold">
                Save Standard Operating Procedure
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL 2: CREATE CATEGORY MASTER USING X-UI.MODAL & X-UI.ODOO-FORM-UI -->
<!-- ========================================================================= -->
<x-ui.modal id="createCategoryModal" title="<i class='feather-tag text-primary me-2'></i> Create SOP Category" size="md" :showFooter="false" :centered="true">
    <form method="POST" action="{{ route('hrms.sop.category.store') }}">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <div class="mb-3">
                <x-ui.odoo-form-ui type="input" label="Category Name" name="name" placeholder="e.g. IT & Security, Quality Assurance" :required="true" />
            </div>
            <div class="mb-3">
                <x-ui.odoo-form-ui type="input" label="Category Code" name="code" placeholder="e.g. IT, QA, SAFETY" />
            </div>
            <div class="mb-3">
                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" :rows="2" placeholder="Description of procedures under this category..." />
            </div>
        </x-ui.odoo-form-ui>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top mt-2">
            <x-ui.button type="button" variant="light" size="sm" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-check" class="fw-bold">
                Save Category
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL 3: CONFIRM PUBLISH SOP MODAL -->
<!-- ========================================================================= -->
<x-ui.modal id="publishSopModal" title="<i class='feather-check-circle text-success me-2'></i> Publish Standard Operating Procedure" size="md" :showFooter="false" :centered="true">
    <form id="publishSopForm" method="POST" action="">
        @csrf
        <div class="text-center py-2 px-1">
            <div class="d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle mb-3" style="width: 56px; height: 56px;">
                <i class="feather-check-circle fs-24"></i>
            </div>
            
            <h5 class="fw-bold text-dark mb-1">Publish & Activate Procedure?</h5>
            <p class="text-muted fs-12 mb-3">You are about to officially publish this SOP document and begin compliance tracking.</p>

            <div class="p-3 bg-light rounded-3 border text-start mb-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span id="modalSopCode" class="badge bg-secondary font-monospace">SOP-001</span>
                    <span id="modalSopTitle" class="fw-bold text-dark fs-13 text-truncate">Procedure Title</span>
                </div>
                <div class="d-flex flex-column gap-1.5 fs-12 text-secondary">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-users text-primary fs-13"></i>
                        <span>Target Audience: <strong id="modalSopAudience" class="text-dark">All Staff</strong></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-clock text-warning fs-13"></i>
                        <span>Acknowledgment Limit: <strong id="modalSopDays" class="text-dark">7 days</strong></span>
                    </div>
                </div>
            </div>

            <div class="alert alert-info text-start d-flex align-items-start gap-2 py-2 px-3 fs-12 mb-0">
                <i class="feather-info text-info fs-14 mt-0.5 flex-shrink-0"></i>
                <div>All targeted employees will automatically receive system notifications to review and digitally sign off.</div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top mt-3">
            <x-ui.button type="button" variant="light" size="sm" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="success" size="sm" icon="feather-check-circle" class="fw-bold">
                Publish & Dispatch Now
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>

@push('scripts')
<script>
    // AJAX Loader for live search, sort, filter, and pagination (same standard as other HRMS modules)
    function loadSopDirectory(page = 1) {
        var search = $('#sop_search').val() || '';
        var categoryId = $('#filter_sop_category_id').val() || '';
        var departmentId = $('#filter_sop_department_id').val() || '';
        var status = $('#filter_sop_status').val() || '';
        var sort = $('#sop_sort_input').val() || 'newest';

        var params = new URLSearchParams({
            active_tab: 'directory',
            search: search,
            category_id: categoryId,
            department_id: departmentId,
            status: status,
            sort: sort,
            sop_page: page
        });

        var url = '{{ route("hrms.sop.index") }}?' + params.toString();

        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(response, 'text/html');

                var oldBody = $('#sopDirectoryTable tbody');
                var newBody = $(doc).find('#sopDirectoryTable tbody');
                if (oldBody.length && newBody.length) {
                    oldBody.html(newBody.html());
                }

                var oldPagination = $('#sop_pagination_container');
                var newPagination = $(doc).find('#sop_pagination_container');
                if (oldPagination.length && newPagination.length) {
                    oldPagination.replaceWith(newPagination);
                }

                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');

                history.pushState(null, '', url);
            }
        });
    }

    var sopSearchTimeout;
    $(document).on('input', '#sop_search', function() {
        clearTimeout(sopSearchTimeout);
        sopSearchTimeout = setTimeout(function() {
            loadSopDirectory(1);
        }, 300);
    });

    window.setSopSort = function(sortKey, elem) {
        $('#sop_sort_input').val(sortKey);
        $('.erp-sort-dropdown .dropdown-item').removeClass('active');
        if (elem) {
            $(elem).addClass('active');
        }
        loadSopDirectory(1);
    };

    $('#sopFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadSopDirectory(1);
        $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
    });

    $('#btnResetSopFilter').on('click', function(e) {
        e.preventDefault();
        $('#sop_search').val('');
        $('#filter_sop_category_id').val('').trigger('change');
        $('#filter_sop_department_id').val('').trigger('change');
        $('#filter_sop_status').val('').trigger('change');
        $('#sop_sort_input').val('newest');
        $('.erp-sort-dropdown .dropdown-item').removeClass('active');
        $('.erp-sort-dropdown .dropdown-item:first').addClass('active');
        loadSopDirectory(1);
        $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
    });

    $(document).on('click', '#sop_pagination_container a', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        if (!url) return;
        var urlParams = new URLSearchParams(url.substring(url.indexOf('?')));
        var page = urlParams.get('sop_page') || urlParams.get('page') || 1;
        loadSopDirectory(page);
    });

    // Auto-focus and place cursor at end after full page load/reload
    $(document).ready(function() {
        var $search = $('#sop_search');
        if ($search.length) {
            var searchEl = $search[0];
            searchEl.focus();
            var len = searchEl.value.length;
            if (len > 0) {
                searchEl.setSelectionRange(len, len);
            }
        }
    });

    let stepIndex = 1;
    document.getElementById('addStepBtn')?.addEventListener('click', function() {
        const container = document.getElementById('stepsContainer');
        const nextNum = stepIndex + 1;
        const html = `
            <div class="step-card bg-light p-3 rounded-3 mb-3 border position-relative" id="stepCard_${stepIndex}">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="fw-bold fs-13 text-dark d-flex align-items-center gap-2">
                        <span class="step-badge">${nextNum}</span> Step ${nextNum}: Procedure Title & Instructions
                    </span>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0 fs-12 text-decoration-none" onclick="document.getElementById('stepCard_${stepIndex}').remove()">
                        <i class="feather-trash-2 me-1"></i> Remove Step
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="odoo-form-group">
                            <label class="odoo-form-label">Step Title <span class="text-danger">*</span></label>
                            <div class="flex-grow-1">
                                <input type="text" name="sections[${stepIndex}][title]" class="odoo-form-control" placeholder="e.g. Step Title" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="odoo-form-group align-items-start">
                            <label class="odoo-form-label pt-1">Step Content <span class="text-danger">*</span></label>
                            <div class="flex-grow-1">
                                <textarea name="sections[${stepIndex}][content]" rows="3" class="odoo-form-control" placeholder="Detailed step instructions..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="odoo-form-group align-items-start">
                            <label class="odoo-form-label pt-1">Checklist Items</label>
                            <div class="flex-grow-1">
                                <textarea name="sections[${stepIndex}][checklist_items]" rows="2" class="odoo-form-control" placeholder="Checklist item 1&#10;Checklist item 2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        stepIndex++;
    });

    // Populate and bind publish SOP modal
    $(document).on('click', '.btn-publish-trigger', function() {
        var sopId = $(this).data('sop-id');
        var sopCode = $(this).data('sop-code');
        var sopTitle = $(this).data('sop-title');
        var sopAudience = $(this).data('sop-audience');
        var sopDays = $(this).data('sop-days');

        var publishUrl = '{{ route("hrms.sop.publish", ":id") }}'.replace(':id', sopId);
        $('#publishSopForm').attr('action', publishUrl);
        $('#modalSopCode').text(sopCode || 'SOP');
        $('#modalSopTitle').text(sopTitle || 'Standard Operating Procedure');
        $('#modalSopAudience').text(sopAudience || 'All Organization Staff');
        $('#modalSopDays').text(sopDays + ' days');
    });
</script>
@endpush
@endsection
