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
                            <a class="dropdown-item py-2 {{ request('request_sort', 'status_asc') == 'status_asc' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'status_asc', this); event.preventDefault();">{{ __('hrms.assets.sort_status_asc') }}</a>
                            <a class="dropdown-item py-2 {{ request('request_sort') == 'status_desc' ? 'active' : '' }}" href="#" onclick="changeSort('request', 'status_desc', this); event.preventDefault();">{{ __('hrms.assets.sort_status_desc') }}</a>
                        </x-ui.sort-dropdown>

                        <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
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
                                        <i class="feather-calendar me-1 text-primary"></i>{{ __('hrms.assets.lbl_requested_date') }} <span class="fw-medium text-dark">{{ $req->request_date ? $req->request_date->format('d M, Y') : '-' }}</span>
                                    </div>
                                </td>
                                <td class="text-start" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                    <div class="fw-bold text-dark fs-13">{{ $req->item->name ?? $req->category->name }}</div>
                                    <div class="my-0.5">
                                        <span class="badge bg-light text-secondary border px-2 py-0.5 fs-11">{{ $req->category->name }}</span>
                                    </div>
                                    <div class="fs-11 text-muted mb-1">
                                        {{ __('hrms.assets.lbl_req_short') }} <strong class="text-dark">{{ $req->quantity }}</strong> | 
                                        {{ __('hrms.assets.lbl_alloc_short') }} <strong class="text-success">{{ $allocatedCount }}</strong> | 
                                        {{ __('hrms.assets.lbl_rem_short') }} <strong class="{{ $remainingQty > 0 ? 'text-danger' : 'text-muted' }}">{{ $remainingQty }}</strong>
                                    </div>
                                    <div>
                                        @if($req->status === 'pending')
                                            <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11 text-capitalize">{{ __('hrms.assets.status_pending') }}</span>
                                        @elseif($req->status === 'partially_allocated')
                                            <span class="badge bg-soft-info text-info px-2.5 py-1 rounded-pill fs-11 text-capitalize">{{ __('hrms.assets.status_partially_allocated') }}</span>
                                        @elseif($req->status === 'allocated')
                                            <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11 text-capitalize">{{ __('hrms.assets.status_allocated') }}</span>
                                        @elseif($req->status === 'rejected')
                                            <span class="badge bg-soft-danger text-danger px-2.5 py-1 rounded-pill fs-11 text-capitalize" title="{{ $req->admin_notes }}">{{ __('hrms.assets.status_rejected') }}</span>
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
                                                {{ __('hrms.assets.btn_fulfill') }}
                                            </button>

                                            <button type="button" class="btn btn-sm btn-soft-danger fw-bold reject-request-btn px-3"
                                                style="font-size: 11px; height: 32px; letter-spacing: 0.5px;"
                                                data-request-id="{{ $req->id }}">
                                                {{ __('hrms.assets.btn_reject') }}
                                            </button>
                                        @elseif($req->status === 'allocated')
                                            <span class="text-success fs-12 fw-semibold ms-1"><i class="feather-check-circle me-1"></i>{{ __('hrms.assets.status_allocated') }}</span>
                                        @elseif($req->status === 'rejected')
                                            <span class="text-danger fs-12 fw-semibold ms-1" title="{{ $req->admin_notes }}"><i class="feather-x-circle me-1"></i>{{ __('hrms.assets.status_rejected') }}</span>
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
                    page-param="request_page"
                />
            </div>
        @endif
    </div>
</div>

<!-- MODAL: REJECT REQUEST -->
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

<!-- MODAL: BULK ALLOCATE ASSETS -->
<div class="modal fade" id="bulkAllocateModal" tabindex="-1" aria-labelledby="bulkAllocateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
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
                                    <th class="text-start" style="width: 30%;">{{ __('hrms.assets.req_asset') }}</th>
                                    <th class="text-start" style="width: 45%;">{{ __('hrms.assets.tbl_available_units') }}</th>
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

<!-- MODAL: BULK REJECT -->
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
</div>

<!-- MODAL: VIEW ASSET REQUEST DETAILS -->
<div class="modal fade" id="viewRequestDetailsModal" tabindex="-1" aria-labelledby="viewRequestDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom py-2.5 px-4">
                <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="viewRequestDetailsModalLabel">
                    <i class="feather-eye me-2 text-primary"></i>{{ __('hrms.assets.lbl_request_details') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3.5" style="max-height: 80vh; overflow-y: auto;">
                <!-- EMPLOYEE & COMPANY CARD WITH REQUEST DATE & REASON -->
                <div class="card border shadow-none bg-light mb-2.5">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="fs-10 text-uppercase fw-bold text-muted d-block mb-0.5">{{ __('hrms.assets.lbl_requested_by') }}</span>
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
                            <span class="fs-10 text-uppercase fw-bold text-muted d-block mb-1"><i class="feather-message-square me-1 text-primary"></i>{{ __('hrms.assets.lbl_reason_for_request') }}</span>
                            <div class="fs-12 text-dark" id="req_detail_reason" style="white-space: pre-wrap; line-height: 1.4;">No reason provided.</div>
                        </div>
                    </div>
                </div>

                <!-- ASSET, CATEGORY, STATUS & QUANTITY PROGRESS -->
                <div class="border rounded-3 p-3 bg-white mb-2.5">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <div>
                            <span class="fs-10 text-uppercase fw-bold text-muted me-2">{{ __('hrms.assets.req_asset') }}</span>
                            <span class="badge bg-light text-secondary border px-2 py-0.5 fs-10" id="req_detail_category">Category</span>
                        </div>
                        <div id="req_detail_status_container">
                            <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11" id="req_detail_status">Pending</span>
                        </div>
                    </div>
                    <h6 class="fw-bold text-dark mb-2 fs-14" id="req_detail_asset_name">Asset Name</h6>

                    <div class="d-flex align-items-center gap-2 mt-1">
                        <div class="flex-fill border rounded py-1 px-2 text-center bg-light">
                            <span class="fs-10 text-uppercase text-muted d-block" style="font-size: 9px;">{{ __('hrms.assets.lbl_req_short') }}</span>
                            <strong class="fs-12 text-dark" id="req_detail_req_qty">0</strong>
                        </div>
                        <div class="flex-fill border rounded py-1 px-2 text-center bg-soft-success border-success-subtle">
                            <span class="fs-10 text-uppercase text-success d-block" style="font-size: 9px;">{{ __('hrms.assets.lbl_alloc_short') }}</span>
                            <strong class="fs-12 text-success" id="req_detail_alloc_qty">0</strong>
                        </div>
                        <div class="flex-fill border rounded py-1 px-2 text-center bg-soft-danger border-danger-subtle">
                            <span class="fs-10 text-uppercase text-danger d-block" style="font-size: 9px;">{{ __('hrms.assets.lbl_rem_short') }}</span>
                            <strong class="fs-12 text-danger" id="req_detail_rem_qty">0</strong>
                        </div>
                    </div>
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
