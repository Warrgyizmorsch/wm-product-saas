<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="{{ __('hrms.org.business_units') }}" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search Form -->
                    <form method="GET" action="{{ route('hrms.org.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 240px;">
                        <input type="hidden" name="tab" value="business-units">
                        <input type="hidden" name="bu_company_id" value="{{ $filters['bu_company_id'] }}">
                        <input type="hidden" name="bu_status" value="{{ $filters['bu_status'] }}">
                        <input type="hidden" name="bu_sort" value="{{ $filters['bu_sort'] }}">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="bu_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.org.search_bu') }}" value="{{ $filters['bu_search'] }}" style="box-shadow: none; height: 32px;">
                    </form>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['bu_sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'business-units', 'bu_sort' => 'name_asc']) }}">
                            <span>{{ __('hrms.common.sort_name_asc') }}</span>
                            @if($filters['bu_sort'] === 'name_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['bu_sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'business-units', 'bu_sort' => 'name_desc']) }}">
                            <span>{{ __('hrms.common.sort_name_desc') }}</span>
                            @if($filters['bu_sort'] === 'name_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['bu_sort'] === 'code_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'business-units', 'bu_sort' => 'code_asc']) }}">
                            <span>{{ __('hrms.org.sort_code_asc') }}</span>
                            @if($filters['bu_sort'] === 'code_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['bu_sort'] === 'code_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'business-units', 'bu_sort' => 'code_desc']) }}">
                            <span>{{ __('hrms.org.sort_code_desc') }}</span>
                            @if($filters['bu_sort'] === 'code_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="{{ __('hrms.common.filter') }}">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                        <form method="GET" action="{{ route('hrms.org.index') }}">
                            <input type="hidden" name="tab" value="business-units">
                            <input type="hidden" name="bu_search" value="{{ $filters['bu_search'] }}">
                            <input type="hidden" name="bu_sort" value="{{ $filters['bu_sort'] }}">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.parent_unit') }}</label>
                                <select name="bu_company_id" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.common.all_companies') }}</option>
                                    @foreach($companiesList as $company)
                                        <option value="{{ $company->id }}" @selected((string) $filters['bu_company_id'] === (string) $company->id)>
                                            {{ $company->company_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.tbl_status') }}</label>
                                <select name="bu_status" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                    <option value="1" @selected($filters['bu_status'] === '1')>{{ __('hrms.employees.frm_status_active') }}</option>
                                    <option value="0" @selected($filters['bu_status'] === '0')>{{ __('hrms.employees.frm_status_inactive') }}</option>
                                </select>
                            </div>
                            
                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('hrms.org.index', ['tab' => 'business-units']) }}" class="btn btn-sm btn-light text-uppercase fw-bold py-2 px-3" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #475569;">{{ __('hrms.common.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary text-uppercase fw-bold py-2 px-3 text-white" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em;">{{ __('hrms.common.apply') }}</button>
                            </div>
                        </form>
                    </x-ui.filter>
                </div>
            </x-slot>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">{{ __('hrms.org.tbl_hash') }}</th>
                            <th>{{ __('hrms.org.tbl_bu_name') }}</th>
                            <th>{{ __('hrms.org.tbl_code') }}</th>
                            <th>{{ __('hrms.org.tbl_company') }}</th>
                            <th>{{ __('hrms.employees.lbl_manager') }}</th>
                            <th>{{ __('hrms.org.tbl_status') }}</th>
                            <th width="150" class="text-end">{{ __('hrms.org.tbl_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="businessUnitsTableBody">
                        @foreach($businessUnits as $unit)
                        <tr>
                            <td>{{ $businessUnits->firstItem() + $loop->index }}</td>
                            <td>
                                <span class="fw-bold text-dark">{{ $unit->name }}</span>
                            </td>
                            <td><code>{{ $unit->code }}</code></td>
                            <td>{{ $unit->company->company_name ?? 'N/A' }}</td>
                            <td>{{ $unit->head ? ($unit->head->first_name . ' ' . $unit->head->last_name) : 'N/A' }}</td>
                            <td>
                                @if($unit->status)
                                    <x-ui.badge variant="success" soft>{{ __('hrms.employees.frm_status_active') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>{{ __('hrms.employees.frm_status_inactive') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('hrms.business-unit.destroy', $unit->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('hrms.org.confirm_delete_bu') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="javascript:void(0)" class="action-dropdown-btn btn-view-bu" data-bs-toggle="modal" data-bs-target="#viewBuModal" data-bu="{{ base64_encode($unit->toJson()) }}" title="{{ __('hrms.employees.view_profile') }}" data-bs-toggle="tooltip">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item btn-edit-bu" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editBuModal" data-bu="{{ base64_encode($unit->toJson()) }}">
                                                    <i class="feather feather-edit-3 me-3"></i>
                                                    <span>{{ __('hrms.assets.edit') }}</span>
                                                </a>
                                            </li>
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                    <i class="feather feather-trash-2 me-3"></i>
                                                    <span>{{ __('hrms.assets.delete') }}</span>
                                                </button>
                                            </li>
                                        </x-ui.action-dropdown>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                        @if($businessUnits->isEmpty())
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                {{ __('hrms.org.empty_bu') }}
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div id="businessUnitsPaginationWrapper">
                @php
                    $currentPage = $businessUnits->currentPage();
                    $totalPages = $businessUnits->lastPage();
                    $totalResults = $businessUnits->total();
                    $perPage = $businessUnits->perPage();
                @endphp
                <x-ui.pagination
                    class="px-4 py-3 border-top"
                    :current-page="$currentPage"
                    :total-pages="$totalPages"
                    :total-results="$totalResults"
                    :per-page="$perPage"
                    tab="business-units"
                />
            </div>
        </x-ui.card>
    </div>
</div>

<script>
    (function() {
        function init() {
            // Bind company change listener for edit business unit modal once
            const editCompanySelect = $('#edit_bu_company_id');
            const editHeadSelect = $('#edit_bu_head_employee_id');
            if (editCompanySelect.length && editHeadSelect.length) {
                editCompanySelect.on('change', function () {
                    const compId = editCompanySelect.val();
                    let originalOptions = editHeadSelect.data('original-options');
                    if (!originalOptions) {
                        originalOptions = editHeadSelect.find('option').clone();
                        editHeadSelect.data('original-options', originalOptions);
                    }

                    const currentSelected = editHeadSelect.val();
                    editHeadSelect.empty();

                    originalOptions.each(function () {
                        const opt = $(this);
                        const optVal = opt.val();
                        const optionCompId = opt.attr('data-company-id');

                        if (!optVal || !compId || String(optionCompId) === String(compId)) {
                            editHeadSelect.append(opt.clone());
                        }
                    });

                    if (editHeadSelect.find(`option[value="${currentSelected}"]`).length) {
                        editHeadSelect.val(currentSelected);
                    } else {
                        editHeadSelect.val('');
                    }

                    if (editHeadSelect.hasClass('select2-hidden-accessible')) {
                        editHeadSelect.trigger('change.select2');
                    }
                });
            }

            function getInitials(name, fallback) {
                const words = String(name || fallback || '').trim().split(/\s+/).filter(Boolean);

                if (words.length >= 2) {
                    return (words[0][0] + words[1][0]).toUpperCase();
                }

                return (words[0] || fallback || '').substring(0, 2).toUpperCase();
            }

            // View Action Trigger
            document.querySelectorAll('.btn-view-bu').forEach(btn => {
                btn.addEventListener('click', function() {
                    let unit = JSON.parse(atob(this.dataset.bu));
                    
                    let nameEl = document.getElementById('modal_view_bu_name');
                    if (nameEl) nameEl.innerText = unit.name;
                    
                    let compEl = document.getElementById('modal_view_bu_company');
                    if (compEl) compEl.innerText = (unit.company && unit.company.company_name) ? unit.company.company_name : 'N/A';
                    
                    let codeEl = document.getElementById('modal_view_bu_code');
                    if (codeEl) codeEl.innerText = unit.code;
                    
                    let headEl = document.getElementById('modal_view_bu_head');
                    if (headEl) headEl.innerText = (unit.head) ? (unit.head.first_name + ' ' + unit.head.last_name) : 'N/A';
                    
                    let descEl = document.getElementById('modal_view_bu_desc');
                    if (descEl) descEl.innerText = unit.description || 'No description provided.';
                    
                    let avatarEl = document.getElementById('modal_view_bu_avatar');
                    if (avatarEl) {
                        avatarEl.innerText = getInitials(unit.name, 'BU');
                    }
                    
                    let statusEl = document.getElementById('modal_view_bu_status');
                    if (statusEl) {
                        if (unit.status === true || unit.status === 1 || unit.status === '1') {
                            statusEl.innerHTML = '<span class="badge bg-soft-success text-success">Active</span>';
                        } else {
                            statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">Inactive</span>';
                        }
                    }
                });
            });

            // Edit Action Trigger
            document.querySelectorAll('.btn-edit-bu').forEach(btn => {
                btn.addEventListener('click', function() {
                    let unit = JSON.parse(atob(this.dataset.bu));
                    
                    let nameEl = document.getElementById('edit_bu_name');
                    if (nameEl) nameEl.value = unit.name || '';
                    
                    let codeEl = document.getElementById('edit_bu_code');
                    if (codeEl) codeEl.value = unit.code || '';
                    
                    let companyEl = document.getElementById('edit_bu_company_id');
                    if (companyEl) {
                        companyEl.value = unit.company_id || '';
                        $(companyEl).trigger('change');
                    }
                    
                    let headEl = document.getElementById('edit_bu_head_employee_id');
                    if (headEl) {
                        $(headEl).val(unit.head_employee_id || '');
                        if ($(headEl).hasClass('select2-hidden-accessible')) {
                            $(headEl).trigger('change.select2');
                        }
                    }
                    
                    let descEl = document.getElementById('edit_bu_description');
                    if (descEl) descEl.value = unit.description || '';
                    
                    let statusSelect = document.getElementById('edit_bu_status');
                    if (statusSelect) {
                        statusSelect.value = (unit.status === true || unit.status === 1 || unit.status === '1') ? '1' : '0';
                    }
                    
                    let form = document.getElementById('bu_edit_form');
                    if (form) {
                        form.action = '/hrms/org/business-unit/update/' + unit.id;
                    }
                });
            });
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", init);
        } else {
            init();
        }
    })();
</script>
