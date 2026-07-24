@extends('layouts.duralux')

@section('title', __('hrms.sidebar.asset_management') . ' | SaaS ERP')
@section('page-title', __('hrms.sidebar.asset_management'))
@section('breadcrumb', 'HRMS / ' . __('hrms.sidebar.asset_management'))

@section('page-actions')
    <div id="hdr-btn-add-item" class="d-none d-flex align-items-center gap-2">
        <x-ui.button variant="outline-primary" icon="feather-upload" data-bs-toggle="modal" data-bs-target="#importAssetModal" class="fw-bold text-uppercase">
            {{ __('hrms.employees.import') }}
        </x-ui.button>
        <x-ui.button variant="outline-primary" icon="feather-download" href="{{ route('hrms.assets.export') }}" id="btn-export-assets-link" class="fw-bold text-uppercase">
            {{ __('hrms.employees.export') }}
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addAssetModal" class="fw-bold text-uppercase">
            {{ __('hrms.assets.add_item') }}
        </x-ui.button>
    </div>
    <div id="hdr-btn-add-category" class="d-none d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addCategoryModal" class="fw-bold text-uppercase">
            {{ __('hrms.assets.add_category') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .btn-outline-primary {
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
            background-color: transparent !important;
        }
        .btn-outline-primary:hover,
        .btn-outline-primary:focus,
        .btn-outline-primary:active,
        .btn-outline-primary.active,
        .btn-outline-primary.show {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #fff !important;
        }

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
        .table-responsive {
            overflow-x: hidden !important;
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
                    <button class="nav-link active" id="items-tab" data-bs-toggle="tab" data-bs-target="#items-pane" type="button" role="tab" aria-controls="items-pane" aria-selected="true">
                        <i class="feather-box me-2"></i>{{ __('hrms.assets.tab_items') }}
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


                <!-- 2. ASSET CATEGORIES TAB -->
                <div class="tab-pane fade" id="categories-pane" role="tabpanel" aria-labelledby="categories-tab">
                    <div class="card border rounded bg-white shadow-sm">
                        <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center py-3 px-4 bg-white gap-3">
                            <div>
                                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.assets.categories_title') }}</h5>
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
                                        <input type="text" name="category_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.assets.search_categories_placeholder') }}" value="{{ request('category_search') }}" style="box-shadow: none; height: 32px;">
                                    </div>

                                    <div class="d-flex gap-2">
                                        <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                                            <a class="dropdown-item py-2 {{ request('category_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'name_asc', this); event.preventDefault();">{{ __('hrms.common.sort_name_asc') }}</a>
                                            <a class="dropdown-item py-2 {{ request('category_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'name_desc', this); event.preventDefault();">{{ __('hrms.common.sort_name_desc') }}</a>
                                            <a class="dropdown-item py-2 {{ request('category_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'newest', this); event.preventDefault();">{{ __('hrms.assets.sort_newest') }}</a>
                                        </x-ui.sort-dropdown>

                                        <x-ui.filter label="{{ __('hrms.assets.filters') }}" offset="0, 5">
                                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                            
                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.assets.org_entity') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="category_company_id">
                                                    <option value="">{{ __('hrms.common.all_companies') }}</option>
                                                    @foreach($companies as $company)
                                                        <option value="{{ $company->id }}" {{ request('category_company_id') == $company->id ? 'selected' : '' }}>
                                                            {{ $company->company_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="d-flex gap-2 justify-content-end mt-4">
                                                <a href="{{ route('hrms.assets.index', request()->except(['category_search', 'category_company_id'])) }}" class="btn btn-sm btn-light border">{{ __('hrms.common.reset') }}</a>
                                                <button type="submit" class="btn btn-sm btn-primary">{{ __('hrms.common.apply') }}</button>
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
                                <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                                    <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                                        <tr>
                                            <th class="text-start px-4" style="width: 40%;">{{ __('hrms.assets.category_name') }} & Description</th>
                                            <th style="width: 15%;">{{ __('hrms.assets.total_assets') }}</th>
                                            <th style="width: 25%;">{{ __('hrms.assets.org_entity') }}</th>
                                            <th style="width: 20%;">{{ __('hrms.assets.created_at') }}</th>
                                            <th class="text-end px-4" style="width: 110px; white-space: nowrap;">{{ __('hrms.assets.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($filteredCategories as $category)
                                            <tr>
                                                <td class="text-start px-4" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                                    <div class="fw-bold text-dark fs-13">{{ $category->name }}</div>
                                                    @if($category->description)
                                                        <div class="text-muted fs-11 mt-1 d-flex align-items-center gap-1 desc-expandable-container">
                                                            <span class="text-truncate desc-text-truncate" style="max-width: 240px;" title="{{ $category->description }}">{{ $category->description }}</span>
                                                            <a href="javascript:void(0)" class="text-primary text-decoration-none fw-bold fs-10 btn-read-more-dynamic d-none" data-title="{{ $category->name }}" data-desc="{{ $category->description }}" style="white-space: nowrap;">(Read More)</a>
                                                        </div>
                                                    @else
                                                        <div class="text-muted fs-11 mt-1 fst-italic">{{ __('hrms.assets.no_description_provided') }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge bg-soft-primary text-primary px-3 py-1 rounded-pill">{{ $category->assets()->count() }}</span>
                                                </td>
                                                <td style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">{{ $category->company->company_name }}</td>
                                                <td class="text-muted fs-12">{{ $category->created_at->format('d M, Y') }}</td>
                                                <td class="text-end px-4">
                                                     <x-ui.action-dropdown>
                                                         <li>
                                                             <a class="dropdown-item edit-category-btn" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                                                 data-category-id="{{ $category->id }}"
                                                                 data-company-id="{{ $category->company_id }}"
                                                                 data-name="{{ $category->name }}"
                                                                 data-description="{{ $category->description }}">
                                                                 <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('hrms.assets.edit') }}
                                                             </a>
                                                         </li>
                                                         <li>
                                                             <form action="{{ route('hrms.assets.category.destroy', $category->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.assets.confirm_delete_category') ?? 'Are you sure you want to delete this asset category? This action cannot be undone.' }}', { title: 'Delete Asset Category', variant: 'danger', confirmButtonText: 'Delete' });">
                                                                 @csrf
                                                                 @method('DELETE')
                                                                 <button type="submit" class="dropdown-item text-danger">
                                                                     <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('hrms.assets.delete') }}
                                                                 </button>
                                                             </form>
                                                         </li>
                                                     </x-ui.action-dropdown>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-5 text-muted fs-12">
                                                    <i class="feather-sliders fs-32 d-block mb-3 text-secondary"></i>
                                                    <div class="fw-bold mb-1">{{ __('hrms.assets.empty_categories_title') }}</div>
                                                    <div>{{ __('hrms.assets.empty_categories_desc') }}</div>
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

                <!-- 2b. ITEM CATALOG TAB -->
                <div class="tab-pane fade show active" id="items-pane" role="tabpanel" aria-labelledby="items-tab">
                    <div class="card border rounded bg-white shadow-sm">
                        <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center py-3 px-4 bg-white gap-3">
                            <div>
                                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">Item Catalog Master</h5>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <!-- Items Search & Filter Form -->
                                <form method="GET" action="{{ route('hrms.assets.index') }}" class="d-flex align-items-center gap-2 m-0">
                                    @foreach(['registry_search', 'registry_category_id', 'registry_status', 'registry_condition', 'category_search', 'category_company_id', 'request_search', 'request_category_id', 'request_company_id', 'request_status'] as $param)
                                        @if(request()->filled($param))
                                            <input type="hidden" name="{{ $param }}" value="{{ request($param) }}">
                                        @endif
                                    @endforeach
                                    <input type="hidden" name="item_sort" id="item_sort" value="{{ request('item_sort', 'name_asc') }}">
                                    
                                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                        <input type="text" name="item_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search item catalog..." value="{{ request('item_search') }}" style="box-shadow: none; height: 32px;">
                                    </div>

                                    <div class="d-flex gap-2">
                                        <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                                            <a class="dropdown-item py-2 {{ request('item_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('item', 'name_asc', this); event.preventDefault();">Name A-Z</a>
                                            <a class="dropdown-item py-2 {{ request('item_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('item', 'name_desc', this); event.preventDefault();">Name Z-A</a>
                                            <a class="dropdown-item py-2 {{ request('item_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('item', 'newest', this); event.preventDefault();">{{ __('hrms.assets.sort_newest') }}</a>
                                        </x-ui.sort-dropdown>

                                        <x-ui.filter label="{{ __('hrms.assets.filters') }}" offset="0, 5">
                                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                            
                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Company</label>
                                                <x-ui.odoo-form-ui type="select" name="item_company_id">
                                                    <option value="">{{ __('hrms.common.all_companies') }}</option>
                                                    @foreach($companies as $company)
                                                        <option value="{{ $company->id }}" {{ request('item_company_id') == $company->id ? 'selected' : '' }}>
                                                            {{ $company->company_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category</label>
                                                <x-ui.odoo-form-ui type="select" name="item_category_id">
                                                    <option value="">All Categories</option>
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat->id }}" {{ request('item_category_id') == $cat->id ? 'selected' : '' }}>
                                                            {{ $cat->name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="d-flex gap-2 justify-content-end mt-4">
                                                <a href="{{ route('hrms.assets.index', request()->except(['item_search', 'item_company_id', 'item_category_id'])) }}" class="btn btn-sm btn-light border">{{ __('hrms.common.reset') }}</a>
                                                <button type="submit" class="btn btn-sm btn-primary">{{ __('hrms.common.apply') }}</button>
                                            </div>
                                        </x-ui.filter>

                                    @if(request()->anyFilled(['item_search', 'item_company_id', 'item_category_id']))
                                        <a href="{{ route('hrms.assets.index', request()->except(['item_search', 'item_company_id', 'item_category_id'])) }}" class="btn btn-sm btn-light border px-2 d-flex align-items-center justify-content-center" style="height: 38px; border-radius: 6px; font-size: 12px;" title="Clear Filters">
                                            <i class="feather-x"></i>
                                        </a>
                                    @endif
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                                     <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                                         <tr>
                                             <th style="width: 35%;" class="py-3 px-4 text-start">Item Name</th>
                                             <th style="width: 25%;" class="py-3">Category</th>
                                             <th style="width: 15%;" class="py-3">Registered Units</th>
                                             <th style="width: 15%;" class="py-3">Available Units</th>
                                             <th style="width: 110px; white-space: nowrap;" class="py-3 text-end px-4">{{ __('hrms.assets.actions') }}</th>
                                         </tr>
                                     </thead>
                                     <tbody class="fs-12">
                                         @forelse($filteredItems as $itemObj)
                                             <tr class="item-main-row" data-item-id="{{ $itemObj->id }}">
                                                 <td class="py-3 px-4 text-start" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                                     <div class="fw-bold text-dark fs-13">{{ $itemObj->name }}</div>
                                                     @if($itemObj->description)
                                                         <div class="text-muted fs-11 mt-0.5 d-flex align-items-center gap-1 desc-expandable-container">
                                                             <span class="text-truncate desc-text-truncate" style="max-width: 240px;" title="{{ $itemObj->description }}">{{ $itemObj->description }}</span>
                                                             <a href="javascript:void(0)" class="text-primary text-decoration-none fw-bold fs-10 btn-read-more-dynamic d-none" data-title="{{ $itemObj->name }}" data-desc="{{ $itemObj->description }}" style="white-space: nowrap;">(Read More)</a>
                                                         </div>
                                                     @endif
                                                 </td>
                                                 <td class="py-3 text-muted" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">{{ $itemObj->category->name ?? 'N/A' }}</td>
                                                 <td class="py-3"><span class="badge bg-light text-dark fw-bold px-2.5 py-1.5 fs-11 rounded-pill">{{ $itemObj->assets->count() }}</span></td>
                                                <td class="py-3">
                                                    @php
                                                        $availCount = $itemObj->assets->where('status', 'available')->count();
                                                    @endphp
                                                    <span class="badge {{ $availCount > 0 ? 'badge-available' : 'bg-light text-muted' }} px-2.5 py-1.5 fs-11 rounded-pill">
                                                        {{ $availCount }}
                                                    </span>
                                                </td>
                                                <td class="py-3 text-end px-4">
                                                    <div class="d-flex justify-content-end align-items-center gap-2">
                                                        <button class="btn btn-sm btn-icon btn-light toggle-assets-btn" type="button" data-item-id="{{ $itemObj->id }}" style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1.5px solid #cbd5e1; background-color: #ffffff; color: #475569;" title="Toggle Serialized Assets">
                                                            <i class="feather-chevron-right toggle-icon"></i>
                                                        </button>
                                                        <x-ui.action-dropdown>
                                                            @php
                                                                $firstAsset = $itemObj->assets->first();
                                                                $encodedAssets = base64_encode($itemObj->assets->toJson());
                                                                $availableCount = $itemObj->assets->where('status', 'available')->count();
                                                                $allocatedCount = $itemObj->assets->where('status', 'allocated')->count();

                                                                $allocatedAssetsOnly = $itemObj->assets->where('status', 'allocated')->values();
                                                                $encodedAllocatedAssets = base64_encode($allocatedAssetsOnly->toJson());

                                                                $allocationsByEmployee = $itemObj->assets->where('status', 'allocated')
                                                                    ->groupBy('assigned_employee_id')
                                                                    ->map(function($assets) {
                                                                        $first = $assets->first();
                                                                        return [
                                                                            'employee_id' => $first->assigned_employee_id,
                                                                            'employee_name' => $first->assignedEmployee->display_name ?? 'Unknown',
                                                                            'count' => $assets->count()
                                                                        ];
                                                                    })->values();
                                                                $encodedAllocations = base64_encode($allocationsByEmployee->toJson());
                                                            @endphp
                                                            <li>
                                                                <a class="dropdown-item edit-asset-item-btn" href="#" 
                                                                   data-bs-toggle="modal" 
                                                                   data-bs-target="#editAssetItemModal" 
                                                                   data-id="{{ $itemObj->id }}" 
                                                                   data-name="{{ $itemObj->name }}" 
                                                                   data-category="{{ $itemObj->asset_category_id }}"
                                                                   data-description="{{ $itemObj->description }}"
                                                                   data-brand="{{ $firstAsset->brand ?? '' }}"
                                                                   data-model-number="{{ $firstAsset->model_number ?? '' }}"
                                                                   data-purchase-date="{{ $firstAsset && $firstAsset->purchase_date ? $firstAsset->purchase_date->format('Y-m-d') : '' }}"
                                                                   data-purchase-cost="{{ $firstAsset->purchase_cost ?? '' }}"
                                                                   data-condition="{{ $firstAsset->condition ?? 'good' }}"
                                                                   data-notes="{{ $firstAsset->notes ?? '' }}"
                                                                   data-units="{{ $encodedAssets }}">
                                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('hrms.assets.edit') }}
                                                                </a>
                                                            </li>
                                                            @if($availableCount > 0)
                                                            <li>
                                                                <a class="dropdown-item item-allocate-trigger-btn" href="#" 
                                                                   data-bs-toggle="modal" 
                                                                   data-bs-target="#allocateAssetModal" 
                                                                   data-item-id="{{ $itemObj->id }}" 
                                                                   data-item-name="{{ $itemObj->name }}" 
                                                                   data-company-id="{{ $itemObj->category->company_id ?? $itemObj->company_id }}" 
                                                                   data-available="{{ $availableCount }}">
                                                                    <i class="feather-user-check me-2 text-muted fs-12"></i>Allocate
                                                                </a>
                                                            </li>
                                                            @endif
                                                            @if($allocatedCount > 0)
                                                            <li>
                                                                <a class="dropdown-item item-return-trigger-btn" href="#" 
                                                                   data-bs-toggle="modal" 
                                                                   data-bs-target="#returnAssetModal" 
                                                                   data-item-id="{{ $itemObj->id }}" 
                                                                   data-item-name="{{ $itemObj->name }}" 
                                                                   data-allocations="{{ $encodedAllocations }}"
                                                                   data-allocated-assets="{{ $encodedAllocatedAssets }}">
                                                                    <i class="feather-user-x me-2 text-muted fs-12"></i>Return
                                                                </a>
                                                            </li>
                                                            @endif
                                                            @php
                                                                $rawAllocations = \App\Domains\HRMS\Models\AssetAllocation::whereIn('asset_id', $itemObj->assets->pluck('id'))
                                                                    ->with(['asset', 'employee'])
                                                                    ->orderBy('allocated_at', 'desc')
                                                                    ->get();

                                                                $groupedItemAllocations = [];
                                                                foreach ($rawAllocations->groupBy(function($alloc) {
                                                                    $empId = $alloc->employee_id;
                                                                    $allocDate = $alloc->allocated_at ? $alloc->allocated_at->format('Y-m-d') : 'no_date';
                                                                    $retDate = $alloc->returned_at ? $alloc->returned_at->format('Y-m-d') : 'active';
                                                                    return $empId . '_' . $allocDate . '_' . $retDate;
                                                                }) as $groupItems) {
                                                                    $first = $groupItems->first();
                                                                    $units = [];
                                                                    foreach ($groupItems as $gItem) {
                                                                        if ($gItem->asset) {
                                                                            $units[] = [
                                                                                'code' => $gItem->asset->asset_code,
                                                                                'serial' => $gItem->asset->serial_number ?: 'N/A'
                                                                            ];
                                                                        }
                                                                    }
                                                                    $groupedItemAllocations[] = [
                                                                        'employee' => $first->employee ? [
                                                                            'display_name' => $first->employee->display_name,
                                                                            'employee_id' => $first->employee->employee_id,
                                                                        ] : null,
                                                                        'allocated_at' => $first->allocated_at ? $first->allocated_at->format('d M, Y') : '-',
                                                                        'returned_at' => $first->returned_at ? $first->returned_at->format('d M, Y') : null,
                                                                        'allocation_condition' => $first->allocation_condition ?? 'good',
                                                                        'return_condition' => $first->return_condition ?? null,
                                                                        'units' => $units,
                                                                        'qty' => count($units),
                                                                    ];
                                                                }

                                                                $encodedItemAllocations = base64_encode(json_encode($groupedItemAllocations));
                                                            @endphp
                                                            <li>
                                                                <a class="dropdown-item show-item-history-btn" href="javascript:void(0);" 
                                                                   data-item-name="{{ $itemObj->name }}" 
                                                                   data-item-allocations="{{ $encodedItemAllocations }}">
                                                                    <i class="feather-clock me-2 text-muted fs-12"></i>Allocation History
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <form action="{{ route('hrms.assets.item.destroy', $itemObj->id) }}" method="POST" class="d-inline" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this item?', { title: 'Delete Asset Item', variant: 'danger', confirmButtonText: 'Delete' });">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="dropdown-item text-danger">
                                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('hrms.assets.delete') }}
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </x-ui.action-dropdown>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr id="assets-row-{{ $itemObj->id }}" class="assets-collapse-row d-none" style="background-color: #f8fafc;">
                                                <td colspan="5" class="p-3">
                                                    <div class="card border rounded shadow-sm bg-white m-2">
                                                        <div class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                                                            <span class="fw-bold text-dark fs-12 text-start"><i class="feather-package me-1 text-primary"></i>Serialized Assets Registry for {{ $itemObj->name }}</span>
                                                            <span class="badge bg-primary fs-11 rounded-pill">{{ $itemObj->assets->count() }} Units</span>
                                                        </div>
                                                        <div class="card-body p-0">
                                                            <div class="table-responsive" style="overflow-x: hidden;">
                                                                <table class="table table-sm table-hover align-middle mb-0 text-center fs-12" style="table-layout: fixed; width: 100%;">
                                                                    <thead class="table-light text-uppercase fs-10" style="letter-spacing: 0.5px;">
                                                                        <tr>
                                                                            <th style="width: 18%;" class="py-2.5 px-3 text-start">Asset Code</th>
                                                                            <th style="width: 22%;" class="py-2.5">Serial Number</th>
                                                                            <th style="width: 15%;" class="py-2.5">Condition</th>
                                                                            <th style="width: 15%;" class="py-2.5">Status</th>
                                                                            <th style="width: 20%;" class="py-2.5">Assigned To</th>
                                                                            <th style="width: 10%; white-space: nowrap;" class="py-2.5 text-end px-3">Actions</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @forelse($itemObj->assets as $asset)
                                                                            <tr>
                                                                                <td class="py-2 px-3 text-start fw-bold text-dark">
                                                                                    <a href="javascript:void(0);" class="show-history-btn" data-asset-name="{{ $asset->name }} ({{ $asset->asset_code }})" data-allocations="{{ base64_encode($asset->allocations()->with('employee')->get()->toJson()) }}">
                                                                                        {{ $asset->asset_code }}
                                                                                    </a>
                                                                                </td>
                                                                                <td class="py-2 text-muted">{{ $asset->serial_number ?? 'N/A' }}</td>
                                                                                <td class="py-2">
                                                                                    @php
                                                                                        $condBadge = [
                                                                                            'new' => 'bg-soft-success text-success',
                                                                                            'good' => 'bg-soft-info text-info',
                                                                                            'fair' => 'bg-soft-warning text-warning',
                                                                                            'damaged' => 'bg-soft-danger text-danger',
                                                                                            'scrapped' => 'bg-soft-secondary text-secondary'
                                                                                        ];
                                                                                        $badgeStyleClass = $condBadge[$asset->condition] ?? 'bg-light text-muted';
                                                                                    @endphp
                                                                                    <span class="badge {{ $badgeStyleClass }} rounded-pill px-2 py-1 fs-11">{{ ucfirst($asset->condition) }}</span>
                                                                                </td>
                                                                                <td class="py-2">
                                                                                    @php
                                                                                        $statusColors = [
                                                                                            'available' => 'badge-available',
                                                                                            'allocated' => 'badge-allocated',
                                                                                            'maintenance' => 'badge-maintenance',
                                                                                            'scrapped' => 'badge-scrapped'
                                                                                        ];
                                                                                        $badgeStyle = $statusColors[$asset->status] ?? 'bg-light text-muted';
                                                                                    @endphp
                                                                                    <span class="badge {{ $badgeStyle }} px-2 py-1 fs-11 rounded-pill">
                                                                                        {{ ucfirst($asset->status) }}
                                                                                    </span>
                                                                                </td>
                                                                                <td class="py-2 text-muted">
                                                                                    @if($asset->status === 'allocated' && $asset->assignedEmployee)
                                                                                        <div class="fw-semibold text-dark fs-11">{{ $asset->assignedEmployee->display_name }}</div>
                                                                                        <div class="fs-9 text-muted mt-0.5" style="font-size: 9px;">Since {{ $asset->allocated_at ? $asset->allocated_at->format('d M, Y') : '-' }}</div>
                                                                                    @else
                                                        -
                                                                                    @endif
                                                                                </td>
                                                                                <td class="py-2 text-end px-3">
                                                                                    <div class="d-flex justify-content-end gap-1 align-items-center">
                                                                                        <button type="button" class="btn btn-xs btn-icon btn-light text-primary show-history-btn" title="View Allocation History" data-asset-name="{{ $asset->name }} ({{ $asset->asset_code }})" data-allocations="{{ base64_encode($asset->allocations()->with('employee')->get()->toJson()) }}">
                                                                                            <i class="feather-clock" style="font-size: 11px;"></i>
                                                                                        </button>
                                                                                        <form action="{{ route('hrms.assets.destroy', $asset->id) }}" method="POST" class="d-inline" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this asset record?', { title: 'Delete Serialized Asset', variant: 'danger', confirmButtonText: 'Delete' });">
                                                                                            @csrf
                                                                                            @method('DELETE')
                                                                                            <button type="submit" class="btn btn-xs btn-icon btn-light text-danger" title="Delete">
                                                                                                <i class="feather-trash-2" style="font-size: 10px;"></i>
                                                                                            </button>
                                                                                        </form>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        @empty
                                                                            <tr>
                                                                                <td colspan="6" class="py-3 text-muted text-center fs-11">No physical units registered under this item.</td>
                                                                            </tr>
                                                                        @endforelse
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-5 text-muted fs-12">
                                                    <i class="feather-box fs-32 d-block mb-3 text-secondary"></i>
                                                    <div class="fw-bold mb-1">No Items Configured</div>
                                                    <div>Create an item master to catalog serialized hardware.</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @php
                            $itemCurrentPage = $filteredItems->currentPage();
                            $itemTotalPages = $filteredItems->lastPage();
                            $itemTotalResults = $filteredItems->total();
                            $itemPerPage = $filteredItems->perPage();
                        @endphp
                        @if($filteredItems->hasPages())
                            <div class="card-footer bg-white border-top px-4 py-3">
                                <x-ui.pagination
                                    class="px-0 py-0"
                                    :current-page="$itemCurrentPage"
                                    :total-pages="$itemTotalPages"
                                    :total-results="$itemTotalResults"
                                    :per-page="$itemPerPage"
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
                                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.assets.requests_title') }}</h5>
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
                                        <input type="text" name="request_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.assets.search_requests_placeholder') }}" value="{{ request('request_search') }}" style="box-shadow: none; height: 32px;">
                                    </div>

                                    <div class="d-flex gap-2 align-items-center">
                                        <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                                            <a class="dropdown-item py-2 {{ request('request_sort', 'newest') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'newest', this); event.preventDefault();">{{ __('hrms.assets.sort_newest') }}</a>
                                            <a class="dropdown-item py-2 {{ request('request_sort') == 'oldest' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'oldest', this); event.preventDefault();">{{ __('hrms.assets.sort_oldest') }}</a>
                                            <a class="dropdown-item py-2 {{ request('request_sort') == 'status_asc' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'status_asc', this); event.preventDefault();">{{ __('hrms.assets.sort_status_asc') }}</a>
                                            <a class="dropdown-item py-2 {{ request('request_sort') == 'status_desc' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'status_desc', this); event.preventDefault();">{{ __('hrms.assets.sort_status_desc') }}</a>
                                        </x-ui.sort-dropdown>

                                        <x-ui.filter label="{{ __('hrms.assets.filters') }}" offset="0, 5">
                                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                            
                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.assets.requested_category') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="request_category_id">
                                                    <option value="">{{ __('hrms.assets.all_categories') }}</option>
                                                    @foreach($categories as $category)
                                                        <option value="{{ $category->id }}" {{ request('request_category_id') == $category->id ? 'selected' : '' }}>
                                                            {{ $category->name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.assets.org_entity') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="request_company_id">
                                                    <option value="">{{ __('hrms.common.all_companies') }}</option>
                                                    @foreach($companies as $company)
                                                        <option value="{{ $company->id }}" {{ request('request_company_id') == $company->id ? 'selected' : '' }}>
                                                            {{ $company->company_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.assets.status') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="request_status">
                                                    <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                                    <option value="pending" {{ request('request_status') === 'pending' ? 'selected' : '' }}>{{ __('hrms.assets.status_pending') }}</option>
                                                    <option value="partially_allocated" {{ request('request_status') === 'partially_allocated' ? 'selected' : '' }}>Partially Allocated</option>
                                                    <option value="allocated" {{ request('request_status') === 'allocated' ? 'selected' : '' }}>{{ __('hrms.assets.status_allocated') }}</option>
                                                    <option value="rejected" {{ request('request_status') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.assets.status_rejected') }}</option>
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <div class="d-flex gap-2 justify-content-end mt-4">
                                                <a href="{{ route('hrms.assets.index', request()->except(['request_search', 'request_category_id', 'request_company_id', 'request_status'])) }}" class="btn btn-sm btn-light border">{{ __('hrms.common.reset') }}</a>
                                                <button type="submit" class="btn btn-sm btn-primary">{{ __('hrms.common.apply') }}</button>
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
                            <!-- Bulk Actions Toolbar (Shifted below search/filter above the table) -->
                            <div id="bulkActionsToolbar" class="d-none border-bottom px-4 py-2 bg-light">
                                <div class="d-flex justify-content-end align-items-center gap-2">
                                    <span class="fs-12 text-muted fw-bold me-1"><span id="selectedRequestsCount">0</span> {{ __('hrms.assets.selected') }}</span>
                                    <button type="button" class="btn btn-sm btn-primary text-uppercase fw-bold px-3 py-1.5" id="btnBulkAllocate" style="font-size: 11px; border-radius: 6px; letter-spacing: 0.5px;">
                                        <i class="feather-user-check me-1"></i> {{ __('hrms.assets.bulk_allocate') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger text-uppercase fw-bold px-3 py-1.5" id="btnBulkReject" style="font-size: 11px; border-radius: 6px; letter-spacing: 0.5px;">
                                        <i class="feather-x me-1"></i> {{ __('hrms.assets.bulk_reject') }}
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                                    <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                                        <tr>
                                            <th style="width: 45px; padding-left: 20px;"><input type="checkbox" id="selectAllRequests" class="form-check-input"></th>
                                            <th class="text-start" style="width: 35%;">{{ __('hrms.employees.title') }} & {{ __('hrms.assets.org_entity') }}</th>
                                            <th class="text-start" style="width: 35%;">{{ __('hrms.assets.req_asset') }} & {{ __('hrms.assets.status') }}</th>
                                            <th class="text-end px-4" style="width: 180px; white-space: nowrap;">{{ __('hrms.assets.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($requests as $req)
                                            @php 
                                                if (isset($req->allocated_assets_count)) {
                                                    $allocatedCount = $req->allocated_assets_count;
                                                } else {
                                                    $allocatedCount = ($req->status === 'allocated' ? $req->quantity : ($req->allocated_asset_id ? 1 : 0));
                                                }
                                                $remainingQty = max(0, $req->quantity - $allocatedCount); 
                                            @endphp
                                            <tr>
                                                <td style="padding-left: 20px;">
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
                                                        <i class="feather-calendar me-1 text-primary"></i>Requested: <span class="fw-medium text-dark">{{ $req->request_date ? $req->request_date->format('d M, Y') : '-' }}</span>
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
                                                        Rem: <strong class="{{ $remainingQty > 0 ? 'text-danger' : 'text-muted' }}">{{ $remainingQty }}</strong>
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
                                                            <span class="badge bg-light text-secondary px-2.5 py-1 rounded-pill fs-11 text-capitalize">{{ __('hrms.assets.status_' . $req->status) }}</span>
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
                                                            style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1.5px solid #cbd5e1; background-color: #ffffff; color: #475569;"
                                                            title="View Request Details & Reason"
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
                                                            data-admin-notes="{{ $req->admin_notes ?: '' }}"
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
                                                    <div class="fw-bold mb-1">{{ __('hrms.assets.empty_requests_title') }}</div>
                                                    <div>{{ __('hrms.assets.empty_requests_desc') }}</div>
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
    <div class="modal fade" id="addAssetModal" aria-labelledby="addAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="addAssetModalLabel">
                        <i class="feather-package me-2 text-primary"></i>{{ __('hrms.assets.log_new_asset') }}
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
                                        <option value="{{ $category->id }}" {{ old('asset_category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }} ({{ $category->company->company_name ?? 'All' }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Item Name" name="name" placeholder="e.g. Laptop, Mobile Phone, Office Desk" :required="true" value="{{ old('name') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Item Description" name="description" placeholder="Brief details about this item..." value="{{ old('description') }}" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.brand_vendor') }}" name="brand" placeholder="e.g. Apple" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.model_number') }}" name="model_number" placeholder="e.g. A2442" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.purchase_date') }}" name="purchase_date" inputType="date" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.purchase_cost') }}" name="purchase_cost" inputType="number" step="0.01" placeholder="0.00" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.internal_notes') }}" name="notes" placeholder="Condition details, license specifications, configurations..." />
                            </div>
                            
                            <div class="col-12 border-top pt-3 mt-3">
                                <h6 class="fw-bold text-dark mb-3">Serialized Units Registry</h6>
                                
                                <!-- Code generator panel -->
                                <div class="bg-light p-3 rounded mb-3 border d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="form-label mb-0 fs-12 fw-bold text-muted">Generate Sequential Codes:</label>
                                        <input type="text" id="gen_prefix" class="form-control form-control-sm" placeholder="Prefix (e.g. AST-)" style="width: 200px; height: 32px;">
                                        <input type="number" id="gen_count" class="form-control form-control-sm" placeholder="Count" min="1" max="50" style="width: 100px; height: 32px;">
                                        <button type="button" class="btn btn-sm btn-primary fw-bold text-uppercase" id="btn-generate-units" style="height: 32px;">Generate</button>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-soft-primary fw-bold text-uppercase" id="btn-add-unit-row" style="height: 32px;"><i class="feather-plus me-1"></i>Add Row</button>
                                </div>

                                <div class="table-responsive border rounded bg-white" style="max-height: 250px;">
                                    <table class="table table-sm table-hover align-middle mb-0 text-center" id="bulk-units-table">
                                        <thead class="table-light text-uppercase fs-11" style="position: sticky; top: 0; z-index: 2;">
                                            <tr>
                                                <th class="py-2.5 px-3 text-start">Asset Code (Unique ID) *</th>
                                                <th class="py-2.5">Serial Number *</th>
                                                <th class="py-2.5">Condition *</th>
                                                <th class="py-2.5 text-end px-3">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bulk-units-tbody">
                                            <tr>
                                                <td class="py-2 px-3 text-start">
                                                    <input type="text" name="units[0][asset_code]" class="form-control form-control-sm text-center" placeholder="e.g. AST-001" required>
                                                </td>
                                                <td class="py-2">
                                                    <input type="text" name="units[0][serial_number]" class="form-control form-control-sm text-center" placeholder="e.g. SN-XXXX" required>
                                                </td>
                                                <td class="py-2" style="min-width: 120px;">
                                                    <select name="units[0][condition]" class="form-select form-select-sm" required>
                                                        <option value="good">Good</option>
                                                        <option value="new">New</option>
                                                        <option value="fair">Fair</option>
                                                        <option value="damaged">Damaged</option>
                                                        <option value="scrapped">Scrapped</option>
                                                    </select>
                                                </td>
                                                <td class="py-2 text-end px-3">
                                                    <div class="d-flex justify-content-end gap-1">
                                                        <button type="button" class="btn btn-sm btn-soft-danger btn-remove-unit-row" disabled><i class="feather-trash-2"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.assets.log_asset') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2: EDIT ASSET -->
    <div class="modal fade" id="editAssetModal" aria-labelledby="editAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="editAssetModalLabel">
                        <i class="feather-edit-3 me-2 text-primary"></i>{{ __('hrms.assets.modify_asset_details') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAssetForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.asset_item') }}" name="asset_item_id" id="edit_asset_item_id" :required="true" select2-selector="default">
                                    <option value="">{{ __('hrms.assets.select_item') }}</option>
                                    @foreach($items as $itm)
                                        <option value="{{ $itm->id }}">{{ $itm->name }} (Category: {{ $itm->category->name ?? 'N/A' }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.asset_code_label') }}" name="asset_code" id="edit_asset_code" :required="true" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.asset_name_label') }}" name="name" id="edit_name" :required="true" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.brand_vendor') }}" name="brand" id="edit_brand" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.model_number') }}" name="model_number" id="edit_model_number" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.serial_number') }}" name="serial_number" id="edit_serial_number" :required="true" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.purchase_date') }}" name="purchase_date" id="edit_purchase_date" inputType="date" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.purchase_cost') }}" name="purchase_cost" id="edit_purchase_cost" inputType="number" step="0.01" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.condition') }}" name="condition" id="edit_condition" :required="true" select2-selector="default">
                                    <option value="good">{{ __('hrms.assets.cond_good') }}</option>
                                    <option value="new">{{ __('hrms.assets.cond_new') }}</option>
                                    <option value="fair">{{ __('hrms.assets.cond_fair') }}</option>
                                    <option value="damaged">{{ __('hrms.assets.cond_damaged') }}</option>
                                    <option value="scrapped">{{ __('hrms.assets.cond_scrapped') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.internal_notes') }}" name="notes" id="edit_notes" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.common.save_changes') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
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
                        <i class="feather-upload me-2 text-primary" style="font-size: 16px;"></i>{{ __('hrms.assets.import_assets') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.assets.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body text-start">
                        <div class="alert bg-light border-0 d-flex flex-column gap-2 p-3 mb-4 rounded-3 text-dark fs-12">
                            <div class="d-flex align-items-center gap-2">
                                <i class="feather-info text-primary fs-15"></i>
                                <span class="fw-bold">{{ __('hrms.assets.import_instructions_title') }}</span>
                            </div>
                            <span class="text-muted leading-relaxed">
                                {{ __('hrms.assets.import_instructions_desc') }}
                            </span>
                            <div class="mt-1">
                                <a href="{{ route('hrms.assets.import.template') }}" class="btn btn-xs btn-soft-primary d-inline-flex align-items-center fw-bold py-1.5 px-3" style="border-radius: 6px; font-size: 11px;">
                                    <i class="feather-download me-1.5 fs-12"></i> {{ __('hrms.assets.download_template') }}
                                </a>
                            </div>
                        </div>
                        <div class="col-12">
                             <div class="erp-custom-file-upload">
                                 <label class="file-upload-label py-3 px-4 w-100" style="cursor: pointer; border-style: dashed; border-width: 2px;" for="asset_import_file">
                                     <i class="feather-upload-cloud me-2 text-primary fs-20"></i>
                                     <span class="file-text text-muted" id="asset_import_file_text">{{ __('hrms.assets.select_excel_file') }}</span>
                                     <input type="file" name="file" id="asset_import_file" class="d-none" required accept=".xlsx" onchange="document.getElementById('asset_import_file_text').innerText = this.files[0]?.name || '{{ __('hrms.assets.select_excel_file') }}'">
                                 </label>
                             </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.import') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
 


    <div class="modal fade" id="allocateAssetModal" aria-labelledby="allocateAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="allocateAssetModalLabel">
                        <i class="feather-user-check me-2 text-primary"></i>{{ __('hrms.assets.assign_asset') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="allocateAssetForm" method="POST">
                    @csrf
                    <input type="hidden" name="request_id" id="allocate_request_id">
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Container A: Registry Checkout (Pre-selected Item, select Employee, specify Qty) -->
                            <div id="registry_checkout_container" class="col-12 p-0 m-0 row g-3">
                                <div class="col-12">
                                    <label class="info-label mb-1">Asset Item</label>
                                    <input type="text" id="allocate_asset_name_display" class="form-control bg-light" readonly>
                                </div>
                                <div class="col-6">
                                    <x-ui.odoo-form-ui type="input" label="Available Units" id="allocate_available_qty_display" :readonly="true" />
                                </div>
                                <div class="col-6">
                                    <x-ui.odoo-form-ui type="input" label="Qty to Allocate" name="quantity" id="allocate_quantity_input" inputType="number" min="1" :required="true" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.employee') }}" name="assigned_employee_id" id="registry_employee_select" :required="true" select2-selector="default">
                                        <option value="">{{ __('hrms.assets.select_employee') }}</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}" data-company-id="{{ $employee->company_id }}">{{ $employee->display_name }} ({{ $employee->employee_id }})</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>
 
                            <!-- Container B: Request Checkout (Pre-selected Employee, select Asset) -->
                            <div id="request_checkout_container" class="col-12 p-0 m-0 row g-3 d-none">
                                <div class="col-12">
                                    <label class="info-label mb-1">{{ __('hrms.assets.employee') }}</label>
                                    <input type="text" id="allocate_employee_name_display" class="form-control bg-light" readonly>
                                    <input type="hidden" name="assigned_employee_id" id="request_employee_id" disabled>
                                </div>
                                <div class="col-4">
                                    <label class="info-label mb-1">Requested</label>
                                    <input type="text" id="allocate_requested_qty" class="form-control bg-light text-center fw-bold" readonly>
                                </div>
                                <div class="col-4">
                                    <label class="info-label mb-1">Allocated</label>
                                    <input type="text" id="allocate_already_allocated_qty" class="form-control bg-light text-center text-success fw-bold" readonly>
                                </div>
                                <div class="col-4">
                                    <label class="info-label mb-1">Remaining</label>
                                    <input type="text" id="allocate_remaining_qty" class="form-control bg-light text-center text-danger fw-bold" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="info-label mb-2 fw-bold text-dark d-block">Select Serialized Assets to Assign</label>
                                    <div id="request_assets_checklist" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                        <!-- Checklist populated via JS -->
                                    </div>
                                    <small class="text-muted mt-1 d-block">Select physical units to fulfill the request (maximum <span id="max_selectable_count" class="fw-bold text-primary">0</span> unit(s)).</small>
                                </div>
                            </div>
 
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.allocation_date') }}" name="allocated_at" inputType="date" :required="true" value="{{ date('Y-m-d') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.expected_return_date') }}" name="expected_return_date" inputType="date" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.assets.confirm_allocation') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 4: ADD CATEGORY -->
    <div class="modal fade" id="addCategoryModal" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="addCategoryModalLabel">
                        <i class="feather-sliders me-2 text-primary"></i>{{ __('hrms.assets.create_category') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.assets.category.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.belongs_to_company') }}" name="company_id" :required="true" select2-selector="default">
                                    <option value="">{{ __('hrms.assets.select_company') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.category_name') }}" name="name" placeholder="e.g. IT Laptops, Office Car Keys" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.description') }}" name="description" placeholder="Brief details about what items go into this category..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.assets.add_category') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
 
    <!-- MODAL 4B: EDIT CATEGORY -->
    <div class="modal fade" id="editCategoryModal" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="editCategoryModalLabel">
                        <i class="feather-sliders me-2 text-primary"></i>{{ __('hrms.assets.edit_category') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editCategoryForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.belongs_to_company') }}" name="company_id" id="edit_category_company_id" :required="true" select2-selector="default">
                                    <option value="">{{ __('hrms.assets.select_company') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.category_name') }}" name="name" id="edit_category_name" placeholder="e.g. IT Laptops, Office Car Keys" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.description') }}" name="description" id="edit_category_description" placeholder="Brief details about what items go into this category..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.common.save_changes') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- MODAL 4C: ADD ASSET ITEM -->
    <div class="modal fade" id="addAssetItemModal" tabindex="-1" aria-labelledby="addAssetItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="addAssetItemModalLabel">
                        <i class="feather-box me-2 text-primary"></i>{{ __('hrms.assets.create_item') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.assets.item.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Asset Category" name="asset_category_id" :required="true" select2-selector="default">
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->company->company_name ?? 'All' }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Item Name" name="name" placeholder="e.g. Laptop, Mobile Phone, Office Desk" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.description') }}" name="description" placeholder="Brief details about this item..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Create Item</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 4D: EDIT ASSET ITEM -->
    <div class="modal fade" id="editAssetItemModal" aria-labelledby="editAssetItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="editAssetItemModalLabel">
                        <i class="feather-box me-2 text-primary"></i>Edit Item & Serialized Assets
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAssetItemForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Asset Category" name="asset_category_id" id="edit_item_category_id" :required="true" select2-selector="default">
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->company->company_name ?? 'All' }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Item Name" name="name" id="edit_item_name" placeholder="e.g. Laptop, Mobile Phone, Office Desk" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Item Description" name="description" id="edit_item_description" placeholder="Brief details about this item..." />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.brand_vendor') }}" name="brand" id="edit_item_brand" placeholder="e.g. Apple" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.model_number') }}" name="model_number" id="edit_item_model_number" placeholder="e.g. A2442" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.purchase_date') }}" name="purchase_date" id="edit_item_purchase_date" inputType="date" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.purchase_cost') }}" name="purchase_cost" id="edit_item_purchase_cost" inputType="number" step="0.01" placeholder="0.00" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.internal_notes') }}" name="notes" id="edit_item_notes" placeholder="Condition details, license specifications, configurations..." />
                            </div>

                            <div class="col-12 border-top pt-3 mt-3">
                                <h6 class="fw-bold text-dark mb-3">Serialized Units Registry</h6>
                                
                                <!-- Code generator panel -->
                                <div class="bg-light p-3 rounded mb-3 border d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="form-label mb-0 fs-12 fw-bold text-muted">Generate Sequential Codes:</label>
                                        <input type="text" id="edit_item_gen_prefix" class="form-control form-control-sm" placeholder="Prefix (e.g. AST-)" style="width: 200px; height: 32px;">
                                        <input type="number" id="edit_item_gen_count" class="form-control form-control-sm" placeholder="Count" min="1" max="50" style="width: 100px; height: 32px;">
                                        <button type="button" class="btn btn-sm btn-primary fw-bold text-uppercase" id="edit-item-btn-generate-units" style="height: 32px;">Generate</button>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-soft-primary fw-bold text-uppercase" id="edit-item-btn-add-unit-row" style="height: 32px;"><i class="feather-plus me-1"></i>Add Row</button>
                                </div>

                                <div class="table-responsive border rounded bg-white" style="max-height: 250px;">
                                    <table class="table table-sm table-hover align-middle mb-0 text-center" id="edit-item-bulk-units-table">
                                        <thead class="table-light text-uppercase fs-11" style="position: sticky; top: 0; z-index: 2;">
                                            <tr>
                                                <th class="py-2.5 px-3 text-start">Asset Code (Unique ID) *</th>
                                                <th class="py-2.5">Serial Number *</th>
                                                <th class="py-2.5">Condition *</th>
                                                <th class="py-2.5 text-end px-3">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="edit-item-bulk-units-tbody">
                                            <!-- Dynamically populated -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.common.save_changes') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="returnAssetModal" aria-labelledby="returnAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="returnAssetModalLabel">
                        <i class="feather-corner-up-left me-2 text-primary"></i>{{ __('hrms.assets.return_asset') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="returnAssetForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="info-label mb-1">Asset Item</label>
                                <input type="text" id="return_asset_name_display" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.employee') }}" name="employee_id" id="return_employee_select" :required="true" select2-selector="default">
                                    <option value="">Select Employee</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <label class="info-label mb-2 fw-bold text-dark d-block">Select Serialized Assets to Return</label>
                                <div id="return_assets_checklist" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <span class="text-muted fs-12">Please select an employee first.</span>
                                </div>
                                <small class="text-muted mt-1 d-block">Select the specific physical units being returned.</small>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.return_date') }}" name="returned_at" inputType="date" :required="true" value="{{ date('Y-m-d') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.return_condition') }}" name="return_condition" :required="true" select2-selector="default">
                                    <option value="good">{{ __('hrms.assets.cond_good') }}</option>
                                    <option value="new">{{ __('hrms.assets.cond_new') }}</option>
                                    <option value="fair">{{ __('hrms.assets.cond_fair') }}</option>
                                    <option value="damaged">{{ __('hrms.assets.cond_damaged') }} ({{ __('hrms.assets.needs_maintenance') }})</option>
                                    <option value="scrapped">{{ __('hrms.assets.cond_scrapped') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.return_notes') }}" name="return_notes" placeholder="Condition details, damage details, return notes..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.assets.process_return') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.cancel') }}</button>
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
                        <i class="feather-clock me-2 text-primary"></i>{{ __('hrms.assets.allocation_history') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 bg-light border-bottom">
                        <span class="text-muted">{{ __('hrms.assets.asset_lbl') }}</span> <strong id="history_asset_name_display" class="text-dark"></strong>
                    </div>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead class="table-light fs-11 text-uppercase">
                                <tr>
                                    <th class="text-start" style="padding-left: 20px;">{{ __('hrms.assets.employee') }}</th>
                                    <th>{{ __('hrms.assets.allocation_date') }}</th>
                                    <th>{{ __('hrms.assets.return_date') }}</th>
                                    <th>{{ __('hrms.assets.alloc_cond_lbl') }}</th>
                                    <th>{{ __('hrms.assets.return_cond_lbl') }}</th>
                                    <th class="text-start" style="padding-right: 20px;">{{ __('hrms.assets.internal_notes') }}</th>
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

    <!-- MODAL: ITEM ALLOCATION HISTORY LOG -->
    <div class="modal fade" id="itemHistoryModal" tabindex="-1" aria-labelledby="itemHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="itemHistoryModalLabel">
                        <i class="feather-clock me-2 text-primary"></i>Item Master Allocation History
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-12">Item Master:</span> <strong id="item_history_name_display" class="text-dark fs-14"></strong>
                        </div>
                        <span class="badge bg-primary fs-11 rounded-pill" id="item_history_total_count">0 Events</span>
                    </div>
                    <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 text-center fs-12">
                            <thead class="table-light fs-11 text-uppercase">
                                <tr>
                                    <th class="text-start px-3">Asset Unit</th>
                                    <th class="text-start px-3">Employee</th>
                                    <th>Allocated Date</th>
                                    <th>Returned Date</th>
                                    <th>Issue Condition</th>
                                    <th>Return Condition</th>
                                </tr>
                            </thead>
                            <tbody id="item_history_table_body">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light-brand px-4" data-bs-dismiss="modal">Close</button>
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
                        <i class="feather-alert-octagon me-2 text-danger"></i>{{ __('hrms.assets.reject_request') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rejectRequestForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.rejection_reason') }}" name="admin_notes" placeholder="{{ __('hrms.assets.rejection_reason_placeholder') }}" :required="true" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-danger px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.assets.reject_request_btn') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="bulkAllocateModal" tabindex="-1" aria-labelledby="bulkAllocateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="bulkAllocateModalLabel">
                        <i class="feather-user-check me-2 text-primary"></i>{{ __('hrms.assets.bulk_allocate_assets') }}
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
                                        <th class="text-start" style="width: 25%;">{{ __('hrms.assets.employee') }}</th>
                                        <th class="text-start" style="width: 30%;">Requested Item & Qty</th>
                                        <th class="text-start" style="width: 45%;">Select Available Asset Units to Fulfill</th>
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
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.assets.confirm_bulk_allocation') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkRejectModal" tabindex="-1" aria-labelledby="bulkRejectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="bulkRejectModalLabel">
                        <i class="feather-x me-2 text-danger"></i>{{ __('hrms.assets.bulk_reject_requests') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="bulkRejectForm" action="{{ route('hrms.assets.requests.bulk-reject') }}" method="POST">
                    @csrf
                    <div id="bulk_reject_ids_container"></div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.bulk_rejection_reason') }}" name="admin_notes" placeholder="{{ __('hrms.assets.bulk_rejection_placeholder') }}" :required="true" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-danger px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.assets.reject_all_selected') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: VIEW FULL DESCRIPTION -->
    <div class="modal fade" id="viewDescriptionModal" tabindex="-1" aria-labelledby="viewDescriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="viewDescriptionModalLabel">
                        <i class="feather-info me-2 text-primary"></i><span id="desc_modal_title">Full Description</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="bg-light p-3 rounded border text-dark fs-13" id="desc_modal_content" style="white-space: pre-wrap; line-height: 1.6; max-height: 350px; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>    <!-- MODAL: VIEW ASSET REQUEST DETAILS -->
    <div class="modal fade" id="viewRequestDetailsModal" tabindex="-1" aria-labelledby="viewRequestDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom py-2.5 px-4">
                    <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="viewRequestDetailsModalLabel">
                        <i class="feather-eye me-2 text-primary"></i>Asset Request Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3.5" style="max-height: 80vh; overflow-y: auto;">
                    <!-- EMPLOYEE & COMPANY CARD WITH REQUEST DATE -->
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
                        </div>
                    </div>

                    <!-- ASSET, CATEGORY, STATUS & QUANTITY PROGRESS -->
                    <div class="border rounded-3 p-3 bg-white mb-2.5">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div>
                                <span class="fs-10 text-uppercase fw-bold text-muted me-2">Requested Asset</span>
                                <span class="badge bg-light text-secondary border px-2 py-0.5 fs-10" id="req_detail_category">Category</span>
                            </div>
                            <div id="req_detail_status_container">
                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11" id="req_detail_status">Pending</span>
                            </div>
                        </div>
                        <h6 class="fw-bold text-dark mb-2 fs-14" id="req_detail_asset_name">Asset Name</h6>

                        <div class="d-flex align-items-center gap-2 mt-1">
                            <div class="flex-fill border rounded py-1 px-2 text-center bg-light">
                                <span class="fs-10 text-uppercase text-muted d-block" style="font-size: 9px;">Requested</span>
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

                    <!-- REASON FOR REQUEST -->
                    <div class="border rounded-3 p-3 bg-white mb-2.5">
                        <span class="fs-10 text-uppercase fw-bold text-muted d-block mb-1"><i class="feather-message-square me-1 text-primary"></i>Reason for Request</span>
                        <div class="fs-12 text-dark" id="req_detail_reason" style="white-space: pre-wrap; line-height: 1.4;">No reason provided.</div>
                    </div>

                    <!-- DYNAMIC FULFILLMENT / REJECTION DETAILS SECTION -->
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
                    <button type="button" class="btn btn-light-brand px-4" data-bs-dismiss="modal">Close</button>
                </div>
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
                'asset_code' => $a->asset_code,
                'serial_number' => $a->serial_number,
                'category_id' => $a->asset_category_id,
                'asset_item_id' => $a->asset_item_id,
                'company_id' => $a->company_id,
                'status' => $a->status ?? 'available'
            ];
        })) !!};

        const langAssets = {
            requestedAssetNotAvail: "{{ __('hrms.assets.requested_asset_not_avail') }}",
            autoMatched: "{{ __('hrms.assets.auto_matched') }}",
            noAvailAssetsInCat: "{{ __('hrms.assets.no_avail_assets_in_cat', ['category' => ':category']) }}"
        };

        $(document).ready(function() {
            // Append modals to body root to prevent Bootstrap backdrop overlay issues inside settings flex container
            $('#addAssetModal').appendTo('body');

            $('#addAssetModal').on('show.bs.modal', function() {
                var modal = $(this);
                // Clear inputs
                modal.find('input[name="name"]').val('');
                modal.find('textarea[name="description"]').val('');
                modal.find('input[name="brand"]').val('');
                modal.find('input[name="model_number"]').val('');
                modal.find('input[name="purchase_date"]').val('');
                modal.find('input[name="purchase_cost"]').val('');
                modal.find('textarea[name="notes"]').val('');
                
                let catSelect = modal.find('select[name="asset_category_id"]');
                if (catSelect.length) {
                    catSelect.val('').trigger('change');
                }
                
                // Reset units table to only have one empty row
                let tbody = $('#bulk-units-tbody');
                tbody.empty();
                unitRowIndex = 1; // start index from 1 for dynamically added rows
                let rowHtml = `
                    <tr>
                        <td class="py-2 px-3 text-start">
                            <input type="text" name="units[0][asset_code]" class="form-control form-control-sm text-center" placeholder="e.g. AST-001" required>
                        </td>
                        <td class="py-2">
                            <input type="text" name="units[0][serial_number]" class="form-control form-control-sm text-center" placeholder="e.g. SN-XXXX">
                        </td>
                        <td class="py-2" style="min-width: 120px;">
                            <select name="units[0][condition]" class="form-select form-select-sm" required>
                                <option value="good">Good</option>
                                <option value="new">New</option>
                                <option value="fair">Fair</option>
                                <option value="damaged">Damaged</option>
                                <option value="scrapped">Scrapped</option>
                            </select>
                        </td>
                        <td class="py-2 text-end px-3">
                            <div class="d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-soft-danger btn-remove-unit-row" disabled><i class="feather-trash-2"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(rowHtml);
                toggleRemoveButtons();
            });
            $('#editAssetModal').appendTo('body');
            $('#allocateAssetModal').appendTo('body');
            $('#addCategoryModal').appendTo('body');
            $('#editCategoryModal').appendTo('body');
            $('#addAssetItemModal').appendTo('body');
            $('#editAssetItemModal').appendTo('body');
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

            // Handle edit item details binding
            $('#editAssetItemModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var categoryId = button.data('category');
                var name = button.data('name');
                var description = button.data('description');
                var brand = button.data('brand');
                var modelNumber = button.data('model-number');
                var purchaseDate = button.data('purchase-date');
                var purchaseCost = button.data('purchase-cost');
                var notes = button.data('notes');
                var encodedUnits = button.data('units');

                var modal = $(this);
                modal.find('form').attr('action', '/hrms/assets/item/update/' + id);

                modal.find('#edit_item_category_id').val(categoryId).trigger('change');
                modal.find('#edit_item_name').val(name);
                modal.find('#edit_item_description').val(description);
                modal.find('#edit_item_brand').val(brand);
                modal.find('#edit_item_model_number').val(modelNumber);
                modal.find('#edit_item_purchase_date').val(purchaseDate);
                modal.find('#edit_item_purchase_cost').val(purchaseCost);
                modal.find('#edit_item_notes').val(notes);

                var tbody = modal.find('#edit-item-bulk-units-tbody');
                tbody.empty();

                if (encodedUnits) {
                    var units = JSON.parse(atob(encodedUnits));
                    if (units && units.length > 0) {
                        units.forEach(function(unit, index) {
                            var rowHtml = `<tr>
                                <td class="py-2 px-3 text-start">
                                    <input type="hidden" name="units[${index}][id]" value="${unit.id}">
                                    <input type="text" name="units[${index}][asset_code]" class="form-control form-control-sm text-center" placeholder="e.g. AST-001" required value="${unit.asset_code}">
                                </td>
                                <td class="py-2">
                                    <input type="text" name="units[${index}][serial_number]" class="form-control form-control-sm text-center" placeholder="e.g. SN123456" value="${unit.serial_number || ''}" required>
                                </td>
                                <td class="py-2" style="min-width: 120px;">
                                    <select name="units[${index}][condition]" class="form-select form-select-sm" required>
                                        <option value="good" ${unit.condition === 'good' ? 'selected' : ''}>Good</option>
                                        <option value="new" ${unit.condition === 'new' ? 'selected' : ''}>New</option>
                                        <option value="fair" ${unit.condition === 'fair' ? 'selected' : ''}>Fair</option>
                                        <option value="damaged" ${unit.condition === 'damaged' ? 'selected' : ''}>Damaged</option>
                                        <option value="scrapped" ${unit.condition === 'scrapped' ? 'selected' : ''}>Scrapped</option>
                                    </select>
                                </td>
                                <td class="py-2 text-end px-3">
                                    <button type="button" class="btn btn-sm btn-icon btn-light text-danger btn-remove-edit-unit-row"><i class="feather-trash-2"></i></button>
                                </td>
                            </tr>`;
                            tbody.append(rowHtml);
                        });
                    }
                }

                if (tbody.children().length === 0) {
                    var rowHtml = `<tr>
                        <td class="py-2 px-3 text-start">
                            <input type="text" name="units[0][asset_code]" class="form-control form-control-sm text-center" placeholder="e.g. AST-001" required>
                        </td>
                        <td class="py-2">
                            <input type="text" name="units[0][serial_number]" class="form-control form-control-sm text-center" placeholder="e.g. SN123456" required>
                        </td>
                        <td class="py-2" style="min-width: 120px;">
                            <select name="units[0][condition]" class="form-select form-select-sm" required>
                                <option value="good">Good</option>
                                <option value="new">New</option>
                                <option value="fair">Fair</option>
                                <option value="damaged">Damaged</option>
                                <option value="scrapped">Scrapped</option>
                            </select>
                        </td>
                        <td class="py-2 text-end px-3">
                            <button type="button" class="btn btn-sm btn-icon btn-light text-danger btn-remove-edit-unit-row"><i class="feather-trash-2"></i></button>
                        </td>
                    </tr>`;
                    tbody.append(rowHtml);
                }
            });

            // Automatically initialize select2 inside modals with dropdownParent set to the modal to fix focus/closing bugs
            $(document).on('shown.bs.modal', '.modal', function() {
                var modal = $(this);
                modal.find('select[select2-selector="default"]').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                    $(this).select2({
                        dropdownParent: modal,
                        placeholder: $(this).attr('placeholder') || "Select Option",
                        allowClear: true
                    });
                });
            });

            // Add unit row in Edit Modal
            $(document).on('click', '#edit-item-btn-add-unit-row', function() {
                var tbody = $('#edit-item-bulk-units-tbody');
                var index = tbody.children().length;
                var rowHtml = `<tr>
                    <td class="py-2 px-3 text-start">
                        <input type="text" name="units[${index}][asset_code]" class="form-control form-control-sm text-center" placeholder="e.g. AST-001" required>
                    </td>
                    <td class="py-2">
                        <input type="text" name="units[${index}][serial_number]" class="form-control form-control-sm text-center" placeholder="e.g. SN123456" required>
                    </td>
                    <td class="py-2" style="min-width: 120px;">
                        <select name="units[${index}][condition]" class="form-select form-select-sm" required>
                            <option value="good">Good</option>
                            <option value="new">New</option>
                            <option value="fair">Fair</option>
                            <option value="damaged">Damaged</option>
                            <option value="scrapped">Scrapped</option>
                        </select>
                    </td>
                    <td class="py-2 text-end px-3">
                        <button type="button" class="btn btn-sm btn-icon btn-light text-danger btn-remove-edit-unit-row"><i class="feather-trash-2"></i></button>
                    </td>
                </tr>`;
                tbody.append(rowHtml);
            });

            // Remove unit row in Edit Modal
            $(document).on('click', '.btn-remove-edit-unit-row', function() {
                var tbody = $('#edit-item-bulk-units-tbody');
                if (tbody.children().length > 1) {
                    $(this).closest('tr').remove();
                    tbody.children().each(function(index, row) {
                        $(row).find('input[name*="units["]').each(function() {
                            var name = $(this).attr('name');
                            var updatedName = name.replace(/units\[\d+\]/, 'units[' + index + ']');
                            $(this).attr('name', updatedName);
                        });
                    });
                } else {
                    alert('At least one physical asset unit must be registered.');
                }
            });

            // Generate units in Edit Modal
            $(document).on('click', '#edit-item-btn-generate-units', function() {
                var prefix = $('#edit_item_gen_prefix').val().trim();
                var count = parseInt($('#edit_item_gen_count').val());
                if (!prefix) {
                    alert('Please enter a code prefix.');
                    return;
                }
                if (isNaN(count) || count < 1 || count > 50) {
                    alert('Please enter a count between 1 and 50.');
                    return;
                }

                var tbody = $('#edit-item-bulk-units-tbody');
                if (tbody.children().length === 1 && !tbody.find('input[name$="[asset_code]"]').val().trim()) {
                    tbody.empty();
                }

                var startIndex = tbody.children().length;
                var currentNumber = startIndex + 1;

                for (var i = 0; i < count; i++) {
                    var finalIndex = startIndex + i;
                    var code = prefix + String(currentNumber).padStart(3, '0');
                    var rowHtml = `<tr>
                        <td class="py-2 px-3 text-start">
                            <input type="text" name="units[${finalIndex}][asset_code]" class="form-control form-control-sm text-center" placeholder="e.g. AST-001" required value="${code}">
                        </td>
                        <td class="py-2">
                            <input type="text" name="units[${finalIndex}][serial_number]" class="form-control form-control-sm text-center" placeholder="e.g. SN123456" required>
                        </td>
                        <td class="py-2" style="min-width: 120px;">
                            <select name="units[${finalIndex}][condition]" class="form-select form-select-sm" required>
                                <option value="good">Good</option>
                                <option value="new">New</option>
                                <option value="fair">Fair</option>
                                <option value="damaged">Damaged</option>
                                <option value="scrapped">Scrapped</option>
                            </select>
                        </td>
                        <td class="py-2 text-end px-3">
                            <button type="button" class="btn btn-sm btn-icon btn-light text-danger btn-remove-edit-unit-row"><i class="feather-trash-2"></i></button>
                        </td>
                    </tr>`;
                    tbody.append(rowHtml);
                    currentNumber++;
                }
            });

            // Handle edit asset details binding
            $('#editAssetModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var assetId = button.data('asset-id');
                var itemId = button.data('asset-item-id');
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
                modal.find('#edit_asset_item_id').val(itemId).trigger('change');
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

            // Dynamic batch logging logic for Serialized Units table
            let unitRowIndex = 1;

            $('#btn-add-unit-row').on('click', function() {
                addUnitRow('', '');
            });

            function addUnitRow(code = '', serial = '', condition = 'good') {
                let tbody = $('#bulk-units-tbody');
                let rowHtml = `
                    <tr>
                        <td class="py-2 px-3 text-start">
                            <input type="text" name="units[${unitRowIndex}][asset_code]" class="form-control form-control-sm text-center" placeholder="e.g. AST-001" value="${code}" required>
                        </td>
                        <td class="py-2">
                            <input type="text" name="units[${unitRowIndex}][serial_number]" class="form-control form-control-sm text-center" placeholder="e.g. SN-XXXX" value="${serial}" required>
                        </td>
                        <td class="py-2" style="min-width: 120px;">
                            <select name="units[${unitRowIndex}][condition]" class="form-select form-select-sm" required>
                                <option value="good" ${condition === 'good' ? 'selected' : ''}>Good</option>
                                <option value="new" ${condition === 'new' ? 'selected' : ''}>New</option>
                                <option value="fair" ${condition === 'fair' ? 'selected' : ''}>Fair</option>
                                <option value="damaged" ${condition === 'damaged' ? 'selected' : ''}>Damaged</option>
                                <option value="scrapped" ${condition === 'scrapped' ? 'selected' : ''}>Scrapped</option>
                            </select>
                        </td>
                        <td class="py-2 text-end px-3">
                            <div class="d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-soft-danger btn-remove-unit-row"><i class="feather-trash-2"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(rowHtml);
                unitRowIndex++;
                toggleRemoveButtons();
            }

            $(document).on('click', '.btn-remove-unit-row', function() {
                $(this).closest('tr').remove();
                toggleRemoveButtons();
            });



            function toggleRemoveButtons() {
                let rows = $('#bulk-units-tbody tr');
                if (rows.length <= 1) {
                    rows.find('.btn-remove-unit-row').prop('disabled', true);
                } else {
                    rows.find('.btn-remove-unit-row').prop('disabled', false);
                }
            }



            // Sequential Code Generator
            $('#btn-generate-units').on('click', function() {
                let prefix = $('#gen_prefix').val().trim();
                let count = parseInt($('#gen_count').val());

                if (!prefix) {
                    alert('Please enter a code prefix.');
                    return;
                }
                if (isNaN(count) || count < 1) {
                    alert('Please enter a valid count of 1 or more.');
                    return;
                }

                let tbody = $('#bulk-units-tbody');
                // Clear initial row if it is empty
                let firstRow = tbody.find('tr').first();
                let firstCode = firstRow.find('input[type="text"]').first().val();
                if (tbody.find('tr').length === 1 && !firstCode) {
                    tbody.empty();
                }

                for (let i = 1; i <= count; i++) {
                    let sequentialCode = prefix + String(i).padStart(3, '0');
                    addUnitRow(sequentialCode, '');
                }

                $('#gen_prefix').val('');
                $('#gen_count').val('');
            });

            // Handle return modal details binding
            $('#returnAssetModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var itemId = button.data('item-id');
                var itemName = button.data('item-name');
                var rawAllocations = button.data('allocations');
                var rawAllocatedAssets = button.data('allocated-assets');

                var modal = $(this);
                modal.find('form').attr('action', '/hrms/assets/item/' + itemId + '/return');
                modal.find('#return_asset_name_display').val(itemName);

                var employeeSelect = modal.find('#return_employee_select');
                employeeSelect.empty();
                employeeSelect.append(new Option('Select Employee', ''));

                var allocations = [];
                if (rawAllocations) {
                    allocations = JSON.parse(atob(rawAllocations));
                }

                var allocatedAssets = [];
                if (rawAllocatedAssets) {
                    allocatedAssets = JSON.parse(atob(rawAllocatedAssets));
                }

                allocations.forEach(function(alloc) {
                    var label = alloc.employee_name + ' (Holds ' + alloc.count + ' unit(s))';
                    var option = new Option(label, alloc.employee_id);
                    employeeSelect.append(option);
                });

                var checklistDiv = modal.find('#return_assets_checklist');
                checklistDiv.html('<span class="text-muted fs-12">Please select an employee first.</span>');

                employeeSelect.off('change').on('change', function() {
                    var employeeId = $(this).val();
                    checklistDiv.empty();

                    if (!employeeId) {
                        checklistDiv.html('<span class="text-muted fs-12">Please select an employee first.</span>');
                        return;
                    }

                    var empAssets = allocatedAssets.filter(function(asset) {
                        return String(asset.assigned_employee_id) === String(employeeId);
                    });

                    if (empAssets.length === 0) {
                        checklistDiv.html('<span class="text-danger fs-12"><i class="feather-alert-triangle me-1"></i>No active allocations found.</span>');
                    } else {
                        empAssets.forEach(function(asset) {
                            var checkboxId = 'return_asset_check_' + asset.id;
                            var itemHtml = `
                                <div class="form-check py-1 border-bottom-dashed d-flex align-items-center">
                                    <input class="form-check-input return-allocated-asset-checkbox" type="checkbox" name="allocated_asset_ids[]" value="${asset.id}" id="${checkboxId}" style="cursor: pointer;">
                                    <label class="form-check-label fs-12 ms-2 text-dark mb-0" for="${checkboxId}" style="cursor: pointer;">
                                        <strong>Code:</strong> ${asset.asset_code} | <strong>Serial:</strong> ${asset.serial_number || 'N/A'}
                                    </label>
                                </div>
                            `;
                            checklistDiv.append(itemHtml);
                        });
                    }
                });

                modal.find('form').off('submit').on('submit', function(e) {
                    var checkedCount = modal.find('.return-allocated-asset-checkbox:checked').length;
                    if (checkedCount === 0) {
                        e.preventDefault();
                        alert('Please select at least one physical asset/serial number to return.');
                    }
                });

                if (employeeSelect.hasClass('select2-hidden-accessible')) {
                    employeeSelect.select2('destroy');
                }
                employeeSelect.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: modal
                });
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
                
                // Show the modal safely attached to body
                $('#assetHistoryModal').appendTo('body').modal('show');
            });

            // Handle Item Master history click
            $(document).on('click', '.show-item-history-btn', function() {
                var btn = $(this);
                var itemName = btn.data('item-name');
                var rawAllocations = btn.data('item-allocations');
                
                var allocations = [];
                try {
                    allocations = JSON.parse(atob(rawAllocations));
                } catch(e) {
                    console.error("Failed to parse item allocations history", e);
                }

                $('#item_history_name_display').text(itemName);
                $('#item_history_total_count').text((allocations ? allocations.length : 0) + ' Events');
                
                var html = '';
                if (!allocations || allocations.length === 0) {
                    html = '<tr><td colspan="6" class="text-center py-4 text-muted fs-12">No allocation history recorded for units under this item.</td></tr>';
                } else {
                    allocations.forEach(function(event, index) {
                        var empName = event.employee ? event.employee.display_name : 'Unknown';
                        var empCode = event.employee && event.employee.employee_id ? ' (' + event.employee.employee_id + ')' : '';
                        var checkInDate = event.returned_at ? event.returned_at : 'Active';
                        var returnCondition = event.return_condition ? event.return_condition : '-';
                        
                        var unitsHtml = '';
                        if (event.units && event.units.length > 0) {
                            event.units.forEach(function(u) {
                                unitsHtml += `<span class="badge bg-white text-dark border px-2 py-1 fs-11 me-1 mb-1 shadow-sm"><i class="feather-box text-primary me-1"></i><strong>${u.code}</strong> <small class="text-muted">(${u.serial})</small></span>`;
                            });
                        }

                        html += '<tr>' +
                            '<td class="text-start px-3" style="min-width: 130px;">' +
                                '<button type="button" class="btn btn-sm btn-soft-primary fw-bold py-1 px-2.5 fs-11 toggle-item-units-btn d-inline-flex align-items-center" data-target="#item-units-box-' + index + '">' +
                                    '<i class="feather-box me-1.5"></i>' + event.qty + ' Unit' + (event.qty > 1 ? 's' : '') +
                                    '<i class="feather-chevron-down ms-1.5 toggle-icon fs-12"></i>' +
                                '</button>' +
                                '<div id="item-units-box-' + index + '" class="d-none mt-2 p-2 bg-light border rounded shadow-sm" style="max-width: 260px;">' +
                                    '<div class="d-flex flex-wrap gap-1">' + unitsHtml + '</div>' +
                                '</div>' +
                            '</td>' +
                            '<td class="text-start px-3"><strong>' + empName + '</strong><span class="text-muted fs-11">' + empCode + '</span></td>' +
                            '<td><span class="fs-12 fw-semibold text-dark">' + event.allocated_at + '</span></td>' +
                            '<td><span class="badge ' + (event.returned_at ? 'bg-soft-success text-success' : 'bg-soft-primary text-primary') + '">' + checkInDate + '</span></td>' +
                            '<td><span class="badge bg-light text-dark text-capitalize">' + event.allocation_condition + '</span></td>' +
                            '<td><span class="badge ' + (event.return_condition ? 'bg-soft-info text-info' : 'bg-light text-secondary') + ' text-capitalize">' + returnCondition + '</span></td>' +
                            '</tr>';
                    });
                }
                
                $('#item_history_table_body').html(html);
                
                // Show the modal safely attached to body
                $('#itemHistoryModal').appendTo('body').modal('show');
            });

            // Toggle unit details box inside Item History table
            $(document).on('click', '.toggle-item-units-btn', function() {
                var btn = $(this);
                var target = $(btn.data('target'));
                var icon = btn.find('.toggle-icon');
                
                target.toggleClass('d-none');
                if (target.hasClass('d-none')) {
                    icon.removeClass('feather-chevron-up').addClass('feather-chevron-down');
                } else {
                    icon.removeClass('feather-chevron-down').addClass('feather-chevron-up');
                }
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

            // Handle direct allocation button click (never opens modal)
            $(document).on('click', '.allocate-direct-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                var requestId = btn.data('request-id');
                var empName = btn.data('employee-name');
                var assetName = btn.data('asset-name');
                var confirmTemplate = btn.data('confirm-template');

                var confirmMsg = confirmTemplate
                    .replace(':asset', assetName)
                    .replace(':employee', empName);

                confirmAction(confirmMsg, function() {
                    var form = $('<form>', {
                        'action': '/hrms/assets/requests/' + requestId + '/allocate-direct',
                        'method': 'POST'
                    });

                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': '_token',
                        'value': $('meta[name="csrf-token"]').attr('content')
                    }));

                    $('body').append(form);
                    form.submit();
                }, { title: 'Allocate Asset Confirmation', variant: 'success', confirmButtonText: 'Allocate' });
            });

            // Client-side validation: ensure Serial Number & Asset Code show inline error messages below fields
            $('#addAssetModal form, #editAssetItemForm, #editAssetForm').on('submit', function(e) {
                let form = $(this);
                let invalid = false;

                form.find('input[name*="[asset_code]"], input[name="asset_code"]').each(function() {
                    let parent = $(this).parent();
                    if (!$(this).val() || !$(this).val().trim()) {
                        invalid = true;
                        $(this).addClass('is-invalid');
                        if (parent.find('.invalid-feedback').length === 0) {
                            $(this).after('<div class="invalid-feedback fs-11 text-start mt-1">Asset code is required.</div>');
                        }
                    } else {
                        $(this).removeClass('is-invalid');
                        parent.find('.invalid-feedback').remove();
                    }
                });

                form.find('input[name*="[serial_number]"], input[name="serial_number"]').each(function() {
                    let parent = $(this).parent();
                    if (!$(this).val() || !$(this).val().trim()) {
                        invalid = true;
                        $(this).addClass('is-invalid');
                        if (parent.find('.invalid-feedback').length === 0) {
                            $(this).after('<div class="invalid-feedback fs-11 text-start mt-1">Serial number is required.</div>');
                        }
                    } else {
                        $(this).removeClass('is-invalid');
                        parent.find('.invalid-feedback').remove();
                    }
                });

                if (invalid) {
                    e.preventDefault();
                    return false;
                }
            });

            // Real-time clearance of inline errors when typing
            $(document).on('input', 'input[name*="[asset_code]"], input[name="asset_code"], input[name*="[serial_number]"], input[name="serial_number"]', function() {
                if ($(this).val() && $(this).val().trim()) {
                    $(this).removeClass('is-invalid');
                    $(this).parent().find('.invalid-feedback').remove();
                }
            });

            // Prevent invalid form submission if no asset is selected in request allocation mode
            $('#allocateAssetForm').on('submit', function(e) {
                var isRequestFlow = !$('#request_checkout_container').hasClass('d-none');
                if (isRequestFlow) {
                    var remainingQty = parseInt($('#allocate_remaining_qty').val()) || parseInt($('#allocate_requested_qty').val());
                    var checkedCount = $('.request-allocated-asset-checkbox:checked').length;
                    if (checkedCount === 0) {
                        e.preventDefault();
                        alert('Please select at least one physical asset/serial number to fulfill this request.');
                        return false;
                    }
                    if (checkedCount > remainingQty) {
                        e.preventDefault();
                        alert('You cannot select more physical units than remaining needed (' + remainingQty + ').');
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
                if (button.hasClass('allocate-request-trigger-btn')) {
                    // FLOW 1: REQUEST ALLOCATION
                    var requestId = button.data('request-id');
                    var employeeId = button.data('employee-id');
                    var employeeName = button.data('employee-name');
                    var itemId = button.data('asset-item-id');
                    var qty = button.data('quantity');
                    var allocatedCount = button.data('allocated-count') || 0;
                    var remainingQty = button.data('remaining-qty') || (qty - allocatedCount);

                    // Set active inputs
                    $('#allocate_request_id').val(requestId);
                    $('#registry_checkout_container').addClass('d-none');
                    $('#request_checkout_container').removeClass('d-none');

                    // Configure inputs disabled and required states to prevent double submissions and force selection
                    $('#registry_employee_select').prop('disabled', true);
                    $('#allocate_quantity_input').prop('disabled', true);
                    $('#request_employee_id').prop('disabled', false).val(employeeId);
                    $('#allocate_employee_name_display').val(employeeName);
                    $('#allocate_requested_qty').val(qty);
                    $('#allocate_already_allocated_qty').val(allocatedCount);
                    $('#allocate_remaining_qty').val(remainingQty);
                    $('#max_selectable_count').text(remainingQty);

                    // Rebuild asset checkboxes checklist based on AssetItem match
                    var checklistDiv = $('#request_assets_checklist');
                    checklistDiv.empty();

                    var filteredAssets = allAvailableAssets.filter(function(asset) {
                        return String(asset.asset_item_id) === String(itemId);
                    });

                    if (filteredAssets.length === 0) {
                        checklistDiv.html('<div class="text-danger fs-12"><i class="feather-alert-triangle me-1"></i>No available units in inventory for this item.</div>');
                    } else {
                        filteredAssets.forEach(function(asset) {
                            var checkboxId = 'allocate_asset_checkbox_' + asset.id;
                            var itemHtml = `
                                <div class="form-check py-1 border-bottom-dashed">
                                    <input class="form-check-input request-allocated-asset-checkbox" type="checkbox" name="allocated_asset_ids[]" value="${asset.id}" id="${checkboxId}">
                                    <label class="form-check-label fs-12 ms-1 text-dark" for="${checkboxId}">
                                        <strong>${asset.name}</strong>
                                    </label>
                                </div>
                            `;
                            checklistDiv.append(itemHtml);
                        });
                    }
                    form.attr('action', '/hrms/assets/requests/' + requestId + '/allocate');
                } else {
                    // FLOW 2: REGISTRY DIRECT ALLOCATION
                    var itemId = button.data('item-id');
                    var itemName = button.data('item-name');
                    var availableQty = button.data('available');
                    var companyId = button.data('company-id');

                    // Set active inputs
                    $('#allocate_request_id').val('');
                    $('#request_checkout_container').addClass('d-none');
                    $('#registry_checkout_container').removeClass('d-none');

                    // Configure inputs disabled and required states
                    $('#request_employee_id').prop('disabled', true);
                    $('#registry_employee_select').prop('disabled', false);
                    $('#allocate_quantity_input').prop('disabled', false);
                    $('#allocate_asset_name_display').val(itemName);
                    $('#allocate_available_qty_display').val(availableQty);

                    var qtyInput = $('#allocate_quantity_input');
                    qtyInput.attr('max', availableQty);
                    qtyInput.val(1);

                    // Re-filter employee options
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
                    form.attr('action', '/hrms/assets/item/' + itemId + '/allocate');
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

            // Toggle assets sub-table collapse
            $(document).on('click', '.toggle-assets-btn', function() {
                let itemId = $(this).data('item-id');
                let targetRow = $('#assets-row-' + itemId);
                let icon = $(this).find('.toggle-icon');
                
                if (targetRow.hasClass('d-none')) {
                    targetRow.removeClass('d-none');
                    icon.removeClass('feather-chevron-right').addClass('feather-chevron-down');
                } else {
                    targetRow.addClass('d-none');
                    icon.removeClass('feather-chevron-down').addClass('feather-chevron-right');
                }
            });

            // Toggle Add buttons in header based on active tab
            function updateHeaderActions() {
                var activeTabId = localStorage.getItem('activeAssetTab') || 'items-tab';
                if (activeTabId === 'registry-tab') {
                    activeTabId = 'items-tab';
                    localStorage.setItem('activeAssetTab', 'items-tab');
                }
                
                if (activeTabId === 'categories-tab') {
                    $('#hdr-btn-log-asset').addClass('d-none');
                    $('#hdr-btn-add-category').removeClass('d-none');
                    $('#hdr-btn-add-item').addClass('d-none');
                } else if (activeTabId === 'items-tab') {
                    $('#hdr-btn-log-asset').addClass('d-none');
                    $('#hdr-btn-add-category').addClass('d-none');
                    $('#hdr-btn-add-item').removeClass('d-none');
                } else {
                    $('#hdr-btn-log-asset').addClass('d-none');
                    $('#hdr-btn-add-category').addClass('d-none');
                    $('#hdr-btn-add-item').addClass('d-none');
                }
            }

            // On page load
            setTimeout(updateHeaderActions, 50);

            // On tab change
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                localStorage.setItem('activeAssetTab', e.target.id);
                updateHeaderActions();
            });
            // Checkbox multi-select logic
            const $selectAll = $('#selectAllRequests');
            const $bulkToolbar = $('#bulkActionsToolbar');
            const $selectedCount = $('#selectedRequestsCount');

            function updateBulkToolbar() {
                const checkedCheckboxes = $('.request-select-checkbox:checked');
                const count = checkedCheckboxes.length;
                if (count > 0) {
                    $bulkToolbar.removeClass('d-none');
                    $selectedCount.text(count);
                } else {
                    $bulkToolbar.addClass('d-none');
                }
            }

            $selectAll.on('change', function() {
                const isChecked = $(this).prop('checked');
                $('.request-select-checkbox').prop('checked', isChecked);
                updateBulkToolbar();
            });

            $(document).on('change', '.request-select-checkbox', function() {
                const total = $('.request-select-checkbox').length;
                const checked = $('.request-select-checkbox:checked').length;
                $selectAll.prop('checked', total === checked);
                updateBulkToolbar();
            });

            // Bulk Reject click handler
            $('#btnBulkReject').on('click', function() {
                const checkedCheckboxes = $('.request-select-checkbox:checked');
                const container = $('#bulk_reject_ids_container');
                container.empty();

                checkedCheckboxes.each(function() {
                    const reqId = $(this).val();
                    container.append(`<input type="hidden" name="request_ids[]" value="${reqId}">`);
                });

                var bulkRejectModal = new bootstrap.Modal(document.getElementById('bulkRejectModal'));
                bulkRejectModal.show();
            });

            // Bulk Allocate click handler (allows multi-unit selection per request)
            $('#btnBulkAllocate').on('click', function() {
                const checkedCheckboxes = $('.request-select-checkbox:checked');
                const tableBody = $('#bulk_allocate_table tbody');
                tableBody.empty();

                checkedCheckboxes.each(function() {
                    const $chk = $(this);
                    const reqId = $chk.val();
                    const empName = $chk.data('employee-name');
                    const catId = $chk.data('category-id');
                    const catName = $chk.data('category-name');
                    const itemId = $chk.data('item-id');
                    const itemName = $chk.data('item-name') || catName;
                    const compId = $chk.data('company-id');
                    const requestedQty = parseInt($chk.data('quantity')) || 1;
                    const allocatedCount = parseInt($chk.data('allocated-count')) || 0;
                    const remainingQty = parseInt($chk.data('remaining-qty')) || (requestedQty - allocatedCount);

                    // Find matching available units
                    const matchedAssets = allAvailableAssets.filter(function(asset) {
                        if (asset.status && asset.status !== 'available') return false;
                        if (itemId && asset.asset_item_id) {
                            return String(asset.asset_item_id) === String(itemId);
                        }
                        if (catId && asset.category_id) {
                            return String(asset.category_id) === String(catId);
                        }
                        return false;
                    });

                    let assetSelectionHtml = '';

                    if (matchedAssets.length > 0) {
                        assetSelectionHtml += `<div class="bg-light p-2 rounded border" style="max-height: 150px; overflow-y: auto;">`;
                        assetSelectionHtml += `<div class="fs-11 text-muted mb-1">Select up to <strong>${remainingQty}</strong> unit(s):</div>`;
                        matchedAssets.forEach(function(asset) {
                            assetSelectionHtml += `
                                <div class="form-check py-1">
                                    <input type="checkbox" name="allocations[${reqId}][]" value="${asset.id}" class="form-check-input bulk-unit-checkbox" data-req-id="${reqId}" data-rem-qty="${remainingQty}">
                                    <label class="form-check-label fs-12 fw-semibold text-dark">
                                        ${asset.asset_code} <span class="text-muted fs-11">(${asset.serial_number || 'No Serial'})</span>
                                    </label>
                                </div>
                            `;
                        });
                        assetSelectionHtml += `</div>`;
                    } else {
                        assetSelectionHtml = `<span class="text-danger fw-bold fs-12"><i class="feather-alert-triangle me-1"></i>No available units found for ${itemName}.</span>`;
                    }

                    const rowHtml = `
                        <tr>
                            <td class="text-start">
                                <strong class="text-dark fs-13">${empName}</strong>
                            </td>
                            <td class="text-start">
                                <div class="fw-bold text-dark fs-12">${itemName}</div>
                                <span class="badge bg-light text-secondary border px-2 py-0.5 fs-11">${catName}</span>
                                <div class="fs-11 text-muted mt-1">
                                    Req: <strong class="text-dark">${requestedQty}</strong> | Rem: <strong class="text-danger">${remainingQty}</strong>
                                </div>
                                <div class="mt-2 pt-1 border-top d-flex align-items-center justify-content-between">
                                    <span class="fs-11 fw-bold text-muted text-uppercase">Allocating Qty:</span>
                                    <span class="badge bg-soft-success text-success border border-success border-opacity-25 px-2 py-1 fs-11" id="bulk_alloc_badge_${reqId}">0 / ${remainingQty} unit(s)</span>
                                </div>
                            </td>
                            <td class="text-start">
                                ${assetSelectionHtml}
                            </td>
                        </tr>
                    `;
                    tableBody.append(rowHtml);
                });

                const modalEl = document.getElementById('bulkAllocateModal');
                const bulkAllocModal = new bootstrap.Modal(modalEl);
                bulkAllocModal.show();
            });

            // Restrict maximum selected checkboxes in Bulk Allocate Modal to remaining qty and update badge
            $(document).on('change', '.bulk-unit-checkbox', function() {
                const reqId = $(this).data('req-id');
                const remQty = parseInt($(this).data('rem-qty')) || 1;
                let checkedCount = $(`.bulk-unit-checkbox[data-req-id="${reqId}"]:checked`).length;

                if (checkedCount > remQty) {
                    $(this).prop('checked', false);
                    checkedCount = remQty;
                    alert(`You can select at most ${remQty} unit(s) for this request.`);
                }

                const badge = $(`#bulk_alloc_badge_${reqId}`);
                if (checkedCount > 0) {
                    badge.removeClass('bg-soft-secondary text-secondary border-secondary').addClass('bg-soft-success text-success border-success').text(`${checkedCount} / ${remQty} unit(s)`);
                } else {
                    badge.removeClass('bg-soft-success text-success border-success').addClass('bg-soft-secondary text-secondary border-secondary').text(`0 / ${remQty} unit(s)`);
                }
            });

            function checkTruncatedDescriptions() {
                $('.desc-expandable-container').each(function() {
                    const textEl = $(this).find('.desc-text-truncate')[0];
                    const readMoreBtn = $(this).find('.btn-read-more-dynamic');
                    if (textEl && (textEl.scrollWidth > textEl.clientWidth + 1)) {
                        readMoreBtn.removeClass('d-none');
                        $(textEl).css('cursor', 'pointer');
                    } else {
                        readMoreBtn.addClass('d-none');
                        $(textEl).css('cursor', 'default');
                    }
                });
            }

            setTimeout(checkTruncatedDescriptions, 100);
            $(window).on('resize', checkTruncatedDescriptions);
            $('a[data-bs-toggle="tab"], button[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
                setTimeout(checkTruncatedDescriptions, 50);
            });

            // View full description modal handler (clicking Read More link OR clicking the truncated text)
            $(document).on('click', '.btn-read-more-dynamic, .desc-text-truncate', function() {
                const container = $(this).closest('.desc-expandable-container');
                const readMoreBtn = container.find('.btn-read-more-dynamic');
                if (!readMoreBtn.hasClass('d-none')) {
                    const title = readMoreBtn.data('title') || 'Description';
                    const desc = readMoreBtn.data('desc') || '';
                    $('#desc_modal_title').text(title + ' - Full Description');
                    $('#desc_modal_content').text(desc);
                    $('#viewDescriptionModal').appendTo('body').modal('show');
                }
            });

            // View request full details modal handler
            $(document).on('click', '.view-req-details-btn', function() {
                const btn = $(this);
                const status = (btn.data('status-raw') || 'pending').toLowerCase();
                
                $('#req_detail_emp_name').text(btn.data('emp-name'));
                $('#req_detail_emp_id').text(btn.data('emp-id'));
                $('#req_detail_company').text(btn.data('company') || 'Company');
                $('#req_detail_asset_name').text(btn.data('asset-name'));
                $('#req_detail_category').text(btn.data('category'));
                $('#req_detail_req_qty').text(btn.data('req-qty') || 0);
                $('#req_detail_alloc_qty').text(btn.data('alloc-qty') || 0);
                $('#req_detail_rem_qty').text(btn.data('rem-qty') || 0);
                $('#req_detail_date').text(btn.data('date'));
                $('#req_detail_reason').text(btn.data('reason'));

                let badgeHtml = '';
                if (status === 'pending') {
                    badgeHtml = '<span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11">Pending</span>';
                } else if (status === 'partially_allocated' || status === 'partial') {
                    badgeHtml = '<span class="badge bg-soft-info text-info px-2.5 py-1 rounded-pill fs-11">Partially Allocated</span>';
                } else if (status === 'allocated') {
                    badgeHtml = '<span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11">Allocated</span>';
                } else if (status === 'rejected') {
                    badgeHtml = '<span class="badge bg-soft-danger text-danger px-2.5 py-1 rounded-pill fs-11">Rejected</span>';
                } else {
                    badgeHtml = `<span class="badge bg-light text-secondary px-2.5 py-1 rounded-pill fs-11">${btn.data('status')}</span>`;
                }
                $('#req_detail_status_container').html(badgeHtml);

                // Handle dynamic Fulfillment / Rejection Section
                const actionSection = $('#req_detail_fulfillment_section');
                const allocBox = $('#req_detail_allocation_box');
                const rejectBox = $('#req_detail_rejection_box');

                actionSection.addClass('d-none');
                allocBox.addClass('d-none');
                rejectBox.addClass('d-none');

                const actionDate = btn.data('action-date') || '-';
                const adminNotes = btn.data('admin-notes') || '';
                const rawUnits = btn.data('allocated-units');
                let allocatedUnits = [];

                if (rawUnits) {
                    try {
                        allocatedUnits = JSON.parse(atob(rawUnits));
                    } catch(err) {
                        allocatedUnits = [];
                    }
                }

                if (status === 'allocated' || status === 'partially_allocated' || status === 'partial') {
                    actionSection.removeClass('d-none');
                    allocBox.removeClass('d-none');
                    $('#req_detail_alloc_date').text(actionDate);

                    let unitsHtml = '';
                    if (allocatedUnits && allocatedUnits.length > 0) {
                        allocatedUnits.forEach(function(unit) {
                            let uDate = unit.date || actionDate;
                            unitsHtml += `
                                <div class="d-inline-flex align-items-center bg-white text-dark border rounded px-2.5 py-1.5 fs-11 me-1 mb-1 shadow-sm">
                                    <i class="feather-box text-primary me-1.5 fs-12"></i>
                                    <div class="lh-sm">
                                        <span class="fw-bold text-dark">${unit.code}</span>
                                        <span class="text-muted fs-10 ms-1">(${unit.serial})</span>
                                        ${uDate && uDate !== '-' ? `<span class="badge bg-light text-secondary border fs-9 ms-1.5 py-0.5 px-1">${uDate}</span>` : ''}
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        unitsHtml = `<span class="fs-12 text-muted fst-italic">No serialized units linked.</span>`;
                    }
                    $('#req_detail_allocated_units_list').html(unitsHtml);

                } else if (status === 'rejected') {
                    actionSection.removeClass('d-none');
                    rejectBox.removeClass('d-none');
                    $('#req_detail_reject_date').text(actionDate);
                    $('#req_detail_reject_notes').text(adminNotes && adminNotes.trim() !== '' ? adminNotes : 'No specific reason provided.');
                }

                $('#viewRequestDetailsModal').appendTo('body').modal('show');
            });

            $('#bulkAllocateModal').appendTo('body');
            $('#bulkRejectModal').appendTo('body');
            $('#viewDescriptionModal').appendTo('body');
            $('#viewRequestDetailsModal').appendTo('body');
            $('#itemHistoryModal').appendTo('body');
            $('#assetHistoryModal').appendTo('body');
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
