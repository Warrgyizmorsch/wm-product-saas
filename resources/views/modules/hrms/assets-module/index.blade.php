@extends('layouts.duralux')

@section('title', 'Assets Custody & Requests | SaaS ERP')
@section('page-title', 'Assets Custody & Requests')
@section('breadcrumb', 'HRMS / Assets')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .badge-new {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #10b981 !important;
            font-weight: 600;
        }
        .badge-good {
            background-color: rgba(59, 130, 246, 0.08) !important;
            color: #3b82f6 !important;
            font-weight: 600;
        }
        .badge-fair {
            background-color: rgba(245, 158, 11, 0.08) !important;
            color: #f59e0b !important;
            font-weight: 600;
        }
        .badge-damaged {
            background-color: rgba(239, 68, 68, 0.08) !important;
            color: #ef4444 !important;
            font-weight: 600;
        }
        .badge-scrapped {
            background-color: rgba(100, 116, 139, 0.15) !important;
            color: #64748b !important;
            font-weight: 600;
        }
        .table-responsive {
            overflow-x: auto !important;
        }
        /* Custom Tabs styling */
        #assetsModuleTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 600;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
        }
        #assetsModuleTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #assetsModuleTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="card border-0 shadow-sm rounded-4">
        <!-- Card Header -->
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h5 class="fw-bold text-dark mb-0 fs-16">Assets Custody & Requests Dashboard</h5>
                <p class="text-muted fs-12 mb-0">Manage employee requests, direct allocations, custody records and histories</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px; border-radius: 6px; padding: 8px 16px;" data-bs-toggle="modal" data-bs-target="#directAllocateModal">
                    <i class="feather-plus me-1"></i> Allocate Asset
                </button>
            </div>
        </div>

        <div class="card-body px-4 pb-4 pt-3">
            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs tab-nav-custom border-bottom mb-4" id="assetsModuleTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests-pane" type="button" role="tab" aria-controls="requests-pane" aria-selected="true">
                        <i class="feather-user-check me-2"></i>Asset Requests
                        @if($pendingRequestsCount > 0)
                            <span class="badge bg-danger rounded-circle p-1 ms-1" style="font-size: 9px; min-width: 16px; min-height: 16px; line-height: 8px;">
                                {{ $pendingRequestsCount }}
                            </span>
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab" aria-controls="history-pane" aria-selected="false">
                        <i class="feather-clock me-2"></i>Allocation History
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="assetsModuleTabsContent">
                <!-- 1. ASSET REQUESTS TAB PANE -->
                <div class="tab-pane fade show active" id="requests-pane" role="tabpanel" aria-labelledby="requests-tab">
                    <!-- Filters Toolbar -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
                        <h6 class="fw-bold mb-0 text-dark fs-14">Requests Registry</h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <form method="GET" action="{{ route('hrms.assets-module.index') }}" class="d-flex align-items-center gap-2 m-0">
                                @foreach(['history_search', 'history_category_id', 'history_company_id'] as $param)
                                    @if(request()->filled($param))
                                        <input type="hidden" name="{{ $param }}" value="{{ request($param) }}">
                                    @endif
                                @endforeach
                                <input type="hidden" name="request_sort" id="request_sort" value="{{ request('request_sort', 'newest') }}">
                                
                                <div class="d-flex align-items-center border rounded px-3 py-1 bg-light" style="min-width: 220px; max-width: 280px; height: 38px;">
                                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                    <input type="text" name="request_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search requests..." value="{{ request('request_search') }}" style="box-shadow: none; height: 32px;">
                                </div>

                                <div class="d-flex gap-2 align-items-center">
                                    <x-ui.sort-dropdown label="Sort">
                                        <a class="dropdown-item py-2 {{ request('request_sort', 'newest') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'newest', this); event.preventDefault();">Newest</a>
                                        <a class="dropdown-item py-2 {{ request('request_sort') == 'oldest' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'oldest', this); event.preventDefault();">Oldest</a>
                                        <a class="dropdown-item py-2 {{ request('request_sort', 'status_asc') == 'status_asc' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'status_asc', this); event.preventDefault();">Status (Asc)</a>
                                        <a class="dropdown-item py-2 {{ request('request_sort') == 'status_desc' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'status_desc', this); event.preventDefault();">Status (Desc)</a>
                                    </x-ui.sort-dropdown>

                                    <x-ui.filter label="Filter" offset="0, 5">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                        
                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Requested Category</label>
                                            <x-ui.odoo-form-ui type="select" name="request_category_id">
                                                <option value="">All Categories</option>
                                                @foreach($categories as $category)
                                                    <option value="{{ $category->id }}" {{ request('request_category_id') == $category->id ? 'selected' : '' }}>
                                                        {{ $category->name }}
                                                    </option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Org Entity</label>
                                            <x-ui.odoo-form-ui type="select" name="request_company_id">
                                                <option value="">All Companies</option>
                                                @foreach($companies as $company)
                                                    <option value="{{ $company->id }}" {{ request('request_company_id') == $company->id ? 'selected' : '' }}>
                                                        {{ $company->company_name }}
                                                    </option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                            <x-ui.odoo-form-ui type="select" name="request_status">
                                                <option value="">All Statuses</option>
                                                <option value="pending" {{ request('request_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                                <option value="partially_allocated" {{ request('request_status') === 'partially_allocated' ? 'selected' : '' }}>Partially Allocated</option>
                                                <option value="allocated" {{ request('request_status') === 'allocated' ? 'selected' : '' }}>Allocated</option>
                                                <option value="rejected" {{ request('request_status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="d-flex gap-2 justify-content-end mt-4">
                                            <a href="{{ route('hrms.assets-module.index', request()->except(['request_search', 'request_category_id', 'request_company_id', 'request_status'])) }}" class="btn btn-sm btn-light border">Reset</a>
                                            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                        </div>
                                    </x-ui.filter>

                                    @if(request()->anyFilled(['request_search', 'request_category_id', 'request_company_id', 'request_status']))
                                        <a href="{{ route('hrms.assets-module.index', request()->except(['request_search', 'request_category_id', 'request_company_id', 'request_status'])) }}" class="btn btn-sm btn-light border px-2 d-flex align-items-center justify-content-center" style="height: 38px; border-radius: 6px; font-size: 12px;" title="Clear Filters">
                                            <i class="feather-x"></i>
                                        </a>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Bulk Actions Toolbar -->
                    <div id="bulkActionsToolbar" class="d-none border-bottom px-4 py-2 bg-light mb-3 rounded">
                        <div class="d-flex justify-content-end align-items-center gap-2">
                            <span class="fs-12 text-muted fw-bold me-1"><span id="selectedRequestsCount">0</span> Selected</span>
                            <button type="button" class="btn btn-sm btn-primary text-uppercase fw-bold px-3 py-1.5" id="btnBulkAllocate" style="font-size: 11px; border-radius: 6px; letter-spacing: 0.5px;">
                                <i class="feather-user-check me-1"></i> Bulk Allocate
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger text-uppercase fw-bold px-3 py-1.5" id="btnBulkReject" style="font-size: 11px; border-radius: 6px; letter-spacing: 0.5px;">
                                <i class="feather-x me-1"></i> Bulk Reject
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                            <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                                <tr>
                                    <th style="width: 45px; padding-left: 20px;"><input type="checkbox" id="selectAllRequests" class="form-check-input"></th>
                                    <th class="text-start" style="width: 35%;">Employee & Org Entity</th>
                                    <th class="text-start" style="width: 35%;">Requested Item & Status</th>
                                    <th class="text-end px-4" style="width: 180px; white-space: nowrap;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $req)
                                    @php 
                                        $allocatedCount = $req->status === 'allocated' ? $req->quantity : ($req->allocated_asset_id ? 1 : 0);
                                        $remainingQty = max(0, $req->quantity - $allocatedCount); 
                                    @endphp
                                    <tr>
                                        <td>
                                            @if(in_array($req->status, ['pending', 'partially_allocated']))
                                                <input type="checkbox" class="form-check-input request-select-checkbox" value="{{ $req->id }}" data-category-id="{{ $req->asset_category_id }}" data-category-name="{{ $req->category->name }}" data-item-id="{{ $req->asset_item_id }}" data-item-name="{{ $req->item->name ?? $req->category->name }}" data-quantity="{{ $req->quantity }}" data-allocated-count="{{ $allocatedCount }}" data-remaining-qty="{{ $remainingQty }}" data-employee-name="{{ $req->employee->display_name }} ({{ $req->employee->employee_id }})" data-company-id="{{ $req->company_id }}" data-requested-asset-id="{{ $req->requested_asset_id }}">
                                            @endif
                                        </td>
                                        <td class="text-start" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                            <div class="fw-bold text-dark fs-13">{{ $req->employee->display_name }}</div>
                                            <div class="text-muted fs-11 mt-0.5">
                                                <span>{{ $req->employee->employee_id }}</span>
                                                @if($req->company)
                                                    <span class="mx-1">•</span><span class="fw-medium text-secondary">{{ $req->company->company_name }}</span>
                                                @endif
                                            </div>
                                            <div class="fs-11 text-muted mt-0.5">
                                                <i class="feather-calendar me-1 text-primary"></i>Requested <span class="fw-medium text-dark">{{ $req->request_date ? $req->request_date->format('d M, Y') : '-' }}</span>
                                            </div>
                                        </td>
                                        <td class="text-start" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                            <div class="fw-bold text-dark fs-13">{{ $req->item->name ?? $req->category->name }}</div>
                                            <div class="my-0.5">
                                                <span class="badge bg-light text-secondary border px-2 py-0.5 fs-11">{{ $req->category->name }}</span>
                                            </div>
                                            <div class="fs-11 text-muted mb-1">
                                                Req: <strong class="text-dark">{{ $req->quantity }}</strong> | 
                                                Allocated: <strong class="text-success">{{ $allocatedCount }}</strong> | 
                                                Remaining: <strong class="{{ $remainingQty > 0 ? 'text-danger' : 'text-muted' }}">{{ $remainingQty }}</strong>
                                            </div>
                                            <div>
                                                @if($req->status === 'pending')
                                                    <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11 text-capitalize">Pending</span>
                                                @elseif($req->status === 'partially_allocated')
                                                    <span class="badge bg-soft-info text-info px-2.5 py-1 rounded-pill fs-11 text-capitalize">Partially Allocated</span>
                                                @elseif($req->status === 'allocated')
                                                    <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11 text-capitalize">Allocated</span>
                                                @elseif($req->status === 'rejected')
                                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1 rounded-pill fs-11 text-capitalize" title="{{ $req->admin_notes }}">Rejected</span>
                                                @else
                                                    <span class="badge bg-light text-secondary px-2.5 py-1 rounded-pill fs-11 text-capitalize">{{ $req->status }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-end px-4">
                                            <div class="d-flex justify-content-end align-items-center gap-2">
                                                @php
                                                    $allocatedUnitsData = [];
                                                    if (in_array($req->status, ['allocated', 'partially_allocated'])) {
                                                        $unitsList = $req->allocatedAssets;
                                                        if (($unitsList->isEmpty() || !$req->relationLoaded('allocatedAssets')) && \Illuminate\Support\Facades\Schema::hasColumn('assets', 'asset_request_id')) {
                                                            $unitsList = \App\Domains\HRMS\Models\Asset::where('asset_request_id', $req->id)->get();
                                                        }
                                                        if ($unitsList->isNotEmpty()) {
                                                            foreach ($unitsList as $aUnit) {
                                                                $allocatedUnitsData[] = [
                                                                    'code' => $aUnit->asset_code,
                                                                    'serial' => $aUnit->serial_number ?: 'N/A',
                                                                    'name' => $aUnit->name ?: ($req->item->name ?? $req->category->name),
                                                                    'date' => $aUnit->allocated_at ? $aUnit->allocated_at->format('d M, Y') : ($req->updated_at ? $req->updated_at->format('d M, Y') : '-')
                                                                ];
                                                            }
                                                        } elseif ($req->allocatedAsset) {
                                                            $allocatedUnitsData[] = [
                                                                'code' => $req->allocatedAsset->asset_code,
                                                                'serial' => $req->allocatedAsset->serial_number ?: 'N/A',
                                                                'name' => $req->allocatedAsset->name ?: ($req->item->name ?? $req->category->name),
                                                                'date' => $req->allocatedAsset->allocated_at ? $req->allocatedAsset->allocated_at->format('d M, Y') : ($req->updated_at ? $req->updated_at->format('d M, Y') : '-')
                                                            ];
                                                        }
                                                    }
                                                @endphp
                                                <button type="button" class="btn btn-sm btn-icon btn-light view-req-details-btn" 
                                                    style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1.5px solid #cbd5e1; background-color: #ffffff; color: #475569;"
                                                    title="View Details"
                                                    data-emp-name="{{ $req->employee->display_name }}"
                                                    data-emp-id="{{ $req->employee->employee_id }}"
                                                    data-company="{{ $req->company->company_name ?? '' }}"
                                                    data-asset-name="{{ $req->item->name ?? $req->category->name }}"
                                                    data-category="{{ $req->category->name }}"
                                                    data-req-qty="{{ $req->quantity }}"
                                                    data-alloc-qty="{{ $allocatedCount }}"
                                                    data-rem-qty="{{ $remainingQty }}"
                                                    data-status-raw="{{ $req->status }}"
                                                    data-status="{{ ucfirst(str_replace('_', ' ', $req->status)) }}"
                                                    data-date="{{ $req->request_date ? $req->request_date->format('d M, Y') : '-' }}"
                                                    data-action-date="{{ $req->updated_at ? $req->updated_at->format('d M, Y') : '-' }}"
                                                    data-reason="{{ $req->reason ?: 'No reason provided.' }}"
                                                    data-admin-notes="{{ $req->formatted_admin_notes ?: ($req->admin_notes ?: '') }}"
                                                    data-allocated-units="{{ base64_encode(json_encode($allocatedUnitsData)) }}">
                                                    <i class="feather-eye"></i>
                                                </button>

                                                @if(in_array($req->status, ['pending', 'partially_allocated']))
                                                    <button type="button" class="btn btn-sm btn-primary fw-bold allocate-request-trigger-btn px-3" 
                                                        style="font-size: 11px; height: 32px; letter-spacing: 0.5px; border-radius: 6px;"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#allocateAssetModal"
                                                        data-request-id="{{ $req->id }}"
                                                        data-employee-id="{{ $req->employee_id }}"
                                                        data-employee-name="{{ $req->employee->display_name }} ({{ $req->employee->employee_id }})"
                                                        data-asset-item-id="{{ $req->asset_item_id }}"
                                                        data-item-name="{{ $req->item->name ?? 'N/A' }}"
                                                        data-quantity="{{ $req->quantity }}"
                                                        data-allocated-count="{{ $allocatedCount }}"
                                                        data-remaining-qty="{{ $remainingQty }}">
                                                        Fulfill
                                                    </button>

                                                    <button type="button" class="btn btn-sm btn-soft-danger fw-bold reject-request-btn px-3"
                                                        style="font-size: 11px; height: 32px; letter-spacing: 0.5px;"
                                                        data-request-id="{{ $req->id }}">
                                                        Reject
                                                    </button>
                                                @elseif($req->status === 'allocated')
                                                    <span class="text-success fs-12 fw-semibold ms-1"><i class="feather-check-circle me-1"></i>Allocated</span>
                                                @elseif($req->status === 'rejected')
                                                    <span class="text-danger fs-12 fw-semibold ms-1" title="{{ $req->admin_notes }}"><i class="feather-x-circle me-1"></i>Rejected</span>
                                                @else
                                                    <span class="text-muted fs-12 ms-1">-</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted fs-12">
                                            <i class="feather-user-check fs-32 d-block mb-3 text-secondary"></i>
                                            <div class="fw-bold mb-1">No Asset Requests Found</div>
                                            <div>There are no requests submitted by employees.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($requests->hasPages())
                        <div class="bg-white border-top px-4 py-3 mt-3">
                            <x-ui.pagination
                                class="px-0 py-0"
                                :current-page="$requests->currentPage()"
                                :total-pages="$requests->lastPage()"
                                :total-results="$requests->total()"
                                :per-page="$requests->perPage()"
                                page-param="request_page"
                            />
                        </div>
                    @endif
                </div>

                <!-- 2. ALLOCATION HISTORY TAB PANE -->
                <div class="tab-pane fade" id="history-pane" role="tabpanel" aria-labelledby="history-tab">
                    <!-- Filters Toolbar -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
                        <h6 class="fw-bold mb-0 text-dark fs-14">Custody & Assignment Registry</h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <form method="GET" action="{{ route('hrms.assets-module.index') }}" class="d-flex align-items-center gap-2 m-0">
                                @foreach(['request_search', 'request_category_id', 'request_company_id', 'request_status'] as $param)
                                    @if(request()->filled($param))
                                        <input type="hidden" name="{{ $param }}" value="{{ request($param) }}">
                                    @endif
                                @endforeach
                                
                                <div class="d-flex align-items-center border rounded px-3 py-1 bg-light" style="min-width: 220px; max-width: 280px; height: 38px;">
                                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                    <input type="text" name="history_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search history..." value="{{ request('history_search') }}" style="box-shadow: none; height: 32px;">
                                </div>

                                <div class="d-flex gap-2 align-items-center">
                                    <x-ui.filter label="Filter" offset="0, 5">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                        
                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Asset Category</label>
                                            <x-ui.odoo-form-ui type="select" name="history_category_id">
                                                <option value="">All Categories</option>
                                                @foreach($categories as $category)
                                                    <option value="{{ $category->id }}" {{ request('history_category_id') == $category->id ? 'selected' : '' }}>
                                                        {{ $category->name }}
                                                    </option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Org Entity</label>
                                            <x-ui.odoo-form-ui type="select" name="history_company_id">
                                                <option value="">All Companies</option>
                                                @foreach($companies as $company)
                                                    <option value="{{ $company->id }}" {{ request('history_company_id') == $company->id ? 'selected' : '' }}>
                                                        {{ $company->company_name }}
                                                    </option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="d-flex gap-2 justify-content-end mt-4">
                                            <a href="{{ route('hrms.assets-module.index', request()->except(['history_search', 'history_category_id', 'history_company_id'])) }}" class="btn btn-sm btn-light border">Reset</a>
                                            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                        </div>
                                    </x-ui.filter>

                                    @if(request()->anyFilled(['history_search', 'history_category_id', 'history_company_id']))
                                        <a href="{{ route('hrms.assets-module.index', request()->except(['history_search', 'history_category_id', 'history_company_id'])) }}" class="btn btn-sm btn-light border px-2 d-flex align-items-center justify-content-center" style="height: 38px; border-radius: 6px; font-size: 12px;" title="Clear Filters">
                                            <i class="feather-x"></i>
                                        </a>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                            <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                                <tr>
                                    <th class="text-start" style="width: 25%; padding-left: 20px;">Custodian Employee</th>
                                    <th class="text-start" style="width: 50%;">Allocated Assets (Item Details)</th>
                                    <th style="width: 13%;">Total Qty</th>
                                    <th class="text-end px-4" style="width: 12%; white-space: nowrap;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($allocations as $empAlloc)
                                    @php
                                        $grouped = $empAlloc->allocations->groupBy('asset.name');
                                        $encodedUnits = base64_encode(json_encode($empAlloc->allocations->map(function($alloc) {
                                            return [
                                                'id' => $alloc->asset->id,
                                                'asset_code' => $alloc->asset->asset_code,
                                                'serial_number' => $alloc->asset->serial_number ?: 'N/A',
                                                'asset_name' => $alloc->asset->name
                                            ];
                                        })->values()->all()));
                                    @endphp
                                    <tr>
                                        <td class="text-start" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal; padding-left: 20px;">
                                            <div class="fw-bold text-dark fs-13">{{ $empAlloc->display_name }}</div>
                                            <div class="text-muted fs-11 mt-0.5">{{ $empAlloc->employee_id }}</div>
                                            @if($empAlloc->company)
                                                <div class="text-secondary fs-10 mt-0.5">{{ $empAlloc->company->company_name }}</div>
                                            @endif
                                        </td>
                                        <td class="text-start" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                            <div class="d-flex align-items-center flex-wrap gap-2">
                                                @php
                                                    $iterator = 0;
                                                    $totalUnique = $grouped->count();
                                                @endphp
                                                @foreach($grouped as $assetName => $group)
                                                    @if($iterator < 2)
                                                        <span class="badge bg-light text-secondary border px-2.5 py-1.5 align-middle d-inline-flex align-items-center" style="border-radius: 4px; font-weight: 500;">
                                                            <i class="feather-package me-1.5 fs-12 text-secondary"></i>
                                                            <span class="text-dark fw-bold" style="font-size: 11px;">{{ $assetName }}</span>
                                                            <span class="ms-2 badge bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="font-size: 9px; min-width: 16px; height: 16px; padding: 0;">{{ $group->count() }}</span>
                                                        </span>
                                                    @endif
                                                    @php $iterator++; @endphp
                                                @endforeach
                                                
                                                @if($totalUnique > 2)
                                                    <button type="button" class="btn p-0 border-0 bg-transparent show-all-assets-offcanvas-btn align-middle" 
                                                        data-employee-name="{{ $empAlloc->display_name }} ({{ $empAlloc->employee_id }})"
                                                        data-company-name="{{ $empAlloc->company->company_name ?? '' }}"
                                                        data-allocated-assets="{{ $encodedUnits }}">
                                                        <span class="badge bg-light text-secondary border d-inline-flex align-items-center justify-content-center" style="font-size: 11px; cursor: pointer; border-radius: 4px; width: 28px; height: 28px; padding: 0;" title="View all {{ $totalUnique }} assets">
                                                            <i class="feather-plus fs-13"></i>
                                                        </span>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-primary text-primary fw-bold fs-12 px-2.5 py-1">{{ $empAlloc->allocations->count() }}</span>
                                        </td>
                                        <td class="text-end px-4">
                                            <button type="button" class="btn btn-sm btn-soft-danger fw-bold return-direct-multi-trigger-btn px-3" 
                                                style="font-size: 11px; height: 30px; border-radius: 6px;"
                                                data-employee-id="{{ $empAlloc->id }}"
                                                data-employee-name="{{ $empAlloc->display_name }} ({{ $empAlloc->employee_id }})"
                                                data-allocated-assets="{{ $encodedUnits }}">
                                                Return
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted fs-12">
                                            <i class="feather-clock fs-32 d-block mb-3 text-secondary"></i>
                                            <div class="fw-bold mb-1">No Custody History Found</div>
                                            <div>There are no active asset allocations.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($allocations->hasPages())
                        <div class="bg-white border-top px-4 py-3 mt-3">
                            <x-ui.pagination
                                class="px-0 py-0"
                                :current-page="$allocations->currentPage()"
                                :total-pages="$allocations->lastPage()"
                                :total-results="$allocations->total()"
                                :per-page="$allocations->perPage()"
                                page-param="history_page"
                            />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: DIRECT ALLOCATE ASSET -->
<div class="modal fade" id="directAllocateModal" tabindex="-1" aria-labelledby="directAllocateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="directAllocateModalLabel">
                    <i class="feather-plus me-2 text-primary"></i>Allocate Asset Directly
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.assets-module.allocate-direct') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Select Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select select2-modal" required style="width: 100%;">
                                <option value="">Choose Employee...</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->display_name }} ({{ $emp->employee_id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Select Available Asset <span class="text-danger">*</span></label>
                            <select name="asset_id" class="form-select select2-modal" required style="width: 100%;">
                                <option value="">Choose Asset...</option>
                                @foreach($availableAssets as $ast)
                                    <option value="{{ $ast->id }}">{{ $ast->name }} - {{ $ast->asset_code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Allocation Date" name="allocated_at" value="{{ date('Y-m-d') }}" :required="true" />
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Expected Return Date" name="expected_return_date" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Internal Notes" name="notes" placeholder="Reason for allocation, condition details..." />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Allocate</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: RETURN ASSET -->
<div class="modal fade" id="returnAssetModal" tabindex="-1" aria-labelledby="returnAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="returnAssetModalLabel">
                    <i class="feather-corner-up-left me-2 text-primary"></i>Return Asset to Inventory
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="returnAssetForm" action="{{ route('hrms.assets-module.return-direct-multi') }}" method="POST">
                @csrf
                <input type="hidden" name="employee_id" id="return_employee_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Custodian Employee</label>
                            <input type="text" id="return_employee_name_display" class="form-control bg-light" readonly>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-2">Select Serialized Unit(s) to Return <span class="text-danger">*</span></label>
                            <div id="return_assets_checklist" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                <!-- Checklist populated via JS -->
                            </div>
                            <small class="text-muted mt-1 d-block">Check the specific physical units being returned.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Condition on Return <span class="text-danger">*</span></label>
                            <x-ui.odoo-form-ui type="select" name="condition_on_return" :required="true">
                                <option value="good">Good</option>
                                <option value="new">New</option>
                                <option value="fair">Fair</option>
                                <option value="damaged">Damaged (Send to Maintenance)</option>
                                <option value="scrapped">Scrapped</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Return Notes" name="notes" placeholder="Condition details, reason for return..." />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-danger px-4 text-uppercase fw-bold" style="font-size: 11px;">Confirm Return</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: REJECT REQUEST -->
<div class="modal fade" id="rejectRequestModal" tabindex="-1" aria-labelledby="rejectRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="rejectRequestModalLabel">
                    <i class="feather-alert-octagon me-2 text-danger"></i>Reject Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectRequestForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Rejection Reason" name="admin_notes" placeholder="Explain why this request is being rejected..." :required="true" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-danger px-4 text-uppercase fw-bold" style="font-size: 11px;">Reject Request</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: ALLOCATE ASSET FOR REQUEST -->
<div class="modal fade" id="allocateAssetModal" tabindex="-1" aria-labelledby="allocateAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="allocateAssetModalLabel">
                    <i class="feather-user-check me-2 text-primary"></i>Allocate Asset for Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="allocateAssetForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="bg-light p-3 rounded border mb-3 text-dark fs-13">
                        <div>Requester: <strong id="alloc_modal_emp_name">-</strong></div>
                        <div class="mt-1">Requesting: <strong id="alloc_modal_item_name">-</strong></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Select Physical Asset <span class="text-danger">*</span></label>
                            <select name="asset_id" id="alloc_modal_asset_select" class="form-select select2-modal" required style="width: 100%;">
                                <option value="">Choose Asset...</option>
                            </select>
                            <div class="form-text text-danger d-none" id="alloc_modal_no_assets_alert">
                                No available assets found for this category!
                            </div>
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Allocation Date" name="allocated_at" value="{{ date('Y-m-d') }}" :required="true" />
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Expected Return Date" name="expected_return_date" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Fulfill Allocation</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: VIEW ASSET REQUEST DETAILS -->
<div class="modal fade" id="viewRequestDetailsModal" tabindex="-1" aria-labelledby="viewRequestDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom py-2.5 px-4">
                <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="viewRequestDetailsModalLabel">
                    <i class="feather-eye me-2 text-primary"></i>Request Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3.5" style="max-height: 80vh; overflow-y: auto;">
                <div class="card border shadow-none bg-light mb-2.5">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="fs-10 text-uppercase fw-bold text-muted d-block mb-0.5">Requested By</span>
                                <h6 class="fw-bold text-dark mb-0 fs-14" id="req_detail_emp_name">Employee Name</h6>
                                <div class="fs-11 text-muted fw-medium" id="req_detail_emp_id">EMP0000</div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-white text-secondary border px-2.5 py-1 fs-11 fw-semibold mb-1 d-inline-block" id="req_detail_company">Company</span>
                                <div class="fs-11 text-muted fw-medium mt-0.5">
                                    <i class="feather-calendar me-1 text-primary"></i><span id="req_detail_date">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2.5 pt-2 border-top">
                            <span class="fs-10 text-uppercase fw-bold text-muted d-block mb-1"><i class="feather-message-square me-1 text-primary"></i>Reason for Request</span>
                            <div class="fs-12 text-dark" id="req_detail_reason" style="white-space: pre-wrap; line-height: 1.4;">No reason provided.</div>
                        </div>
                    </div>
                </div>

                <div class="border rounded-3 p-3 bg-white mb-2.5">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <div>
                            <span class="fs-10 text-uppercase fw-bold text-muted me-2">Requested Item</span>
                            <span class="badge bg-light text-secondary border px-2 py-0.5 fs-10" id="req_detail_category">Category</span>
                        </div>
                        <div id="req_detail_status_container">
                            <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11" id="req_detail_status">Pending</span>
                        </div>
                    </div>
                    <h6 class="fw-bold text-dark mb-2 fs-14" id="req_detail_asset_name">Asset Name</h6>

                    <div class="d-flex align-items-center gap-2 mt-1">
                        <div class="flex-fill border rounded py-1 px-2 text-center bg-light">
                            <span class="fs-10 text-uppercase text-muted d-block" style="font-size: 9px;">Req</span>
                            <strong class="fs-12 text-dark" id="req_detail_req_qty">0</strong>
                        </div>
                        <div class="flex-fill border rounded py-1 px-2 text-center bg-soft-success border-success-subtle">
                            <span class="fs-10 text-uppercase text-success d-block" style="font-size: 9px;">Allocated</span>
                            <strong class="fs-12 text-success" id="req_detail_alloc_qty">0</strong>
                        </div>
                        <div class="flex-fill border rounded py-1 px-2 text-center bg-soft-danger border-danger-subtle">
                            <span class="fs-10 text-uppercase text-danger d-block" style="font-size: 9px;">Remaining</span>
                            <strong class="fs-12 text-danger" id="req_detail_rem_qty">0</strong>
                        </div>
                    </div>
                </div>

                <div id="req_detail_fulfillment_section" class="d-none">
                    <!-- Allocation Details Box -->
                    <div id="req_detail_allocation_box" class="border rounded-3 p-3 bg-soft-success border-success-subtle d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-10 text-uppercase fw-bold text-success"><i class="feather-check-circle me-1"></i>Allocation Details</span>
                            <span class="fs-11 fw-semibold text-dark" id="req_detail_alloc_date">-</span>
                        </div>
                        <div>
                            <span class="fs-10 text-uppercase text-muted d-block mb-1">Allocated Asset Units</span>
                            <div id="req_detail_allocated_units_list" class="d-flex flex-wrap gap-1.5">
                            </div>
                        </div>
                    </div>

                    <!-- Rejection Details Box -->
                    <div id="req_detail_rejection_box" class="border rounded-3 p-3 bg-soft-danger border-danger-subtle d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-10 text-uppercase fw-bold text-danger"><i class="feather-x-circle me-1"></i>Rejection Details</span>
                            <span class="fs-11 fw-semibold text-dark" id="req_detail_reject_date">-</span>
                        </div>
                        <div>
                            <span class="fs-10 text-uppercase text-muted d-block mb-1">Reason / Admin Notes</span>
                            <div class="fs-12 text-dark fw-medium" id="req_detail_reject_notes">No specific reason provided.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: BULK ALLOCATE ASSETS -->
<div class="modal fade" id="bulkAllocateModal" tabindex="-1" aria-labelledby="bulkAllocateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="bulkAllocateModalLabel">
                    <i class="feather-user-check me-2 text-primary"></i>Bulk Allocate Assets
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkAllocateForm" action="{{ route('hrms.assets.requests.bulk-allocate') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle text-center mb-0" id="bulk_allocate_table">
                            <thead class="table-light fs-11 text-uppercase">
                                <tr>
                                    <th class="text-start" style="width: 25%;">Employee</th>
                                    <th class="text-start" style="width: 30%;">Requested Item</th>
                                    <th class="text-start" style="width: 45%;">Available Units</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dynamic rows populated via JS -->
                            </tbody>
                        </table>
                    </div>

                    <input type="hidden" name="allocated_at" value="{{ date('Y-m-d') }}">
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Confirm Bulk Allocation</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: BULK REJECT -->
<div class="modal fade" id="bulkRejectModal" tabindex="-1" aria-labelledby="bulkRejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="bulkRejectModalLabel">
                    <i class="feather-x me-2 text-danger"></i>Bulk Reject Requests
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkRejectForm" action="{{ route('hrms.assets.requests.bulk-reject') }}" method="POST">
                @csrf
                <div id="bulk_reject_ids_container"></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Bulk Rejection Reason" name="admin_notes" placeholder="Explain why the selected requests are rejected..." :required="true" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-danger px-4 text-uppercase fw-bold" style="font-size: 11px;">Reject All Selected</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- OFFCANVAS: VIEW ALL EMPLOYEE ASSETS -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="employeeAssetsOffcanvas" aria-labelledby="employeeAssetsOffcanvasLabel" style="width: 420px; border-left: 1px solid #e2e8f0; box-shadow: -4px 0 24px rgba(0,0,0,0.08);">
    <div class="offcanvas-header border-bottom py-3 px-4">
        <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0" id="employeeAssetsOffcanvasLabel">
            <i class="feather-package me-2 text-primary"></i>Custodian Assets List
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4">
        <div class="card border bg-light shadow-none mb-4">
            <div class="card-body p-3">
                <span class="fs-10 text-uppercase fw-bold text-muted d-block mb-1">Custodian Employee</span>
                <h6 class="fw-bold text-dark mb-0.5 fs-13" id="offcanvas_emp_name">-</h6>
                <span class="text-secondary fs-11" id="offcanvas_emp_company">-</span>
            </div>
        </div>
        
        <div>
            <span class="fs-10 text-uppercase fw-bold text-muted d-block mb-2.5">Currently Allocated Items</span>
            <div id="offcanvas_assets_list" class="d-flex flex-column gap-2">
                <!-- Populated via JS -->
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const allAvailableAssets = {!! json_encode($availableAssets->map(function($a) {
        return [
            'id' => $a->id,
            'name' => $a->name . ' (' . $a->asset_code . ')',
            'asset_code' => $a->asset_code,
            'serial_number' => $a->serial_number,
            'category_id' => $a->asset_category_id,
            'asset_item_id' => $a->asset_item_id,
            'company_id' => $a->company_id,
            'status' => $a->status ?? 'available'
        ];
    })) !!};

    // Helper for URL parameters
    function updateUrlParameter(url, param, value) {
        var newUrl = new URL(url);
        if (value) {
            newUrl.searchParams.set(param, value);
        } else {
            newUrl.searchParams.delete(param);
        }
        return newUrl.toString();
    }

    function changeSort(type, sortVal, element) {
        var currentUrl = window.location.href;
        var nextUrl = updateUrlParameter(currentUrl, type + '_sort', sortVal);
        window.location.href = nextUrl;
    }

    $(document).ready(function() {
        // Append modals to body to prevent Bootstrap backdrop overlay issues
        $('#directAllocateModal').appendTo('body');
        $('#returnAssetModal').appendTo('body');
        $('#rejectRequestModal').appendTo('body');
        $('#allocateAssetModal').appendTo('body');
        $('#viewRequestDetailsModal').appendTo('body');
        $('#bulkAllocateModal').appendTo('body');
        $('#bulkRejectModal').appendTo('body');

        // Initialize select2 elements in modals
        $('.select2-modal').each(function() {
            var dropdownParent = $(this).closest('.modal');
            $(this).select2({
                dropdownParent: dropdownParent,
                theme: 'bootstrap-5',
                width: '100%'
            });
        });

        // Retain active tab on page reload
        var activeTab = localStorage.getItem('activeAssetsTab') || '#requests-tab';
        var activeTabTrigger = document.querySelector('button[data-bs-target="' + activeTab.replace('-tab', '-pane') + '"]');
        if (activeTabTrigger) {
            bootstrap.Tab.getOrCreateInstance(activeTabTrigger).show();
        }

        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
            var targetId = $(e.target).attr('id');
            localStorage.setItem('activeAssetsTab', '#' + targetId);
        });

        // 1. REJECT REQUEST ACTION
        $('.reject-request-btn').on('click', function() {
            var requestId = $(this).attr('data-request-id');
            var actionUrl = "{{ route('hrms.assets.requests.reject', ':id') }}".replace(':id', requestId);
            
            $('#rejectRequestForm').attr('action', actionUrl);
            $('#rejectRequestForm').find('textarea[name="admin_notes"]').val('');
            
            var rejectModal = new bootstrap.Modal(document.getElementById('rejectRequestModal'));
            rejectModal.show();
        });

        // 2. ALLOCATE ASSET FOR REQUEST ACTION
        $('.allocate-request-trigger-btn').on('click', function() {
            var button = $(this);
            var requestId = button.attr('data-request-id');
            var employeeId = button.attr('data-employee-id');
            var employeeName = button.attr('data-employee-name');
            var assetItemId = button.attr('data-asset-item-id');
            var itemName = button.attr('data-item-name');

            var actionUrl = "{{ route('hrms.assets.requests.allocate', ':id') }}".replace(':id', requestId);
            $('#allocateAssetForm').attr('action', actionUrl);

            $('#alloc_modal_emp_name').text(employeeName);
            $('#alloc_modal_item_name').text(itemName);

            // Populate available asset options based on requested category / item
            var select = $('#alloc_modal_asset_select');
            select.empty().append('<option value="">Choose Asset...</option>');

            var filteredAssets = allAvailableAssets.filter(function(a) {
                return a.asset_item_id == assetItemId;
            });

            if (filteredAssets.length > 0) {
                filteredAssets.forEach(function(a) {
                    select.append('<option value="' + a.id + '">' + a.name + '</option>');
                });
                $('#alloc_modal_no_assets_alert').addClass('d-none');
                $('#allocateAssetForm').find('button[type="submit"]').prop('disabled', false);
            } else {
                $('#alloc_modal_no_assets_alert').removeClass('d-none');
                $('#allocateAssetForm').find('button[type="submit"]').prop('disabled', true);
            }

            select.val('').trigger('change');
        });

        // 3. VIEW DETAILS MODAL
        $('.view-req-details-btn').on('click', function() {
            var btn = $(this);
            
            $('#req_detail_emp_name').text(btn.attr('data-emp-name'));
            $('#req_detail_emp_id').text(btn.attr('data-emp-id'));
            $('#req_detail_company').text(btn.attr('data-company') || 'N/A');
            $('#req_detail_date').text(btn.attr('data-date'));
            $('#req_detail_reason').text(btn.attr('data-reason'));
            $('#req_detail_category').text(btn.attr('data-category'));
            $('#req_detail_asset_name').text(btn.attr('data-asset-name'));
            $('#req_detail_req_qty').text(btn.attr('data-req-qty'));
            $('#req_detail_alloc_qty').text(btn.attr('data-alloc-qty'));
            $('#req_detail_rem_qty').text(btn.attr('data-rem-qty'));

            var statusRaw = btn.attr('data-status-raw');
            var statusLabel = btn.attr('data-status');
            var statusContainer = $('#req_detail_status_container');
            statusContainer.empty();

            var badgeClass = 'bg-soft-warning text-warning';
            if (statusRaw === 'allocated') {
                badgeClass = 'bg-soft-success text-success';
            } else if (statusRaw === 'partially_allocated') {
                badgeClass = 'bg-soft-info text-info';
            } else if (statusRaw === 'rejected') {
                badgeClass = 'bg-soft-danger text-danger';
            }
            statusContainer.append('<span class="badge ' + badgeClass + ' px-2.5 py-1 rounded-pill fs-11">' + statusLabel + '</span>');

            var fulfillSection = $('#req_detail_fulfillment_section');
            var allocBox = $('#req_detail_allocation_box');
            var rejectBox = $('#req_detail_rejection_box');

            fulfillSection.addClass('d-none');
            allocBox.addClass('d-none');
            rejectBox.addClass('d-none');

            if (statusRaw === 'allocated' || statusRaw === 'partially_allocated') {
                fulfillSection.removeClass('d-none');
                allocBox.removeClass('d-none');
                $('#req_detail_alloc_date').text(btn.attr('data-action-date'));

                var listContainer = $('#req_detail_allocated_units_list');
                listContainer.empty();

                try {
                    var rawUnits = btn.attr('data-allocated-units');
                    if (rawUnits) {
                        var units = JSON.parse(atob(rawUnits));
                        if (units.length > 0) {
                            units.forEach(function(unit) {
                                listContainer.append(
                                    '<div class="badge bg-light text-dark border px-2.5 py-1.5 text-start w-100 rounded-3 mb-1.5">' +
                                        '<div class="fw-bold fs-11">' + unit.code + '</div>' +
                                        '<div class="text-muted fs-10 mt-0.5">' + unit.name + ' • Serial: ' + unit.serial + '</div>' +
                                    '</div>'
                                );
                            });
                        } else {
                            listContainer.append('<span class="text-muted fs-12">No specific asset codes mapped.</span>');
                        }
                    }
                } catch(e) {
                    console.error("Error parsing units details:", e);
                }
            } else if (statusRaw === 'rejected') {
                fulfillSection.removeClass('d-none');
                rejectBox.removeClass('d-none');
                $('#req_detail_reject_date').text(btn.attr('data-action-date'));
                $('#req_detail_reject_notes').text(btn.attr('data-admin-notes') || 'No specific notes provided.');
            }

            var detailModal = new bootstrap.Modal(document.getElementById('viewRequestDetailsModal'));
            detailModal.show();
        });

        // 4. RETURN DIRECT ACTION WITH MULTI-SELECT CHECKLIST
        $(document).on('click', '.return-direct-multi-trigger-btn', function() {
            var button = $(this);
            var employeeId = button.attr('data-employee-id');
            var employeeName = button.attr('data-employee-name');
            var rawAssets = button.attr('data-allocated-assets');

            $('#return_employee_id').val(employeeId);
            $('#return_employee_name_display').val(employeeName);

            var checklistDiv = $('#return_assets_checklist');
            checklistDiv.empty();

            var assets = [];
            if (rawAssets) {
                assets = JSON.parse(atob(rawAssets));
            }

            if (assets.length === 0) {
                checklistDiv.html('<span class="text-danger fs-12"><i class="feather-alert-triangle me-1"></i>No active allocations found.</span>');
            } else {
                assets.forEach(function(asset) {
                    var checkboxId = 'return_asset_check_' + asset.id;
                    var itemHtml = `
                        <div class="form-check py-1 border-bottom-dashed d-flex align-items-center">
                            <input class="form-check-input return-allocated-asset-checkbox" type="checkbox" name="allocated_asset_ids[]" value="${asset.id}" id="${checkboxId}" style="cursor: pointer;">
                            <label class="form-check-label fs-12 ms-2 text-dark mb-0" for="${checkboxId}" style="cursor: pointer;">
                                <strong>${asset.asset_name}</strong> (Code: ${asset.asset_code} | Serial: ${asset.serial_number || 'N/A'})
                            </label>
                        </div>
                    `;
                    checklistDiv.append(itemHtml);
                });
            }

            $('#returnAssetForm').off('submit').on('submit', function(e) {
                var checkedCount = $('.return-allocated-asset-checkbox:checked').length;
                if (checkedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one physical asset/serial number to return.');
                }
            });

            var returnModal = new bootstrap.Modal(document.getElementById('returnAssetModal'));
            returnModal.show();
        });

        // 5. BULK SELECTION LOGIC
        $('#selectAllRequests').on('change', function() {
            $('.request-select-checkbox').prop('checked', this.checked).trigger('change');
        });

        $(document).on('change', '.request-select-checkbox', function() {
            var selectedCheckboxes = $('.request-select-checkbox:checked');
            var count = selectedCheckboxes.length;
            $('#selectedRequestsCount').text(count);

            if (count > 0) {
                $('#bulkActionsToolbar').removeClass('d-none');
            } else {
                $('#bulkActionsToolbar').addClass('d-none');
            }
        });

        // 6. BULK ALLOCATE MODAL GENERATOR
        $('#btnBulkAllocate').on('click', function() {
            var selectedCheckboxes = $('.request-select-checkbox:checked');
            var tbody = $('#bulk_allocate_table tbody');
            tbody.empty();

            selectedCheckboxes.each(function() {
                var cb = $(this);
                var reqId = cb.val();
                var empName = cb.attr('data-employee-name');
                var itemName = cb.attr('data-item-name');
                var catName = cb.attr('data-category-name');
                var assetItemId = cb.attr('data-item-id');
                var compId = cb.attr('data-company-id');
                var reqQty = cb.attr('data-quantity');
                var allocatedCount = cb.attr('data-allocated-count');
                var remainingQty = cb.attr('data-remaining-qty');

                // Filter available options for this item
                var optionsHtml = '<option value="">Choose Asset...</option>';
                var itemAssets = allAvailableAssets.filter(function(a) {
                    return a.asset_item_id == assetItemId;
                });

                itemAssets.forEach(function(a) {
                    optionsHtml += '<option value="' + a.id + '">' + a.name + '</option>';
                });

                var rowHtml = '<tr>' +
                    '<td class="text-start fs-12 fw-bold text-dark">' + empName + '</td>' +
                    '<td class="text-start">' +
                        '<div class="fs-12 fw-semibold text-dark">' + itemName + '</div>' +
                        '<div class="text-muted fs-10">' + catName + ' (Qty: ' + remainingQty + ')</div>' +
                    '</td>' +
                    '<td class="text-start">' +
                        '<select name="allocations[' + reqId + ']" class="form-select fs-12 py-1 select2-modal" required style="width: 100%;">' +
                            optionsHtml +
                        '</select>' +
                    '</td>' +
                '</tr>';

                tbody.append(rowHtml);
            });

            // Initialize select2 inside the modal
            var bulkModal = new bootstrap.Modal(document.getElementById('bulkAllocateModal'));
            bulkModal.show();

            $('#bulkAllocateModal .select2-modal').each(function() {
                $(this).select2({
                    dropdownParent: $('#bulkAllocateModal'),
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            });
        });

        // 7. BULK REJECT MODAL GENERATOR
        $('#btnBulkReject').on('click', function() {
            var selectedCheckboxes = $('.request-select-checkbox:checked');
            var container = $('#bulk_reject_ids_container');
            container.empty();

            selectedCheckboxes.each(function() {
                container.append('<input type="hidden" name="request_ids[]" value="' + $(this).val() + '">');
            });

            var bulkRejectModal = new bootstrap.Modal(document.getElementById('bulkRejectModal'));
            bulkRejectModal.show();
        });

        // 8. SHOW ALL ASSETS IN OFFCANVAS
        $(document).on('click', '.show-all-assets-offcanvas-btn', function() {
            var button = $(this);
            var empName = button.attr('data-employee-name');
            var compName = button.attr('data-company-name');
            var rawAssets = button.attr('data-allocated-assets');

            $('#offcanvas_emp_name').text(empName);
            $('#offcanvas_emp_company').text(compName || 'N/A');

            var listDiv = $('#offcanvas_assets_list');
            listDiv.empty();

            var assets = [];
            if (rawAssets) {
                assets = JSON.parse(atob(rawAssets));
            }

            // Group assets by name on the client side
            var groupedAssets = {};
            assets.forEach(function(asset) {
                if (!groupedAssets[asset.asset_name]) {
                    groupedAssets[asset.asset_name] = 0;
                }
                groupedAssets[asset.asset_name]++;
            });

            Object.keys(groupedAssets).forEach(function(name) {
                var qty = groupedAssets[name];
                var itemHtml = `
                    <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <i class="feather-package text-secondary me-2.5 fs-15"></i>
                            <span class="fw-bold text-dark fs-12">${name}</span>
                        </div>
                        <span class="badge bg-soft-secondary text-secondary border px-2.5 py-1 fs-11 fw-bold">Qty: ${qty}</span>
                    </div>
                `;
                listDiv.append(itemHtml);
            });

            var offcanvas = new bootstrap.Offcanvas(document.getElementById('employeeAssetsOffcanvas'));
            offcanvas.show();
        });
    });
</script>
@endpush
