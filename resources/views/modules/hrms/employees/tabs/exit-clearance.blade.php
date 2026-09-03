<div class="tab-pane fade {{ $activeTabName === 'exit-clearance' ? 'show active' : '' }}" id="exit-clearance-pane" role="tabpanel" aria-labelledby="exit-clearance-tab">
    @php
        $activeExit = $employee->activeExit;
        $totalItems = $activeExit ? $activeExit->clearances->count() : 0;
        $clearedItems = $activeExit ? $activeExit->clearances->whereIn('status', ['cleared', 'waived'])->count() : 0;
        $progress = $activeExit ? $activeExit->getClearanceProgressPercentage() : 0;
        $isSettled = $activeExit && ($activeExit->status === 'settled' || ($activeExit->fnfSettlement->status ?? '') === 'paid');

        $deptMeta = [
            'it'      => ['name' => 'IT & Systems Clearance', 'icon' => 'feather-monitor', 'color' => 'primary'],
            'admin'   => ['name' => 'Admin & Facilities Clearance', 'icon' => 'feather-briefcase', 'color' => 'warning'],
            'finance' => ['name' => 'Finance & Accounts Clearance', 'icon' => 'feather-dollar-sign', 'color' => 'success'],
            'hr'      => ['name' => 'Human Resources & Policy', 'icon' => 'feather-users', 'color' => 'info'],
            'manager' => ['name' => 'Line Management & KT Handover', 'icon' => 'feather-user-check', 'color' => 'secondary'],
        ];

        $groupedClearances = $activeExit ? $activeExit->clearances->groupBy('department') : collect();
    @endphp

    @if(!$activeExit)
        <!-- Empty State: No Resignation / Active Employee -->
        <div class="card-custom mb-4">
            <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="card-custom-title mb-0">
                        <i class="feather-log-out text-primary me-2"></i> Exit & Separation Status
                    </h5>
                    <span class="text-muted fs-12">Track separation requests, multi-department clearances, and final relieving documents.</span>
                </div>
                <div>
                    <x-ui.button variant="primary" size="sm" icon="feather-user-minus" data-bs-toggle="modal" data-bs-target="#profileExitModal" class="fw-semibold">
                        Apply for Resignation / Exit
                    </x-ui.button>
                </div>
            </div>
            <div class="card-body p-5 text-center">
                <div class="avatar-text avatar-lg bg-soft-success text-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                    <i class="feather-shield fs-24"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Active Employee in Good Standing</h5>
                <p class="text-muted fs-13 mb-0 mx-auto" style="max-width: 480px;">
                    No resignation, exit request, or separation process is currently active for <strong>{{ $employee->full_name }}</strong>.
                </p>
            </div>
        </div>
    @else
        <!-- Active Exit Tracking Dashboard -->
        <div class="card-custom mb-4">
            <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="card-custom-title mb-0">
                        <i class="feather-log-out text-primary me-2"></i> Exit & Separation Status
                    </h5>
                    <span class="text-muted fs-12">
                        Separation: <strong class="text-dark">{{ ucfirst(str_replace('_', ' ', $activeExit->separation_type)) }}</strong> &bull; Reason: {{ $activeExit->reason_category }}
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($isSettled)
                        <x-ui.badge soft variant="success" class="fs-12 px-3 py-1.5 fw-semibold">
                            <i class="feather-check-circle me-1"></i> Full & Final Settled
                        </x-ui.badge>
                    @elseif($progress === 100)
                        <x-ui.badge soft variant="primary" class="fs-12 px-3 py-1.5 fw-semibold">
                            <i class="feather-check me-1"></i> 100% Cleared (Ready for FnF)
                        </x-ui.badge>
                    @else
                        <x-ui.badge soft variant="warning" class="fs-12 px-3 py-1.5 fw-semibold">
                            <i class="feather-shield me-1"></i> In Clearance ({{ $progress }}%)
                        </x-ui.badge>
                    @endif
                </div>
            </div>

            <div class="card-body p-4">
                <!-- 1. Key Overview Metrics Cards Row -->
                <div class="row g-3 mb-4">
                    <!-- Card 1: Resignation Date -->
                    <div class="col-md-3 col-sm-6">
                        <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                            <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                                <i class="feather-calendar text-primary me-1"></i> Resignation Date
                            </span>
                            <strong class="fs-14 text-dark">{{ $activeExit->resignation_date ? \Carbon\Carbon::parse($activeExit->resignation_date)->format('d M, Y') : 'N/A' }}</strong>
                        </div>
                    </div>

                    <!-- Card 2: Last Working Day -->
                    <div class="col-md-3 col-sm-6">
                        <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                            <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                                <i class="feather-flag text-danger me-1"></i> Last Working Day (LWD)
                            </span>
                            <strong class="fs-14 text-danger">{{ $activeExit->effective_lwd ? \Carbon\Carbon::parse($activeExit->effective_lwd)->format('d M, Y') : 'TBD' }}</strong>
                        </div>
                    </div>

                    <!-- Card 3: Notice Period -->
                    <div class="col-md-3 col-sm-6">
                        <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                            <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                                <i class="feather-file-text text-warning me-1"></i> Notice Period
                            </span>
                            <div>
                                <strong class="fs-14 text-dark">{{ $activeExit->notice_period_days }} Days</strong>
                                @if($activeExit->notice_shortfall_days > 0)
                                    <span class="text-danger fs-11 ms-1">({{ $activeExit->notice_shortfall_days }}d shortfall)</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Card 4: Clearance Progress -->
                    <div class="col-md-3 col-sm-6">
                        <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider">
                                    <i class="feather-check-circle text-success me-1"></i> Clearance Progress
                                </span>
                                <span class="fs-12 fw-bold text-primary">{{ $progress }}%</span>
                            </div>
                            <div>
                                <div class="progress mb-1.5" style="height: 6px; background-color: #e2e8f0;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min(100, $progress) }}%" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="text-muted fs-11">{{ $clearedItems }} of {{ $totalItems }} Checkpoints Cleared</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Multi-Department Clearance & NOC Checklist Grouped by Department -->
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="feather-shield text-primary me-1.5"></i> Multi-Department Clearance & NOC Checklists
                    </h6>
                    <span class="badge bg-light text-secondary border fs-11">
                        {{ $clearedItems }} / {{ $totalItems }} Cleared ({{ $groupedClearances->count() }} Departments)
                    </span>
                </div>

                <div class="row g-3 mb-4">
                    @foreach($groupedClearances as $deptKey => $items)
                        @php
                            $meta = $deptMeta[$deptKey] ?? ['name' => ucfirst($deptKey) . ' Clearance', 'icon' => 'feather-folder', 'color' => 'secondary'];
                            $deptCleared = $items->whereIn('status', ['cleared', 'waived'])->count();
                            $deptTotal = $items->count();
                            $isDeptDone = $deptCleared === $deptTotal && $deptTotal > 0;
                        @endphp
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-white h-100 shadow-none d-flex flex-column justify-content-between">
                                <div>
                                    <!-- Department Header -->
                                    <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-text avatar-xs bg-soft-{{ $meta['color'] }} text-{{ $meta['color'] }} rounded-circle d-flex align-items-center justify-content-center" style="width: 26px; height: 26px; font-size: 11px;">
                                                <i class="{{ $meta['icon'] }}"></i>
                                            </div>
                                            <strong class="fs-13 text-dark">{{ $meta['name'] }}</strong>
                                        </div>
                                        <div>
                                            @if($isDeptDone)
                                                <x-ui.badge soft variant="success" class="fs-10 px-2 py-0.5">
                                                    <i class="feather-check me-0.5"></i> Cleared
                                                </x-ui.badge>
                                            @else
                                                <span class="text-muted fs-11 fw-semibold">{{ $deptCleared }}/{{ $deptTotal }} Done</span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Department Items List -->
                                    <div class="d-flex flex-column gap-2">
                                        @foreach($items as $item)
                                            <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light bg-opacity-50">
                                                <div class="me-2" style="max-width: 70%;">
                                                    <div class="fs-12 fw-semibold text-dark">{{ $item->item_name }}</div>
                                                    @if($item->remarks)
                                                        <div class="fs-11 text-muted">{{ $item->remarks }}</div>
                                                    @endif
                                                </div>
                                                <div class="text-end">
                                                    @if($item->status === 'cleared')
                                                        <x-ui.badge soft variant="success" class="fs-10 px-2 py-0.5">Cleared</x-ui.badge>
                                                    @elseif($item->status === 'waived')
                                                        <x-ui.badge soft variant="info" class="fs-10 px-2 py-0.5">Waived</x-ui.badge>
                                                    @elseif($item->status === 'issues_found')
                                                        <x-ui.badge soft variant="danger" class="fs-10 px-2 py-0.5">Dues: ${{ number_format($item->deduction_amount, 2) }}</x-ui.badge>
                                                    @else
                                                        <x-ui.badge soft variant="warning" class="fs-10 px-2 py-0.5">Pending</x-ui.badge>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- 4. Generated Official Exit Documents (Active / Settled Exits) -->
                @if($isSettled || $activeExit->documents->count() > 0)
                    <div class="p-3.5 bg-light rounded-3 border">
                        <h6 class="fw-bold text-dark mb-2">
                            <i class="feather-file-text text-primary me-1.5"></i> Official Relieving Certificates & Documents
                        </h6>
                        <p class="text-muted fs-12 mb-3">Official separation letters generated and signed off for this employee.</p>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <!-- 1. Relieving Letter -->
                            <a href="{{ route('hrms.exits.relieving-letter.view', $activeExit->id) }}" target="_blank" class="btn-doc-card" title="Open & Print Relieving Letter">
                                <i class="feather-file-text text-primary"></i>
                                <span>Relieving Letter</span>
                            </a>

                            <!-- 2. Experience Certificate -->
                            <a href="{{ route('hrms.exits.experience-certificate.view', $activeExit->id) }}" target="_blank" class="btn-doc-card" title="Open & Print Experience Certificate">
                                <i class="feather-award text-success"></i>
                                <span>Experience Certificate</span>
                            </a>

                            <!-- 3. NOC Certificate -->
                            <a href="{{ route('hrms.exits.noc-certificate.view', $activeExit->id) }}" target="_blank" class="btn-doc-card" title="Open & Print Clearance NOC">
                                <i class="feather-shield text-info"></i>
                                <span>NOC Certificate</span>
                            </a>

                            <!-- 4. FnF Statement -->
                            <a href="{{ route('hrms.exits.fnf-statement.view', $activeExit->id) }}" target="_blank" class="btn-doc-card" title="Open & Print Full & Final Statement">
                                <i class="feather-dollar-sign text-secondary"></i>
                                <span>F&F Statement</span>
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

<!-- Modal: In-Profile Resignation & Exit Initiation -->
<div class="modal fade text-start" id="profileExitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-bottom p-4">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-1">
                        <i class="feather-user-minus text-primary me-2"></i>Initiate Exit / Resignation Request
                    </h5>
                    <p class="text-muted fs-13 mb-0">Employee: <strong>{{ $employee->full_name }}</strong> ({{ $employee->employee_id }})</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('hrms.exits.initiate') }}">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Separation Type <span class="text-danger">*</span></label>
                            <x-ui.odoo-form-ui type="select" name="separation_type" :required="true">
                                <option value="resignation" selected>Voluntary Resignation</option>
                                <option value="termination">Involuntary Termination</option>
                                <option value="retirement">Retirement</option>
                                <option value="layoff">Layoff / Restructuring</option>
                                <option value="contract_end">Contract End</option>
                                <option value="absconding">Absconding / Abandonment</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Reason Category <span class="text-danger">*</span></label>
                            <x-ui.odoo-form-ui type="select" name="reason_category" :required="true">
                                <option value="Career Growth" selected>Career Growth & Advancement</option>
                                <option value="Higher Studies">Higher Studies</option>
                                <option value="Personal / Family Reasons">Personal / Family Reasons</option>
                                <option value="Relocation">Relocation</option>
                                <option value="Health Reasons">Health Reasons</option>
                                <option value="Better Compensation">Better Compensation</option>
                                <option value="Role Fit / Restructuring">Role Fit / Restructuring</option>
                                <option value="Other">Other</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Resignation Date <span class="text-danger">*</span></label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="resignation_date" :value="date('Y-m-d')" :required="true" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Notice Period (Days) <span class="text-danger">*</span></label>
                            <x-ui.odoo-form-ui type="input" inputType="number" name="notice_period_days" value="30" :required="true" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Preferred Last Working Day</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="preferred_lwd" :value="\Carbon\Carbon::today()->addDays(30)->format('Y-m-d')" />
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Detailed Reason & Transition Remarks</label>
                        <textarea name="reason_details" class="form-control" rows="3" placeholder="Provide transition details, feedback, or justification for resignation / exit..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                    <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                        <i class="feather-check-circle me-1"></i> Submit Resignation & Initiate Exit
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>
