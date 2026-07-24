<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="{{ __('hrms.org.branches') }}" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search Form -->
                    <form method="GET" action="{{ route('hrms.org.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 240px;">
                        <input type="hidden" name="tab" value="branches">
                        <input type="hidden" name="br_company_id" value="{{ $filters['br_company_id'] }}">
                        <input type="hidden" name="br_business_unit_id" value="{{ $filters['br_business_unit_id'] }}">
                        <input type="hidden" name="br_status" value="{{ $filters['br_status'] }}">
                        <input type="hidden" name="br_sort" value="{{ $filters['br_sort'] }}">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="br_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.org.search_branch') }}" value="{{ $filters['br_search'] }}" style="box-shadow: none; height: 32px;">
                    </form>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['br_sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'branches', 'br_sort' => 'name_asc']) }}">
                            <span>{{ __('hrms.common.sort_name_asc') }}</span>
                            @if($filters['br_sort'] === 'name_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['br_sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'branches', 'br_sort' => 'name_desc']) }}">
                            <span>{{ __('hrms.common.sort_name_desc') }}</span>
                            @if($filters['br_sort'] === 'name_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['br_sort'] === 'code_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'branches', 'br_sort' => 'code_asc']) }}">
                            <span>{{ __('hrms.common.sort_code_asc') }}</span>
                            @if($filters['br_sort'] === 'code_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['br_sort'] === 'code_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'branches', 'br_sort' => 'code_desc']) }}">
                            <span>{{ __('hrms.common.sort_code_desc') }}</span>
                            @if($filters['br_sort'] === 'code_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="{{ __('hrms.common.filter') }}">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                        <form method="GET" action="{{ route('hrms.org.index') }}">
                            <input type="hidden" name="tab" value="branches">
                            <input type="hidden" name="br_search" value="{{ $filters['br_search'] }}">
                            <input type="hidden" name="br_sort" value="{{ $filters['br_sort'] }}">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.tbl_company') }}</label>
                                <x-ui.odoo-form-ui type="select" name="br_company_id" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.common.all_companies') }}</option>
                                    @foreach($companiesList as $company)
                                        <option value="{{ $company->id }}" @selected((string) $filters['br_company_id'] === (string) $company->id)>
                                            {{ $company->company_name }}
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.tbl_bu') }}</label>
                                <x-ui.odoo-form-ui type="select" name="br_business_unit_id" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.employees.lbl_all_bu') ?? __('hrms.org.empty_bu') }}</option>
                                    @foreach($businessUnitsList as $unit)
                                        <option value="{{ $unit->id }}" @selected((string) $filters['br_business_unit_id'] === (string) $unit->id)>
                                            {{ $unit->name }}
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.tbl_status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="br_status" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                    <option value="1" @selected($filters['br_status'] === '1')>{{ __('hrms.employees.frm_status_active') }}</option>
                                    <option value="0" @selected($filters['br_status'] === '0')>{{ __('hrms.employees.frm_status_inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            
                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('hrms.org.index', ['tab' => 'branches']) }}" class="btn btn-sm btn-light text-uppercase fw-bold py-2 px-3" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #475569;">{{ __('hrms.common.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary text-uppercase fw-bold py-2 px-3 text-white" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em;">{{ __('hrms.common.apply') }}</button>
                            </div>
                        </form>
                    </x-ui.filter>
                </div>
            </x-slot>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" style="table-layout: fixed; width: 100%;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 45px;">{{ __('hrms.org.tbl_hash') }}</th>
                            <th style="width: 26%;">{{ __('hrms.org.tbl_branch_name') }}</th>
                            <th style="width: 15%;">{{ __('hrms.org.tbl_code') }}</th>
                            <th style="width: 24%;">{{ __('hrms.org.tbl_bu') }}</th>
                            <th style="width: 19%;">{{ __('hrms.employees.lbl_manager') }}</th>
                            <th style="width: 95px; white-space: nowrap;">{{ __('hrms.org.tbl_status') }}</th>
                            <th style="width: 110px; white-space: nowrap;" class="text-end">{{ __('hrms.org.tbl_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="branchesTableBody">
                        @foreach($branches as $br)
                        @php
                            $buName = $br->businessUnit->name ?? ($br->company->company_name ?? 'N/A');
                            $managerName = $br->manager ? ($br->manager->first_name . ' ' . $br->manager->last_name) : 'N/A';
                        @endphp
                        <tr>
                            <td>{{ $branches->firstItem() + $loop->index }}</td>
                            <td style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                <span class="fw-bold text-dark">{{ $br->name }}</span>
                            </td>
                            <td style="word-break: break-word; overflow-wrap: anywhere;">
                                <code style="word-break: break-all; white-space: normal;">{{ $br->code }}</code>
                            </td>
                            <td style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                {{ $buName }}
                            </td>
                            <td style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                {{ $managerName }}
                            </td>
                            <td>
                                @if($br->status)
                                    <x-ui.badge variant="success" soft>{{ __('hrms.employees.frm_status_active') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>{{ __('hrms.employees.frm_status_inactive') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('hrms.branch.destroy', $br->id) }}" method="POST" class="d-inline" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.org.confirm_delete_branch') }}', { title: 'Delete Branch', variant: 'danger', confirmButtonText: 'Delete' });">
                                    @csrf
                                    @method('DELETE')
                                    <div class="hstack gap-2 justify-content-end align-items-center">
                                        <a href="javascript:void(0)" class="action-dropdown-btn btn-view-branch" data-bs-toggle="modal" data-bs-target="#viewBranchModal" data-branch="{{ base64_encode($br->toJson()) }}" title="{{ __('hrms.common.view') ?? 'View' }}" style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1.5px solid #cbd5e1; background-color: #ffffff; color: #475569;">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                        <x-ui.action-dropdown>
                                             <li>
                                                 <a class="dropdown-item btn-edit-branch" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editBranchModal" data-branch="{{ base64_encode($br->toJson()) }}">
                                                     <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('hrms.assets.edit') }}
                                                 </a>
                                             </li>
                                             <li>
                                                 <button type="submit" class="dropdown-item text-danger">
                                                     <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('hrms.assets.delete') }}
                                                 </button>
                                             </li>
                                        </x-ui.action-dropdown>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                        @if($branches->isEmpty())
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                {{ __('hrms.org.empty_branch') }}
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div id="branchesPaginationWrapper">
                @php
                    $currentPage = $branches->currentPage();
                    $totalPages = $branches->lastPage();
                    $totalResults = $branches->total();
                    $perPage = $branches->perPage();
                @endphp
                <x-ui.pagination 
                    class="px-4 py-3 border-top"
                    :current-page="$currentPage"
                    :total-pages="$totalPages"
                    :total-results="$totalResults"
                    :per-page="$perPage"
                    tab="branches"
                />
            </div>
        </x-ui.card>
    </div>
</div>

<script>
    (function() {
        function init() {
            function getInitials(name, fallback) {
                const words = String(name || fallback || '').trim().split(/\s+/).filter(Boolean);

                if (words.length >= 2) {
                    return (words[0][0] + words[1][0]).toUpperCase();
                }

                return (words[0] || fallback || '').substring(0, 2).toUpperCase();
            }

            // View Action Trigger
            document.querySelectorAll('.btn-view-branch').forEach(btn => {
                btn.addEventListener('click', function() {
                    let branch = JSON.parse(atob(this.dataset.branch));
                    
                    let nameEl = document.getElementById('modal_view_branch_name');
                    if (nameEl) nameEl.innerText = branch.name;
                    
                    let buEl = document.getElementById('modal_view_branch_bu');
                    if (buEl) buEl.innerText = (branch.business_unit && branch.business_unit.name) ? branch.business_unit.name : ((branch.company && branch.company.company_name) ? branch.company.company_name : 'N/A');
                    
                    let codeEl = document.getElementById('modal_view_branch_code');
                    if (codeEl) codeEl.innerText = branch.code;
                    
                    let managerEl = document.getElementById('modal_view_branch_manager');
                    if (managerEl) managerEl.innerText = (branch.manager) ? (branch.manager.first_name + ' ' + branch.manager.last_name) : 'N/A';
                    
                    let phoneEl = document.getElementById('modal_view_branch_phone');
                    if (phoneEl) phoneEl.innerText = branch.phone || 'N/A';
                    
                    let emailEl = document.getElementById('modal_view_branch_email');
                    if (emailEl) emailEl.innerText = branch.email || 'N/A';
                    
                    let countryEl = document.getElementById('modal_view_branch_country');
                    if (countryEl) countryEl.innerText = branch.country || 'N/A';
                    
                    let stateEl = document.getElementById('modal_view_branch_state');
                    if (stateEl) stateEl.innerText = branch.state || 'N/A';
                    
                    let cityEl = document.getElementById('modal_view_branch_city');
                    if (cityEl) cityEl.innerText = branch.city || 'N/A';
                    
                    let zipEl = document.getElementById('modal_view_branch_zip');
                    if (zipEl) zipEl.innerText = branch.postal_code || 'N/A';
                    
                    let addressEl = document.getElementById('modal_view_branch_address');
                    if (addressEl) addressEl.innerText = branch.address || 'N/A';
                    
                    let avatarEl = document.getElementById('modal_view_branch_avatar');
                    if (avatarEl) {
                        avatarEl.innerText = getInitials(branch.name, 'BR');
                    }
                    
                    let statusEl = document.getElementById('modal_view_branch_status');
                    if (statusEl) {
                        if (branch.status === true || branch.status === 1 || branch.status === '1') {
                            statusEl.innerHTML = '<span class="badge bg-soft-success text-success fw-bold fs-13">Active</span>';
                        } else {
                            statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger fw-bold fs-13">Inactive</span>';
                        }
                    }
                });
            });

            // Edit Action Trigger
            document.querySelectorAll('.btn-edit-branch').forEach(btn => {
                btn.addEventListener('click', function() {
                    let branch = JSON.parse(atob(this.dataset.branch));
                    
                    let nameEl = document.getElementById('edit_branch_name');
                    if (nameEl) nameEl.value = branch.name || '';
                    
                    let codeEl = document.getElementById('edit_branch_code');
                    if (codeEl) codeEl.value = branch.code || '';
                    
                    let buEl = document.getElementById('edit_branch_bu_id');
                    if (buEl) buEl.value = branch.business_unit_id || '';
                    
                    let companyEl = document.getElementById('edit_branch_company_id');
                    if (companyEl) companyEl.value = branch.company_id || '';
                    
                    let managerEl = document.getElementById('edit_branch_manager_id');
                    if (managerEl) managerEl.value = branch.manager_employee_id || '';
                    
                    let phoneEl = document.getElementById('edit_branch_phone');
                    if (phoneEl) phoneEl.value = branch.phone || '';
                    
                    let emailEl = document.getElementById('edit_branch_email');
                    if (emailEl) emailEl.value = branch.email || '';
                    
                    let countryEl = document.getElementById('edit_branch_country');
                    if (countryEl) countryEl.value = branch.country || '';
                    
                    let stateEl = document.getElementById('edit_branch_state');
                    if (stateEl) stateEl.value = branch.state || '';
                    
                    let cityEl = document.getElementById('edit_branch_city');
                    if (cityEl) cityEl.value = branch.city || '';
                    
                    let postalEl = document.getElementById('edit_branch_postal_code');
                    if (postalEl) postalEl.value = branch.postal_code || '';
                    
                    let addressEl = document.getElementById('edit_branch_address');
                    if (addressEl) addressEl.value = branch.address || '';
                    
                    let statusSelect = document.getElementById('edit_branch_status');
                    if (statusSelect) {
                        statusSelect.value = (branch.status === true || branch.status === 1 || branch.status === '1') ? '1' : '0';
                    }
                    
                    let form = document.getElementById('branch_edit_form');
                    if (form) {
                        form.action = '/hrms/org/branch/update/' + branch.id;
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
