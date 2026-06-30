<style>
    .bu-link {
        transition: all 0.2s ease-in-out;
    }
    .bu-link.active-entity {
        background-color: rgba(13, 110, 253, 0.08) !important;
    }
    .bu-link.active-entity td:first-child {
        border-left: 4px solid var(--bs-primary, #0d6efd) !important;
    }
</style>

<div class="row">

    <!-- Details Card -->
    <div class="col-xxl-8">
        <div class="card stretch stretch-full">
            <div class="card-body">
                @php
                    $bu = $businessUnits->first();
                @endphp
                @if($bu)
                <div id="bu_details_view_mode">
                    <div class="d-flex justify-content-between">
                        <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                            <div class="avatar-text avatar-lg rounded bg-soft-primary text-primary" id="detail_bu_avatar" style="width: 50px; height: 50px; min-width: 50px; min-height: 50px; font-size: 16px;">
                                {{ substr($bu->name ?? 'BU', 0, 2) }}
                            </div>
                            <div>
                                <h4 class="mb-1" id="detail_bu_name">{{ $bu->name }}</h4>
                                <span class="fs-12 text-muted" id="detail_bu_company_name">{{ $bu->company->company_name ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <a id="edit_bu_details_toggle" href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit Business Unit Details">
                            <i class="feather-edit"></i>
                        </a>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Business Unit Name</label>
                            <p class="mb-3" id="bu_name">{{ $bu->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Business Unit Code</label>
                            <p class="mb-3" id="bu_code">{{ $bu->code }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Parent Company</label>
                            <p class="mb-3" id="bu_company_name_text">{{ $bu->company->company_name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Unit Head</label>
                            <p class="mb-3" id="bu_head_name">
                                {{ $bu->head ? ($bu->head->first_name . ' ' . $bu->head->last_name) : 'N/A' }}
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Status</label>
                            <p class="mb-3" id="bu_status">
                                @if($bu->status)
                                    <span class="badge bg-soft-success text-success">Active</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="fs-12 fw-semibold text-muted mb-1">Description</label>
                            <p class="mb-0" id="bu_description">{{ $bu->description ?? 'No description provided.' }}</p>
                        </div>
                    </div> <!-- Close row g-4 -->
                </div> <!-- Close bu_details_view_mode -->

                <!-- Edit Mode Container -->
                <div id="bu_details_edit_mode" class="d-none">
                    <form id="bu_edit_form" action="{{ route('hrms.business-unit.update', $bu->id ?? 0) }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Business Unit Name</label>
                                <input type="text" class="form-control" name="name" id="edit_bu_name" value="{{ $bu->name }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Business Unit Code</label>
                                <input type="text" class="form-control" name="code" id="edit_bu_code" value="{{ $bu->code }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Parent Company</label>
                                <select class="form-control" name="company_id" id="edit_bu_company_id">
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ $company->id == $bu->company_id ? 'selected' : '' }}>
                                            {{ $company->company_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Unit Head</label>
                                <select class="form-control" name="head_employee_id" id="edit_bu_head_employee_id">
                                    <option value="">Select Unit Head</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ $employee->id == $bu->head_employee_id ? 'selected' : '' }}>
                                            {{ $employee->first_name }} {{ $employee->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-control" name="status" id="edit_bu_status">
                                    <option value="1" {{ $bu->status ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !$bu->status ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" name="description" id="edit_bu_description" rows="3">{{ $bu->description }}</textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-light me-2" id="cancel_bu_edit_btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Details</button>
                        </div>
                    </form>
                </div>
                @else
                <div class="text-center py-5">
                    <p class="text-muted mb-0">No Business Units found. Click "Add Business Unit" to create one.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sidebar List Card -->
    <div class="col-xxl-4 h-100">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Business Units</h5>
                <a href="/hrms/org/business-unit/create" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Add Business Unit">
                    <i class="feather-plus"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <tbody>
                        @foreach($businessUnits as $unit)
                        <tr class="bu-link @if($loop->first) active-entity @endif"
                            data-bu='@json($unit)'
                            style="cursor: pointer;">

                            <td>
                                <div class="hstack gap-3">
                                    <div class="avatar-text avatar-lg rounded bg-soft-primary text-primary" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                        {{ substr($unit->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <span class="d-block fw-semibold">
                                            {{ $unit->name }}
                                        </span>
                                        <span class="fs-12 text-muted">
                                            {{ $unit->company->company_name ?? 'N/A' }}
                                        </span>
                                    </div>
                                    
                                </div>
                            </td>

                            <!-- <td width="120">
                                <small class="text-muted d-block">Code</small>
                                <span>{{ $unit->code }}</span>
                            </td> -->

                            <td class="text-end">
                                @if($unit->status)
                                    <span class="badge bg-soft-success text-success">
                                        Active
                                    </span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Toggle Edit Mode Logic
        let isBuEditMode = false;
        const viewModeBu = document.getElementById('bu_details_view_mode');
        const editModeBu = document.getElementById('bu_details_edit_mode');
        const toggleBuBtn = document.getElementById('edit_bu_details_toggle');
        const cancelBuBtn = document.getElementById('cancel_bu_edit_btn');

        function toggleBuEdit(edit) {
            isBuEditMode = edit;
            if (!viewModeBu || !editModeBu) return;
            if (isBuEditMode) {
                viewModeBu.classList.add('d-none');
                editModeBu.classList.remove('d-none');
                if (toggleBuBtn) {
                    toggleBuBtn.innerHTML = '<i class="feather-x"></i>';
                    toggleBuBtn.setAttribute('title', 'Cancel Edit');
                }
            } else {
                viewModeBu.classList.remove('d-none');
                editModeBu.classList.add('d-none');
                if (toggleBuBtn) {
                    toggleBuBtn.innerHTML = '<i class="feather-edit"></i>';
                    toggleBuBtn.setAttribute('title', 'Edit Business Unit Details');
                }
            }
        }

        if (toggleBuBtn) {
            toggleBuBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleBuEdit(!isBuEditMode);
            });
        }

        if (cancelBuBtn) {
            cancelBuBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleBuEdit(false);
            });
        }

        // Sidebar click handler to select business unit
        document.querySelectorAll('.bu-link').forEach(link => {
            link.addEventListener('click', function(e){
                e.preventDefault();

                // Close edit mode on switch
                toggleBuEdit(false);

                // Toggle active highlights on list rows
                document.querySelectorAll('.bu-link').forEach(row => {
                    row.classList.remove('active-entity');
                });
                this.classList.add('active-entity');

                let unit = JSON.parse(this.dataset.bu);

                // Update text fields dynamically
                if (document.getElementById('bu_name')) document.getElementById('bu_name').innerText = unit.name || '';
                if (document.getElementById('bu_code')) document.getElementById('bu_code').innerText = unit.code || '';
                if (document.getElementById('bu_company_name_text')) {
                    document.getElementById('bu_company_name_text').innerText = (unit.company && unit.company.company_name) ? unit.company.company_name : 'N/A';
                }
                if (document.getElementById('bu_head_name')) {
                    document.getElementById('bu_head_name').innerText = (unit.head) ? (unit.head.first_name + ' ' + unit.head.last_name) : 'N/A';
                }
                if (document.getElementById('bu_description')) document.getElementById('bu_description').innerText = unit.description || 'No description provided.';
                if (document.getElementById('detail_bu_name')) document.getElementById('detail_bu_name').innerText = unit.name || '';
                if (document.getElementById('detail_bu_company_name')) {
                    document.getElementById('detail_bu_company_name').innerText = (unit.company && unit.company.company_name) ? unit.company.company_name : 'N/A';
                }

                // Update Initials Avatar
                let avatarEl = document.getElementById('detail_bu_avatar');
                if (avatarEl) {
                    avatarEl.innerText = (unit.name) ? unit.name.substring(0, 2) : 'BU';
                }

                // Update edit fields dynamically
                if (document.getElementById('edit_bu_name')) document.getElementById('edit_bu_name').value = unit.name || '';
                if (document.getElementById('edit_bu_code')) document.getElementById('edit_bu_code').value = unit.code || '';
                if (document.getElementById('edit_bu_company_id')) document.getElementById('edit_bu_company_id').value = unit.company_id || '';
                if (document.getElementById('edit_bu_head_employee_id')) document.getElementById('edit_bu_head_employee_id').value = unit.head_employee_id || '';
                if (document.getElementById('edit_bu_status')) {
                    document.getElementById('edit_bu_status').value = (unit.status === true || unit.status === 1 || unit.status === '1') ? '1' : '0';
                }
                if (document.getElementById('edit_bu_description')) document.getElementById('edit_bu_description').value = unit.description || '';

                // Update form action URL to point to current business unit
                let editForm = document.getElementById('bu_edit_form');
                if (editForm) {
                    editForm.action = '/hrms/org/business-unit/update/' + unit.id;
                }

                // Update Status Badge
                let statusEl = document.getElementById('bu_status');
                if (statusEl) {
                    if (unit.status === true || unit.status === 1 || unit.status === '1') {
                        statusEl.innerHTML = '<span class="badge bg-soft-success text-success">Active</span>';
                    } else {
                        statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">Inactive</span>';
                    }
                }
            });
        });
    });
</script>
