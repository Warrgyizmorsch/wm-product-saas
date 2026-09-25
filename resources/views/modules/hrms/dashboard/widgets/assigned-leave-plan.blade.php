@php
    $leaveTypes = !empty($myLeaveTypesList) && count($myLeaveTypesList) > 0 ? $myLeaveTypesList : [
        ['name' => 'Casual Leave', 'code' => 'CL', 'remaining' => 12, 'allocated' => 12, 'color' => '#3b82f6'],
        ['name' => 'Sick Leave', 'code' => 'SL', 'remaining' => 12, 'allocated' => 12, 'color' => '#3b82f6'],
        ['name' => 'Earned Leave', 'code' => 'EL', 'remaining' => 18, 'allocated' => 18, 'color' => '#3b82f6'],
        ['name' => 'Unpaid Leave', 'code' => 'UL', 'remaining' => 0, 'allocated' => 0, 'color' => '#3b82f6'],
    ];
@endphp

<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-body p-3.5 p-md-4 d-flex flex-column justify-content-between h-100">
        <div>
            <!-- Header: Assigned Leave Plan -->
            <div class="d-flex align-items-center gap-2.5 mb-2.5" style="gap: 10px;">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; background: #eef2ff; border-radius: 8px; color: #4f46e5;">
                    <i class="feather-calendar fs-14"></i>
                </div>
                <h6 class="fw-bold mb-0 text-dark" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">Assigned Leave Plan</h6>
            </div>

            <!-- Standard Leave Plan Banner -->
            <div class="p-2.5 p-md-3 rounded-3 mb-2.5" style="background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px;">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-bold text-dark" style="font-size: 14.5px; color: #1e293b !important; font-weight: 700;">{{ $myAssignedPlan->name ?? 'Standard Leave Plan' }}</span>
                    <span class="badge px-2.5 py-1 fs-11 fw-bold rounded-2" style="background: #dcfce7; color: #15803d;">Active</span>
                </div>
                <span class="fs-12 text-muted d-block" style="color: #64748b !important; font-weight: 400;">{{ $myAssignedPlan->description ?? 'Regular corporate leave plan' }}</span>
            </div>

            <!-- Effective From Header -->
            <div class="d-flex align-items-center justify-content-between fs-11 text-muted fw-bold mt-2.5 mb-2">
                <span class="text-uppercase" style="letter-spacing: 0.05em; color: #64748b !important; font-weight: 700; font-size: 11px;">EFFECTIVE FROM</span>
                <span class="text-dark fw-bold" style="color: #1e293b !important; font-weight: 700; font-size: 12.5px;">{{ date('d M, Y') }}</span>
            </div>

            <!-- Table Header & List -->
            <div class="mb-2">
                <!-- Header row -->
                <div class="d-flex align-items-center justify-content-between text-uppercase fs-11 text-muted fw-bold pb-1.5 mb-1.5 border-bottom" style="color: #64748b !important; font-weight: 700; letter-spacing: 0.05em; font-size: 11px; border-color: #f1f5f9 !important;">
                    <div style="flex: 2;">TYPE NAME</div>
                    <div class="text-center" style="flex: 1;">BALANCE</div>
                    <div class="text-end" style="width: 48px;">RULES</div>
                </div>

                <!-- Rows -->
                <div class="d-flex flex-column gap-1.5 pt-0.5">
                    @foreach($leaveTypes as $index => $lt)
                        @php
                            $ltName = is_array($lt) ? ($lt['name'] ?? 'Leave Type') : ($lt->name ?? 'Leave Type');
                            $ltCode = is_array($lt) ? ($lt['code'] ?? '') : ($lt->code ?? '');
                            $ltRem = is_array($lt) ? ($lt['remaining'] ?? 0) : ($lt->remaining ?? 0);
                            $ltAlloc = is_array($lt) ? ($lt['allocated'] ?? 0) : ($lt->allocated ?? 0);
                            $ltColor = is_array($lt) ? ($lt['color'] ?? '#3b82f6') : ($lt->color ?? '#3b82f6');
                            $modalId = "leaveRulesModal_" . $index;
                        @endphp
                        <div class="d-flex align-items-center justify-content-between py-1 border-bottom" style="border-color: #f8fafc;">
                            <!-- Type Name & Code -->
                            <div class="d-flex align-items-center" style="flex: 2;">
                                <span class="d-inline-block rounded-circle me-2 flex-shrink-0" style="width: 8px; height: 8px; background-color: {{ $ltColor }};"></span>
                                <span class="fw-bold text-dark fs-13" style="color: #1e293b !important; font-weight: 700;">{{ $ltName }}</span>
                                <span class="fs-11 text-muted fw-normal ms-1" style="color: #94a3b8 !important; font-weight: 400;">{{ $ltCode }}</span>
                            </div>

                            <!-- Balance -->
                            <div class="text-center fw-bold text-dark fs-13" style="flex: 1; color: #1e293b !important; font-weight: 700;">
                                {{ $ltRem }} / {{ $ltAlloc }}
                            </div>

                            <!-- Rules Button -->
                            <div class="text-end" style="width: 48px;">
                                <button type="button" class="btn border-0 p-0 d-inline-flex align-items-center justify-content-center shadow-2xs" 
                                        style="width: 28px; height: 28px; background: #f1f5f9; color: #475569; border-radius: 8px;" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#{{ $modalId }}" 
                                        onclick="var m=document.getElementById('{{ $modalId }}'); if(m && m.parentElement!==document.body){document.body.appendChild(m);}"
                                        title="View {{ $ltName }} Rules">
                                    <i class="feather-sliders fs-12"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex mt-auto pt-2" style="gap: 12px !important;">
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.leaves.index') ? route('hrms.leaves.index', ['action' => 'apply']) : url('/hrms/leaves?action=apply') }}" class="btn text-white fw-bold py-2 flex-fill text-uppercase fs-11 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-2xs" style="background: #4a3838 !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 11px !important; letter-spacing: 0.03em !important;">
                <i class="feather-plus fs-12"></i> APPLY LEAVE
            </a>
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.leaves.index') ? route('hrms.leaves.index', ['tab' => 'encashments', 'action' => 'encash']) : url('/hrms/leaves?tab=encashments&action=encash') }}" class="btn text-white fw-bold py-2 flex-fill text-uppercase fs-11 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-2xs" style="background: #4a3838 !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 11px !important; letter-spacing: 0.03em !important;">
                <span class="fw-bold fs-12 me-1">$</span> ENCASHMENT
            </a>
        </div>
    </div>
</div>

<!-- Modals rendered for Leave Category Rules (matching design of Image 2) -->
@foreach($leaveTypes as $index => $lt)
    @php
        $ltName = is_array($lt) ? ($lt['name'] ?? 'Leave Category') : ($lt->name ?? 'Leave Category');
        $ltCode = is_array($lt) ? ($lt['code'] ?? '') : ($lt->code ?? '');
        $ltRem = is_array($lt) ? ($lt['remaining'] ?? 0) : ($lt->remaining ?? 0);
        $ltAlloc = is_array($lt) ? ($lt['allocated'] ?? 0) : ($lt->allocated ?? 0);
        $ltRules = is_array($lt) ? ($lt['rules'] ?? []) : ($lt->rules ?? []);
        if (is_string($ltRules)) {
            $ltRules = json_decode($ltRules, true) ?: [];
        }

        // Accrual & Quota Rules
        $calcUnit = $ltRules['accrual']['unit'] ?? 'DAYS';
        $accrualRate = $ltRules['accrual']['rate'] ?? 'Immediate (Full Year)';
        $prorated = isset($ltRules['accrual']['prorated']) ? ($ltRules['accrual']['prorated'] ? 'Yes' : 'No') : 'Yes';
        $maxAcc = $ltRules['accrual']['max_accumulation'] ?? ($ltAlloc > 0 ? ($ltAlloc * 2) . ' Days' : '30 Days');

        // Application & Duration Limits
        $minDur = $ltRules['application']['min_duration'] ?? '1 Day(s)';
        $maxDur = $ltRules['application']['max_duration'] ?? ($ltAlloc > 0 ? "{$ltAlloc} Day(s)" : '10 Day(s)');
        $advNotice = $ltRules['application']['advance_notice'] ?? 'None';
        $medicalAtt = isset($ltRules['attachment']['require_attachment']) 
            ? ($ltRules['attachment']['require_attachment'] ? 'Required' : 'Not Required')
            : ($ltRules['application']['attachment'] ?? 'Not Required');

        // Year-End Settlement
        $yearendAction = isset($ltRules['yearend']['action']) ? ucfirst($ltRules['yearend']['action']) . ' at year end' : 'Lapse at year end';
        $maxCarry = isset($ltRules['yearend']['max_carry']) ? $ltRules['yearend']['max_carry'] . ' Days' : '6 Days';
        $maxEncash = isset($ltRules['yearend']['max_encash']) ? $ltRules['yearend']['max_encash'] . ' Days' : '5 Days';

        // Eligibility & Approval
        $probationRule = isset($ltRules['probation']['probation_rule']) ? ucfirst($ltRules['probation']['probation_rule']) : 'Allowed';
        $noticeRule = isset($ltRules['notice']['notice_rule']) ? ucfirst($ltRules['notice']['notice_rule']) : 'Allowed';
        $workflowLevel = isset($ltRules['approval']['workflow_level']) 
            ? ($ltRules['approval']['workflow_level'] === '2_level' ? '2-Level Approval (HR & Manager)' : '1-Level Approval (Reporting Manager)')
            : '1-Level Approval (Reporting Manager)';

        $modalId = "leaveRulesModal_" . $index;
    @endphp
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true" style="z-index: 1060 !important; filter: none !important; -webkit-filter: none !important; backdrop-filter: none !important;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; background: #ffffff;">
                <!-- Modal Header -->
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; background: #eef2ff; border-radius: 10px; color: #4f46e5;">
                            <i class="feather-sliders fs-16"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark fs-16 mb-0" id="{{ $modalId }}Label">{{ $ltName }} ({{ $ltCode }})</h5>
                            <span class="fs-12 text-muted">Policy rules, limits & eligibility configured for this leave category</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Modal Body: 2x2 Grid of 4 Cards -->
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Card 1: Accrual & Quota Rules -->
                        <div class="col-md-6">
                            <div class="card border shadow-none h-100" style="border-radius: 12px; border-color: #e2e8f0 !important; background: #ffffff;">
                                <div class="card-body p-3.5">
                                    <h6 class="fw-bold text-dark fs-14 mb-3 d-flex align-items-center">
                                        <i class="feather-clock text-primary me-2 fs-15"></i>Accrual & Quota Rules
                                    </h6>
                                    <div class="d-flex flex-column gap-2 fs-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Annual Quota (Quantity):</span>
                                            <span class="fw-bold text-dark">{{ $ltAlloc }} Days / Year</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Calculation Unit:</span>
                                            <span class="fw-bold text-dark">{{ $calcUnit }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Accrual Rate & Frequency:</span>
                                            <span class="fw-bold text-dark">{{ $accrualRate }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Prorated on Join:</span>
                                            <span class="fw-bold text-dark">{{ $prorated }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Max Accumulation Limit:</span>
                                            <span class="fw-bold text-dark">{{ $maxAcc }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Application & Duration Limits -->
                        <div class="col-md-6">
                            <div class="card border shadow-none h-100" style="border-radius: 12px; border-color: #e2e8f0 !important; background: #ffffff;">
                                <div class="card-body p-3.5">
                                    <h6 class="fw-bold text-dark fs-14 mb-3 d-flex align-items-center">
                                        <i class="feather-file-text text-info me-2 fs-15"></i>Application & Duration Limits
                                    </h6>
                                    <div class="d-flex flex-column gap-2 fs-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Minimum Duration:</span>
                                            <span class="fw-bold text-dark">{{ $minDur }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Maximum Duration:</span>
                                            <span class="fw-bold text-dark">{{ $maxDur }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Advance Notice Required:</span>
                                            <span class="fw-bold text-dark">{{ $advNotice }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Medical Attachment:</span>
                                            <span class="fw-bold text-dark">{{ $medicalAtt }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Year-End Settlement -->
                        <div class="col-md-6">
                            <div class="card border shadow-none h-100" style="border-radius: 12px; border-color: #e2e8f0 !important; background: #ffffff;">
                                <div class="card-body p-3.5">
                                    <h6 class="fw-bold text-dark fs-14 mb-3 d-flex align-items-center">
                                        <i class="feather-refresh-cw text-success me-2 fs-15"></i>Year-End Settlement
                                    </h6>
                                    <div class="d-flex flex-column gap-2 fs-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Year-End Action:</span>
                                            <span class="fw-bold text-dark">{{ $yearendAction }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Max Carry Forward:</span>
                                            <span class="fw-bold text-dark">{{ $maxCarry }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Max Encashable Qty:</span>
                                            <span class="fw-bold text-dark">{{ $maxEncash }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 4: Eligibility & Approval -->
                        <div class="col-md-6">
                            <div class="card border shadow-none h-100" style="border-radius: 12px; border-color: #e2e8f0 !important; background: #ffffff;">
                                <div class="card-body p-3.5">
                                    <h6 class="fw-bold text-dark fs-14 mb-3 d-flex align-items-center">
                                        <i class="feather-check-circle text-warning me-2 fs-15"></i>Eligibility & Approval
                                    </h6>
                                    <div class="d-flex flex-column gap-2 fs-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Probation Period Rule:</span>
                                            <span class="fw-bold text-dark">{{ $probationRule }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Notice Period Rule:</span>
                                            <span class="fw-bold text-dark">{{ $noticeRule }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Approval Workflow:</span>
                                            <span class="fw-bold text-primary">{{ $workflowLevel }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer border-top-0 pt-2 pb-4 px-4">
                    <button type="button" class="btn text-white fw-bold px-4 py-2 text-uppercase fs-12 rounded-2" style="background: #334155; border: none;" data-bs-dismiss="modal">CLOSE</button>
                </div>
            </div>
        </div>
    </div>
@endforeach

<script>
    (function() {
        function relocateModals() {
            var modals = document.querySelectorAll('[id^="leaveRulesModal_"]');
            modals.forEach(function(modal) {
                if (modal && modal.parentElement !== document.body) {
                    document.body.appendChild(modal);
                }
            });
        }

        relocateModals();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', relocateModals);
        }
        window.addEventListener('load', relocateModals);

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-bs-target^="#leaveRulesModal_"]');
            if (btn) {
                var targetId = btn.getAttribute('data-bs-target');
                var modalEl = document.querySelector(targetId);
                if (modalEl && modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }
            }
        }, true);

        document.addEventListener('show.bs.modal', function(e) {
            if (e.target && e.target.id && e.target.id.indexOf('leaveRulesModal_') !== -1) {
                if (e.target.parentElement !== document.body) {
                    document.body.appendChild(e.target);
                }
                e.target.style.zIndex = '1060';
                setTimeout(function() {
                    var backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(function(b) {
                        b.style.zIndex = '1050';
                    });
                }, 10);
            }
        }, true);
    })();
</script>


