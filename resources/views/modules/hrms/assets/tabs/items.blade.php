<!-- 1. ASSET ITEMS TAB -->
<div class="tab-pane fade show active" id="items-pane" role="tabpanel" aria-labelledby="items-tab">
    <div class="card border rounded bg-white shadow-sm">
        <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center py-3 px-4 bg-white gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.assets.item_catalog_master') }}</h5>
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
                        <input type="text" name="item_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.assets.search_item_catalog') }}" value="{{ request('item_search') }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <div class="d-flex gap-2">
                        <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                            <a class="dropdown-item py-2 {{ request('item_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('item', 'name_asc', this); event.preventDefault();">{{ __('hrms.common.sort_name_asc') }}</a>
                            <a class="dropdown-item py-2 {{ request('item_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('item', 'name_desc', this); event.preventDefault();">{{ __('hrms.common.sort_name_desc') }}</a>
                            <a class="dropdown-item py-2 {{ request('item_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('item', 'newest', this); event.preventDefault();">{{ __('hrms.assets.sort_newest') }}</a>
                        </x-ui.sort-dropdown>

                        <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                            
                            <div class="mb-3" style="min-width: 250px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.assets.org_entity') }}</label>
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
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.assets.tbl_category') }}</label>
                                <x-ui.odoo-form-ui type="select" name="item_category_id">
                                    <option value="">{{ __('hrms.assets.all_categories') }}</option>
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
                             <th style="width: 35%;" class="py-3 px-4 text-start">{{ __('hrms.assets.tbl_item_name') }}</th>
                             <th style="width: 25%;" class="py-3">{{ __('hrms.assets.tbl_category') }}</th>
                             <th style="width: 15%;" class="py-3">{{ __('hrms.assets.tbl_registered_units') }}</th>
                             <th style="width: 15%;" class="py-3">{{ __('hrms.assets.tbl_available_units') }}</th>
                             <th style="width: 110px; white-space: nowrap;" class="py-3 text-end px-4">{{ __('hrms.assets.actions') }}</th>
                         </tr>
                     </thead>
                     <tbody class="fs-12">
                         @forelse($filteredItems as $itemObj)
                             <tr class="item-main-row" data-item-id="{{ $itemObj->id }}">
                                 <td class="py-3 px-4 text-start" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                     <div class="fw-bold text-dark fs-13">{{ $itemObj->name }}</div>
                                     @if($itemObj->description)
                                         <div class="text-muted fs-11 mt-0.5" style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;">{{ $itemObj->description }}</div>
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
                                                    <i class="feather-user-check me-2 text-muted fs-12"></i>{{ __('hrms.assets.btn_allocate') }}
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
                                                    <i class="feather-user-x me-2 text-muted fs-12"></i>{{ __('hrms.assets.btn_return') }}
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
                                                    <i class="feather-clock me-2 text-muted fs-12"></i>{{ __('hrms.assets.allocation_history') }}
                                                </a>
                                            </li>
                                            <li>
                                                <form action="{{ route('hrms.assets.item.destroy', $itemObj->id) }}" method="POST" class="d-inline" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.assets.confirm_delete_item') }}', { title: '{{ __('hrms.assets.delete_item_title') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.common.delete') }}' });">
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
                                             <span class="fw-bold text-dark fs-12 text-start"><i class="feather-package me-1 text-primary"></i>{{ __('hrms.assets.serialized_registry_for') }} {{ $itemObj->name }}</span>
                                             <span class="badge bg-primary fs-11 rounded-pill">{{ $itemObj->assets->count() }} {{ __('hrms.assets.units_count') }}</span>
                                         </div>
                                         <div class="card-body p-0">
                                             <div class="table-responsive" style="overflow-x: hidden;">
                                                 <table class="table table-sm table-hover align-middle mb-0 text-center fs-12" style="table-layout: fixed; width: 100%;">
                                                     <thead class="table-light text-uppercase fs-10" style="letter-spacing: 0.5px;">
                                                         <tr>
                                                             <th style="width: 18%;" class="py-2.5 px-3 text-start">{{ __('hrms.assets.tbl_asset_code') }}</th>
                                                             <th style="width: 22%;" class="py-2.5">{{ __('hrms.assets.tbl_serial_number') }}</th>
                                                             <th style="width: 15%;" class="py-2.5">{{ __('hrms.assets.tbl_condition') }}</th>
                                                             <th style="width: 15%;" class="py-2.5">{{ __('hrms.assets.tbl_status') }}</th>
                                                             <th style="width: 20%;" class="py-2.5">{{ __('hrms.assets.tbl_assigned_to') }}</th>
                                                             <th style="width: 10%; white-space: nowrap;" class="py-2.5 text-end px-3">{{ __('hrms.assets.actions') }}</th>
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
                                                                     <span class="badge {{ $badgeStyleClass }} rounded-pill px-2 py-1 fs-11">{{ __('hrms.assets.cond_' . $asset->condition) }}</span>
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
                                                                         {{ __('hrms.assets.status_' . $asset->status) }}
                                                                     </span>
                                                                 </td>
                                                                 <td class="py-2 text-muted">
                                                                     @if($asset->status === 'allocated' && $asset->assignedEmployee)
                                                                         <div class="fw-semibold text-dark fs-11">{{ $asset->assignedEmployee->display_name }}</div>
                                                                         <div class="fs-9 text-muted mt-0.5" style="font-size: 9px;">{{ __('hrms.assets.since_date') }} {{ $asset->allocated_at ? $asset->allocated_at->format('d M, Y') : '-' }}</div>
                                                                     @else
                                                                         -
                                                                     @endif
                                                                 </td>
                                                                 <td class="py-2 text-end px-3">
                                                                     <div class="d-flex justify-content-end gap-1 align-items-center">
                                                                         <button type="button" class="btn btn-xs btn-icon btn-light text-primary show-history-btn" title="View Allocation History" data-asset-name="{{ $asset->name }} ({{ $asset->asset_code }})" data-allocations="{{ base64_encode($asset->allocations()->with('employee')->get()->toJson()) }}">
                                                                             <i class="feather-clock" style="font-size: 11px;"></i>
                                                                         </button>
                                                                         <form action="{{ route('hrms.assets.destroy', $asset->id) }}" method="POST" class="d-inline" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.assets.confirm_delete_asset') }}', { title: '{{ __('hrms.assets.delete_asset_title') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.common.delete') }}' });">
                                                                             @csrf
                                                                             @method('DELETE')
                                                                             <button type="submit" class="btn btn-xs btn-icon btn-light text-danger" title="{{ __('hrms.common.delete') }}">
                                                                                 <i class="feather-trash-2" style="font-size: 10px;"></i>
                                                                             </button>
                                                                         </form>
                                                                     </div>
                                                                 </td>
                                                             </tr>
                                                         @empty
                                                             <tr>
                                                                 <td colspan="6" class="py-3 text-muted text-center fs-11">{{ __('hrms.assets.no_physical_units') }}</td>
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
                                     <div class="fw-bold mb-1">{{ __('hrms.assets.no_items_configured') }}</div>
                                     <div>{{ __('hrms.assets.no_items_desc') }}</div>
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
                    page-param="item_page"
                />
            </div>
        @endif
    </div>
</div>

<!-- MODAL: ADD ASSET -->
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
                                                <input type="text" name="units[0][asset_code]" class="odoo-table-input text-center" placeholder="e.g. AST-001" required>
                                            </td>
                                            <td class="py-2">
                                                <input type="text" name="units[0][serial_number]" class="odoo-table-input text-center" placeholder="e.g. SN-XXXX" required>
                                            </td>
                                            <td class="py-2" style="min-width: 120px;">
                                                <select name="units[0][condition]" class="odoo-table-select" required>
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

<!-- MODAL: EDIT ASSET -->
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

<!-- MODAL: IMPORT ASSETS -->
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

<!-- MODAL: ALLOCATE ASSET -->
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
                        <div id="registry_checkout_container" class="col-12 p-0 m-0 row g-3">
                            <div class="col-12">
                                <label class="info-label mb-1">{{ __('hrms.assets.asset_item') }}</label>
                                <input type="text" id="allocate_asset_name_display" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.tbl_available_units') }}" id="allocate_available_qty_display" :readonly="true" />
                            </div>
                            <div class="col-6">
                                 <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.qty_to_allocate') }}" name="quantity" id="allocate_quantity_input" inputType="number" min="1" :required="true" />
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

                        <div id="request_checkout_container" class="col-12 p-0 m-0 row g-3 d-none">
                            <div class="col-12">
                                <label class="info-label mb-1">{{ __('hrms.assets.employee') }}</label>
                                <input type="text" id="allocate_employee_name_display" class="form-control bg-light" readonly>
                                <input type="hidden" name="assigned_employee_id" id="request_employee_id" disabled>
                            </div>
                            <div class="col-4">
                                <label class="info-label mb-1">{{ __('hrms.assets.lbl_req_short') }}</label>
                                <input type="text" id="allocate_requested_qty" class="form-control bg-light text-center fw-bold" readonly>
                            </div>
                            <div class="col-4">
                                <label class="info-label mb-1">{{ __('hrms.assets.lbl_alloc_short') }}</label>
                                <input type="text" id="allocate_already_allocated_qty" class="form-control bg-light text-center text-success fw-bold" readonly>
                            </div>
                            <div class="col-4">
                                <label class="info-label mb-1">{{ __('hrms.assets.lbl_rem_short') }}</label>
                                <input type="text" id="allocate_remaining_qty" class="form-control bg-light text-center text-danger fw-bold" readonly>
                            </div>
                            <div class="col-12">
                                <label class="info-label mb-2 fw-bold text-dark d-block">{{ __('hrms.assets.serialized_units_registry') }}</label>
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

<!-- MODAL: ADD ASSET ITEM -->
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
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.tbl_category') }}" name="asset_category_id" :required="true" select2-selector="default">
                                <option value="">{{ __('hrms.assets.lbl_select_category') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->company->company_name ?? 'All' }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.lbl_item_name') }}" name="name" placeholder="e.g. Laptop, Mobile Phone, Office Desk" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.description') }}" name="description" placeholder="Brief details about this item..." />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.assets.lbl_create_item') }}</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDIT ASSET ITEM -->
<div class="modal fade" id="editAssetItemModal" aria-labelledby="editAssetItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="editAssetItemModalLabel">
                    <i class="feather-box me-2 text-primary"></i>{{ __('hrms.assets.lbl_edit_item') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editAssetItemForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.tbl_category') }}" name="asset_category_id" id="edit_item_category_id" :required="true" select2-selector="default">
                                <option value="">{{ __('hrms.assets.lbl_select_category') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->company->company_name ?? 'All' }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.lbl_item_name') }}" name="name" id="edit_item_name" placeholder="e.g. Laptop, Mobile Phone, Office Desk" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.lbl_item_description') }}" name="description" id="edit_item_description" placeholder="Brief details about this item..." />
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
                            <h6 class="fw-bold text-dark mb-3">{{ __('hrms.assets.serialized_units_registry') }}</h6>
                            
                            <!-- Code generator panel -->
                            <div class="bg-light p-3 rounded mb-3 border d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                <div class="d-flex align-items-center gap-2">
                                    <label class="form-label mb-0 fs-12 fw-bold text-muted">{{ __('hrms.assets.generate_sequential_codes') }}</label>
                                    <input type="text" id="edit_item_gen_prefix" class="form-control form-control-sm" placeholder="Prefix (e.g. AST-)" style="width: 200px; height: 32px;">
                                    <input type="number" id="edit_item_gen_count" class="form-control form-control-sm" placeholder="Count" min="1" max="50" style="width: 100px; height: 32px;">
                                    <button type="button" class="btn btn-sm btn-primary fw-bold text-uppercase" id="edit-item-btn-generate-units" style="height: 32px;">{{ __('hrms.assets.btn_generate') }}</button>
                                </div>
                                <button type="button" class="btn btn-sm btn-soft-primary fw-bold text-uppercase" id="edit-item-btn-add-unit-row" style="height: 32px;"><i class="feather-plus me-1"></i>{{ __('hrms.assets.btn_add_row') }}</button>
                            </div>

                            <div class="table-responsive border rounded bg-white" style="max-height: 250px;">
                                <table class="table table-sm table-hover align-middle mb-0 text-center" id="edit-item-bulk-units-table">
                                    <thead class="table-light text-uppercase fs-11" style="position: sticky; top: 0; z-index: 2;">
                                        <tr>
                                            <th class="py-2.5 px-3 text-start">{{ __('hrms.assets.asset_code_unique') }}</th>
                                            <th class="py-2.5">{{ __('hrms.assets.tbl_serial_number') }} *</th>
                                            <th class="py-2.5">{{ __('hrms.assets.tbl_condition') }} *</th>
                                            <th class="py-2.5 text-end px-3">{{ __('hrms.assets.actions') }}</th>
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

<!-- MODAL: RETURN ASSET -->
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
                            <label class="info-label mb-1">{{ __('hrms.assets.asset_item') }}</label>
                            <input type="text" id="return_asset_name_display" class="form-control bg-light" readonly>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.employee') }}" name="employee_id" id="return_employee_select" :required="true" select2-selector="default">
                                <option value="">{{ __('hrms.assets.select_employee') }}</option>
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

<!-- MODAL: ASSET HISTORY LOG -->
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
                    <i class="feather-clock me-2 text-primary"></i>{{ __('hrms.assets.allocation_history') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12">{{ __('hrms.assets.asset_item') }}:</span> <strong id="item_history_name_display" class="text-dark fs-14"></strong>
                    </div>
                    <span class="badge bg-primary fs-11 rounded-pill" id="item_history_total_count">0 Events</span>
                </div>
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0 text-center fs-12">
                        <thead class="table-light fs-11 text-uppercase">
                            <tr>
                                <th class="text-start px-3">{{ __('hrms.assets.tbl_asset_code') }}</th>
                                <th class="text-start px-3">{{ __('hrms.assets.employee') }}</th>
                                <th>{{ __('hrms.assets.allocation_date') }}</th>
                                <th>{{ __('hrms.assets.return_date') }}</th>
                                <th>{{ __('hrms.assets.alloc_cond_lbl') }}</th>
                                <th>{{ __('hrms.assets.return_cond_lbl') }}</th>
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
