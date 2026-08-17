@extends('layouts.duralux')

@section('title', 'My Assets | SaaS ERP')
@section('page-title', 'My Assets')
@section('breadcrumb', 'HRMS / Assets / My Assets')
@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <!-- Toggle View Button: Switch between history and log -->
        <button type="button" class="btn btn-outline-light border text-dark fw-bold text-uppercase d-flex align-items-center gap-1.5" id="btn-toggle-asset-view" style="height: 38px; border-radius: 6px; font-size: 11px; padding-inline: 14px; background-color: #fff; border-color: #cbd5e1 !important; color: #334155 !important;">
            <i class="feather-git-pull-request text-muted" id="toggle-view-icon"></i>
            <span id="toggle-view-text">Request Log</span>
        </button>

        <!-- Action: Request Asset -->
        <x-ui.button type="button" variant="primary" class="fw-bold text-uppercase d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#requestAssetModal" style="height: 38px; border-radius: 6px; font-size: 11px;">
            <i class="feather-plus-circle"></i> Request Asset
        </x-ui.button>
    </div>
@endsection

@section('content')
<style>
    .card-custom {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background-color: #fff;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.02);
        margin-bottom: 24px;
    }

    .card-custom-header {
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-custom-title {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .documents-search {
        height: 38px;
        min-width: 230px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        transition: all 0.2s ease;
    }

    .documents-search:focus-within {
        background: #fff;
        border-color: rgba(var(--bs-primary-rgb), 0.45);
        box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.08);
    }

    .documents-search input::placeholder {
        color: #94a3b8;
    }

    .asset-toolbar .documents-search {
        min-width: 220px;
    }

    .asset-toolbar .sort-toggle-custom,
    .asset-toolbar .filter-toggle-custom,
    .asset-action-btn {
        height: 38px;
        border-radius: 10px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        border: 1px solid #dbe3ec !important;
        background: #fff !important;
        color: #0f172a !important;
        box-shadow: none !important;
        padding-inline: 14px !important;
    }

    .asset-toolbar .sort-toggle-custom:hover,
    .asset-toolbar .filter-toggle-custom:hover,
    .asset-action-btn:hover {
        border-color: var(--bs-primary) !important;
        color: var(--bs-primary) !important;
        background: rgba(var(--bs-primary-rgb), 0.06) !important;
    }

    .asset-action-btn-primary {
        border-color: var(--bs-primary) !important;
        background: var(--bs-primary) !important;
        color: #fff !important;
    }

    .asset-action-btn-primary:hover {
        background: color-mix(in srgb, var(--bs-primary) 88%, #000) !important;
        color: #fff !important;
    }

    .info-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    /* Scoped wrap rules for modal columns so they stack vertically if width is cramped */
    .modal-body .odoo-form-group {
        flex-wrap: wrap !important;
    }
    .modal-body .odoo-form-group > .flex-grow-1 {
        min-width: 200px !important;
    }
    .modal-body .odoo-form-label {
        width: 100% !important;
        margin-bottom: 5px !important;
    }
</style>
<div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 pb-3 border-bottom">
                <div>
                    <h5 class="card-custom-title" id="my_assets_card_title">
                        <i class="feather-package text-primary"></i> {{ __('hrms.employees.lbl_co_assets') }}
                    </h5>
                    <small class="text-muted d-block mt-1" id="my_assets_card_desc">{{ __('hrms.employees.lbl_co_assets_desc') }}</small>
                </div>
                <div class="asset-toolbar d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                    <!-- Search bar (Assigned Assets) -->
                    <div class="documents-search d-flex align-items-center px-3 py-1" id="search_assigned_wrapper">
                        <i class="feather-search text-muted me-2 fs-14"></i>
                        <input type="text" id="assignedAssetSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.employees.lbl_search_assets') }}" autocomplete="off" style="box-shadow: none; height: 32px;">
                    </div>
                    <!-- Search bar (Requests Log) -->
                    <div class="documents-search d-flex align-items-center px-3 py-1 d-none" id="search_requests_wrapper">
                        <i class="feather-search text-muted me-2 fs-14"></i>
                        <input type="text" id="assetRequestSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.employees.lbl_search_requests') }}" autocomplete="off" style="box-shadow: none; height: 32px;">
                    </div>

                    <!-- Assigned Toolbar Action Dropdowns -->
                    <div id="toolbar_assigned_buttons" class="d-flex align-items-center gap-2">
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
                    </div>

                    <!-- Requests Toolbar Action Dropdowns -->
                    <div id="toolbar_requests_buttons" class="d-flex align-items-center gap-2 d-none">
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
                </div>

                <!-- VIEW 1: ASSIGNED ASSETS -->
                <div id="view_assigned_assets" class="table-responsive">
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
                                    <td class="ps-3" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px; background-color: rgba(59, 130, 246, 0.08) !important; color: #3b82f6 !important;">
                                                <i class="feather-package fs-16"></i>
                                            </div>
                                            <div style="flex-grow: 1; min-width: 0;">
                                                <div class="fw-bold text-dark mb-0.5" style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;" title="{{ $asset->name }}">{{ $asset->name }}</div>
                                                @if($asset->model_number)
                                                    <small class="text-muted d-block" style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;" title="{{ __('hrms.employees.lbl_model') }}: {{ $asset->model_number }}">{{ __('hrms.employees.lbl_model') }}: {{ $asset->model_number }}</small>
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
                                            <button type="button" class="btn btn-sm btn-soft-primary fw-bold text-uppercase px-3" data-bs-toggle="modal" data-bs-target="#viewAssetDetailsModal" data-item-name="{{ $asset->name }}" data-allocated-assets="{{ base64_encode(json_encode($units)) }}" style="font-size: 11px; height: 30px; border-radius: 6px;">
                                                <i class="feather-info me-1.5 fs-12 text-primary"></i>{{ __('hrms.common.detail') }}
                                            </button>
                                            
                                            <button type="button" class="btn btn-sm btn-soft-warning fw-bold text-uppercase px-3" data-bs-toggle="modal" data-bs-target="#returnAssetModal" data-item-id="{{ $asset->asset_item_id }}" data-item-name="{{ $asset->name }}" data-allocated-assets="{{ base64_encode(json_encode($units)) }}" style="font-size: 11px; height: 30px; border-radius: 6px;">
                                                <i class="feather-corner-up-left me-1.5 fs-12 text-warning"></i>{{ __('hrms.employees.lbl_return') }}
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

                <!-- VIEW 2: REQUEST LOG (Initially Hidden) -->
                <div id="view_request_log" class="table-responsive d-none">
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
                                    <td class="ps-3" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                        <div class="fw-bold text-dark mb-0.5" style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;" title="{{ $request->item->name ?? 'N/A' }}">{{ $request->item->name ?? 'N/A' }}</div>
                                        @if($request->reason)
                                            <small class="text-muted d-block" style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;" title="{{ $request->reason }}">{{ $request->reason }}</small>
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

<!-- RETURN ASSET MODAL -->
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
                            <div id="my_return_checklist_error" class="d-none mt-2">
                                <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-2" style="background: #fff3f3; border: 1px solid #f5c2c7;">
                                    <i class="feather-alert-circle text-danger" style="font-size: 15px; flex-shrink: 0;"></i>
                                    <span class="text-danger fs-12 fw-semibold">Please select at least one unit to return.</span>
                                </div>
                            </div>
                            <small class="text-muted mt-1 d-block">{{ __('hrms.employees.mdl_select_units_desc') }}</small>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_return_date') }}" name="returned_at" inputType="date" :required="true" value="{{ date('Y-m-d') }}" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_return_condition') }}" name="condition_on_return" :required="true" select2-selector="default">
                                <option value="good">{{ __('hrms.employees.mdl_condition_good') }}</option>
                                <option value="new">{{ __('hrms.employees.mdl_condition_new') }}</option>
                                <option value="fair">{{ __('hrms.employees.mdl_condition_fair') }}</option>
                                <option value="damaged">{{ __('hrms.employees.mdl_condition_damaged') }}</option>
                                <option value="scrapped">{{ __('hrms.employees.mdl_condition_scrapped') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_return_notes') }}" name="notes" placeholder="{{ __('hrms.employees.mdl_return_notes_placeholder') }}" />
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
                                <th class="text-start py-2.5 px-3">{{ __('hrms.employees.tbl_notes') }}</th>
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

<!-- REQUEST ASSET MODAL -->
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
                                            <td class="py-2 px-3">
                                                <select name="items[0][asset_item_id]" class="form-select form-select-sm req-item-select" required style="width: 100%;">
                                                    <option value="">{{ __('hrms.employees.mdl_select_item') }}</option>
                                                    @foreach($availableAssetItems as $item)
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
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Append modals to body
        $('#returnAssetModal').appendTo('body');
        $('#viewAssetDetailsModal').appendTo('body');
        $('#requestAssetModal').appendTo('body');

        // Universal backdrop cleanup — fixes blur/frozen screen after closing any modal
        $(document).on('hidden.bs.modal', '.modal', function() {
            if ($('.modal.show').length === 0) {
                $('body').removeClass('modal-open').css({ overflow: '', paddingRight: '' });
                $('.modal-backdrop').remove();
            }
        });

        // Normalize text utility
        function normalizeText(text) {
            return (text || '').toLowerCase().trim();
        }

        const $assignedAssetRows = $('.assigned-asset-row');
        const $assignedAssetTbody = $('.assigned-assets-table tbody');
        const $assignedAssetNoResultsRow = $('#assignedAssetNoResultsRow');
        let assignedAssetSortMode = 'name_asc';
        let appliedAssignedAssetFilters = {
            category: '',
            serial: '',
        };

        function getAssignedAssetFilters() {
            return {
                search: normalizeText($('#assignedAssetSearchInput').val()),
                category: appliedAssignedAssetFilters.category,
                serial: appliedAssignedAssetFilters.serial,
            };
        }

        function compareAssignedAssetRows(firstRow, secondRow) {
            const $first = $(firstRow);
            const $second = $(secondRow);
            const firstName = $first.data('name') || '';
            const secondName = $second.data('name') || '';
            const firstAssigned = parseInt($first.data('assigned'), 10);
            const secondAssigned = parseInt($second.data('assigned'), 10);
            const firstAssignedValue = Number.isNaN(firstAssigned) ? 0 : firstAssigned;
            const secondAssignedValue = Number.isNaN(secondAssigned) ? 0 : secondAssigned;

            if (assignedAssetSortMode === 'name_desc') {
                return secondName.localeCompare(firstName);
            }

            if (assignedAssetSortMode === 'assigned_desc') {
                return secondAssignedValue - firstAssignedValue || firstName.localeCompare(secondName);
            }

            if (assignedAssetSortMode === 'assigned_asc') {
                return firstAssignedValue - secondAssignedValue || firstName.localeCompare(secondName);
            }

            return firstName.localeCompare(secondName);
        }

        function refreshAssignedAssetRows() {
            const filters = getAssignedAssetFilters();
            let visibleCount = 0;
            const sortedRows = $assignedAssetRows.toArray().sort(compareAssignedAssetRows);

            $.each(sortedRows, function(_, row) {
                const $row = $(row);
                const matchesSearch = !filters.search || normalizeText($row.data('search')).includes(filters.search);
                const matchesCategory = !filters.category || $row.data('category') === filters.category;
                const matchesSerial = filters.serial === '' || String($row.data('has-serial')) === filters.serial;
                const isVisible = matchesSearch && matchesCategory && matchesSerial;

                $row.toggleClass('d-none', !isVisible);
                if (isVisible) {
                    visibleCount++;
                }

                $assignedAssetTbody.append(row);
            });

            if ($assignedAssetNoResultsRow.length) {
                $assignedAssetTbody.append($assignedAssetNoResultsRow);
                $assignedAssetNoResultsRow.toggleClass('d-none', !(visibleCount === 0 && $assignedAssetRows.length > 0));
            }
        }

        const $assetRequestRows = $('.asset-request-row');
        const $assetRequestTbody = $('.asset-requests-table tbody');
        const $assetRequestNoResultsRow = $('#assetRequestNoResultsRow');
        let assetRequestSortMode = 'date_desc';
        let appliedAssetRequestFilters = {
            category: '',
            status: '',
        };

        function getAssetRequestFilters() {
            return {
                search: normalizeText($('#assetRequestSearchInput').val()),
                category: appliedAssetRequestFilters.category,
                status: appliedAssetRequestFilters.status,
            };
        }

        function compareAssetRequestRows(firstRow, secondRow) {
            const $first = $(firstRow);
            const $second = $(secondRow);
            const firstCategory = $first.data('category') || '';
            const secondCategory = $second.data('category') || '';
            const firstStatus = $first.data('status') || '';
            const secondStatus = $second.data('status') || '';
            const firstDate = parseInt($first.data('date'), 10);
            const secondDate = parseInt($second.data('date'), 10);
            const firstDateValue = Number.isNaN(firstDate) ? 0 : firstDate;
            const secondDateValue = Number.isNaN(secondDate) ? 0 : secondDate;

            if (assetRequestSortMode === 'date_asc') {
                return firstDateValue - secondDateValue || firstCategory.localeCompare(secondCategory);
            }

            if (assetRequestSortMode === 'category_asc') {
                return firstCategory.localeCompare(secondCategory) || secondDateValue - firstDateValue;
            }

            if (assetRequestSortMode === 'status_asc') {
                return firstStatus.localeCompare(secondStatus) || secondDateValue - firstDateValue;
            }

            return secondDateValue - firstDateValue || firstCategory.localeCompare(secondCategory);
        }

        function refreshAssetRequestRows() {
            const filters = getAssetRequestFilters();
            let visibleCount = 0;
            const sortedRows = $assetRequestRows.toArray().sort(compareAssetRequestRows);

            $.each(sortedRows, function(_, row) {
                const $row = $(row);
                const matchesSearch = !filters.search || normalizeText($row.data('search')).includes(filters.search);
                const matchesCategory = !filters.category || $row.data('category') === filters.category;
                const matchesStatus = !filters.status || $row.data('status') === filters.status;
                const isVisible = matchesSearch && matchesCategory && matchesStatus;

                $row.toggleClass('d-none', !isVisible);
                if (isVisible) {
                    visibleCount++;
                }

                $assetRequestTbody.append(row);
            });

            if ($assetRequestNoResultsRow.length) {
                $assetRequestTbody.append($assetRequestNoResultsRow);
                $assetRequestNoResultsRow.toggleClass('d-none', !(visibleCount === 0 && $assetRequestRows.length > 0));
            }
        }

        $('#assignedAssetSearchInput').on('input', refreshAssignedAssetRows);
        $('#assetRequestSearchInput').on('input', refreshAssetRequestRows);

        $('#btnAssignedAssetFilterApply').on('click', function() {
            const $form = $('#assignedAssetFilterForm');
            appliedAssignedAssetFilters = {
                category: $form.find('[name="category"]').val(),
            };

            refreshAssignedAssetRows();
            $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
            $('.erp-filter-dropdown.show').removeClass('show');
        });

        $('#btnResetAssignedAssetFilter').on('click', function() {
            $('#assignedAssetFilterForm [name="category"]').val('').trigger('change');
            appliedAssignedAssetFilters = {
                category: '',
            };
            refreshAssignedAssetRows();
        });

        $('#btnAssetRequestFilterApply').on('click', function() {
            const $form = $('#assetRequestFilterForm');
            appliedAssetRequestFilters = {
                category: $form.find('[name="category"]').val(),
                status: $form.find('[name="status"]').val(),
            };

            refreshAssetRequestRows();
            $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
            $('.erp-filter-dropdown.show').removeClass('show');
        });

        $('#btnAssetRequestFilterReset').on('click', function() {
            $('#assetRequestFilterForm [name="category"]').val('').trigger('change');
            $('#assetRequestFilterForm [name="status"]').val('').trigger('change');
            appliedAssetRequestFilters = {
                category: '',
                status: '',
            };
            refreshAssetRequestRows();
        });

        $('.assigned-asset-sort-link').on('click', function(e) {
            e.preventDefault();
            assignedAssetSortMode = $(this).data('sort') || 'name_asc';
            $('.assigned-asset-sort-link').removeClass('active').find('.feather-check').remove();
            $(this).addClass('active').append('<i class="feather-check ms-3"></i>');
            refreshAssignedAssetRows();
            $('.erp-sort-dropdown .dropdown-menu.show').removeClass('show');
            $('.erp-sort-dropdown.show').removeClass('show');
        });

        $('.asset-request-sort-link').on('click', function(e) {
            e.preventDefault();
            assetRequestSortMode = $(this).data('sort') || 'date_desc';
            $('.asset-request-sort-link').removeClass('active').find('.feather-check').remove();
            $(this).addClass('active').append('<i class="feather-check ms-3"></i>');
            refreshAssetRequestRows();
            $('.erp-sort-dropdown .dropdown-menu.show').removeClass('show');
            $('.erp-sort-dropdown.show').removeClass('show');
        });

        // Initialize display
        refreshAssignedAssetRows();
        refreshAssetRequestRows();

        // Add dynamic request item row logic
        var requestItemIndex = 1;

        // Initialize Select2 on the static first row when modal opens
        $('#requestAssetModal').on('shown.bs.modal', function() {
            $('#req-items-tbody .req-item-select').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        dropdownParent: $('#requestAssetModal'),
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: '{{ __('hrms.employees.mdl_select_item') }}'
                    });
                }
            });
        });

        $('#btn-add-req-item-row').on('click', function() {
            var tbody = $('#req-items-tbody');
            // Clone options from static first row (strip selected state)
            var rawOptions = $('#req-items-tbody tr:first-child select option').map(function() {
                return '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
            }).get().join('');

            var newRow = `
                <tr>
                    <td class="py-2 px-3">
                        <select name="items[${requestItemIndex}][asset_item_id]" class="form-select form-select-sm req-item-select" required style="width: 100%;">
                            ${rawOptions}
                        </select>
                    </td>
                    <td class="py-2 text-center">
                        <input type="number" name="items[${requestItemIndex}][quantity]" class="form-control form-control-sm text-center req-qty-input" min="1" value="1" required style="width: 65px; height: 32px; margin: 0 auto; font-weight: 600;">
                    </td>
                    <td class="py-2 text-center px-2">
                        <button type="button" class="btn btn-sm btn-soft-danger btn-remove-req-item-row" style="width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"><i class="feather-trash-2"></i></button>
                    </td>
                </tr>
            `;
            var $newRow = $(newRow);
            tbody.append($newRow);

            // Initialize Select2 on the newly added row
            $newRow.find('.req-item-select').select2({
                dropdownParent: $('#requestAssetModal'),
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '{{ __('hrms.employees.mdl_select_item') }}'
            });

            requestItemIndex++;
            $('#req-items-tbody tr:first-child .btn-remove-req-item-row').prop('disabled', false);
        });

        $(document).on('click', '.btn-remove-req-item-row', function() {
            // Destroy Select2 before removing
            var $sel = $(this).closest('tr').find('.req-item-select');
            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }
            $(this).closest('tr').remove();
            if ($('#req-items-tbody tr').length === 1) {
                $('#req-items-tbody tr:first-child .btn-remove-req-item-row').prop('disabled', true);
            }
        });

        // Handle Return modal binding
        $('#returnAssetModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var itemId = button.data('item-id');
            var itemName = button.data('item-name');
            var rawAssets = button.data('allocated-assets');

            // Reset error banner when modal opens fresh
            var modal = $(this);
            modal.find('#my_return_checklist_error').addClass('d-none');
            modal.find('form').attr('action', "{{ route('hrms.assets-module.return-direct-multi') }}");
            modal.find('#return_asset_name_display').val(itemName);

            var checklistDiv = modal.find('#return_assets_checklist');
            checklistDiv.empty();

            var assets = [];
            if (rawAssets) {
                assets = JSON.parse(atob(rawAssets));
            }

            if (assets.length === 0) {
                checklistDiv.html('<span class="text-danger fs-12"><i class="feather-alert-triangle me-1"></i>No active allocations found.</span>');
            } else {
                assets.forEach(function(asset) {
                    var checkboxId = 'my_return_asset_check_' + asset.id;
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

            modal.find('form').off('submit').on('submit', function(e) {
                var checkedCount = modal.find('.return-allocated-asset-checkbox:checked').length;
                if (checkedCount === 0) {
                    e.preventDefault();
                    modal.find('#my_return_checklist_error').removeClass('d-none');
                    modal.find('#return_assets_checklist')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });

            // Auto-dismiss error when any checkbox is checked
            modal.find('#return_assets_checklist').off('change.myReturn').on('change.myReturn', '.return-allocated-asset-checkbox', function() {
                if (modal.find('.return-allocated-asset-checkbox:checked').length > 0) {
                    modal.find('#my_return_checklist_error').addClass('d-none');
                }
            });
        });

        // Handle View Details modal binding
        $('#viewAssetDetailsModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var itemName = button.data('item-name');
            var rawAssets = button.data('allocated-assets');

            var modal = $(this);
            modal.find('#detail_asset_item_name').val(itemName);

            var tbody = modal.find('#detail_assets_table_body');
            tbody.empty();

            var assets = [];
            if (rawAssets) {
                assets = JSON.parse(atob(rawAssets));
            }

            if (assets.length === 0) {
                tbody.append('<tr><td colspan="5" class="py-3 text-muted">No units assigned.</td></tr>');
            } else {
                assets.forEach(function(asset) {
                    var dateStr = asset.allocated_at ? new Date(asset.allocated_at).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }) : 'N/A';
                    var condBadge = {
                        'new': 'bg-soft-success text-success',
                        'good': 'bg-soft-info text-info',
                        'fair': 'bg-soft-warning text-warning',
                        'damaged': 'bg-soft-danger text-danger',
                        'scrapped': 'bg-soft-secondary text-secondary'
                    };
                    var badgeClass = condBadge[asset.condition] || 'bg-light text-muted';
                    var rowHtml = `
                        <tr>
                            <td class="text-start py-2 px-3 fw-bold text-dark"><code>${asset.asset_code}</code></td>
                            <td class="py-2">${asset.serial_number || 'N/A'}</td>
                            <td class="py-2 text-muted">${dateStr}</td>
                            <td class="py-2">
                                <span class="badge ${badgeClass} rounded-pill px-2 py-0.5" style="font-size: 11px;">${asset.condition.charAt(0).toUpperCase() + asset.condition.slice(1)}</span>
                            </td>
                            <td class="text-start py-2 px-3 text-muted" style="white-space: normal; word-wrap: break-word; overflow-wrap: break-word; max-width: 320px;" title="${asset.notes || ''}">${asset.notes || '-'}</td>
                        </tr>
                    `;
                    tbody.append(rowHtml);
                });
            }
        });

        // 8. UNIFIED VIEW TOGGLER JS
        var activeAssetView = 'assigned'; // 'assigned' or 'requests'
        $('#btn-toggle-asset-view').on('click', function() {
            if (activeAssetView === 'assigned') {
                // Change to requests log view
                activeAssetView = 'requests';
                $('#my_assets_card_title').html('<i class="feather-git-pull-request text-primary"></i> Asset Requests History');
                $('#my_assets_card_desc').text('History of submitted asset requests.');
                
                $('#search_assigned_wrapper').addClass('d-none');
                $('#search_requests_wrapper').removeClass('d-none');
                
                $('#toolbar_assigned_buttons').addClass('d-none');
                $('#toolbar_requests_buttons').removeClass('d-none');
                
                $('#view_assigned_assets').addClass('d-none');
                $('#view_request_log').removeClass('d-none');
                
                $('#toggle-view-icon').attr('class', 'feather-package');
                $('#toggle-view-text').text('Asset History');
            } else {
                // Change back to assigned assets history view
                activeAssetView = 'assigned';
                $('#my_assets_card_title').html('<i class="feather-package text-primary"></i> {{ __("hrms.employees.lbl_co_assets") }}');
                $('#my_assets_card_desc').text('{{ __("hrms.employees.lbl_co_assets_desc") }}');
                
                $('#search_assigned_wrapper').removeClass('d-none');
                $('#search_requests_wrapper').addClass('d-none');
                
                $('#toolbar_assigned_buttons').removeClass('d-none');
                $('#toolbar_requests_buttons').addClass('d-none');
                
                $('#view_assigned_assets').removeClass('d-none');
                $('#view_request_log').addClass('d-none');
                
                $('#toggle-view-icon').attr('class', 'feather-git-pull-request');
                $('#toggle-view-text').text('Request Log');
            }
        });
    });

    function confirmFormSubmit(event, message, options) {
        event.preventDefault();
        var form = event.target;
        if (confirm(message)) {
            form.submit();
        }
        return false;
    }
</script>
@endpush
