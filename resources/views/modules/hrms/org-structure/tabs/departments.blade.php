<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="{{ __('hrms.org.departments') }}" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search Form -->
                    <form method="GET" action="{{ route('hrms.org.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 240px;">
                        <input type="hidden" name="tab" value="departments">
                        <input type="hidden" name="dp_company_id" value="{{ $filters['dp_company_id'] }}">
                        <input type="hidden" name="dp_business_unit_id" value="{{ $filters['dp_business_unit_id'] }}">
                        <input type="hidden" name="dp_branch_id" value="{{ $filters['dp_branch_id'] }}">
                        <input type="hidden" name="dp_status" value="{{ $filters['dp_status'] }}">
                        <input type="hidden" name="dp_sort" value="{{ $filters['dp_sort'] }}">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="dp_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.org.search_dept') }}" value="{{ $filters['dp_search'] }}" style="box-shadow: none; height: 32px;">
                    </form>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['dp_sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'departments', 'dp_sort' => 'name_asc']) }}">
                            <span>{{ __('hrms.common.sort_name_asc') }}</span>
                            @if($filters['dp_sort'] === 'name_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['dp_sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'departments', 'dp_sort' => 'name_desc']) }}">
                            <span>{{ __('hrms.common.sort_name_desc') }}</span>
                            @if($filters['dp_sort'] === 'name_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['dp_sort'] === 'code_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'departments', 'dp_sort' => 'code_asc']) }}">
                            <span>{{ __('hrms.org.sort_code_asc') }}</span>
                            @if($filters['dp_sort'] === 'code_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['dp_sort'] === 'code_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'departments', 'dp_sort' => 'code_desc']) }}">
                            <span>{{ __('hrms.org.sort_code_desc') }}</span>
                            @if($filters['dp_sort'] === 'code_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="{{ __('hrms.common.filter') }}">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                        <form method="GET" action="{{ route('hrms.org.index') }}">
                            <input type="hidden" name="tab" value="departments">
                            <input type="hidden" name="dp_search" value="{{ $filters['dp_search'] }}">
                            <input type="hidden" name="dp_sort" value="{{ $filters['dp_sort'] }}">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.tbl_company') }}</label>
                                <select name="dp_company_id" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.common.all_companies') }}</option>
                                    @foreach($companiesList as $company)
                                        <option value="{{ $company->id }}" @selected((string) $filters['dp_company_id'] === (string) $company->id)>
                                            {{ $company->company_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.tbl_bu') }}</label>
                                <select name="dp_business_unit_id" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.employees.lbl_all_bu') ?? __('hrms.org.empty_bu') }}</option>
                                    @foreach($businessUnitsList as $unit)
                                        <option value="{{ $unit->id }}" @selected((string) $filters['dp_business_unit_id'] === (string) $unit->id)>
                                            {{ $unit->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.branches') }}</label>
                                <select name="dp_branch_id" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.common.all_companies') }} - {{ __('hrms.org.branches') }}</option>
                                    @foreach($branchesList as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) $filters['dp_branch_id'] === (string) $branch->id)>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.tbl_status') }}</label>
                                <select name="dp_status" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                    <option value="1" @selected($filters['dp_status'] === '1')>{{ __('hrms.employees.frm_status_active') }}</option>
                                    <option value="0" @selected($filters['dp_status'] === '0')>{{ __('hrms.employees.frm_status_inactive') }}</option>
                                </select>
                            </div>
                            
                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('hrms.org.index', ['tab' => 'departments']) }}" class="btn btn-sm btn-light text-uppercase fw-bold py-2 px-3" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #475569;">{{ __('hrms.common.reset') }}</a>
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
                            <th>{{ __('hrms.org.tbl_dept_name') }}</th>
                            <th>{{ __('hrms.org.tbl_code') }}</th>
                            <th>{{ __('hrms.org.branches') }}</th>
                            <th>{{ __('hrms.org.tbl_parent_dept') }}</th>
                            <th>{{ __('hrms.org.tbl_status') }}</th>
                            <th width="150" class="text-end">{{ __('hrms.org.tbl_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="departmentsTableBody">
                        @foreach($departments as $d)
                        <tr>
                            <td>{{ $departments->firstItem() + $loop->index }}</td>
                            <!-- <td>
                                <div class="avatar-text avatar-md rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center fw-bold fs-12" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                    {{ substr($d->name ?? 'DP', 0, 2) }}
                                </div>
                            </td> -->
                            <td><span class="fw-bold text-dark">{{ $d->name }}</span></td>
                            <td><code>{{ $d->code }}</code></td>
                            <td>{{ $d->branch->name ?? ($d->businessUnit->name ?? ($d->company->company_name ?? 'N/A')) }}</td>
                            <td>{{ $d->head ? ($d->head->first_name . ' ' . $d->head->last_name) : 'N/A' }}</td>
                            <td>
                                @if($d->status)
                                    <x-ui.badge variant="success" soft>{{ __('hrms.employees.frm_status_active') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>{{ __('hrms.employees.frm_status_inactive') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('hrms.department.destroy', $d->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('hrms.org.confirm_delete_dept') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="javascript:void(0)" class="action-dropdown-btn btn-view-dept" data-bs-toggle="modal" data-bs-target="#viewDeptModal" data-dept="{{ base64_encode($d->toJson()) }}" title="{{ __('hrms.employees.view_profile') }}" data-bs-toggle="tooltip">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item btn-edit-dept" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editDeptModal" data-dept="{{ base64_encode($d->toJson()) }}">
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
                        @if($departments->isEmpty())
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                {{ __('hrms.org.empty_dept') }}
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div id="departmentsPaginationWrapper">
                @php
                    $currentPage = $departments->currentPage();
                    $totalPages = $departments->lastPage();
                    $totalResults = $departments->total();
                    $perPage = $departments->perPage();
                @endphp
                <x-ui.pagination
                    class="px-4 py-3 border-top"
                    :current-page="$currentPage"
                    :total-pages="$totalPages"
                    :total-results="$totalResults"
                    :per-page="$perPage"
                    tab="departments"
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
            document.querySelectorAll('.btn-view-dept').forEach(btn => {
                btn.addEventListener('click', function() {
                    let dept = JSON.parse(atob(this.dataset.dept));
                    
                    let nameEl = document.getElementById('modal_view_dept_name');
                    if (nameEl) nameEl.innerText = dept.name;
                    
                    let branchEl = document.getElementById('modal_view_dept_branch');
                    if (branchEl) branchEl.innerText = (dept.branch && dept.branch.name) ? dept.branch.name : ((dept.business_unit && dept.business_unit.name) ? dept.business_unit.name : ((dept.company && dept.company.company_name) ? dept.company.company_name : 'N/A'));
                    
                    let codeEl = document.getElementById('modal_view_dept_code');
                    if (codeEl) codeEl.innerText = dept.code;
                    
                    let headEl = document.getElementById('modal_view_dept_head');
                    if (headEl) headEl.innerText = (dept.head) ? (dept.head.first_name + ' ' + dept.head.last_name) : 'N/A';
                    
                    let descEl = document.getElementById('modal_view_dept_desc');
                    if (descEl) descEl.innerText = dept.description || '{{ __("hrms.employees.lbl_no_description") }}';
                    
                    let avatarEl = document.getElementById('modal_view_dept_avatar');
                    if (avatarEl) {
                        avatarEl.innerText = getInitials(dept.name, 'DP');
                    }
                    
                    let statusEl = document.getElementById('modal_view_dept_status');
                    if (statusEl) {
                        if (dept.status === true || dept.status === 1 || dept.status === '1') {
                            statusEl.innerHTML = '<span class="badge bg-soft-success text-success">{{ __("hrms.employees.frm_status_active") }}</span>';
                        } else {
                            statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">{{ __("hrms.employees.frm_status_inactive") }}</span>';
                        }
                    }
                });
            });

            // Edit Action Trigger
            document.querySelectorAll('.btn-edit-dept').forEach(btn => {
                btn.addEventListener('click', function() {
                    let dept = JSON.parse(atob(this.dataset.dept));
                    
                    let nameEl = document.getElementById('edit_dept_name');
                    if (nameEl) nameEl.value = dept.name || '';
                    
                    let codeEl = document.getElementById('edit_dept_code');
                    if (codeEl) codeEl.value = dept.code || '';
                    
                    let branchEl = document.getElementById('edit_dept_branch_id');
                    if (branchEl) branchEl.value = dept.branch_id || '';
                    
                    let companyEl = document.getElementById('edit_dept_company_id');
                    if (companyEl) companyEl.value = dept.company_id || '';
                    
                    let buEl = document.getElementById('edit_dept_bu_id');
                    if (buEl) buEl.value = dept.business_unit_id || '';
                    
                    let headEl = document.getElementById('edit_dept_head_id');
                    if (headEl) headEl.value = dept.head_employee_id || '';
                    
                    let descEl = document.getElementById('edit_dept_description');
                    if (descEl) descEl.value = dept.description || '';
                    
                    let statusSelect = document.getElementById('edit_dept_status');
                    if (statusSelect) {
                        statusSelect.value = (dept.status === true || dept.status === 1 || dept.status === '1') ? '1' : '0';
                    }
                    
                    let form = document.getElementById('dept_edit_form');
                    if (form) {
                        form.action = '/hrms/org/department/update/' + dept.id;
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
