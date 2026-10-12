<div class="tab-pane fade {{ $activeTabName === 'exit-clearance' ? 'show active' : '' }}" id="exit-clearance-pane" role="tabpanel" aria-labelledby="exit-clearance-tab">
    @php
        $activeExit = $employee->activeExit;
        $totalItems = $activeExit && $activeExit->clearances ? $activeExit->clearances->count() : 0;
        $clearedItems = $activeExit && $activeExit->clearances ? $activeExit->clearances->whereIn('status', ['cleared', 'waived'])->count() : 0;
        $reviewedItems = $activeExit && $activeExit->clearances ? $activeExit->clearances->where('status', '!=', 'pending')->count() : 0;
        $progress = $totalItems > 0 ? (int) round(($reviewedItems / $totalItems) * 100) : 0;
        $isSettled = $activeExit && ($activeExit->status === 'settled' || ($activeExit->fnfSettlement->status ?? '') === 'paid');

        $deptMeta = [
            'it'      => ['name' => __('hrms.exit_clearance.dept_it'), 'icon' => 'feather-monitor', 'color' => 'primary'],
            'admin'   => ['name' => __('hrms.exit_clearance.dept_admin'), 'icon' => 'feather-briefcase', 'color' => 'warning'],
            'finance' => ['name' => __('hrms.exit_clearance.dept_finance'), 'icon' => 'feather-dollar-sign', 'color' => 'success'],
            'hr'      => ['name' => __('hrms.exit_clearance.dept_hr'), 'icon' => 'feather-users', 'color' => 'info'],
            'manager' => ['name' => __('hrms.exit_clearance.dept_manager'), 'icon' => 'feather-user-check', 'color' => 'secondary'],
        ];

        $groupedClearances = ($activeExit && $activeExit->clearances) ? $activeExit->clearances->groupBy('department') : collect();
    @endphp

    @if(!$activeExit)
        <!-- Empty State: No Resignation / Active Employee -->
        <div class="card-custom mb-4">
            <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="card-custom-title mb-0">
                        <i class="feather-log-out text-primary me-2"></i> {{ __('hrms.exit_clearance.exit_separation_status') }}
                    </h5>
                    <span class="text-muted fs-12">{{ __('hrms.exit_clearance.exit_separation_desc') }}</span>
                </div>
                @php
                    $authUser = auth()->user();
                    $canInitiateExit = (isset($isOwnProfile) && $isOwnProfile) 
                        || ($authUser && ($authUser->employee?->id === $employee->id || \App\Domains\HRMS\Models\Employee::resolveForUser($authUser)?->id === $employee->id))
                        || ($authUser && app(\App\Services\Access\AccessService::class)->allows($authUser, 'hrms.employees.update', ['tenant_id' => $authUser->tenant_id]));
                @endphp
                @if($canInitiateExit)
                    <div>
                        <x-ui.button variant="primary" size="sm" icon="feather-user-minus" data-bs-toggle="modal" data-bs-target="#profileExitModal" class="fw-semibold">
                            {{ __('hrms.exit_clearance.apply_resignation_exit') }}
                        </x-ui.button>
                    </div>
                @endif
            </div>
            <div class="card-body p-5 text-center">
                <div class="avatar-text avatar-lg bg-soft-success text-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                    <i class="feather-shield fs-24"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">{{ __('hrms.exit_clearance.active_good_standing') }}</h5>
                <p class="text-muted fs-13 mb-0 mx-auto" style="max-width: 480px;">
                    {{ __('hrms.exit_clearance.no_exit_active_desc', ['name' => $employee->full_name]) }}
                </p>
            </div>
        </div>
    @else
        <!-- Active Exit Tracking Dashboard -->
        <div class="card-custom mb-4">
            <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="card-custom-title mb-0">
                        <i class="feather-log-out text-primary me-2"></i> {{ __('hrms.exit_clearance.exit_separation_status') }}
                    </h5>
                    <span class="text-muted fs-12">
                        {{ __('hrms.employees.lbl_stage') }}: <strong class="text-dark">{{ ucfirst(str_replace('_', ' ', (string) $activeExit->separation_type)) }}</strong> &bull; {{ __('hrms.wfh.reason') }}: {{ $activeExit->reason_category }}
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($isSettled)
                        <x-ui.badge soft variant="success" class="fs-12 px-3 py-1.5 fw-semibold">
                            <i class="feather-check-circle me-1"></i> {{ __('hrms.exit_clearance.full_final_settled') }}
                        </x-ui.badge>
                    @elseif($activeExit->status === 'pending_manager')
                        <x-ui.badge soft variant="warning" class="fs-12 px-3 py-1.5 fw-semibold">
                            <i class="feather-clock me-1"></i> {{ __('hrms.exit_clearance.pending_manager_approval') }}
                        </x-ui.badge>
                    @elseif($activeExit->status === 'pending_hr')
                        <x-ui.badge soft variant="info" class="fs-12 px-3 py-1.5 fw-semibold">
                            <i class="feather-clock me-1"></i> {{ __('hrms.exit_clearance.pending_hr_approval') }}
                        </x-ui.badge>
                    @elseif($progress === 100)
                        <x-ui.badge soft variant="primary" class="fs-12 px-3 py-1.5 fw-semibold">
                            <i class="feather-check me-1"></i> {{ __('hrms.exit_clearance.cleared_ready_fnf') }}
                        </x-ui.badge>
                    @else
                        <x-ui.badge soft variant="warning" class="fs-12 px-3 py-1.5 fw-semibold">
                            <i class="feather-shield me-1"></i> {{ __('hrms.exit_clearance.in_clearance', ['progress' => $progress]) }}
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
                                <i class="feather-calendar text-primary me-1"></i> {{ __('hrms.exit_clearance.resignation_date') }}
                            </span>
                            <strong class="fs-14 text-dark">{{ $activeExit->resignation_date ? \Carbon\Carbon::parse($activeExit->resignation_date)->format('d M, Y') : __('hrms.common.na') }}</strong>
                        </div>
                    </div>

                    <!-- Card 2: Last Working Day -->
                    <div class="col-md-3 col-sm-6">
                        <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                            <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                                <i class="feather-flag text-danger me-1"></i> {{ __('hrms.exit_clearance.last_working_day') }}
                            </span>
                            <strong class="fs-14 text-danger">{{ $activeExit->effective_lwd ? \Carbon\Carbon::parse($activeExit->effective_lwd)->format('d M, Y') : __('hrms.common.na') }}</strong>
                        </div>
                    </div>

                    <!-- Card 3: Notice Period -->
                    <div class="col-md-3 col-sm-6">
                        <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                            <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                                <i class="feather-file-text text-warning me-1"></i> {{ __('hrms.exit_clearance.notice_period') }}
                            </span>
                            <div>
                                <strong class="fs-14 text-dark">{{ $activeExit->notice_period_days ?? 0 }} {{ __('hrms.wfh.days_count') }}</strong>
                                @if(($activeExit->notice_shortfall_days ?? 0) > 0)
                                    <span class="text-danger fs-11 ms-1">({{ $activeExit->notice_shortfall_days }} {{ __('hrms.exit_clearance.days_shortfall') }})</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Card 4: Clearance Progress -->
                    <div class="col-md-3 col-sm-6">
                        <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider">
                                    <i class="feather-check-circle text-success me-1"></i> {{ __('hrms.exit_clearance.clearance_progress') }}
                                </span>
                                <span class="fs-12 fw-bold text-primary">{{ $progress }}%</span>
                            </div>
                            <div>
                                <x-ui.progress-bar :value="$progress" height="8px" :color="$progress === 100 ? 'success' : 'primary'" class="my-2" />
                                <span class="text-muted fs-11">{{ $clearedItems }} / {{ $totalItems }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Multi-Department Clearance & NOC Checklist Grouped by Department -->
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="feather-shield text-primary me-1.5"></i> {{ __('hrms.exit_clearance.multi_dept_clearance') }}
                    </h6>
                    <x-ui.badge soft variant="secondary" class="border fs-11">
                        {{ $clearedItems }} / {{ $totalItems }}
                    </x-ui.badge>
                </div>

                @php
                    $assignedAssets = $employee->assets ?? collect();
                    $hasItCard = $groupedClearances->has('it') || $groupedClearances->has('it_assets');
                @endphp

                @if($totalItems === 0)
                    <div class="p-4 bg-light rounded-3 border text-center mb-4">
                        <div class="avatar-text avatar-md bg-soft-info text-info rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center">
                            <i class="feather-clock fs-18"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">{{ __('hrms.exit_clearance.checklist_pending_approval') }}</h6>
                        <p class="text-muted fs-12 mb-0 mx-auto" style="max-width: 480px;">
                            {{ __('hrms.exit_clearance.checklist_pending_desc', ['approver' => $activeExit->status === 'pending_manager' ? __('hrms.offboarding_policies.dept_reporting_manager') : __('hrms.exit_clearance.hr_team')]) }}
                        </p>
                    </div>
                @endif

                <div class="row g-3 mb-4">
                    @foreach($groupedClearances as $deptKey => $items)
                        @php
                            $meta = $deptMeta[$deptKey] ?? \App\Domains\HRMS\Models\ExitClearanceTemplate::getCategoryMetadata($deptKey);
                            $deptCleared = $items->whereIn('status', ['cleared', 'waived'])->count();
                            $deptTotal = $items->count();
                            $isDeptDone = $deptCleared === $deptTotal && $deptTotal > 0;
                            $isItDept = in_array($deptKey, ['it', 'it_assets']);
                        @endphp
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-white h-100 shadow-none d-flex flex-column justify-content-between">
                                <div>
                                    <!-- Department Header -->
                                    <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-text avatar-xs bg-soft-{{ $meta['color'] ?? 'primary' }} text-{{ $meta['color'] ?? 'primary' }} rounded-circle d-flex align-items-center justify-content-center" style="width: 26px; height: 26px; font-size: 11px;">
                                                <i class="{{ $meta['icon'] ?? 'feather-check-circle' }}"></i>
                                            </div>
                                            <strong class="fs-13 text-dark">{{ $meta['name'] ?? ucfirst($deptKey) }}</strong>
                                        </div>
                                        <div>
                                            @if($isDeptDone)
                                                <x-ui.badge soft variant="success" class="fs-10 px-2 py-0.5">
                                                    <i class="feather-check me-0.5"></i> {{ __('hrms.exit_clearance.cleared') }}
                                                </x-ui.badge>
                                            @else
                                                <span class="text-muted fs-11 fw-semibold">{{ $deptCleared }}/{{ $deptTotal }} {{ __('hrms.exit_clearance.done') }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Department Items List -->
                                    <div class="d-flex flex-column gap-2 mb-2">
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
                                                        <x-ui.badge soft variant="success" class="fs-10 px-2 py-0.5">{{ __('hrms.exit_clearance.cleared') }}</x-ui.badge>
                                                    @elseif($item->status === 'waived')
                                                        <x-ui.badge soft variant="info" class="fs-10 px-2 py-0.5">{{ __('hrms.exit_clearance.waived') }}</x-ui.badge>
                                                    @elseif($item->status === 'issues_found')
                                                        <x-ui.badge soft variant="danger" class="fs-10 px-2 py-0.5">{{ __('hrms.exit_clearance.dues') }}: ₹{{ number_format((float) ($item->deduction_amount ?? 0), 2) }}</x-ui.badge>
                                                    @else
                                                        <x-ui.badge soft variant="warning" class="fs-10 px-2 py-0.5">{{ __('hrms.common.pending') }}</x-ui.badge>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <!-- Linked Hardware Inventory Assets (Inside IT / Systems Card) -->
                                    @if($isItDept)
                                        @if($assignedAssets->count() > 0)
                                            <div class="mt-2.5 pt-2.5 border-top">
                                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                                    <span class="fs-11 fw-bold text-uppercase text-muted">
                                                        <i class="feather-cpu me-1 text-primary"></i> {{ __('hrms.exit_clearance.assigned_hardware_assets', ['count' => $assignedAssets->count()]) }}
                                                    </span>
                                                </div>
                                                <div class="d-flex flex-column gap-1.5">
                                                    @foreach($assignedAssets as $ast)
                                                        <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border border-opacity-50">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <i class="feather-hard-drive text-muted fs-14"></i>
                                                                <div>
                                                                    <div class="fs-12 fw-bold text-dark">{{ $ast->name }}</div>
                                                                    <div class="fs-11 text-muted">
                                                                        <code>{{ $ast->asset_code ?? 'AST-#' . $ast->id }}</code>
                                                                        @if($ast->serial_number)
                                                                            &bull; SN: <span class="font-monospace">{{ $ast->serial_number }}</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                @if($ast->status === 'available' || $ast->status === 'returned')
                                                                    <x-ui.badge soft variant="success" class="fs-10 px-2 py-0.5"><i class="feather-check me-0.5"></i> {{ __('hrms.exit_clearance.asset_returned') }}</x-ui.badge>
                                                                @elseif($ast->status === 'lost' || $ast->status === 'damaged')
                                                                    <x-ui.badge soft variant="danger" class="fs-10 px-2 py-0.5"><i class="feather-alert-triangle me-0.5"></i> {{ ucfirst($ast->status) }}</x-ui.badge>
                                                                @else
                                                                    <x-ui.badge soft variant="warning" class="fs-10 px-2 py-0.5"><i class="feather-clock me-0.5"></i> {{ __('hrms.exit_clearance.asset_in_custody') }}</x-ui.badge>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @else
                                            <div class="mt-2 p-2 rounded-2 bg-light border border-dashed text-muted fs-11 d-flex align-items-center gap-1.5">
                                                <i class="feather-info text-info"></i> {{ __('hrms.exit_clearance.no_hardware_assets') }}
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <!-- Dynamic Fallback Card: If NO IT Card was configured in policy, but employee holds hardware assets -->
                    @if(!$hasItCard && $assignedAssets->count() > 0)
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-white h-100 shadow-none d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 26px; height: 26px; font-size: 11px;">
                                                <i class="feather-monitor"></i>
                                            </div>
                                            <strong class="fs-13 text-dark">{{ __('hrms.exit_clearance.it_systems_asset_recovery') }}</strong>
                                        </div>
                                        <x-ui.badge soft variant="warning" class="fs-10 px-2 py-0.5">
                                            {{ __('hrms.exit_clearance.assets_in_custody_count', ['count' => $assignedAssets->count()]) }}
                                        </x-ui.badge>
                                    </div>
                                    <div class="d-flex flex-column gap-1.5">
                                        @foreach($assignedAssets as $ast)
                                            <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border border-opacity-50">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="feather-hard-drive text-muted fs-14"></i>
                                                    <div>
                                                        <div class="fs-12 fw-bold text-dark">{{ $ast->name }}</div>
                                                        <div class="fs-11 text-muted">
                                                            <code>{{ $ast->asset_code ?? 'AST-#' . $ast->id }}</code>
                                                            @if($ast->serial_number)
                                                                &bull; SN: <span class="font-monospace">{{ $ast->serial_number }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <x-ui.badge soft variant="warning" class="fs-10 px-2 py-0.5"><i class="feather-clock me-0.5"></i> {{ __('hrms.exit_clearance.asset_in_custody') }}</x-ui.badge>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- 4. Generated Official Exit Documents (Active 100% Cleared / Settled Exits) -->
                @if($progress === 100 || $isSettled || ($activeExit->documents ? $activeExit->documents->count() > 0 : false))
                    <div class="p-3.5 bg-light rounded-3 border">
                        <h6 class="fw-bold text-dark mb-2">
                            <i class="feather-file-text text-primary me-1.5"></i> {{ __('hrms.exit_clearance.official_relieving_docs') }}
                        </h6>
                        <p class="text-muted fs-12 mb-3">{{ __('hrms.exit_clearance.official_relieving_docs_desc') }}</p>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <!-- 1. Relieving Letter -->
                            <x-ui.button variant="light" size="sm" icon="feather-file-text" href="{{ route('hrms.exits.relieving-letter.view', $activeExit->id) }}" target="_blank" class="border text-dark fw-medium fs-12 shadow-2xs text-nowrap py-1.5 px-3">
                                {{ __('hrms.exit_clearance.relieving_letter') }}
                            </x-ui.button>

                            <!-- 2. Experience Certificate -->
                            <x-ui.button variant="light" size="sm" icon="feather-award" href="{{ route('hrms.exits.experience-certificate.view', $activeExit->id) }}" target="_blank" class="border text-dark fw-medium fs-12 shadow-2xs text-nowrap py-1.5 px-3">
                                {{ __('hrms.exit_clearance.experience_certificate') }}
                            </x-ui.button>

                            <!-- 3. NOC Certificate -->
                            <x-ui.button variant="light" size="sm" icon="feather-shield" href="{{ route('hrms.exits.noc-certificate.view', $activeExit->id) }}" target="_blank" class="border text-dark fw-medium fs-12 shadow-2xs text-nowrap py-1.5 px-3">
                                {{ __('hrms.exit_clearance.noc_certificate') }}
                            </x-ui.button>

                            <!-- 4. FnF Statement -->
                            <x-ui.button variant="light" size="sm" icon="feather-dollar-sign" href="{{ route('hrms.exits.fnf-statement.view', $activeExit->id) }}" target="_blank" class="border text-dark fw-medium fs-12 shadow-2xs text-nowrap py-1.5 px-3">
                                {{ __('hrms.exit_clearance.fnf_statement') }}
                            </x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

<!-- Modal: In-Profile Resignation & Exit Initiation -->
<x-ui.modal 
    id="profileExitModal" 
    title="<div class='d-flex align-items-center gap-2'><div class='avatar-text avatar-xs bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center'><i class='feather-user-minus fs-12'></i></div><div><span class='fw-bold text-dark d-block fs-14'>{{ __('hrms.exit_clearance.initiate_exit_title') }}</span><span class='text-muted fs-11 fw-normal'>{{ __('hrms.employees.lbl_employee') }}: <strong>{{ $employee->full_name }}</strong> ({{ $employee->employee_id }})</span></div></div>" 
    size="lg" 
    formAction="{{ route('hrms.exits.initiate') }}" 
    formMethod="POST"
    :showFooter="true"
>
    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
    <input type="hidden" name="initiated_by" value="employee">
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <x-ui.modal-form-ui type="select" :label="__('hrms.exit_clearance.separation_type')" name="separation_type" :required="true" :searchable="false">
                <option value="resignation" selected>{{ __('hrms.exits.resignation') }}</option>
                <option value="termination">{{ __('hrms.exits.termination') }}</option>
                <option value="retirement">{{ __('hrms.exits.retirement') }}</option>
                <option value="layoff">{{ __('hrms.exits.layoff') }}</option>
                <option value="contract_end">{{ __('hrms.exits.contract_end') }}</option>
                <option value="absconding">{{ __('hrms.exits.absconding') }}</option>
            </x-ui.modal-form-ui>
        </div>
        <div class="col-md-6">
            <x-ui.modal-form-ui type="select" :label="__('hrms.exit_clearance.reason_category')" name="reason_category" :required="true" :searchable="false">
                <option value="Career Growth" selected>{{ __('hrms.exits.reason_career_growth') }}</option>
                <option value="Higher Studies">{{ __('hrms.exits.reason_higher_studies') }}</option>
                <option value="Personal / Family Reasons">{{ __('hrms.exits.reason_personal') }}</option>
                <option value="Relocation">{{ __('hrms.exits.reason_relocation') }}</option>
                <option value="Health Reasons">{{ __('hrms.exits.reason_health') }}</option>
                <option value="Better Compensation">{{ __('hrms.exits.reason_compensation') }}</option>
                <option value="Role Fit / Restructuring">{{ __('hrms.exits.reason_restructuring') }}</option>
                <option value="Other">{{ __('hrms.common.other') }}</option>
            </x-ui.modal-form-ui>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <x-ui.modal-form-ui type="input" inputType="date" :label="__('hrms.exit_clearance.resignation_date')" name="resignation_date" id="profile_resignation_date" :value="date('Y-m-d')" :required="true" />
        </div>
        <div class="col-md-4">
            <x-ui.modal-form-ui type="input" inputType="number" :label="__('hrms.exit_clearance.notice_period_days')" name="notice_period_days" id="profile_notice_period_days" value="30" :required="true" />
        </div>
        <div class="col-md-4">
            <x-ui.modal-form-ui type="input" inputType="date" :label="__('hrms.exit_clearance.preferred_lwd')" name="preferred_lwd" id="profile_preferred_lwd" :value="\Carbon\Carbon::today()->addDays(30)->format('Y-m-d')" />
        </div>
    </div>

    <div class="mb-2">
        <x-ui.modal-form-ui type="textarea" :label="__('hrms.exit_clearance.reason_details')" name="reason_details" rows="3" placeholder="{{ __('hrms.exit_clearance.reason_details') }}..." />
    </div>

    <x-slot:footer>
        <div class="d-flex justify-content-between align-items-center w-100">
            <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                <i class="feather-check-circle me-1"></i> {{ __('hrms.exit_clearance.btn_submit_exit') }}
            </x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.modal>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const resDateInput = document.getElementById('profile_resignation_date');
        const noticeDaysInput = document.getElementById('profile_notice_period_days');
        const preferredLwdInput = document.getElementById('profile_preferred_lwd');

        function syncProfilePreferredLwd() {
            if (!resDateInput || !noticeDaysInput || !preferredLwdInput) return;
            const resDateVal = resDateInput.value;
            const noticeDaysVal = parseInt(noticeDaysInput.value) || 0;
            if (resDateVal) {
                const resDate = new Date(resDateVal);
                if (!isNaN(resDate.getTime())) {
                    resDate.setDate(resDate.getDate() + noticeDaysVal);
                    const yyyy = resDate.getFullYear();
                    const mm = String(resDate.getMonth() + 1).padStart(2, '0');
                    const dd = String(resDate.getDate()).padStart(2, '0');
                    preferredLwdInput.value = `${yyyy}-${mm}-${dd}`;
                }
            }
        }

        if (resDateInput) resDateInput.addEventListener('change', syncProfilePreferredLwd);
        if (noticeDaysInput) noticeDaysInput.addEventListener('input', syncProfilePreferredLwd);
    });
</script>
