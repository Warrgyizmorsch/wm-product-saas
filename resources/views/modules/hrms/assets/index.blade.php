@extends('layouts.duralux')

@section('title', 'ASSET MANAGEMENT | SaaS ERP')
@section('page-title', 'Asset Management')
@section('breadcrumb', 'HRMS / Asset Management')

@section('page-actions')
    <div id="hdr-btn-log-asset" class="d-none d-flex align-items-center gap-2">
        <x-ui.button variant="outline-primary" icon="feather-upload" data-bs-toggle="modal" data-bs-target="#importAssetModal" class="fw-bold text-uppercase">
            Import
        </x-ui.button>
        <x-ui.button variant="outline-primary" icon="feather-download" href="{{ route('hrms.assets.export') }}" id="btn-export-assets-link" class="fw-bold text-uppercase">
            Export
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addAssetModal" class="fw-bold text-uppercase">
            {{ __('hrms.assets.log_asset') }}
        </x-ui.button>
    </div>
    <div id="hdr-btn-add-category" class="d-none d-flex align-items-center gap-2">
        <x-ui.button variant="outline-primary" icon="feather-upload" data-bs-toggle="modal" data-bs-target="#importCategoryModal" class="fw-bold text-uppercase">
            Import
        </x-ui.button>
        <x-ui.button variant="outline-primary" icon="feather-download" href="{{ route('hrms.assets.categories.export') }}" id="btn-export-categories-link" class="fw-bold text-uppercase">
            Export
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addCategoryModal" class="fw-bold text-uppercase">
            {{ __('hrms.assets.add_category') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Modern layouts for connected settings sidebar */
        @media (min-width: 992px) {
            .nxl-content {
                padding: 0 !important;
            }
            .page-header {
                padding: 24px 24px 16px 24px !important;
                margin-bottom: 0 !important;
                border-bottom: 1px solid #e5e7eb;
                background-color: #fff;
            }
            .main-content {
                padding: 0 !important;
            }
            .settings-container {
                display: flex;
                min-height: calc(100vh - 120px);
                background-color: #f8fafc;
            }
            .settings-sidebar-col {
                width: 280px;
                min-width: 280px;
                background-color: #fff;
                border-right: 1px solid #e5e7eb;
                display: flex;
                flex-direction: column;
            }
            .settings-content-col {
                flex-grow: 1;
                padding: 24px 30px;
                background-color: #f8fafc;
                min-width: 0;
            }
        }

        @media (max-width: 991.98px) {
            .settings-sidebar-col {
                width: 100%;
                background-color: #fff;
                border-bottom: 1px solid #e5e7eb;
                margin-bottom: 20px;
                padding: 10px;
            }
            .settings-content-col {
                width: 100%;
                padding: 0 15px;
            }
        }

        /* Tabs styling */
        #assetModuleTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 600;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
        }
        #assetModuleTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #assetModuleTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
        }

        .badge-available {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #10b981 !important;
            font-weight: 600;
        }
        .badge-allocated {
            background-color: rgba(13, 110, 253, 0.08) !important;
            color: var(--bs-primary) !important;
            font-weight: 600;
        }
        .badge-maintenance {
            background-color: rgba(245, 158, 11, 0.08) !important;
            color: #f59e0b !important;
            font-weight: 600;
        }
        .badge-scrapped {
            background-color: rgba(100, 116, 139, 0.08) !important;
            color: #64748b !important;
            font-weight: 600;
        }
        .badge-new {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #10b981 !important;
            font-weight: 600;
        }
        .badge-good {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #10b981 !important;
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
        #registry-pane .table-responsive {
            min-height: 350px;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="settings-container">
        <!-- Left Subsidebar Column -->
        <div class="settings-sidebar-col">
            @include('modules.hrms.partials.settings-sidebar')
        </div>

        <!-- Right Content Column -->
        <div class="settings-content-col flex-grow-1">
            @if(session('success'))
                <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                    {{ session('success') }}
                </x-ui.alert>
            @endif
            @if(session('error'))
                <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                    {{ session('error') }}
                </x-ui.alert>
            @endif

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs border-bottom mb-4" id="assetModuleTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="registry-tab" data-bs-toggle="tab" data-bs-target="#registry-pane" type="button" role="tab" aria-controls="registry-pane" aria-selected="true">
                        <i class="feather-package me-2"></i>{{ __('hrms.assets.tab_registry') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-pane" type="button" role="tab" aria-controls="categories-pane" aria-selected="false">
                        <i class="feather-sliders me-2"></i>{{ __('hrms.assets.tab_categories') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests-pane" type="button" role="tab" aria-controls="requests-pane" aria-selected="false">
                        <i class="feather-user-check me-2"></i>{{ __('hrms.assets.tab_requests') }}
                        @if($pendingRequestsCount > 0)
                            <span class="badge bg-danger rounded-circle p-1 ms-1" style="font-size: 9px; min-width: 16px; min-height: 16px; line-height: 8px;">
                                {{ $pendingRequestsCount }}
                            </span>
                        @endif
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="assetModuleTabsContent">
                <!-- 1. ASSET REGISTRY TAB -->
                <div class="tab-pane fade show active" id="registry-pane" role="tabpanel" aria-labelledby="registry-tab">
                    <div class="card border rounded bg-white shadow-sm">
                        <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center py-3 px-4 bg-white gap-3">
                            <div>
                                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.assets.directory_title') }}</h5>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <!-- Registry Search & Filter Form -->
                                <form method="GET" action="{{ route('hrms.assets.index') }}" class="d-flex align-items-center gap-2 m-0">
                                    @foreach(['category_search', 'category_company_id', 'request_search', 'request_category_id', 'request_company_id', 'request_status'] as $param)
                                        @if(request()->filled($param))
                                            <input type="hidden" name="{{ $param }}" value="{{ request($param) }}">
                                        @endif
                                    @endforeach
                                    <input type="hidden" name="registry_sort" id="registry_sort" value="{{ request('registry_sort', 'code_asc') }}">
                                    
                                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                        <input type="text" name="registry_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.assets.search_placeholder') }}" value="{{ request('registry_search') }}" style="box-shadow: none; height: 32px;">
                                    </div>

                                    <div class="d-flex gap-2">
                                        <x-ui.sort-dropdown label="SORT">
                                            <a class="dropdown-item py-2 {{ request('registry_sort', 'code_asc') == 'code_asc' ? 'active' : '' }}" href="#" onclick="changeSort('registry', 'code_asc', this); event.preventDefault();">Code (A-Z)</a>
                                            <a class="dropdown-item py-2 {{ request('registry_sort') == 'code_desc' ? 'active' : '' }}" href="#" onclick="changeSort('registry', 'code_desc', this); event.preventDefault();">Code (Z-A)</a>
                                            <a class="dropdown-item py-2 {{ request('registry_sort') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('registry', 'name_asc', this); event.preventDefault();">Name (A-Z)</a>
                                            <a class="dropdown-item py-2 {{ request('registry_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('registry', 'name_desc', this); event.preventDefault();">Name (Z-A)</a>
                                            <a class="dropdown-item py-2 {{ request('registry_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('registry', 'newest', this); event.preventDefault();">Newest First</a>
                                        </x-ui.sort-dropdown>

                                        <x-ui.filter label="{{ __('hrms.assets.filters') }}" offset="0, 5">
                                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                            
                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Asset Category</label>
                                                <x-ui.odoo-form-ui type="select" name="registry_category_id">
                                                    <option value="">All Categories</option>
                                                    @foreach($categories as $category)
                                                        <option value="{{ $category->id }}" {{ request('registry_category_id') == $category->id ? 'selected' : '' }}>
                                                            {{ $category->name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                                <x-ui.odoo-form-ui type="select" name="registry_status">
                                                    <option value="">All Statuses</option>
                                                    <option value="available" {{ request('registry_status') === 'available' ? 'selected' : '' }}>Available</option>
                                                    <option value="allocated" {{ request('registry_status') === 'allocated' ? 'selected' : '' }}>Allocated</option>
                                                    <option value="maintenance" {{ request('registry_status') === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                                    <option value="scrapped" {{ request('registry_status') === 'scrapped' ? 'selected' : '' }}>Scrapped</option>
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Condition</label>
                                                <x-ui.odoo-form-ui type="select" name="registry_condition">
                                                    <option value="">All Conditions</option>
                                                    <option value="new" {{ request('registry_condition') === 'new' ? 'selected' : '' }}>New</option>
                                                    <option value="good" {{ request('registry_condition') === 'good' ? 'selected' : '' }}>Good</option>
                                                    <option value="fair" {{ request('registry_condition') === 'fair' ? 'selected' : '' }}>Fair</option>
                                                    <option value="damaged" {{ request('registry_condition') === 'damaged' ? 'selected' : '' }}>Damaged</option>
                                                    <option value="scrapped" {{ request('registry_condition') === 'scrapped' ? 'selected' : '' }}>Scrapped</option>
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="d-flex gap-2 justify-content-end mt-4">
                                                <a href="{{ route('hrms.assets.index', request()->except(['registry_search', 'registry_category_id', 'registry_status', 'registry_condition'])) }}" class="btn btn-sm btn-light border">Reset</a>
                                                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                            </div>
                                        </x-ui.filter>

                                    @if(request()->anyFilled(['registry_search', 'registry_category_id', 'registry_status', 'registry_condition']))
                                        <a href="{{ route('hrms.assets.index', request()->except(['registry_search', 'registry_category_id', 'registry_status', 'registry_condition'])) }}" class="btn btn-sm btn-light border px-2 d-flex align-items-center justify-content-center" style="height: 38px; border-radius: 6px; font-size: 12px;" title="Clear Filters">
                                            <i class="feather-x"></i>
                                        </a>
                                    @endif
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 text-center">
                                    <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                                        <tr>
                                            <th class="text-start" style="padding-left: 20px; width: 140px;">{{ __('hrms.assets.asset_tag') }}</th>
                                            <th class="text-start">{{ __('hrms.assets.asset_brand') }}</th>
                                            <th>{{ __('hrms.assets.category') }}</th>
                                            <th>{{ __('hrms.assets.serial_number') }}</th>
                                            <th>{{ __('hrms.assets.status') }}</th>
                                            <th>{{ __('hrms.assets.condition') }}</th>
                                            <th>{{ __('hrms.assets.holder') }}</th>
                                            <th class="text-end" style="padding-right: 20px; width: 220px;">{{ __('hrms.assets.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($assets as $asset)
                                            <tr>
                                                <td class="text-start" style="padding-left: 20px;">
                                                    <code class="fw-bold fs-13 text-dark">{{ $asset->asset_code }}</code>
                                                </td>
                                                <td class="text-start">
                                                    <div class="fw-bold text-dark fs-13">{{ $asset->name }}</div>
                                                    <small class="text-muted fs-11">{{ $asset->brand }} {{ $asset->model_number }}</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-secondary border px-2 py-1">{{ $asset->category->name }}</span>
                                                </td>
                                                <td>
                                                    <span class="text-dark fw-semibold fs-13">{{ $asset->serial_number ?: 'N/A' }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-{{ $asset->status }} px-2 py-1 text-capitalize">
                                                        {{ $asset->status }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-{{ $asset->condition }} px-2 py-1 text-capitalize">
                                                        {{ $asset->condition }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($asset->assignedEmployee)
                                                        <div class="fw-bold text-dark fs-12">{{ $asset->assignedEmployee->display_name }}</div>
                                                        <small class="text-muted fs-10" style="font-size: 10px;">Since {{ $asset->allocated_at ? $asset->allocated_at->format('d M, Y') : '-' }}</small>
                                                    @else
                                                        <span class="text-muted fs-12">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-end" style="padding-right: 20px;">
                                                    <div class="d-flex justify-content-end align-items-center gap-2">
                                                        @if($asset->status === 'available')
                                                            <button type="button" class="action-dropdown-btn text-uppercase fw-bold px-3" style="width: auto !important; font-size: 11px; letter-spacing: 0.5px; height: 32px;" data-bs-toggle="modal" data-bs-target="#allocateAssetModal" data-asset-id="{{ $asset->id }}" data-asset-name="{{ $asset->name }} ({{ $asset->asset_code }})" data-company-id="{{ $asset->company_id }}">
                                                                {{ __('hrms.assets.allocate') }}
                                                            </button>
                                                        @elseif($asset->status === 'allocated')
                                                            <button type="button" class="action-dropdown-btn text-uppercase fw-bold px-3" style="width: auto !important; font-size: 11px; letter-spacing: 0.5px; height: 32px;" data-bs-toggle="modal" data-bs-target="#returnAssetModal" data-asset-id="{{ $asset->id }}" data-asset-name="{{ $asset->name }} ({{ $asset->asset_code }})">
                                                                {{ __('hrms.assets.release') }}
                                                            </button>
                                                        @endif

                                                        <x-ui.action-dropdown>
                                                            <li>
                                                                <a class="dropdown-item show-history-btn" href="javascript:void(0)" data-asset-name="{{ $asset->name }} ({{ $asset->asset_code }})" data-allocations="{{ base64_encode($asset->allocations()->with('employee')->get()->toJson()) }}">
                                                                    <i class="feather feather-clock me-3"></i>
                                                                    <span>{{ __('hrms.assets.history_log') }}</span>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editAssetModal" 
                                                                    data-asset-id="{{ $asset->id }}" 
                                                                    data-asset-category-id="{{ $asset->asset_category_id }}" 
                                                                    data-asset-code="{{ $asset->asset_code }}" 
                                                                    data-name="{{ $asset->name }}" 
                                                                    data-brand="{{ $asset->brand }}" 
                                                                    data-model-number="{{ $asset->model_number }}" 
                                                                    data-serial-number="{{ $asset->serial_number }}" 
                                                                    data-purchase-date="{{ $asset->purchase_date ? $asset->purchase_date->format('Y-m-d') : '' }}" 
                                                                    data-purchase-cost="{{ $asset->purchase_cost }}" 
                                                                    data-condition="{{ $asset->condition }}" 
                                                                    data-notes="{{ $asset->notes }}">
                                                                    <i class="feather feather-edit-3 me-3"></i>
                                                                    <span>{{ __('hrms.assets.edit') }}</span>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <hr class="dropdown-divider">
                                                            </li>
                                                            <li>
                                                                <form action="{{ route('hrms.assets.destroy', $asset->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this asset record?');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                                        <i class="feather feather-trash-2 me-3"></i>
                                                                        <span>{{ __('hrms.assets.delete') }}</span>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </x-ui.action-dropdown>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center py-5 text-muted fs-12">
                                                    <i class="feather-package fs-32 d-block mb-3 text-secondary"></i>
                                                    <div class="fw-bold mb-1">No Assets Logged Yet</div>
                                                    <div>Click "Log Asset" to start tracking hardware devices.</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @php
                                $currentPage = $assets->currentPage();
                                $totalPages = $assets->lastPage();
                                $totalResults = $assets->total();
                                $perPage = $assets->perPage();
                            @endphp
                            @if($assets->hasPages())
                                <div class="card-footer bg-white border-top px-4 py-3">
                                    <x-ui.pagination
                                        class="px-0 py-0"
                                        :current-page="$currentPage"
                                        :total-pages="$totalPages"
                                        :total-results="$totalResults"
                                        :per-page="$perPage"
                                    />
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 2. ASSET CATEGORIES TAB -->
                <div class="tab-pane fade" id="categories-pane" role="tabpanel" aria-labelledby="categories-tab">
                    <div class="card border rounded bg-white shadow-sm">
                        <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center py-3 px-4 bg-white gap-3">
                            <div>
                                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">Asset Configurations & Categories</h5>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <!-- Categories Search & Filter Form -->
                                <form method="GET" action="{{ route('hrms.assets.index') }}" class="d-flex align-items-center gap-2 m-0">
                                    @foreach(['registry_search', 'registry_category_id', 'registry_status', 'registry_condition', 'request_search', 'request_category_id', 'request_company_id', 'request_status'] as $param)
                                        @if(request()->filled($param))
                                            <input type="hidden" name="{{ $param }}" value="{{ request($param) }}">
                                        @endif
                                    @endforeach
                                    <input type="hidden" name="category_sort" id="category_sort" value="{{ request('category_sort', 'name_asc') }}">
                                    
                                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                        <input type="text" name="category_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search categories..." value="{{ request('category_search') }}" style="box-shadow: none; height: 32px;">
                                    </div>

                                    <div class="d-flex gap-2">
                                        <x-ui.sort-dropdown label="SORT">
                                            <a class="dropdown-item py-2 {{ request('category_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'name_asc', this); event.preventDefault();">Name (A-Z)</a>
                                            <a class="dropdown-item py-2 {{ request('category_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'name_desc', this); event.preventDefault();">Name (Z-A)</a>
                                            <a class="dropdown-item py-2 {{ request('category_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'newest', this); event.preventDefault();">Newest First</a>
                                        </x-ui.sort-dropdown>

                                        <x-ui.filter label="Filters" offset="0, 5">
                                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                            
                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Organization Entity</label>
                                                <x-ui.odoo-form-ui type="select" name="category_company_id">
                                                    <option value="">All Companies</option>
                                                    @foreach($companies as $company)
                                                        <option value="{{ $company->id }}" {{ request('category_company_id') == $company->id ? 'selected' : '' }}>
                                                            {{ $company->company_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="d-flex gap-2 justify-content-end mt-4">
                                                <a href="{{ route('hrms.assets.index', request()->except(['category_search', 'category_company_id'])) }}" class="btn btn-sm btn-light border">Reset</a>
                                                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                            </div>
                                        </x-ui.filter>

                                    @if(request()->anyFilled(['category_search', 'category_company_id']))
                                        <a href="{{ route('hrms.assets.index', request()->except(['category_search', 'category_company_id'])) }}" class="btn btn-sm btn-light border px-2 d-flex align-items-center justify-content-center" style="height: 38px; border-radius: 6px; font-size: 12px;" title="Clear Filters">
                                            <i class="feather-x"></i>
                                        </a>
                                    @endif
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 text-center">
                                    <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                                        <tr>
                                            <th class="text-start" style="padding-left: 20px;">{{ __('hrms.assets.category_name') }}</th>
                                            <th class="text-start">{{ __('hrms.assets.description') }}</th>
                                            <th>{{ __('hrms.assets.total_assets') }}</th>
                                            <th>{{ __('hrms.assets.org_entity') }}</th>
                                            <th>{{ __('hrms.assets.created_at') }}</th>
                                            <th class="text-end" style="padding-right: 20px;">{{ __('hrms.assets.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($filteredCategories as $category)
                                            <tr>
                                                <td class="text-start" style="padding-left: 20px; font-weight: 700; color: #0f172a;">
                                                    {{ $category->name }}
                                                </td>
                                                <td class="text-start text-muted fs-12" style="max-width: 300px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                                    {{ $category->description ?: 'No description provided.' }}
                                                </td>
                                                <td>
                                                    <span class="badge bg-soft-primary text-primary px-3 py-1 rounded-pill">{{ $category->assets()->count() }}</span>
                                                </td>
                                                <td>{{ $category->company->company_name }}</td>
                                                <td class="text-muted fs-12">{{ $category->created_at->format('d M, Y') }}</td>
                                                <td class="text-end" style="padding-right: 20px;">
                                                    <x-ui.action-dropdown>
                                                        <li>
                                                            <a class="dropdown-item edit-category-btn" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                                                data-category-id="{{ $category->id }}"
                                                                data-company-id="{{ $category->company_id }}"
                                                                data-name="{{ $category->name }}"
                                                                data-description="{{ $category->description }}">
                                                                <i class="feather feather-edit-3 me-3"></i>
                                                                <span>Edit</span>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <hr class="dropdown-divider">
                                                        </li>
                                                        <li>
                                                            <form action="{{ route('hrms.assets.category.destroy', $category->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this asset category? This action cannot be undone.');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                                    <i class="feather feather-trash-2 me-3"></i>
                                                                    <span>Delete</span>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </x-ui.action-dropdown>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-5 text-muted fs-12">
                                                    <i class="feather-sliders fs-32 d-block mb-3 text-secondary"></i>
                                                    <div class="fw-bold mb-1">No Categories Set</div>
                                                    <div>Configure asset types like Laptops, Keys, Vehicles etc.</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @php
                            $categoryCurrentPage = $filteredCategories->currentPage();
                            $categoryTotalPages = $filteredCategories->lastPage();
                            $categoryTotalResults = $filteredCategories->total();
                            $categoryPerPage = $filteredCategories->perPage();
                        @endphp
                        @if($filteredCategories->hasPages())
                            <div class="card-footer bg-white border-top px-4 py-3">
                                <x-ui.pagination
                                    class="px-0 py-0"
                                    :current-page="$categoryCurrentPage"
                                    :total-pages="$categoryTotalPages"
                                    :total-results="$categoryTotalResults"
                                    :per-page="$categoryPerPage"
                                />
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 3. ASSET REQUESTS TAB -->
                <div class="tab-pane fade" id="requests-pane" role="tabpanel" aria-labelledby="requests-tab">
                    <div class="card border rounded bg-white shadow-sm">
                        <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center py-3 px-4 bg-white gap-3">
                            <div>
                                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">Employee Asset Requests</h5>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <!-- Requests Search & Filter Form -->
                                <form method="GET" action="{{ route('hrms.assets.index') }}" class="d-flex align-items-center gap-2 m-0">
                                    @foreach(['registry_search', 'registry_category_id', 'registry_status', 'registry_condition', 'category_search', 'category_company_id'] as $param)
                                        @if(request()->filled($param))
                                            <input type="hidden" name="{{ $param }}" value="{{ request($param) }}">
                                        @endif
                                    @endforeach
                                    <input type="hidden" name="request_sort" id="request_sort" value="{{ request('request_sort', 'newest') }}">
                                    
                                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                        <input type="text" name="request_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search requests..." value="{{ request('request_search') }}" style="box-shadow: none; height: 32px;">
                                    </div>

                                    <div class="d-flex gap-2">
                                        <x-ui.sort-dropdown label="SORT">
                                            <a class="dropdown-item py-2 {{ request('request_sort', 'newest') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'newest', this); event.preventDefault();">Newest First</a>
                                            <a class="dropdown-item py-2 {{ request('request_sort') == 'oldest' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'oldest', this); event.preventDefault();">Oldest First</a>
                                            <a class="dropdown-item py-2 {{ request('request_sort') == 'status_asc' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'status_asc', this); event.preventDefault();">Status (A-Z)</a>
                                            <a class="dropdown-item py-2 {{ request('request_sort') == 'status_desc' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'status_desc', this); event.preventDefault();">Status (Z-A)</a>
                                        </x-ui.sort-dropdown>

                                        <x-ui.filter label="Filters" offset="0, 5">
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
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Organization Entity</label>
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
                                                    <option value="allocated" {{ request('request_status') === 'allocated' ? 'selected' : '' }}>Allocated</option>
                                                    <option value="rejected" {{ request('request_status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="d-flex gap-2 justify-content-end mt-4">
                                                <a href="{{ route('hrms.assets.index', request()->except(['request_search', 'request_category_id', 'request_company_id', 'request_status'])) }}" class="btn btn-sm btn-light border">Reset</a>
                                                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                            </div>
                                        </x-ui.filter>

                                    @if(request()->anyFilled(['request_search', 'request_category_id', 'request_company_id', 'request_status']))
                                        <a href="{{ route('hrms.assets.index', request()->except(['request_search', 'request_category_id', 'request_company_id', 'request_status'])) }}" class="btn btn-sm btn-light border px-2 d-flex align-items-center justify-content-center" style="height: 38px; border-radius: 6px; font-size: 12px;" title="Clear Filters">
                                            <i class="feather-x"></i>
                                        </a>
                                    @endif
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 text-center">
                                    <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                                        <tr>
                                            <th class="text-start" style="padding-left: 20px;">{{ __('hrms.employees.title') }}</th>
                                            <th>{{ __('hrms.assets.org_entity') }}</th>
                                            <th>{{ __('hrms.assets.req_asset') }}</th>
                                            <th>{{ __('hrms.assets.req_reason') }}</th>
                                            <th>{{ __('hrms.assets.req_at') }}</th>
                                            <th>{{ __('hrms.assets.status') }}</th>
                                            <th class="text-end" style="padding-right: 20px; width: 220px;">{{ __('hrms.assets.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($requests as $req)
                                            <tr>
                                                <td class="text-start" style="padding-left: 20px;">
                                                    <div class="fw-bold text-dark fs-13">{{ $req->employee->display_name }}</div>
                                                    <small class="text-muted fs-11">{{ $req->employee->employee_id }}</small>
                                                </td>
                                                <td>{{ $req->company->company_name }}</td>
                                                <td>
                                                    <span class="badge bg-light text-secondary border px-2 py-1">{{ $req->category->name }}</span>
                                                </td>
                                                <td class="text-start text-muted fs-12" style="max-width: 250px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="{{ $req->reason }}">
                                                    {{ $req->reason }}
                                                </td>
                                                <td class="text-muted fs-12">{{ $req->request_date ? $req->request_date->format('d M, Y') : '-' }}</td>
                                                <td>
                                                    @if($req->status === 'pending')
                                                        <span class="badge bg-soft-warning text-warning px-2 py-1 text-capitalize">{{ $req->status }}</span>
                                                    @elseif($req->status === 'allocated')
                                                        <span class="badge bg-soft-success text-success px-2 py-1 text-capitalize">{{ $req->status }}</span>
                                                    @elseif($req->status === 'rejected')
                                                        <span class="badge bg-soft-danger text-danger px-2 py-1 text-capitalize" title="{{ $req->admin_notes }}">{{ $req->status }}</span>
                                                    @else
                                                        <span class="badge bg-light text-secondary px-2 py-1 text-capitalize">{{ $req->status }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-end" style="padding-right: 20px;">
                                                    @if($req->status === 'pending')
                                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                                            <!-- Allocate Trigger -->
                                                            <button type="button" class="action-dropdown-btn text-uppercase fw-bold px-3 allocate-from-request-btn" 
                                                                style="width: auto !important; font-size: 11px; letter-spacing: 0.5px; height: 32px;"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#allocateAssetModal"
                                                                data-request-id="{{ $req->id }}"
                                                                data-employee-id="{{ $req->employee_id }}"
                                                                data-employee-name="{{ $req->employee->display_name }} ({{ $req->employee->employee_id }})"
                                                                data-category-id="{{ $req->asset_category_id }}"
                                                                data-company-id="{{ $req->company_id }}">
                                                                Allocate
                                                            </button>

                                                            <!-- Reject Trigger -->
                                                            <button type="button" class="btn btn-sm btn-outline-danger text-uppercase fw-bold px-3 d-inline-flex align-items-center justify-content-center reject-request-btn"
                                                                style="font-size: 11px; letter-spacing: 0.5px; height: 32px; border-radius: 8px;"
                                                                data-request-id="{{ $req->id }}">
                                                                Reject
                                                            </button>
                                                        </div>
                                                    @elseif($req->status === 'allocated' && $req->allocatedAsset)
                                                        <span class="text-muted fs-12">Allocated: <code class="fw-bold text-dark">{{ $req->allocatedAsset->asset_code }}</code></span>
                                                    @else
                                                        <span class="text-muted fs-12">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-5 text-muted fs-12">
                                                    <i class="feather-user-check fs-32 d-block mb-3 text-secondary"></i>
                                                    <div class="fw-bold mb-1">No Asset Requests</div>
                                                    <div>Pending request tickets from employees will be shown here.</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @php
                            $requestCurrentPage = $requests->currentPage();
                            $requestTotalPages = $requests->lastPage();
                            $requestTotalResults = $requests->total();
                            $requestPerPage = $requests->perPage();
                        @endphp
                        @if($requests->hasPages())
                            <div class="card-footer bg-white border-top px-4 py-3">
                                <x-ui.pagination
                                    class="px-0 py-0"
                                    :current-page="$requestCurrentPage"
                                    :total-pages="$requestTotalPages"
                                    :total-results="$requestTotalResults"
                                    :per-page="$requestPerPage"
                                />
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 1: ADD ASSET -->
    <div class="modal fade" id="addAssetModal" tabindex="-1" aria-labelledby="addAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="addAssetModalLabel">
                        <i class="feather-package me-2 text-primary"></i>Log New Company Asset
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.assets.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Asset Category" name="asset_category_id" :required="true" select2-selector="default">
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->company->company_name }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Asset Code" name="asset_code" placeholder="e.g. AST-IT-008" :required="true" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Asset Name" name="name" placeholder="e.g. MacBook Pro 16" :required="true" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Brand / Vendor" name="brand" placeholder="e.g. Apple" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Model Number" name="model_number" placeholder="e.g. A2442" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Serial Number" name="serial_number" placeholder="e.g. C02FXXXX" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Purchase Date" name="purchase_date" inputType="date" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Purchase Cost (₹)" name="purchase_cost" inputType="number" step="0.01" placeholder="0.00" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Asset Condition" name="condition" :required="true" select2-selector="default">
                                    <option value="good">Good</option>
                                    <option value="new">New</option>
                                    <option value="fair">Fair</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="scrapped">Scrapped</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Internal Notes" name="notes" placeholder="Condition details, license specifications, configurations..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Log Asset</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2: EDIT ASSET -->
    <div class="modal fade" id="editAssetModal" tabindex="-1" aria-labelledby="editAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="editAssetModalLabel">
                        <i class="feather-edit-3 me-2 text-primary"></i>Modify Asset Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAssetForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Asset Category" name="asset_category_id" id="edit_asset_category_id" :required="true" select2-selector="default">
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Asset Code" name="asset_code" id="edit_asset_code" :required="true" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Asset Name" name="name" id="edit_name" :required="true" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Brand / Vendor" name="brand" id="edit_brand" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Model Number" name="model_number" id="edit_model_number" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Serial Number" name="serial_number" id="edit_serial_number" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Purchase Date" name="purchase_date" id="edit_purchase_date" inputType="date" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="Purchase Cost (₹)" name="purchase_cost" id="edit_purchase_cost" inputType="number" step="0.01" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Asset Condition" name="condition" id="edit_condition" :required="true" select2-selector="default">
                                    <option value="good">Good</option>
                                    <option value="new">New</option>
                                    <option value="fair">Fair</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="scrapped">Scrapped</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Internal Notes" name="notes" id="edit_notes" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Save Changes</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- IMPORT ASSET REGISTRY MODAL -->
    <div class="modal fade" id="importAssetModal" tabindex="-1" aria-labelledby="importAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="importAssetModalLabel">
                        <i class="feather-upload me-2 text-primary" style="font-size: 16px;"></i>Import Assets
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.assets.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body text-start">
                        <div class="alert bg-light border-0 d-flex flex-column gap-2 p-3 mb-4 rounded-3 text-dark fs-12">
                            <div class="d-flex align-items-center gap-2">
                                <i class="feather-info text-primary fs-15"></i>
                                <span class="fw-bold">Excel Template Instructions</span>
                            </div>
                            <span class="text-muted leading-relaxed">
                                Please download the Excel template, populate your asset details, and upload the completed file. Ensure mandatory columns (`asset_code`, `name`, `category_name`, and `company_name`) are filled correctly. New categories will be auto-created during the import.
                            </span>
                            <div class="mt-1">
                                <a href="{{ route('hrms.assets.import.template') }}" class="btn btn-xs btn-soft-primary d-inline-flex align-items-center fw-bold py-1.5 px-3" style="border-radius: 6px; font-size: 11px;">
                                    <i class="feather-download me-1.5 fs-12"></i> Download Template
                                </a>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="erp-custom-file-upload">
                                <label class="file-upload-label py-3 px-4 w-100" style="cursor: pointer; border-style: dashed; border-width: 2px;" for="asset_import_file">
                                    <i class="feather-upload-cloud me-2 text-primary fs-20"></i>
                                    <span class="file-text text-muted" id="asset_import_file_text">Select Excel (.xlsx) File</span>
                                    <input type="file" name="file" id="asset_import_file" class="d-none" required accept=".xlsx" onchange="document.getElementById('asset_import_file_text').innerText = this.files[0]?.name || 'Select Excel (.xlsx) File'">
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Import</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- IMPORT CATEGORY MODAL -->
    <div class="modal fade" id="importCategoryModal" tabindex="-1" aria-labelledby="importCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="importCategoryModalLabel">
                        <i class="feather-upload me-2 text-primary" style="font-size: 16px;"></i>Import Asset Categories
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.assets.categories.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body text-start">
                        <div class="alert bg-light border-0 d-flex flex-column gap-2 p-3 mb-4 rounded-3 text-dark fs-12">
                            <div class="d-flex align-items-center gap-2">
                                <i class="feather-info text-primary fs-15"></i>
                                <span class="fw-bold">Excel Template Instructions</span>
                            </div>
                            <span class="text-muted leading-relaxed">
                                Please download the Excel template, populate your category details, and upload the completed file. Ensure mandatory columns (`name` and `company_name`) are filled correctly.
                            </span>
                            <div class="mt-1">
                                <a href="{{ route('hrms.assets.categories.import.template') }}" class="btn btn-xs btn-soft-primary d-inline-flex align-items-center fw-bold py-1.5 px-3" style="border-radius: 6px; font-size: 11px;">
                                    <i class="feather-download me-1.5 fs-12"></i> Download Template
                                </a>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="erp-custom-file-upload">
                                <label class="file-upload-label py-3 px-4 w-100" style="cursor: pointer; border-style: dashed; border-width: 2px;" for="category_import_file">
                                    <i class="feather-upload-cloud me-2 text-primary fs-20"></i>
                                    <span class="file-text text-muted" id="category_import_file_text">Select Excel (.xlsx) File</span>
                                    <input type="file" name="file" id="category_import_file" class="d-none" required accept=".xlsx" onchange="document.getElementById('category_import_file_text').innerText = this.files[0]?.name || 'Select Excel (.xlsx) File'">
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Import</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="allocateAssetModal" tabindex="-1" aria-labelledby="allocateAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="allocateAssetModalLabel">
                        <i class="feather-user-check me-2 text-primary"></i>Assign Asset to Employee
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="allocateAssetForm" method="POST">
                    @csrf
                    <input type="hidden" name="request_id" id="allocate_request_id">
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Container A: Registry Checkout (Pre-selected Asset, select Employee) -->
                            <div id="registry_checkout_container" class="col-12 p-0 m-0 row g-3">
                                <div class="col-12">
                                    <label class="info-label mb-1">Asset To Assign</label>
                                    <input type="text" id="allocate_asset_name_display" class="form-control bg-light" readonly>
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="select" label="Employee" name="assigned_employee_id" id="registry_employee_select" :required="true" select2-selector="default">
                                        <option value="">Select Employee</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}" data-company-id="{{ $employee->company_id }}">{{ $employee->display_name }} ({{ $employee->employee_id }})</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>

                            <!-- Container B: Request Checkout (Pre-selected Employee, select Asset) -->
                            <div id="request_checkout_container" class="col-12 p-0 m-0 row g-3 d-none">
                                <div class="col-12">
                                    <label class="info-label mb-1">Employee</label>
                                    <input type="text" id="allocate_employee_name_display" class="form-control bg-light" readonly>
                                    <input type="hidden" name="assigned_employee_id" id="request_employee_id" disabled>
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="select" label="Select Asset" name="asset_id" id="request_asset_select" :required="true" select2-selector="default">
                                        <option value="">Select Asset</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>

                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Allocation Date" name="allocated_at" inputType="date" :required="true" value="{{ date('Y-m-d') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Expected Return Date" name="expected_return_date" inputType="date" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Confirm Allocation</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 4: ADD CATEGORY -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="addCategoryModalLabel">
                        <i class="feather-sliders me-2 text-primary"></i>Create Asset Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.assets.category.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Belongs to Company" name="company_id" :required="true" select2-selector="default">
                                    <option value="">Select Company</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Category Name" name="name" placeholder="e.g. IT Laptops, Office Car Keys" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Brief details about what items go into this category..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Create Category</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 4B: EDIT CATEGORY -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="editCategoryModalLabel">
                        <i class="feather-sliders me-2 text-primary"></i>Edit Asset Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editCategoryForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Belongs to Company" name="company_id" id="edit_category_company_id" :required="true" select2-selector="default">
                                    <option value="">Select Company</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Category Name" name="name" id="edit_category_name" placeholder="e.g. IT Laptops, Office Car Keys" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_category_description" placeholder="Brief details about what items go into this category..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Save Changes</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="returnAssetModal" tabindex="-1" aria-labelledby="returnAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="returnAssetModalLabel">
                        <i class="feather-corner-up-left me-2 text-primary"></i>Return Asset to Inventory
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="returnAssetForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="info-label mb-1">Asset To Return</label>
                                <input type="text" id="return_asset_name_display" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Return Date" name="returned_at" inputType="date" :required="true" value="{{ date('Y-m-d') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Return Condition" name="return_condition" :required="true" select2-selector="default">
                                    <option value="good">Good</option>
                                    <option value="new">New</option>
                                    <option value="fair">Fair</option>
                                    <option value="damaged">Damaged (Needs Maintenance)</option>
                                    <option value="scrapped">Scrapped</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Return Notes" name="return_notes" placeholder="Condition details, damage details, return notes..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Process Return</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 6: ASSET HISTORY LOG -->
    <div class="modal fade" id="assetHistoryModal" tabindex="-1" aria-labelledby="assetHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="assetHistoryModalLabel">
                        <i class="feather-clock me-2 text-primary"></i>Asset Allocation History
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 bg-light border-bottom">
                        <span class="text-muted">Asset:</span> <strong id="history_asset_name_display" class="text-dark"></strong>
                    </div>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead class="table-light fs-11 text-uppercase">
                                <tr>
                                    <th class="text-start" style="padding-left: 20px;">Employee</th>
                                    <th>Allocated At</th>
                                    <th>Returned At</th>
                                    <th>Alloc. Cond.</th>
                                    <th>Return Cond.</th>
                                    <th class="text-start" style="padding-right: 20px;">Notes</th>
                                </tr>
                            </thead>
                            <tbody id="history_table_body">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 7: REJECT REQUEST -->
    <div class="modal fade" id="rejectRequestModal" tabindex="-1" aria-labelledby="rejectRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="rejectRequestModalLabel">
                        <i class="feather-alert-octagon me-2 text-danger"></i>Reject Asset Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rejectRequestForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Rejection Reason" name="admin_notes" placeholder="Please state the reason for rejecting this request..." :required="true" />
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
@endsection

@push('scripts')
    <script>
        const allEmployees = {!! json_encode($employees->map(function($e) {
            return [
                'id' => $e->id,
                'company_id' => $e->company_id,
                'display_name' => $e->display_name,
                'employee_id' => $e->employee_id
            ];
        })) !!};

        const allAvailableAssets = {!! json_encode($availableAssets->map(function($a) {
            return [
                'id' => $a->id,
                'name' => $a->name . ' (' . $a->asset_code . ')',
                'category_id' => $a->asset_category_id,
                'company_id' => $a->company_id
            ];
        })) !!};

        $(document).ready(function() {
            // Append modals to body root to prevent Bootstrap backdrop overlay issues inside settings flex container
            $('#addAssetModal').appendTo('body');
            $('#editAssetModal').appendTo('body');
            $('#allocateAssetModal').appendTo('body');
            $('#addCategoryModal').appendTo('body');
            $('#editCategoryModal').appendTo('body');
            $('#returnAssetModal').appendTo('body');
            $('#assetHistoryModal').appendTo('body');
            $('#rejectRequestModal').appendTo('body');
            $('#importAssetModal').appendTo('body');
            $('#importCategoryModal').appendTo('body');

            // Dynamically append search/filter parameters to export links on click
            $(document).on('click', '#btn-export-assets-link', function(e) {
                var search = $('#registry-pane input[name="registry_search"]').val() || '';
                var category = $('#registry-pane select[name="registry_category_id"]').val() || '';
                var status = $('#registry-pane select[name="registry_status"]').val() || '';
                var condition = $('#registry-pane select[name="registry_condition"]').val() || '';
                var sort = $('#registry_sort').val() || '';
                
                var url = '{{ route("hrms.assets.export") }}?registry_search=' + encodeURIComponent(search) +
                          '&registry_category_id=' + encodeURIComponent(category) +
                          '&registry_status=' + encodeURIComponent(status) +
                          '&registry_condition=' + encodeURIComponent(condition) +
                          '&registry_sort=' + encodeURIComponent(sort);
                $(this).attr('href', url);
            });

            $(document).on('click', '#btn-export-categories-link', function(e) {
                var search = $('#categories-pane input[name="category_search"]').val() || '';
                var company = $('#categories-pane select[name="category_company_id"]').val() || '';
                var sort = $('#category_sort').val() || '';
                
                var url = '{{ route("hrms.assets.categories.export") }}?category_search=' + encodeURIComponent(search) +
                          '&category_company_id=' + encodeURIComponent(company) +
                          '&category_sort=' + encodeURIComponent(sort);
                $(this).attr('href', url);
            });

            // Handle edit category details binding
            $('#editCategoryModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var categoryId = button.data('category-id');
                var companyId = button.data('company-id');
                var name = button.data('name');
                var description = button.data('description');

                var modal = $(this);
                // Set form action URL dynamically
                modal.find('form').attr('action', '/hrms/assets/category/update/' + categoryId);

                // Bind values
                modal.find('#edit_category_company_id').val(companyId).trigger('change');
                modal.find('#edit_category_name').val(name);
                modal.find('#edit_category_description').val(description);
            });

            // Handle edit asset details binding
            $('#editAssetModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var assetId = button.data('asset-id');
                var categoryId = button.data('asset-category-id');
                var assetCode = button.data('asset-code');
                var name = button.data('name');
                var brand = button.data('brand');
                var modelNumber = button.data('model-number');
                var serialNumber = button.data('serial-number');
                var purchaseDate = button.data('purchase-date');
                var purchaseCost = button.data('purchase-cost');
                var condition = button.data('condition');
                var notes = button.data('notes');

                var modal = $(this);
                // Set form action URL dynamically
                modal.find('form').attr('action', '/hrms/assets/update/' + assetId);

                // Bind values
                modal.find('#edit_asset_category_id').val(categoryId).trigger('change');
                modal.find('#edit_asset_code').val(assetCode);
                modal.find('#edit_name').val(name);
                modal.find('#edit_brand').val(brand);
                modal.find('#edit_model_number').val(modelNumber);
                modal.find('#edit_serial_number').val(serialNumber);
                modal.find('#edit_purchase_date').val(purchaseDate);
                modal.find('#edit_purchase_cost').val(purchaseCost);
                modal.find('#edit_condition').val(condition).trigger('change');
                modal.find('#edit_notes').val(notes);
            });

            // Handle return modal details binding
            $('#returnAssetModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var assetId = button.data('asset-id');
                var assetName = button.data('asset-name');

                var modal = $(this);
                modal.find('form').attr('action', '/hrms/assets/' + assetId + '/return');
                modal.find('#return_asset_name_display').val(assetName);
            });

            // Handle asset history log click
            $(document).on('click', '.show-history-btn', function() {
                var btn = $(this);
                var assetName = btn.data('asset-name');
                var rawAllocations = btn.data('allocations');
                
                var allocations = [];
                try {
                    allocations = JSON.parse(atob(rawAllocations));
                } catch(e) {
                    console.error("Failed to parse allocations history", e);
                }

                $('#history_asset_name_display').text(assetName);
                
                var html = '';
                if (allocations.length === 0) {
                    html = '<tr><td colspan="6" class="text-center py-4 text-muted fs-12">No allocation logs found for this asset.</td></tr>';
                } else {
                    allocations.forEach(function(log) {
                        var empName = log.employee ? log.employee.full_name : 'Unknown';
                        var empCode = log.employee && log.employee.employee_id ? ' (' + log.employee.employee_id + ')' : '';
                        var checkInDate = log.returned_at ? log.returned_at.substring(0, 10) : 'Active';
                        var returnCondition = log.return_condition ? log.return_condition : '-';
                        var notes = log.notes ? log.notes : '-';
                        
                        html += '<tr>' +
                            '<td class="text-start" style="padding-left: 20px;"><strong>' + empName + '</strong><span class="text-muted fs-11">' + empCode + '</span></td>' +
                            '<td><span class="fs-12">' + log.allocated_at.substring(0, 10) + '</span></td>' +
                            '<td><span class="badge ' + (log.returned_at ? 'bg-soft-success text-success' : 'bg-soft-primary text-primary') + '">' + checkInDate + '</span></td>' +
                            '<td><span class="badge badge-' + log.allocation_condition + ' text-capitalize">' + log.allocation_condition + '</span></td>' +
                            '<td><span class="badge ' + (log.return_condition ? 'badge-' + log.return_condition : 'bg-light text-secondary') + ' text-capitalize">' + returnCondition + '</span></td>' +
                            '<td class="text-start text-muted fs-11" style="max-width: 180px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; padding-right: 20px;" title="' + notes + '">' + notes + '</td>' +
                            '</tr>';
                    });
                }
                
                $('#history_table_body').html(html);
                
                // Show the modal
                var historyModal = new bootstrap.Modal(document.getElementById('assetHistoryModal'));
                historyModal.show();
            });

            // Bind request rejection details dynamically
            $(document).on('click', '.reject-request-btn', function() {
                var btn = $(this);
                var requestId = btn.data('request-id');
                var modal = $('#rejectRequestModal');
                modal.find('form').attr('action', '/hrms/assets/requests/' + requestId + '/reject');
                
                var rejectModal = new bootstrap.Modal(document.getElementById('rejectRequestModal'));
                rejectModal.show();
            });

            // Form submit intercept for Request allocation flow (asset is picked dropdown)
            $('#request_asset_select').on('change', function() {
                var assetId = $(this).val();
                if (assetId) {
                    $('#allocateAssetForm').attr('action', '/hrms/assets/' + assetId + '/allocate');
                } else {
                    $('#allocateAssetForm').attr('action', '');
                }
            });

            // Prevent invalid form submission if no asset is selected in request allocation mode
            $('#allocateAssetForm').on('submit', function(e) {
                var isRequestFlow = !$('#request_checkout_container').hasClass('d-none');
                if (isRequestFlow) {
                    var assetId = $('#request_asset_select').val();
                    if (!assetId || assetId === '') {
                        e.preventDefault();
                        alert('Please select an asset before confirming allocation.');
                        return false;
                    }
                }
            });

            // Handle allocation details binding
            $('#allocateAssetModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var modal = $(this);
                var form = $('#allocateAssetForm');

                // Determine if allocation is triggered from a Request ticket or from the Registry Directory
                if (button.hasClass('allocate-from-request-btn')) {
                    // FLOW 1: REQUEST ALLOCATION
                    var requestId = button.data('request-id');
                    var employeeId = button.data('employee-id');
                    var employeeName = button.data('employee-name');
                    var categoryId = button.data('category-id');
                    var companyId = button.data('company-id');

                    // Set active inputs
                    $('#allocate_request_id').val(requestId);
                    $('#registry_checkout_container').addClass('d-none');
                    $('#request_checkout_container').removeClass('d-none');

                    // Configure inputs disabled and required states to prevent double submissions and force selection
                    $('#registry_employee_select').prop('disabled', true);
                    $('#request_employee_id').prop('disabled', false).val(employeeId);
                    $('#request_asset_select').prop('disabled', false).prop('required', true);
                    $('#allocate_employee_name_display').val(employeeName);

                    // Rebuild asset options list based on Category and Company match
                    var assetSelect = $('#request_asset_select');
                    assetSelect.empty();
                    assetSelect.append(new Option('Select Asset', ''));

                    var filteredAssets = allAvailableAssets.filter(function(asset) {
                        return String(asset.category_id) === String(categoryId) && String(asset.company_id) === String(companyId);
                    });

                    filteredAssets.forEach(function(asset) {
                        assetSelect.append(new Option(asset.name, asset.id));
                    });

                    if (assetSelect.hasClass('select2-hidden-accessible')) {
                        assetSelect.select2('destroy');
                    }
                    assetSelect.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownParent: modal
                    });
                    assetSelect.val('').trigger('change');
                    form.attr('action', ''); // Reset action until asset is selected
                } else {
                    // FLOW 2: REGISTRY DIRECT ALLOCATION
                    var assetId = button.data('asset-id');
                    var assetName = button.data('asset-name');
                    var companyId = button.data('company-id');

                    // Set active inputs
                    $('#allocate_request_id').val('');
                    $('#request_checkout_container').addClass('d-none');
                    $('#registry_checkout_container').removeClass('d-none');

                    // Configure inputs disabled and required states
                    $('#request_employee_id').prop('disabled', true);
                    $('#request_asset_select').prop('disabled', true).prop('required', false);
                    $('#registry_employee_select').prop('disabled', false);
                    $('#allocate_asset_name_display').val(assetName);

                    // Re-filter employee options by company
                    var employeeSelect = $('#registry_employee_select');
                    employeeSelect.empty();
                    employeeSelect.append(new Option('Select Employee', ''));

                    var filteredEmployees = allEmployees.filter(function(emp) {
                        return !companyId || String(emp.company_id) === String(companyId);
                    });

                    filteredEmployees.forEach(function(emp) {
                        var label = emp.display_name + (emp.employee_id ? ' (' + emp.employee_id + ')' : '');
                        employeeSelect.append(new Option(label, emp.id));
                    });

                    if (employeeSelect.hasClass('select2-hidden-accessible')) {
                        employeeSelect.select2('destroy');
                    }
                    employeeSelect.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownParent: modal
                    });
                    employeeSelect.val('').trigger('change');
                    form.attr('action', '/hrms/assets/' + assetId + '/allocate');
                }
            });

            // Tab Persistence
            var activeTab = localStorage.getItem('activeAssetTab') || 'registry-tab';
            var tabEl = document.querySelector('#' + activeTab);
            if (tabEl) {
                var tab = new bootstrap.Tab(tabEl);
                tab.show();
            }

            // Handle search form submission via AJAX (covers Enter key and clicking Apply in filter)
            $(document).on('submit', 'form[action*="assets"][method="GET"], form[action*="assets"][method="get"]', function(e) {
                e.preventDefault();
                var form = $(this);
                var formData = form.serialize();
                var url = form.attr('action') + '?' + formData;
                var tabPaneId = form.closest('.tab-pane').attr('id');
                
                // Close the filter dropdown if open
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');

                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        // Update table
                        var oldTable = $('#' + tabPaneId + ' .table-responsive');
                        var newTable = $(doc).find('#' + tabPaneId + ' .table-responsive');
                        oldTable.html(newTable.html());
                        
                        // Update pagination footer
                        var oldPagination = $('#' + tabPaneId + ' .erp-pagination-container');
                        var newPagination = $(doc).find('#' + tabPaneId + ' .erp-pagination-container');
                        if (newPagination.length) {
                            if (oldPagination.length) {
                                oldPagination.replaceWith(newPagination);
                            } else {
                                $('#' + tabPaneId + ' .card-body').append(newPagination);
                            }
                        } else {
                            oldPagination.remove();
                        }
                    }
                });
            });

            // Live Search (AJAX) as user types with debounce (only trigger for input, NOT dropdown selects)
            var searchTimeout;
            $(document).on('input', 'input[name$="_search"]', function() {
                var $input = $(this);
                var form = $input.closest('form');
                clearTimeout(searchTimeout);
                
                searchTimeout = setTimeout(function() {
                    var formData = form.serialize();
                    var url = form.attr('action') + '?' + formData;

                    $.ajax({
                        url: url,
                        type: 'GET',
                        success: function(response) {
                            var parser = new DOMParser();
                            var doc = parser.parseFromString(response, 'text/html');
                            var tabPaneId = $input.closest('.tab-pane').attr('id');
                            
                            // Update table
                            var oldTable = $('#' + tabPaneId + ' .table-responsive');
                            var newTable = $(doc).find('#' + tabPaneId + ' .table-responsive');
                            oldTable.html(newTable.html());
                            
                            // Update pagination footer
                            var oldPagination = $('#' + tabPaneId + ' .erp-pagination-container');
                            var newPagination = $(doc).find('#' + tabPaneId + ' .erp-pagination-container');
                            if (newPagination.length) {
                                if (oldPagination.length) {
                                    oldPagination.replaceWith(newPagination);
                                } else {
                                    $('#' + tabPaneId + ' .card-body').append(newPagination);
                                }
                            } else {
                                oldPagination.remove();
                            }
                        }
                    });
                }, 300); // 300ms debounce
            });

            // Reset filters via AJAX click
            $(document).on('click', '.erp-filter-dropdown a.btn-light, form a.btn-light', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var url = $btn.attr('href');
                var tabPaneId = $btn.closest('.tab-pane').attr('id');
                
                // Close the filter dropdown if open
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');

                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        // Update card header (to clear search text)
                        var oldHeader = $('#' + tabPaneId + ' .card-header');
                        var newHeader = $(doc).find('#' + tabPaneId + ' .card-header');
                        oldHeader.html(newHeader.html());
                        
                        // Update table
                        var oldTable = $('#' + tabPaneId + ' .table-responsive');
                        var newTable = $(doc).find('#' + tabPaneId + ' .table-responsive');
                        oldTable.html(newTable.html());
                        
                        // Update pagination footer
                        var oldPagination = $('#' + tabPaneId + ' .erp-pagination-container');
                        var newPagination = $(doc).find('#' + tabPaneId + ' .erp-pagination-container');
                        if (newPagination.length) {
                            if (oldPagination.length) {
                                oldPagination.replaceWith(newPagination);
                            } else {
                                $('#' + tabPaneId + ' .card-body').append(newPagination);
                            }
                        } else {
                            oldPagination.remove();
                        }
                    }
                });
            });

            // AJAX Pagination click
            $(document).on('click', '.tab-pane .erp-pagination-container a', function(e) {
                e.preventDefault();
                var $link = $(this);
                var url = $link.attr('href');
                if (!url || url.indexOf('javascript') === 0) return;
                
                var tabPaneId = $link.closest('.tab-pane').attr('id');
                
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        // Update table
                        var oldTable = $('#' + tabPaneId + ' .table-responsive');
                        var newTable = $(doc).find('#' + tabPaneId + ' .table-responsive');
                        oldTable.html(newTable.html());
                        
                        // Update pagination footer
                        var oldPagination = $('#' + tabPaneId + ' .erp-pagination-container');
                        var newPagination = $(doc).find('#' + tabPaneId + ' .erp-pagination-container');
                        if (newPagination.length) {
                            if (oldPagination.length) {
                                oldPagination.replaceWith(newPagination);
                            } else {
                                $('#' + tabPaneId + ' .card-body').append(newPagination);
                            }
                        } else {
                            oldPagination.remove();
                        }
                    }
                });
            });

            // Dynamically adjust dropdown direction (dropup vs dropdown) to prevent clipping
            $(document).on('click', '.dropdown-toggle-custom', function() {
                var dropdown = $(this).closest('.dropdown');
                var menu = dropdown.find('.dropdown-menu');
                if (!menu.length) return;

                var windowHeight = $(window).height();
                var toggleOffset = $(this).offset().top - $(window).scrollTop();
                var dropdownHeight = menu.outerHeight() || 150;

                if (toggleOffset + dropdownHeight > windowHeight - 50) {
                    dropdown.addClass('dropup');
                } else {
                    dropdown.removeClass('dropup');
                }
            });

            // Autofocus active search inputs on load and restore cursor to the end
            var searchInputs = document.querySelectorAll('input[name$="_search"]');
            searchInputs.forEach(function(input) {
                if (input.value) {
                    input.focus();
                    var val = input.value;
                    input.value = '';
                    input.value = val;
                }
            });

            // Toggle Add buttons in header based on active tab
            function updateHeaderActions() {
                var activeTabId = localStorage.getItem('activeAssetTab') || 'registry-tab';
                if (activeTabId === 'registry-tab') {
                    $('#hdr-btn-log-asset').removeClass('d-none');
                    $('#hdr-btn-add-category').addClass('d-none');
                } else if (activeTabId === 'categories-tab') {
                    $('#hdr-btn-log-asset').addClass('d-none');
                    $('#hdr-btn-add-category').removeClass('d-none');
                } else {
                    $('#hdr-btn-log-asset').addClass('d-none');
                    $('#hdr-btn-add-category').addClass('d-none');
                }
            }

            // On page load
            setTimeout(updateHeaderActions, 50);

            // On tab change
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                localStorage.setItem('activeAssetTab', e.target.id);
                updateHeaderActions();
            });
        });

        // Global function for sorting
        function changeSort(tab, criteria, element) {
            var input = document.getElementById(tab + '_sort');
            if (input) {
                input.value = criteria;
            }

            if (element) {
                var menu = element.closest('.dropdown-menu');
                if (menu) {
                    menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                        el.classList.remove('active');
                    });
                }
                element.classList.add('active');
            }

            if (input) {
                var form = input.closest('form');
                if (form) {
                    $(form).submit();
                }
            }
        }
    </script>
@endpush
