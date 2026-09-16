{{-- HRM Reference UI: patterns modeled on an external HRM product screenshot set.
     New reusable components used here: x-ui.icon-input, x-ui.status-pill-group,
     x-ui.stat-pill, x-ui.section-panel, x-ui.row-actions, x-ui.upload-dropdown.
     The top module navbar below is a static sandbox mock only — it is NOT wired
     into the real app shell (layouts/duralux.blade.php still drives navigation). --}}

<x-ui.card title="Top Module Navbar (sandbox mock only)" class="mb-4">
    <p class="fs-13 text-muted mb-3">A horizontal sub-navigation bar alternative to the left sidebar, for a module-scoped page. Static markup only — not wired to real routes.</p>
    <div class="d-flex align-items-center justify-content-between px-3 py-2 rounded-3" style="background:#0f172a;">
        <div class="d-flex align-items-center gap-4">
            <span class="fw-bold text-white fs-14">HRM</span>
            <nav class="d-flex align-items-center gap-3">
                @foreach (['Home', 'Employees', 'Payroll', 'Attendance History', 'Tasks', 'Leave', 'Projects', 'Support'] as $i => $navLabel)
                    <a href="#" class="fs-13 text-decoration-none px-1 pb-1 {{ $i === 3 ? 'text-white fw-semibold border-bottom border-2 border-primary' : 'text-white-50' }}">{{ $navLabel }}</a>
                @endforeach
            </nav>
        </div>
        <div class="d-flex align-items-center gap-3">
            <i class="feather-plus-circle text-white-50"></i>
            <i class="feather-bell text-white-50"></i>
            <span class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center">P</span>
        </div>
    </div>
</x-ui.card>

<x-ui.card title="Compact KPI Stat Strip" class="mb-4">
    <p class="fs-13 text-muted mb-3">x-ui.stat-pill — a lightweight horizontal stat, for a strip of related counters (e.g. Leave Allotment header).</p>
    <div class="d-flex flex-wrap gap-3">
        <x-ui.stat-pill icon="feather-users" value="40" label="Employees" color="primary" />
        <x-ui.stat-pill icon="feather-check-circle" value="31" label="Allotted entries" color="info" />
        <x-ui.stat-pill icon="feather-layers" value="47.0" label="Total days" color="teal" />
        <x-ui.stat-pill icon="feather-minus-circle" value="33.0" label="Used leaves" color="danger" />
        <x-ui.stat-pill icon="feather-check" value="98.5" label="Available balance" color="success" />
        <x-ui.stat-pill icon="feather-alert-circle" value="10.5" label="Salary deduction" color="warning" />
    </div>
</x-ui.card>

<x-ui.card title="Multi-Badge Status Cluster + Row Actions" class="mb-4">
    <p class="fs-13 text-muted mb-3">x-ui.status-pill-group for a per-row count summary (e.g. daily attendance), x-ui.row-actions for inline view/edit/delete icons.</p>
    <div class="table-responsive">
        <table class="table erp-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Attendance History</th>
                    <th class="text-center" style="width: 140px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-semibold text-dark">16 Sep 2026</td>
                    <td>
                        <x-ui.status-pill-group :items="[
                            ['label' => 'Present', 'value' => 32, 'variant' => 'success'],
                            ['label' => 'Overtime', 'value' => 0, 'variant' => 'info'],
                            ['label' => 'Half Day', 'value' => 0, 'variant' => 'warning'],
                            ['label' => 'Leave', 'value' => 2, 'variant' => 'danger'],
                            ['label' => 'Absent', 'value' => 8, 'variant' => 'danger'],
                            ['label' => 'WFH', 'value' => 1, 'variant' => 'indigo'],
                            ['label' => 'Missing Punch', 'value' => 31, 'variant' => 'danger'],
                        ]" />
                    </td>
                    <td>
                        <x-ui.row-actions view-url="#" edit-url="#" delete-url="#" />
                    </td>
                </tr>
                <tr>
                    <td class="fw-semibold text-dark">15 Sep 2026</td>
                    <td>
                        <x-ui.status-pill-group :items="[
                            ['label' => 'Present', 'value' => 24, 'variant' => 'success'],
                            ['label' => 'Half Day', 'value' => 12, 'variant' => 'warning'],
                            ['label' => 'Leave', 'value' => 1, 'variant' => 'danger'],
                            ['label' => 'Absent', 'value' => 7, 'variant' => 'danger'],
                            ['label' => 'WFH', 'value' => 1, 'variant' => 'indigo'],
                        ]" />
                    </td>
                    <td>
                        <x-ui.row-actions view-url="#" edit-url="#" delete-url="#" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</x-ui.card>

<x-ui.card title="Toolbar Upload-in-Dropdown Panel" class="mb-4">
    <p class="fs-13 text-muted mb-3">x-ui.upload-dropdown — a toolbar icon button that opens a small form panel (device/source select + file input + submit), instead of a full modal.</p>
    <div class="d-flex align-items-center gap-2">
        <x-ui.icon-btn variant="transparent-dark" icon="feather-users">Employee Wise</x-ui.icon-btn>
        <x-ui.icon-btn variant="transparent-dark" icon="feather-alert-triangle">Missing Punches</x-ui.icon-btn>
        <x-ui.icon-btn variant="transparent-dark" icon="feather-refresh-cw">Sync Punches</x-ui.icon-btn>
        <x-ui.upload-dropdown
            label="Import Excel/CSV"
            action="#"
            field-label="zk device (or manual codes)"
            :field-options="['zk1' => 'ZK Device 1', 'manual' => 'Manual Codes']"
            button-text="Upload & Calculate" />
        <x-ui.button variant="primary" icon="feather-plus">Add</x-ui.button>
    </div>
</x-ui.card>

<x-ui.card title="Icon-Prefixed Form Inputs in a Section Panel" class="mb-4">
    <p class="fs-13 text-muted mb-3">x-ui.section-panel groups related fields under an icon + title header; x-ui.icon-input pairs each field with a leading icon.</p>
    <div class="row g-4">
        <div class="col-md-6">
            <x-ui.section-panel title="Employee Information" icon="feather-user" color="primary">
                <x-ui.icon-input label="Employee Code" name="employee_code" icon="feather-hash" placeholder="Enter employee code" />
                <x-ui.icon-input label="Name" name="employee_name" icon="feather-user" placeholder="Enter employee name" required />
                <x-ui.icon-input label="Mobile Number" name="mobile" icon="feather-phone" placeholder="Enter mobile number" required />
                <x-ui.icon-input label="Designation" name="designation" icon="feather-briefcase" placeholder="Select designation" required />
            </x-ui.section-panel>
        </div>
        <div class="col-md-6">
            <x-ui.section-panel title="Identity & Contact" icon="feather-credit-card" color="teal">
                <x-ui.icon-input label="Aadhaar Number" name="aadhaar" icon="feather-image" placeholder="Enter Aadhaar number" />
                <x-ui.icon-input label="PAN Number" name="pan" icon="feather-credit-card" placeholder="E.G. ABCDE2548K" />
                <x-ui.icon-input label="Email" name="email" icon="feather-mail" type="email" placeholder="Enter email" />
            </x-ui.section-panel>
        </div>
    </div>
</x-ui.card>

<x-ui.card title="Slide-Over Detail Panel (existing x-ui.drawer)" class="mb-4">
    <p class="fs-13 text-muted mb-3">The reference "leave application details" side panel is the same pattern as the existing x-ui.drawer used elsewhere on this page — sectioned info cards inside an offcanvas.</p>
    <x-ui.button variant="light-brand" data-bs-toggle="offcanvas" data-bs-target="#hrmDetailDrawer">
        Preview Detail Panel
    </x-ui.button>
</x-ui.card>

<x-ui.drawer id="hrmDetailDrawer" title="Leave application details" position="end">
    <x-ui.horizontal-tabs id="hrmDetailTabs" :tabs="[
        ['id' => 'hrm-detail-current', 'label' => 'Current Application', 'active' => true],
        ['id' => 'hrm-detail-history', 'label' => 'Full History', 'active' => false],
    ]" />
    <div class="tab-content mt-3">
        <div class="tab-pane fade show active" id="hrm-detail-current">
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <span class="fs-11 text-uppercase text-muted d-block mb-1">Leave Info</span>
                    <span class="fw-semibold text-dark d-block mb-1">Half Day (Second Half)</span>
                    <x-ui.badge variant="success" soft>Approved</x-ui.badge>
                </div>
                <div class="col-6">
                    <span class="fs-11 text-uppercase text-muted d-block mb-1">Duration & Days</span>
                    <span class="fw-bold fs-18 text-primary">0.5 Days</span>
                </div>
            </div>
            <div class="mb-3">
                <span class="fs-11 text-uppercase text-muted d-block mb-1">Reason</span>
                <p class="fs-13 text-dark mb-0">Need half day for some top secret reason.</p>
            </div>
        </div>
        <div class="tab-pane fade" id="hrm-detail-history">
            <p class="fs-13 text-muted">Full leave history list goes here.</p>
        </div>
    </div>
</x-ui.drawer>
