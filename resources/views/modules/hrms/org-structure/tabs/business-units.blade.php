<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="Business Units" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addBuModal">
                    Add Business Unit
                </x-ui.button>
            </x-slot>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">#</th>
                            <th>Business Unit Name</th>
                            <th>Parent Company</th>
                            <th>Unit Head</th>
                            <th>Status</th>
                            <th width="150" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($businessUnits as $unit)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <span class="fw-bold text-dark">{{ $unit->name }}</span>
                            </td>
                            <td>{{ $unit->company->company_name ?? 'N/A' }}</td>
                            <td>{{ $unit->head ? ($unit->head->first_name . ' ' . $unit->head->last_name) : 'N/A' }}</td>
                            <td>
                                @if($unit->status)
                                    <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('hrms.business-unit.destroy', $unit->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this business unit?');">
                                    @csrf
                                    @method('DELETE')
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="javascript:void(0)" class="action-dropdown-btn btn-view-bu" data-bs-toggle="modal" data-bs-target="#viewBuModal" data-bu="{{ base64_encode($unit->toJson()) }}" title="View Details" data-bs-toggle="tooltip">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item btn-edit-bu" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editBuModal" data-bu="{{ base64_encode($unit->toJson()) }}">
                                                    <i class="feather feather-edit-3 me-3"></i>
                                                    <span>Edit</span>
                                                </a>
                                            </li>
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                    <i class="feather feather-trash-2 me-3"></i>
                                                    <span>Delete</span>
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
                                No Business Units found. Click "Add Business Unit" to create one.
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
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
                    if (companyEl) companyEl.value = unit.company_id || '';
                    
                    let headEl = document.getElementById('edit_bu_head_employee_id');
                    if (headEl) headEl.value = unit.head_employee_id || '';
                    
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
