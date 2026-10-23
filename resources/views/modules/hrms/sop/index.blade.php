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
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2 border-bottom pb-1">
            <div class="flex-grow-1" style="min-width: 0;">
                <x-ui.horizontal-tabs id="sopTabs" :tabs="$sopTabs" />
            </div>
        </div>

        <!-- TAB CONTENT CONTAINER -->
        <div class="tab-content pt-1" id="sopTabContent">

            <!-- TAB 1: SOP DIRECTORY (ADMIN / HR) -->
            @if($isHrOrAdmin)
                <div class="tab-pane fade {{ $activeTab === 'directory' ? 'show active' : '' }}" id="tab-directory" role="tabpanel">
                    
                    <!-- TOP TOOLBAR: TITLE + SEARCH, SORT, FILTER (RIGHT CORNER) -->
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
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
                    <div class="table-responsive" style="overflow-x: hidden;">
                        <table class="table table-hover align-middle mb-0 w-100" id="sopDirectoryTable" style="table-layout: fixed;">
                            <thead class="table-light">
                                <tr>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="width: 36%;">SOP Details</th>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 18%;">Category & Department</th>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 115px;">Version / Level</th>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 85px;">Status</th>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 120px;">Compliance</th>
                                    <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="width: 95px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sops as $sop)
                                    @php
                                        $totalAssigned = (int) ($sop->assignments_count ?? 0);
                                        $acknowledged = (int) ($sop->acknowledged_count ?? 0);
                                        $rate = $totalAssigned > 0 ? round(($acknowledged / $totalAssigned) * 100) : 0;
                                    @endphp
                                    <tr class="sop-row">
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                <x-ui.badge variant="light" class="border font-monospace fw-bold fs-11 text-nowrap">{{ $sop->code }}</x-ui.badge>
                                                <a href="{{ route('hrms.sop.show', $sop->id) }}" class="fw-bold text-primary text-decoration-none fs-13 d-inline" style="line-height: 1.35; word-wrap: break-word; white-space: normal;">
                                                    {{ $sop->title }}
                                                </a>
                                            </div>
                                            @if($sop->summary || $sop->objective)
                                                <div class="text-muted fs-12" style="line-height: 1.35; word-wrap: break-word; white-space: normal;">
                                                    {{ $sop->summary ?: Str::limit($sop->objective, 90) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="mb-1" style="word-wrap: break-word; white-space: normal;">
                                                @if($sop->category)
                                                    <x-ui.badge variant="primary" soft class="fs-11 text-wrap text-start">
                                                        <i class="{{ $sop->category->icon ?? 'feather-tag' }} me-1"></i>{{ $sop->category->name }}
                                                    </x-ui.badge>
                                                @else
                                                    <span class="text-muted fs-11">Uncategorized</span>
                                                @endif
                                            </div>
                                            <div class="text-muted fs-11" style="word-wrap: break-word; white-space: normal;">
                                                <i class="feather-briefcase me-1 text-muted"></i>{{ $sop->department->name ?? 'All Departments' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <x-ui.badge variant="secondary" soft class="fs-11 text-nowrap">v{{ $sop->version }}</x-ui.badge>
                                                @if($sop->criticality === 'critical')
                                                    <x-ui.badge variant="danger" class="fs-11 text-nowrap">Critical</x-ui.badge>
                                                @elseif($sop->criticality === 'high')
                                                    <x-ui.badge variant="warning" class="fs-11 text-nowrap">High</x-ui.badge>
                                                @elseif($sop->criticality === 'medium')
                                                    <x-ui.badge variant="info" soft class="fs-11 text-nowrap">Medium</x-ui.badge>
                                                @else
                                                    <x-ui.badge variant="light" class="border fs-11 text-nowrap">Low</x-ui.badge>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @if($sop->status === 'published')
                                                <x-ui.badge variant="success" soft class="fs-11 text-nowrap"><i class="feather-check-circle me-1"></i>Active</x-ui.badge>
                                            @elseif($sop->status === 'archived')
                                                <x-ui.badge variant="light" class="border fs-11 text-nowrap">Archived</x-ui.badge>
                                            @else
                                                <x-ui.badge variant="secondary" soft class="fs-11 text-nowrap">Draft</x-ui.badge>
                                            @endif
                                        </td>
                                        <td>
                                            @if($sop->status === 'draft')
                                                <div class="text-muted fs-11 text-nowrap">
                                                    <i class="feather-info text-primary me-1"></i>Publish to assign
                                                </div>
                                            @else
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <div class="flex-grow-1" style="min-width: 50px;">
                                                        <x-ui.progress-bar :value="$rate" :max="100" :color="$totalAssigned > 0 ? 'auto' : 'secondary'" height="6px" />
                                                    </div>
                                                    <span class="fw-bold fs-11 text-muted">{{ $rate }}%</span>
                                                </div>
                                                <div class="text-muted fs-11 text-nowrap">{{ $acknowledged }}/{{ $totalAssigned }} staff</div>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-flex align-items-center justify-content-end gap-1">
                                                <x-ui.icon-btn href="{{ route('hrms.sop.show', $sop->id) }}" icon="feather-eye" variant="soft-primary" size="sm" title="View & Read SOP" />

                                                @if($sop->status === 'draft')
                                                    <x-ui.icon-btn type="button" icon="feather-check-circle" variant="soft-success" size="sm" title="Publish SOP" data-bs-toggle="modal" data-bs-target="#publishSopModal" data-sop-id="{{ $sop->id }}" data-sop-code="{{ $sop->code }}" data-sop-title="{{ $sop->title }}" data-sop-audience="{{ $sop->target_audience_type === 'all' ? 'All Organization Staff' : ($sop->target_audience_type === 'department' ? 'Department Only' : 'Designated Staff') }}" data-sop-days="{{ $sop->acknowledgment_days_limit ?? 7 }}" class="btn-publish-trigger" />
                                                @endif

                                                <form method="POST" action="{{ route('hrms.sop.destroy', $sop->id) }}" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this SOP?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.icon-btn type="submit" icon="feather-trash-2" variant="soft-danger" size="sm" title="Delete SOP" />
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
                <!-- TOP TOOLBAR: TITLE + SEARCH, SORT, FILTER (OPPOSITE TO TITLE) -->
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="fw-bold text-dark mb-0"><i class="feather-user-check me-2 text-primary"></i> My Assigned SOPs</h5>
                            <div class="d-flex align-items-center gap-1 ms-1">
                                <x-ui.badge variant="primary" soft>{{ $myTotalCount }} Total</x-ui.badge>
                                @if($myPendingCount > 0)
                                    <x-ui.badge variant="danger" soft>{{ $myPendingCount }} Pending Action</x-ui.badge>
                                @endif
                                <x-ui.badge variant="success" soft>{{ $myCompletedCount }} Completed</x-ui.badge>
                            </div>
                        </div>
                        <p class="text-muted fs-12 mb-0">Browse and sign mandatory standard operating procedures and compliance documents</p>
                    </div>

                    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                        <!-- Live Instant Search Input -->
                        <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 200px; max-width: 260px; height: 38px;">
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input type="text" name="my_search" id="my_sop_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search my SOPs..." value="{{ request('my_search') }}" style="box-shadow: none; height: 32px;" autocomplete="off">
                        </div>

                        <!-- Sort Dropdown -->
                        <x-ui.sort-dropdown label="Sort">
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('my_sort', 'pending_first') === 'pending_first' ? 'active' : '' }}" href="javascript:void(0);" onclick="setMySopSort('pending_first', this)">
                                <span>Pending First</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('my_sort') === 'newest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setMySopSort('newest', this)">
                                <span>Newest First</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('my_sort') === 'oldest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setMySopSort('oldest', this)">
                                <span>Oldest First</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('my_sort') === 'due_soon' ? 'active' : '' }}" href="javascript:void(0);" onclick="setMySopSort('due_soon', this)">
                                <span>Due Soonest</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('my_sort') === 'title_asc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setMySopSort('title_asc', this)">
                                <span>Title (A-Z)</span>
                            </a>
                            <a class="dropdown-item py-2 d-flex align-items-center {{ request('my_sort') === 'code_asc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setMySopSort('code_asc', this)">
                                <span>Code (A-Z)</span>
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Filter Dropdown -->
                        <x-ui.filter label="Filter" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                            <form method="GET" action="javascript:void(0);" id="mySopFilterForm">
                                <input type="hidden" name="my_sort" id="my_sop_sort_input" value="{{ request('my_sort', 'pending_first') }}">

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                    <x-ui.odoo-form-ui type="select" name="my_status" id="filter_my_sop_status">
                                        <option value="">All Statuses</option>
                                        <option value="pending" {{ request('my_status') == 'pending' ? 'selected' : '' }}>Pending Sign-off</option>
                                        <option value="overdue" {{ request('my_status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                                        <option value="acknowledged" {{ request('my_status') == 'acknowledged' ? 'selected' : '' }}>Acknowledged / Completed</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category</label>
                                    <x-ui.odoo-form-ui type="select" name="my_category_id" id="filter_my_sop_category_id">
                                        <option value="">All Categories</option>
                                        @foreach(($allCategories ?? $categories) as $cat)
                                            <option value="{{ $cat->id }}" {{ request('my_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="dropdown-divider my-3"></div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                    <button type="button" id="btnResetMySopFilter" class="btn btn-light btn-sm border flex-grow-1 text-center">Reset</button>
                                </div>
                            </form>
                        </x-ui.filter>
                    </div>
                </div>

                <!-- TABLE CONTAINER -->
                <div id="mySopsContainer">
                    @if($allMyAssignments->count() > 0)
                        <div class="table-responsive" style="overflow-x: hidden;">
                            <table class="table table-hover align-middle mb-0 w-100" style="table-layout: fixed;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 95px;">Code</th>
                                        <th style="width: 36%;">SOP Title</th>
                                        <th style="width: 16%;">Category</th>
                                        <th style="width: 60px;">Version</th>
                                        <th style="width: 95px;">Status</th>
                                        <th style="width: 120px;">Date / Timeline</th>
                                        <th style="width: 145px;" class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($allMyAssignments as $assignment)
                                        @php
                                            $sop = $assignment->document;
                                            if (!$sop) {
                                                continue;
                                            }
                                            $isPending = ($assignment->status === 'pending');
                                            $isOverdue = $isPending && $assignment->due_date && $assignment->due_date->isPast();
                                        @endphp
                                        <tr class="{{ $isPending ? 'bg-light-subtle' : '' }}">
                                            <td>
                                                <x-ui.badge variant="light" class="border font-monospace text-nowrap">{{ $sop->code }}</x-ui.badge>
                                            </td>
                                            <td>
                                                <a href="{{ route('hrms.sop.show', $sop->id) }}" class="fw-bold text-dark text-decoration-none d-block mb-1" style="line-height: 1.35; word-wrap: break-word; white-space: normal;">
                                                    {{ $sop->title }}
                                                </a>
                                                @if($sop->summary)
                                                    <div class="text-muted fs-11" style="line-height: 1.35; word-wrap: break-word; white-space: normal;">{{ $sop->summary }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="text-muted fs-12 d-block" style="word-wrap: break-word; white-space: normal; line-height: 1.35;">{{ $sop->category->name ?? 'General' }}</span>
                                            </td>
                                            <td>
                                                <x-ui.badge variant="light" class="border">v{{ $assignment->version_assigned }}</x-ui.badge>
                                            </td>
                                            <td>
                                                @if($isPending)
                                                    @if($isOverdue)
                                                        <x-ui.badge variant="danger" soft class="text-nowrap"><i class="feather-clock me-1"></i>Overdue</x-ui.badge>
                                                    @else
                                                        <x-ui.badge variant="warning" soft class="text-nowrap"><i class="feather-clock me-1"></i>Pending</x-ui.badge>
                                                    @endif
                                                @else
                                                    <x-ui.badge variant="success" soft class="text-nowrap"><i class="feather-check-circle me-1"></i>Completed</x-ui.badge>
                                                @endif
                                            </td>
                                            <td>
                                                @if($isPending)
                                                    @if($assignment->due_date)
                                                        <span class="{{ $isOverdue ? 'text-danger' : 'text-muted' }} fs-12 text-nowrap">
                                                            <i class="feather-calendar me-1"></i>{{ $assignment->due_date->format('M d, Y') }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted fs-12">&mdash;</span>
                                                    @endif
                                                @else
                                                    <span class="text-success fs-12 text-nowrap">
                                                        <i class="feather-check-circle me-1"></i>{{ $assignment->acknowledged_at ? $assignment->acknowledged_at->format('M d, Y H:i') : 'Signed' }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-3">
                                                @if($isPending)
                                                    <x-ui.button variant="primary" size="sm" icon="feather-book-open" href="{{ route('hrms.sop.show', $sop->id) }}" class="fw-bold px-2 py-1 text-nowrap fs-12">
                                                        Review & Sign
                                                    </x-ui.button>
                                                @else
                                                    <x-ui.icon-btn variant="soft-primary" size="sm" icon="feather-eye" href="{{ route('hrms.sop.show', $sop->id) }}" title="Reference SOP" />
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- PAGINATION -->
                        <div id="my_sop_pagination_container" class="mt-3">
                            @if($allMyAssignments->hasPages())
                                <x-ui.pagination :currentPage="$allMyAssignments->currentPage()" :totalPages="$allMyAssignments->lastPage()" :totalResults="$allMyAssignments->total()" :perPage="$allMyAssignments->perPage()" pageParam="my_page" />
                            @endif
                        </div>
                    @else
                        <div class="bg-light p-5 rounded-3 text-center text-muted">
                            <i class="feather-check-circle fs-36 text-success d-block mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">No Matching SOPs</h6>
                            <p class="mb-0 fs-12 text-muted">No standard operating procedures matched your current search/filter criteria.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- TAB 3: CATEGORIES MASTER (ADMIN / HR) -->
            @if($isHrOrAdmin)
                <div class="tab-pane fade {{ $activeTab === 'categories' ? 'show active' : '' }}" id="tab-categories" role="tabpanel">
                    <!-- TOP TOOLBAR: TITLE + SEARCH, SORT, FILTER (RIGHT CORNER) -->
                    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-0"><i class="feather-tag me-2 text-primary"></i> SOP Categories & Domains</h5>
                            <p class="text-muted fs-12 mb-0">Functional classifications for company standard operating procedures</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                            <!-- Instant Live Search Input -->
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="cat_search" id="cat_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search code, name..." value="{{ request('cat_search') }}" style="box-shadow: none; height: 32px;" autocomplete="off">
                            </div>

                            <!-- Sort Dropdown -->
                            <x-ui.sort-dropdown label="Sort">
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('cat_sort', 'name_asc') === 'name_asc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setCatSort('name_asc', this)">
                                    <span>Name (A-Z)</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('cat_sort') === 'name_desc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setCatSort('name_desc', this)">
                                    <span>Name (Z-A)</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('cat_sort') === 'code_asc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setCatSort('code_asc', this)">
                                    <span>Code (A-Z)</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('cat_sort') === 'code_desc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setCatSort('code_desc', this)">
                                    <span>Code (Z-A)</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('cat_sort') === 'sops_desc' ? 'active' : '' }}" href="javascript:void(0);" onclick="setCatSort('sops_desc', this)">
                                    <span>Most SOPs Linked</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('cat_sort') === 'newest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setCatSort('newest', this)">
                                    <span>Newest First</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ request('cat_sort') === 'oldest' ? 'active' : '' }}" href="javascript:void(0);" onclick="setCatSort('oldest', this)">
                                    <span>Oldest First</span>
                                </a>
                            </x-ui.sort-dropdown>

                            <!-- Filter Dropdown -->
                            <x-ui.filter label="Filter" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                <form method="GET" action="javascript:void(0);" id="catFilterForm">
                                    <input type="hidden" name="cat_sort" id="cat_sort_input" value="{{ request('cat_sort', 'name_asc') }}">

                                    <div class="mb-3" style="min-width: 250px;">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                        <x-ui.odoo-form-ui type="select" name="cat_status" id="filter_cat_status">
                                            <option value="">All Statuses</option>
                                            <option value="active" {{ request('cat_status') == 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ request('cat_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </x-ui.odoo-form-ui>
                                    </div>

                                    <div class="mb-3" style="min-width: 250px;">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">SOPs Linked</label>
                                        <x-ui.odoo-form-ui type="select" name="cat_has_sops" id="filter_cat_has_sops">
                                            <option value="">All</option>
                                            <option value="yes" {{ request('cat_has_sops') == 'yes' ? 'selected' : '' }}>Has Linked SOPs</option>
                                            <option value="no" {{ request('cat_has_sops') == 'no' ? 'selected' : '' }}>No Linked SOPs</option>
                                        </x-ui.odoo-form-ui>
                                    </div>

                                    <div class="dropdown-divider my-3"></div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                        <button type="button" id="btnResetCatFilter" class="btn btn-light btn-sm border flex-grow-1 text-center">Reset</button>
                                    </div>
                                </form>
                            </x-ui.filter>
                        </div>
                    </div>

                    <!-- CATEGORIES GRID CONTAINER -->
                    <div id="categoriesContainer">
                        <div class="row g-3">
                            @forelse($categories as $cat)
                                <div class="col-md-4 col-sm-6">
                                    <x-ui.card class="h-100 position-relative" style="border-left: 4px solid var(--bs-primary) !important;">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <x-ui.badge variant="primary" soft>
                                                <i class="{{ $cat->icon ?? 'feather-tag' }} me-1"></i>{{ $cat->code ?? 'CAT' }}
                                            </x-ui.badge>
                                            <div class="d-flex align-items-center gap-1">
                                                <x-ui.icon-btn variant="soft-primary" size="sm" icon="feather-edit" title="Edit Category" data-bs-toggle="modal" data-bs-target="#editCategoryModal{{ $cat->id }}" />
                                                <form method="POST" action="{{ route('hrms.sop.category.destroy', $cat->id) }}" class="d-inline m-0" onsubmit="return confirm('Delete this category?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.icon-btn type="submit" icon="feather-trash-2" variant="soft-danger" size="sm" title="Delete Category" />
                                                </form>
                                            </div>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">{{ $cat->name }}</h6>
                                        <p class="text-muted fs-12 mb-2">{{ $cat->description ?: 'No description provided.' }}</p>
                                        <small class="text-muted fs-11">
                                            <i class="feather-file-text me-1"></i>{{ $cat->documents_count ?? $cat->documents()->count() }} SOPs linked
                                        </small>
                                    </x-ui.card>

                                    <!-- Edit Modal for Category {{ $cat->id }} -->
                                    <x-ui.modal id="editCategoryModal{{ $cat->id }}" title="<i class='feather-edit me-2 text-primary'></i> Edit Category: {{ $cat->name }}" formAction="{{ route('hrms.sop.category.update', $cat->id) }}" formMethod="PUT" submitText="Save Changes" centered>
                                        <x-ui.odoo-form-ui type="sheet" class="p-1">
                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="input" label="Category Name" name="name" value="{{ $cat->name }}" placeholder="e.g. IT & Security, Quality Assurance" :required="true" />
                                            </div>
                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="input" label="Category Code" name="code" value="{{ $cat->code }}" placeholder="e.g. IT, QA, SAFETY" />
                                            </div>
                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" :value="$cat->description" :rows="2" placeholder="Description of procedures under this category..." />
                                            </div>
                                        </x-ui.odoo-form-ui>
                                    </x-ui.modal>
                                </div>
                            @empty
                                <div class="col-12 text-center py-5 text-muted">
                                    <i class="feather-tag fs-36 d-block mb-2 text-muted opacity-50"></i>
                                    <p class="mb-0 fw-medium">No categories matching criteria.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

        </div>

    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: CREATE NEW SOP DOCUMENT USING X-UI.MODAL & X-UI.ODOO-FORM-UI -->
<!-- ========================================================================= -->
<x-ui.modal id="createSopModal" title="<i class='feather-file-plus text-primary me-2'></i> Create Standard Operating Procedure (SOP)" size="lg" :showFooter="false" :centered="true" :scrollable="true">
    <form method="POST" action="{{ route('hrms.sop.store') }}" enctype="multipart/form-data" id="createSopForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <!-- PRIMARY ESSENTIAL FIELDS (2 COLUMNS) -->
            <div class="row g-3">
                <div class="col-md-7">
                    <x-ui.odoo-form-ui type="input" label="SOP Title" name="title" placeholder="e.g. Production Server Deployment Guidelines" :required="true" />
                </div>
                <div class="col-md-5">
                    <x-ui.odoo-form-ui type="input" label="SOP Code" name="code" placeholder="Auto-generated if empty" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Category" name="sop_category_id">
                        <option value="">Choose category...</option>
                        @foreach(($allCategories ?? $categories) as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Criticality" name="criticality">
                        <option value="medium" selected>Medium</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="critical">Critical / Regulatory</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Audience Scope" name="target_audience_type" id="targetAudienceSelect">
                        <option value="all" selected>Entire Company (All Employees)</option>
                        <option value="department">Specific Department(s)</option>
                        <option value="designation">Specific Designation(s)</option>
                        <option value="custom">Specific Employee(s)</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <!-- Dynamic Target Selection Container based on Audience Scope -->
                    <div id="targetAllContainer">
                        <div class="odoo-form-group">
                            <label class="odoo-form-label">Target Scope</label>
                            <div class="flex-grow-1">
                                <input type="text" class="odoo-form-control text-muted bg-light" value="Organization-Wide (All Employees)" readonly>
                            </div>
                        </div>
                    </div>
                    <div id="targetDeptContainer" class="d-none">
                        <x-ui.odoo-form-ui type="select" label="Department" name="department_id" id="sopDepartmentSelect">
                            <option value="">Select Department...</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <div id="targetDesignationContainer" class="d-none">
                        <x-ui.odoo-form-ui type="select" label="Designation" name="target_designation_ids[]" id="sopDesignationSelect">
                            <option value="">Select Designation...</option>
                            @foreach($designations as $desig)
                                <option value="{{ $desig->id }}">{{ $desig->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <div id="targetEmployeeContainer" class="d-none">
                        <x-ui.odoo-form-ui type="select" label="Employee" name="target_employee_ids[]" id="sopEmployeeSelect">
                            <option value="">Select Employee...</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id ?? $emp->id }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="textarea" label="Summary" name="summary" placeholder="Brief 1-2 sentence overview of this SOP..." rows="2" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="file" label="Attachment PDF" name="attachment" helperText="Attach policy PDF or checklist document" />
                </div>
            </div>

            <!-- GOVERNANCE & PROCEDURE BUILDER -->
            <div class="mt-3 pt-3 border-top">
                <div class="p-3 bg-light rounded-3 border">
                    <!-- Compliance & Governance -->
                    <h6 class="fw-bold text-dark fs-12 text-uppercase mb-3"><i class="feather-shield me-1 text-primary"></i> Compliance & Governance Settings</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Initial Status" name="status">
                                <option value="published" selected>Published & Active (Dispatch to Staff)</option>
                                <option value="draft">Save as Draft</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Sign-off SLA (Days)" name="acknowledgment_days_limit" value="7" helperText="Days allowed for employee sign-off" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Review Cycle (Months)" name="review_interval_months" value="12" helperText="Review frequency in months" />
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex flex-column gap-2 pt-2">
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input" type="checkbox" name="is_mandatory" value="1" id="is_mandatory_check" checked>
                                    <label class="form-check-label fs-12 text-dark" for="is_mandatory_check">Mandatory Employee Sign-off</label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input" type="checkbox" name="auto_assign_new_hires" value="1" id="auto_assign_check" checked>
                                    <label class="form-check-label fs-12 text-dark" for="auto_assign_check">Auto-assign to new hires</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Objective, Scope & Prerequisites -->
                    <h6 class="fw-bold text-dark fs-12 text-uppercase mb-2 pt-2 border-top"><i class="feather-file-text me-1 text-primary"></i> Scope & Objective Details</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="textarea" label="Objective" name="objective" placeholder="Why this SOP exists and what standard it establishes..." rows="2" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="textarea" label="Scope" name="scope" placeholder="Who must follow this procedure and when it applies..." rows="2" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Prerequisites" name="prerequisites" placeholder="Access permissions, PPE, software tools, or approvals required..." rows="2" />
                        </div>
                    </div>

                    <!-- Step-by-Step Procedure Builder -->
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top mb-2">
                        <div>
                            <h6 class="fw-bold text-dark fs-12 text-uppercase mb-0"><i class="feather-list me-1 text-primary"></i> Procedure Steps & Checklist</h6>
                            <small class="text-muted fs-11">Add structured steps now or manage them on the SOP detail view.</small>
                        </div>
                        <x-ui.button type="button" variant="outline-primary" size="sm" icon="feather-plus" id="addStepBtn">
                            Add Step
                        </x-ui.button>
                    </div>

                    <div id="stepsContainer"></div>
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

    // Dynamic Audience Scope change handler
    function handleTargetAudienceChange() {
        var scope = $('#targetAudienceSelect').val();
        $('#targetAllContainer').addClass('d-none');
        $('#targetDeptContainer').addClass('d-none');
        $('#targetDesignationContainer').addClass('d-none');
        $('#targetEmployeeContainer').addClass('d-none');

        $('#sopDepartmentSelect').prop('disabled', true);
        $('#sopDesignationSelect').prop('disabled', true);
        $('#sopEmployeeSelect').prop('disabled', true);

        var modalParent = $('#createSopModal .modal-content').length ? $('#createSopModal .modal-content') : $(document.body);

        function initDropdown($el) {
            $el.prop('disabled', false);
            if ($.fn.select2) {
                if ($el.data('select2')) {
                    $el.select2('destroy');
                }
                $el.select2({
                    theme: "bootstrap-5",
                    width: "100%",
                    dropdownParent: modalParent
                });
            }
        }

        if (scope === 'department') {
            $('#targetDeptContainer').removeClass('d-none');
            initDropdown($('#sopDepartmentSelect'));
        } else if (scope === 'designation') {
            $('#targetDesignationContainer').removeClass('d-none');
            initDropdown($('#sopDesignationSelect'));
        } else if (scope === 'custom') {
            $('#targetEmployeeContainer').removeClass('d-none');
            initDropdown($('#sopEmployeeSelect'));
        } else {
            $('#targetAllContainer').removeClass('d-none');
        }
    }

    $(document).on('change', '#targetAudienceSelect', handleTargetAudienceChange);
    $(document).ready(function() {
        handleTargetAudienceChange();
    });
    $('#createSopModal').on('shown.bs.modal', function() {
        handleTargetAudienceChange();
    });

    let stepIndex = 0;
    document.getElementById('addStepBtn')?.addEventListener('click', function() {
        const container = document.getElementById('stepsContainer');
        const nextNum = container.children.length + 1;
        const currentIdx = stepIndex;
        const html = `
            <div class="step-card bg-white p-3 rounded-3 mb-2 border position-relative" id="stepCard_${currentIdx}">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold fs-12 text-dark d-flex align-items-center gap-2">
                        <span class="step-badge">${nextNum}</span> Step ${nextNum}: Title & Instructions
                    </span>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0 fs-12 text-decoration-none" onclick="document.getElementById('stepCard_${currentIdx}').remove()">
                        <i class="feather-trash-2 me-1"></i> Remove
                    </button>
                </div>
                <div class="row g-2">
                    <div class="col-12">
                        <div class="odoo-form-group">
                            <label class="odoo-form-label">Step Title</label>
                            <div class="flex-grow-1">
                                <input type="text" name="sections[${currentIdx}][title]" class="odoo-form-control" placeholder="e.g. Pre-Deployment Verification">
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="odoo-form-group align-items-start">
                            <label class="odoo-form-label pt-1">Instructions</label>
                            <div class="flex-grow-1">
                                <textarea name="sections[${currentIdx}][content]" rows="2" class="odoo-form-control" placeholder="Detailed step instructions or protocol..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="odoo-form-group align-items-start">
                            <label class="odoo-form-label pt-1">Checklist</label>
                            <div class="flex-grow-1">
                                <textarea name="sections[${currentIdx}][checklist_items]" rows="2" class="odoo-form-control" placeholder="Checklist item 1&#10;Checklist item 2"></textarea>
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

    // ==========================================
    // CATEGORIES MASTER: LIVE SEARCH, SORT & FILTER
    // ==========================================
    function loadCategories() {
        var params = new URLSearchParams();
        params.set('active_tab', 'categories');

        var searchVal = $('#cat_search').val();
        if (searchVal) params.set('cat_search', searchVal);

        var sortVal = $('#cat_sort_input').val();
        if (sortVal) params.set('cat_sort', sortVal);

        var statusVal = $('#filter_cat_status').val();
        if (statusVal) params.set('cat_status', statusVal);

        var hasSopsVal = $('#filter_cat_has_sops').val();
        if (hasSopsVal) params.set('cat_has_sops', hasSopsVal);

        var url = '{{ route("hrms.sop.index") }}?' + params.toString();

        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(response, 'text/html');

                var oldCont = $('#categoriesContainer');
                var newCont = $(doc).find('#categoriesContainer');
                if (oldCont.length && newCont.length) {
                    oldCont.html(newCont.html());
                }

                $('#tab-categories .erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('#tab-categories .erp-filter-dropdown.show').removeClass('show');

                history.pushState(null, '', url);
            }
        });
    }

    var catSearchTimeout;
    $(document).on('input', '#cat_search', function() {
        clearTimeout(catSearchTimeout);
        catSearchTimeout = setTimeout(function() {
            loadCategories();
        }, 300);
    });

    window.setCatSort = function(sortKey, elem) {
        $('#cat_sort_input').val(sortKey);
        $('#tab-categories .erp-sort-dropdown .dropdown-item').removeClass('active');
        if (elem) {
            $(elem).addClass('active');
        }
        loadCategories();
    };

    $('#catFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadCategories();
        $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
    });

    $('#btnResetCatFilter').on('click', function(e) {
        e.preventDefault();
        $('#cat_search').val('');
        $('#filter_cat_status').val('').trigger('change');
        $('#filter_cat_has_sops').val('').trigger('change');
        $('#cat_sort_input').val('name_asc');
        $('#tab-categories .erp-sort-dropdown .dropdown-item').removeClass('active');
        $('#tab-categories .erp-sort-dropdown .dropdown-item:first').addClass('active');
        loadCategories();
        $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
    });

    // ==========================================
    // MY SOPS: LIVE SEARCH, SORT, FILTER & PAGINATION
    // ==========================================
    function loadMySops(page) {
        var params = new URLSearchParams();
        params.set('active_tab', 'my_sops');
        if (page) params.set('my_page', page);

        var searchVal = $('#my_sop_search').val();
        if (searchVal) params.set('my_search', searchVal);

        var sortVal = $('#my_sop_sort_input').val();
        if (sortVal) params.set('my_sort', sortVal);

        var statusVal = $('#filter_my_sop_status').val();
        if (statusVal) params.set('my_status', statusVal);

        var catVal = $('#filter_my_sop_category_id').val();
        if (catVal) params.set('my_category_id', catVal);

        var url = '{{ route("hrms.sop.index") }}?' + params.toString();

        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(response, 'text/html');

                var oldCont = $('#mySopsContainer');
                var newCont = $(doc).find('#mySopsContainer');
                if (oldCont.length && newCont.length) {
                    oldCont.html(newCont.html());
                }

                $('#tab-mysops .erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('#tab-mysops .erp-filter-dropdown.show').removeClass('show');

                history.pushState(null, '', url);
            }
        });
    }

    var mySopSearchTimeout;
    $(document).on('input', '#my_sop_search', function() {
        clearTimeout(mySopSearchTimeout);
        mySopSearchTimeout = setTimeout(function() {
            loadMySops(1);
        }, 300);
    });

    window.setMySopSort = function(sortKey, elem) {
        $('#my_sop_sort_input').val(sortKey);
        $('#tab-mysops .erp-sort-dropdown .dropdown-item').removeClass('active');
        if (elem) {
            $(elem).addClass('active');
        }
        loadMySops(1);
    };

    $('#mySopFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadMySops(1);
        $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
    });

    $('#btnResetMySopFilter').on('click', function(e) {
        e.preventDefault();
        $('#my_sop_search').val('');
        $('#filter_my_sop_status').val('').trigger('change');
        $('#filter_my_sop_category_id').val('').trigger('change');
        $('#my_sop_sort_input').val('pending_first');
        $('#tab-mysops .erp-sort-dropdown .dropdown-item').removeClass('active');
        $('#tab-mysops .erp-sort-dropdown .dropdown-item:first').addClass('active');
        loadMySops(1);
        $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
    });

    $(document).on('click', '#my_sop_pagination_container a', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        if (!url) return;
        var urlParams = new URLSearchParams(url.substring(url.indexOf('?')));
        var page = urlParams.get('my_page') || urlParams.get('page') || 1;
        loadMySops(page);
    });
</script>
@endpush
@endsection
