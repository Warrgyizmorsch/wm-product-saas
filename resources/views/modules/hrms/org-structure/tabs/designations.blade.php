<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="{{ __('hrms.org.designations') }}" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search Form -->
                    <form method="GET" action="{{ route('hrms.org.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 240px;">
                        <input type="hidden" name="tab" value="designations">
                        <input type="hidden" name="ds_department_id" value="{{ $filters['ds_department_id'] }}">
                        <input type="hidden" name="ds_status" value="{{ $filters['ds_status'] }}">
                        <input type="hidden" name="ds_sort" value="{{ $filters['ds_sort'] }}">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="ds_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.org.search_desig') }}" value="{{ $filters['ds_search'] }}" style="box-shadow: none; height: 32px;">
                    </form>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['ds_sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'designations', 'ds_sort' => 'name_asc']) }}">
                            <span>{{ __('hrms.common.sort_name_asc') }}</span>
                            @if($filters['ds_sort'] === 'name_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['ds_sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'designations', 'ds_sort' => 'name_desc']) }}">
                            <span>{{ __('hrms.common.sort_name_desc') }}</span>
                            @if($filters['ds_sort'] === 'name_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['ds_sort'] === 'level_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'designations', 'ds_sort' => 'level_asc']) }}">
                            <span>{{ __('hrms.employees.lbl_grade') ?? 'Grade' }} (A-Z)</span>
                            @if($filters['ds_sort'] === 'level_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['ds_sort'] === 'level_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'designations', 'ds_sort' => 'level_desc']) }}">
                            <span>{{ __('hrms.employees.lbl_grade') ?? 'Grade' }} (Z-A)</span>
                            @if($filters['ds_sort'] === 'level_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="{{ __('hrms.common.filter') }}">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                        <form method="GET" action="{{ route('hrms.org.index') }}">
                            <input type="hidden" name="tab" value="designations">
                            <input type="hidden" name="ds_search" value="{{ $filters['ds_search'] }}">
                            <input type="hidden" name="ds_sort" value="{{ $filters['ds_sort'] }}">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.tbl_dept') }}</label>
                                <select name="ds_department_id" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.common.all_departments') }}</option>
                                    @foreach($departmentsList as $dept)
                                        <option value="{{ $dept->id }}" @selected((string) $filters['ds_department_id'] === (string) $dept->id)>
                                            {{ $dept->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.tbl_status') }}</label>
                                <select name="ds_status" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                    <option value="1" @selected($filters['ds_status'] === '1')>{{ __('hrms.employees.frm_status_active') }}</option>
                                    <option value="0" @selected($filters['ds_status'] === '0')>{{ __('hrms.employees.frm_status_inactive') }}</option>
                                </select>
                            </div>
                            
                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('hrms.org.index', ['tab' => 'designations']) }}" class="btn btn-sm btn-light text-uppercase fw-bold py-2 px-3" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #475569;">{{ __('hrms.common.reset') }}</a>
                                <button type="submit" class="btn btn-sm text-uppercase fw-bold py-2 px-3 text-white bg-primary border-primary">{{ __('hrms.common.apply') }}</button>
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
                            <th>{{ __('hrms.org.tbl_desig_name') }}</th>
                            <th>{{ __('hrms.employees.lbl_grade') ?? 'Grade Level' }}</th>
                            <th>{{ __('hrms.org.tbl_dept') }}</th>
                            <th>{{ __('hrms.org.tbl_status') }}</th>
                            <th width="150" class="text-end">{{ __('hrms.org.tbl_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="designationsTableBody">
                        @foreach($designations as $ds)
                        <tr>
                            <td>{{ $designations->firstItem() + $loop->index }}</td>
                            <!-- <td>
                                <div class="avatar-text avatar-md rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center fw-bold fs-12" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                    {{ substr($ds->name ?? 'DS', 0, 2) }}
                                </div>
                            </td> -->
                            <td><span class="fw-bold text-dark">{{ $ds->name }}</span></td>
                            <td><code>{{ $ds->level ?? 'N/A' }}</code></td>
                            <td>{{ $ds->department->name ?? 'N/A' }}</td>
                            <td>
                                @if($ds->status)
                                    <x-ui.badge variant="success" soft>{{ __('hrms.employees.frm_status_active') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>{{ __('hrms.employees.frm_status_inactive') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('hrms.designation.destroy', $ds->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('hrms.org.confirm_delete_desig') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="javascript:void(0)" class="action-dropdown-btn btn-view-desig" data-bs-toggle="modal" data-bs-target="#viewDesigModal" data-desig="{{ base64_encode($ds->toJson()) }}" title="{{ __('hrms.employees.view_profile') }}" data-bs-toggle="tooltip">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item btn-edit-desig" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editDesigModal" data-desig="{{ base64_encode($ds->toJson()) }}">
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
                        @if($designations->isEmpty())
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                {{ __('hrms.org.empty_desig') }}
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div id="designationsPaginationWrapper">
                @php
                    $currentPage = $designations->currentPage();
                    $totalPages = $designations->lastPage();
                    $totalResults = $designations->total();
                    $perPage = $designations->perPage();
                @endphp
                <x-ui.pagination 
                    class="px-4 py-3 border-top"
                    :current-page="$currentPage"
                    :total-pages="$totalPages"
                    :total-results="$totalResults"
                    :per-page="$perPage"
                    tab="designations"
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
            document.querySelectorAll('.btn-view-desig').forEach(btn => {
                btn.addEventListener('click', function() {
                    let desig = JSON.parse(atob(this.dataset.desig));
                    
                    let nameEl = document.getElementById('modal_view_desig_name');
                    if (nameEl) nameEl.innerText = desig.name;
                    
                    let deptEl = document.getElementById('modal_view_desig_dept');
                    if (deptEl) deptEl.innerText = (desig.department && desig.department.name) ? desig.department.name : 'N/A';
                    
                    let levelEl = document.getElementById('modal_view_desig_level');
                    if (levelEl) levelEl.innerText = desig.level || 'N/A';
                    
                    let descEl = document.getElementById('modal_view_desig_desc');
                    if (descEl) descEl.innerText = desig.description || '{{ __("hrms.employees.lbl_no_description") }}';
                    
                    let avatarEl = document.getElementById('modal_view_desig_avatar');
                    if (avatarEl) {
                        avatarEl.innerText = getInitials(desig.name, 'DS');
                    }
                    
                    let statusEl = document.getElementById('modal_view_desig_status');
                    if (statusEl) {
                        if (desig.status === true || desig.status === 1 || desig.status === '1') {
                            statusEl.innerHTML = '<span class="badge bg-soft-success text-success">{{ __("hrms.employees.frm_status_active") }}</span>';
                        } else {
                            statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">{{ __("hrms.employees.frm_status_inactive") }}</span>';
                        }
                    }
                });
            });

            // Edit Action Trigger
            document.querySelectorAll('.btn-edit-desig').forEach(btn => {
                btn.addEventListener('click', function() {
                    let desig = JSON.parse(atob(this.dataset.desig));
                    
                    let nameEl = document.getElementById('edit_desig_name');
                    if (nameEl) nameEl.value = desig.name || '';
                    
                    let levelEl = document.getElementById('edit_desig_level');
                    if (levelEl) levelEl.value = desig.level || '';
                    
                    let deptEl = document.getElementById('edit_desig_dept_id');
                    if (deptEl) deptEl.value = desig.department_id || '';
                    
                    let descEl = document.getElementById('edit_desig_description');
                    if (descEl) descEl.value = desig.description || '';
                    
                    let statusSelect = document.getElementById('edit_desig_status');
                    if (statusSelect) {
                        statusSelect.value = (desig.status === true || desig.status === 1 || desig.status === '1') ? '1' : '0';
                    }
                    
                    let form = document.getElementById('desig_edit_form');
                    if (form) {
                        form.action = '/hrms/org/designation/update/' + desig.id;
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
