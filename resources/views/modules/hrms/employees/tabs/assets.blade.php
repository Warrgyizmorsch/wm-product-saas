<div class="tab-pane fade {{ $activeTabName === 'assets' ? 'show active' : '' }}" id="assets-pane" role="tabpanel" aria-labelledby="assets-tab">
    @php
        $categories = \App\Domains\HRMS\Models\AssetCategory::query()->orderBy('name')->get();

        // Group assigned assets by name (each Asset row = one unit)
        $assignedAssetsRaw = $employee->assets()->with('category')->get();
        $assignedAssetsGrouped = $assignedAssetsRaw->groupBy('name');
        $assignedAssets = $assignedAssetsGrouped->map(function($group) {
            $first = $group->first();
            $latestDate = $group->max('allocated_at');
            
            $mappedUnits = $group->map(function($unit) {
                return [
                    'id'            => $unit->id,
                    'asset_code'    => $unit->asset_code,
                    'serial_number' => $unit->serial_number,
                    'allocated_at'  => $unit->allocated_at ? (\Carbon\Carbon::parse($unit->allocated_at)->format('Y-m-d')) : null,
                    'condition'     => $unit->condition,
                    'notes'         => $unit->notes,
                ];
            })->values()->all();

            return [
                'asset'               => $first,
                'units'               => $mappedUnits,
                'latest_assigned_date'=> $latestDate ? \Carbon\Carbon::parse($latestDate) : null,
            ];
        })->values();

        $assignedAssetCategories = $assignedAssetsRaw->pluck('category.name')->filter()->unique()->sort()->values();
        $requestAssetCategories = $employee->assetRequests->pluck('category.name')->filter()->unique()->sort()->values();
        $requestAssetStatuses = $employee->assetRequests->pluck('status')->filter()->unique()->sort()->values();
    @endphp
    <div class="card-custom">
        <div class="card-custom-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h5 class="card-custom-title"><i class="feather-package text-primary"></i> {{ __('hrms.employees.lbl_co_assets') }}</h5>
                <small class="text-muted d-block mt-1">{{ __('hrms.employees.lbl_co_assets_desc') }}</small>
            </div>
            <div class="asset-toolbar d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                <div class="documents-search d-flex align-items-center px-3 py-1">
                    <i class="feather-search text-muted me-2 fs-14"></i>
                    <input type="text" id="assignedAssetSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.employees.lbl_search_assets') }}" autocomplete="off" style="box-shadow: none; height: 32px;">
                </div>
                <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                    <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2 active" href="javascript:void(0)" data-sort="name_asc">
                        <span>{{ __('hrms.employees.lbl_asset_name_asc') }}</span>
                        <i class="feather-check ms-3"></i>
                    </a>
                    <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="name_desc">
                        <span>{{ __('hrms.employees.lbl_asset_name_desc') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="assigned_desc">
                        <span>{{ __('hrms.employees.lbl_assigned_latest') }}</span>
                    </a>
                    <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="assigned_asc">
                        <span>{{ __('hrms.employees.lbl_assigned_oldest') }}</span>
                    </a>
                </x-ui.sort-dropdown>
                <x-ui.filter label="{{ __('hrms.common.filter') }}">
                    <div class="document-filter-panel">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                        <form id="assignedAssetFilterForm" onsubmit="return false;">
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_category') }}</label>
                                <x-ui.odoo-form-ui type="select" name="category">
                                    <option value="">{{ __('hrms.employees.tbl_category') }} - {{ __('hrms.common.filter') }}</option>
                                    @foreach($assignedAssetCategories as $catName)
                                        <option value="{{ $catName }}">{{ $catName }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="dropdown-divider my-3"></div>
                            <div class="d-flex gap-2">
                                <x-ui.button type="button" id="btnAssignedAssetFilterApply" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.common.apply') }}</x-ui.button>
                                <x-ui.button type="button" id="btnResetAssignedAssetFilter" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') }}</x-ui.button>
                            </div>
                        </form>
                    </div>
                </x-ui.filter>
                <x-ui.button type="button" variant="primary" class="fw-bold text-uppercase d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#requestAssetModal">
                    <i class="feather-plus-circle"></i> {{ __('hrms.employees.btn_request_asset') }}
                </x-ui.button>
            </div>
        </div>
        <div class="card-body p-0">
             <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 assigned-assets-table" id="assignedAssetsTable" style="table-layout: fixed; width: 100%;">
                    <thead class="table-light text-uppercase fs-11">
                        <tr>
                            <th class="ps-3" style="width: 40%;">{{ __('hrms.employees.tbl_asset_name') }}</th>
                            <th style="width: 22%;">{{ __('hrms.employees.tbl_category') }}</th>
                            <th class="text-center" style="width: 8%;">{{ __('hrms.employees.tbl_units_assigned') }}</th>
                            <th style="width: 13%;">{{ __('hrms.employees.tbl_date_assigned') }}</th>
                            <th class="text-end pe-3" style="width: 17%;">{{ __('hrms.employees.tbl_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignedAssets as $groupedAsset)
                            @php
                                $asset = $groupedAsset['asset'];
                                $units = $groupedAsset['units'];
                                $latestAssignedDate = $groupedAsset['latest_assigned_date'];
                            @endphp
                             <tr class="assigned-asset-row" 
                                data-name="{{ strtolower($asset->name) }}" 
                                data-search="{{ strtolower($asset->name) }}" 
                                data-category="{{ $asset->category->name ?? '' }}"
                                data-assigned="{{ $latestAssignedDate ? $latestAssignedDate->timestamp : 0 }}">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px;">
                                            <i class="feather-package fs-16"></i>
                                        </div>
                                        <div style="flex-grow: 1; min-width: 0;">
                                            <div class="fw-bold text-dark mb-0.5" title="{{ $asset->name }}">{{ $asset->name }}</div>
                                            @if($asset->model_number)
                                                <small class="text-muted d-block" title="{{ __('hrms.employees.lbl_model') }}: {{ $asset->model_number }}">{{ __('hrms.employees.lbl_model') }}: {{ $asset->model_number }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark">{{ $asset->category->name ?? 'N/A' }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-soft-primary text-primary fw-bold fs-12 px-2.5 py-1">{{ count($units) }}</span>
                                </td>
                                <td>
                                    <span class="text-dark fw-medium fs-12">{{ $latestAssignedDate ? $latestAssignedDate->format('d M, Y') : 'N/A' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <button type="button" class="btn btn-sm btn-soft-primary fw-bold text-uppercase d-flex align-items-center gap-1.5 py-1 px-3" data-bs-toggle="modal" data-bs-target="#viewAssetDetailsModal" data-item-name="{{ $asset->name }}" data-allocated-assets="{{ base64_encode(json_encode($units)) }}" style="border-radius: 8px; font-size: 11px;">
                                            <i class="feather-info"></i> {{ __('hrms.common.detail') }}
                                        </button>
                                        
                                        <button type="button" class="btn btn-sm btn-soft-warning fw-bold text-uppercase d-flex align-items-center gap-1.5 py-1 px-3" data-bs-toggle="modal" data-bs-target="#returnAssetModal" data-item-id="{{ $asset->asset_item_id }}" data-item-name="{{ $asset->name }}" data-allocated-assets="{{ base64_encode(json_encode($units)) }}" style="border-radius: 8px; font-size: 11px;">
                                            <i class="feather-corner-up-left"></i> {{ __('hrms.employees.lbl_return') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr id="noAssignedAssetsRowEmpty">
                                <td colspan="5" class="text-center py-5 text-muted fs-13">
                                    <i class="feather-package d-block fs-32 text-light-muted mb-2"></i>
                                    {{ __('hrms.employees.lbl_no_assigned_assets') }}
                                </td>
                            </tr>
                        @endforelse
                         <tr id="assignedAssetNoResultsRow" class="d-none">
                            <td colspan="5" class="text-center py-5 text-muted fs-13">
                                <i class="feather-package d-block fs-32 text-light-muted mb-2"></i>
                                {{ __('hrms.employees.lbl_no_matching_assigned_assets') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Requests History Block -->
    <div class="card-custom mt-4">
        <div class="card-custom-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h5 class="card-custom-title"><i class="feather-git-pull-request text-primary"></i> {{ __('hrms.employees.lbl_requests_history') }}</h5>
            </div>
            <div class="asset-toolbar d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                 <div class="documents-search d-flex align-items-center px-3 py-1">
                    <i class="feather-search text-muted me-2 fs-14"></i>
                    <input type="text" id="assetRequestSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.employees.lbl_search_requests') }}" autocomplete="off" style="box-shadow: none; height: 32px;">
                </div>
                <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2 active" href="javascript:void(0)" data-sort="date_desc">
                        <span>{{ __('hrms.employees.lbl_req_date_desc') }}</span>
                        <i class="feather-check ms-3"></i>
                    </a>
                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="date_asc">
                        <span>{{ __('hrms.employees.lbl_req_date_asc') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="qty_desc">
                        <span>{{ __('hrms.employees.lbl_qty_desc') }}</span>
                    </a>
                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="qty_asc">
                        <span>{{ __('hrms.employees.lbl_qty_asc') }}</span>
                    </a>
                </x-ui.sort-dropdown>
                <x-ui.filter label="{{ __('hrms.common.filter') }}">
                    <div class="document-filter-panel">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                         <form id="assetRequestFilterForm" onsubmit="return false;">
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_category') }}</label>
                                <x-ui.odoo-form-ui type="select" name="category">
                                    <option value="">{{ __('hrms.employees.tbl_category') }} - {{ __('hrms.common.filter') }}</option>
                                    @foreach($requestAssetCategories as $catName)
                                        <option value="{{ $catName }}">{{ $catName }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('hrms.employees.tbl_status') }} - {{ __('hrms.common.filter') }}</option>
                                    @foreach($requestAssetStatuses as $statVal)
                                        <option value="{{ $statVal }}">{{ ucfirst($statVal) }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="dropdown-divider my-3"></div>
                            <div class="d-flex gap-2">
                                <x-ui.button type="button" id="btnAssetRequestFilterApply" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.common.apply') }}</x-ui.button>
                                <x-ui.button type="button" id="btnAssetRequestFilterReset" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') }}</x-ui.button>
                            </div>
                        </form>
                    </div>
                </x-ui.filter>
            </div>
        </div>
        <div class="card-body p-0">
             <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 asset-requests-table" id="reqAssetsTable" style="table-layout: fixed; width: 100%;">
                    <thead class="table-light text-uppercase fs-11">
                        <tr>
                            <th class="ps-3" style="width: 40%;">{{ __('hrms.employees.tbl_item') }}</th>
                            <th style="width: 22%;">{{ __('hrms.employees.tbl_category') }}</th>
                            <th class="text-center" style="width: 8%;">{{ __('hrms.employees.tbl_quantity') }}</th>
                            <th style="width: 12%;">{{ __('hrms.employees.tbl_date_requested') }}</th>
                            <th style="width: 10%;">{{ __('hrms.employees.tbl_status') }}</th>
                            <th class="text-end pe-3" style="width: 8%;">{{ __('hrms.employees.tbl_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employee->assetRequests()->with('item.category')->get()->sortByDesc('created_at') as $request)
                            @php
                                $statusBadgeClass = match($request->status) {
                                    'pending' => 'bg-soft-warning text-warning',
                                    'approved' => 'bg-soft-success text-success',
                                    'allocated' => 'bg-soft-info text-info',
                                    'rejected' => 'bg-soft-danger text-danger',
                                    'returned' => 'bg-soft-secondary text-secondary',
                                    default => 'bg-light text-secondary'
                                };
                            @endphp
                             <tr class="asset-request-row" 
                                data-name="{{ strtolower($request->item->name ?? '') }}" 
                                data-search="{{ strtolower($request->item->name ?? '') }}" 
                                data-category="{{ $request->item->category->name ?? '' }}" 
                                data-status="{{ $request->status }}"
                                data-qty="{{ $request->quantity }}"
                                data-date="{{ $request->created_at ? $request->created_at->timestamp : 0 }}">
                                <td class="ps-3">
                                    <div class="fw-bold text-dark mb-0.5" title="{{ $request->item->name ?? 'N/A' }}">{{ $request->item->name ?? 'N/A' }}</div>
                                    @if($request->reason)
                                        <small class="text-muted d-block" title="{{ $request->reason }}">{{ $request->reason }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-medium text-dark">{{ $request->item->category->name ?? 'N/A' }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-soft-primary text-primary fw-bold fs-12 px-2 py-0.5">{{ $request->quantity }}</span>
                                </td>
                                <td>
                                    <span class="text-dark fw-medium fs-12">{{ $request->created_at ? $request->created_at->format('d M, Y') : 'N/A' }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadgeClass }} px-2.5 py-1 rounded-pill fs-11 text-uppercase">{{ $request->status }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        @if($request->status === 'pending')
                                            <form action="{{ route('hrms.assets.requests.reject', $request->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.employees.confirm_withdraw_request') }}', { title: '{{ __('hrms.employees.lbl_withdraw_request') }}', variant: 'warning', confirmButtonText: '{{ __('hrms.employees.btn_withdraw') }}' });" class="m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-soft-danger border d-flex align-items-center justify-content-center p-0" style="border-radius: 8px; width: 32px; height: 32px;" title="Withdraw Request">
                                                    <i class="feather-trash-2 fs-13"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted fs-12 italic">{{ __('hrms.employees.lbl_locked') }}</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr id="noReqAssetsRowEmpty">
                                <td colspan="6" class="text-center py-5 text-muted fs-13">
                                    <i class="feather-git-pull-request d-block fs-32 text-light-muted mb-2"></i>
                                    {{ __('hrms.employees.lbl_no_requests_history') }}
                                </td>
                            </tr>
                        @endforelse
                         <tr id="assetRequestNoResultsRow" class="d-none">
                            <td colspan="6" class="text-center py-5 text-muted fs-13">
                                <i class="feather-git-pull-request d-block fs-32 text-light-muted mb-2"></i>
                                {{ __('hrms.employees.lbl_no_matching_requests') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="returnAssetModal" tabindex="-1" aria-labelledby="returnAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="returnAssetModalLabel">
                        <i class="feather-corner-up-left me-2 text-primary"></i>{{ __('hrms.employees.mdl_return_asset_title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="returnAssetForm" method="POST">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="info-label mb-1">{{ __('hrms.employees.mdl_asset_to_return') }}</label>
                                <input type="text" id="return_asset_name_display" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-12">
                                <label class="info-label mb-2 fw-bold text-dark d-block">{{ __('hrms.employees.mdl_select_serialized_assets') }}</label>
                                <div id="return_assets_checklist" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <!-- Checklist populated via JS -->
                                </div>
                                <small class="text-muted mt-1 d-block">{{ __('hrms.employees.mdl_select_units_desc') }}</small>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_return_date') }}" name="returned_at" inputType="date" :required="true" value="{{ date('Y-m-d') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_return_condition') }}" name="return_condition" :required="true" select2-selector="default">
                                    <option value="good">{{ __('hrms.employees.mdl_condition_good') }}</option>
                                    <option value="new">{{ __('hrms.employees.mdl_condition_new') }}</option>
                                    <option value="fair">{{ __('hrms.employees.mdl_condition_fair') }}</option>
                                    <option value="damaged">{{ __('hrms.employees.mdl_condition_damaged') }}</option>
                                    <option value="scrapped">{{ __('hrms.employees.mdl_condition_scrapped') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_return_notes') }}" name="return_notes" placeholder="{{ __('hrms.employees.mdl_return_notes_placeholder') }}" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_process_return') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- VIEW ASSET DETAILS MODAL -->
    <div class="modal fade" id="viewAssetDetailsModal" tabindex="-1" aria-labelledby="viewAssetDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="viewAssetDetailsModalLabel">
                        <i class="feather-info me-2 text-primary"></i>{{ __('hrms.employees.mdl_assigned_units_details') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="info-label mb-1">{{ __('hrms.employees.tbl_item') }}</label>
                        <input type="text" id="detail_asset_item_name" class="form-control bg-light fw-bold text-dark" readonly>
                    </div>
                    <div class="table-responsive border rounded bg-white">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead class="table-light text-uppercase fs-11">
                                <tr>
                                    <th class="text-start py-2.5 px-3">{{ __('hrms.employees.tbl_asset_code') }}</th>
                                    <th class="py-2.5">{{ __('hrms.employees.tbl_serial_number') }}</th>
                                    <th class="py-2.5">{{ __('hrms.employees.tbl_assigned_date') }}</th>
                                    <th class="py-2.5">{{ __('hrms.employees.tbl_condition') }}</th>
                                    <th class="py-2.5 px-3">{{ __('hrms.employees.tbl_notes') }}</th>
                                </tr>
                            </thead>
                            <tbody id="detail_assets_table_body" style="font-size: 13px;">
                                <!-- Dynamically populated via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_cancel') }}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- REQUEST ASSET MODAL FOR PROFILE TAB -->
    <div class="modal fade" id="requestAssetModal" aria-labelledby="requestAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 620px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="requestAssetModalLabel">
                        <i class="feather-plus me-2 text-primary"></i>{{ __('hrms.employees.mdl_request_asset_title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.assets.requests.store') }}" method="POST" id="requestAssetMultiForm" novalidate>
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_emp_requesting') }}" name="employee_name_display" value="{{ $employee->display_name }} ({{ $employee->employee_id }})" :readonly="true" />
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="info-label fw-bold text-dark mb-0">{{ __('hrms.employees.mdl_req_items_qty') }}</label>
                                    <button type="button" class="btn btn-sm btn-soft-primary fw-bold text-uppercase" id="btn-add-req-item-row" style="font-size: 11px;">
                                        <i class="feather-plus me-1"></i>{{ __('hrms.employees.mdl_add_another_item') }}
                                    </button>
                                </div>
                                <div class="table-responsive border rounded bg-white req-item-table-container">
                                    <table class="table table-sm align-middle mb-0" id="req-items-table" style="table-layout: fixed; width: 100%;">
                                        <thead class="table-light text-uppercase fs-11">
                                            <tr>
                                                <th class="py-2 px-3 text-start" style="width: 62%;">{{ __('hrms.employees.tbl_item') }}</th>
                                                <th class="py-2 text-center" style="width: 95px;">{{ __('hrms.employees.tbl_quantity') }}</th>
                                                <th class="py-2 text-center px-2" style="width: 50px;">{{ __('hrms.employees.tbl_action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="req-items-tbody">
                                            <tr>
                                                <td class="py-2 px-3" style="overflow: hidden; max-width: 0;">
                                                    <select name="items[0][asset_item_id]" class="form-select form-select-sm req-item-select" required style="width: 100%;">
                                                        <option value="">{{ __('hrms.employees.mdl_select_item') }}</option>
                                                        @foreach(\App\Domains\HRMS\Models\AssetItem::whereHas('category', function($q) use ($employee) { $q->where('company_id', $employee->company_id); })->get() as $item)
                                                            <option value="{{ $item->id }}">{{ $item->name }} (Category: {{ $item->category->name ?? 'N/A' }})</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="py-2 text-center">
                                                    <input type="number" name="items[0][quantity]" class="form-control form-control-sm text-center req-qty-input" min="1" value="1" required style="width: 65px; height: 32px; margin: 0 auto; font-weight: 600;">
                                                </td>
                                                <td class="py-2 text-center px-2">
                                                    <button type="button" class="btn btn-sm btn-soft-danger btn-remove-req-item-row" disabled style="width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"><i class="feather-trash-2"></i></button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_reason_req') }}" name="reason" placeholder="{{ __('hrms.employees.mdl_reason_placeholder') }}" :required="true" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_submit_request') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
