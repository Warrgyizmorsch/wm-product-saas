<div class="tab-pane fade {{ in_array($activeTabName, ['penalization', 'penalties']) ? 'show active' : '' }}" id="penalization-pane" role="tabpanel" aria-labelledby="penalization-tab">
    <div class="row g-4">
        <!-- Left: Applied Penalties Log -->
        <div class="col-lg-8 col-12">
            <div class="card-custom">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-list text-primary"></i> {{ __('hrms.employees.lbl_penalization_history') }}</h5>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#addPenaltyModal" @disabled(!isset($attendancePenalty) || !$attendancePenalty)>
                        {{ __('hrms.employees.btn_log_instance') }}
                    </button>
                </div>
                <div class="card-body p-0">
                    @if(!isset($penalties) || $penalties->isEmpty())
                        <div class="p-5 text-center text-muted">
                            <i class="feather-check-circle fs-32 d-block mb-3 text-success"></i>
                            <div class="fw-bold mb-1">{{ __('hrms.employees.lbl_no_penalties') }}</div>
                            <div>{{ __('hrms.employees.lbl_no_penalties_desc') }}</div>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('hrms.employees.tbl_date_occurred') }}</th>
                                        <th>{{ __('hrms.employees.tbl_rule_violation') }}</th>
                                        <th>{{ __('hrms.employees.tbl_status') }}</th>
                                        <th>{{ __('hrms.employees.tbl_month') }}</th>
                                        <th class="text-end">{{ __('hrms.employees.tbl_deduction_penalty') }}</th>
                                        <th class="text-end">{{ __('hrms.employees.tbl_actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($penalties as $penalty)
                                        <tr>
                                            <td class="fw-semibold">{{ $penalty->date ? $penalty->date->format('d M, Y') : 'N/A' }}</td>
                                            <td>
                                                <div class="text-dark fw-bold">{{ ucwords(str_replace('_', ' ', $penalty->rule_type)) }}</div>
                                                 @if(!empty($penalty->remarks))
                                                     <small class="text-muted d-block">{{ \Illuminate\Support\Str::before($penalty->remarks, ' (') }}</small>
                                                 @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-secondary text-secondary">{{ $penalty->status }}</span>
                                            </td>
                                            <td class="text-muted fs-13">{{ $penalty->payroll_month }}</td>
                                            <td class="text-end fw-semibold text-danger">₹{{ number_format($penalty->penalty_amount, 2) }}</td>
                                            <td class="text-end">
                                                <form action="{{ route('hrms.employees.penalties.destroy', [$employee->id, $penalty->id]) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.employees.confirm_delete_penalty') }}', { title: '{{ __('hrms.employees.lbl_delete_penalty') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.common.delete') }}' });">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-link text-danger p-1"><i class="feather-trash-2"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right: Current Policy Summary -->
        <div class="col-lg-4 col-12">
            <div class="card-custom">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-alert-triangle text-primary"></i> {{ __('hrms.employees.lbl_policy_summary') }}</h5>
                </div>
                <div class="card-body p-4 fs-13">
                    @if(isset($attendancePenalty) && $attendancePenalty)
                        <div class="fw-bold mb-2">{{ $attendancePenalty->name }}</div>
                        <div class="text-muted mb-4">{{ $attendancePenalty->description ?: __('hrms.employees.lbl_no_description') }}</div>

                        @php
                            $rulesList = is_string($attendancePenalty->rules) ? json_decode($attendancePenalty->rules, true) : ($attendancePenalty->rules ?? []);
                        @endphp
                        
                        <div class="d-flex flex-column gap-3 mt-2">
                            @foreach(($rulesList ?? []) as $ruleType => $ruleConfig)
                                @if(!empty($ruleConfig['enabled']))
                                    @php
                                        $label = match($ruleType) {
                                            'no_attendance' => __('hrms.employees.rule_no_attendance'),
                                            'late_arrival'  => __('hrms.employees.rule_late_arrival'),
                                            'under_hours'   => __('hrms.employees.rule_under_hours'),
                                            'missing_logs'  => __('hrms.employees.rule_missing_logs'),
                                            default => ucwords(str_replace('_', ' ', $ruleType))
                                        };
                                        $desc = match($ruleType) {
                                            'no_attendance' => __('hrms.employees.rule_no_attendance_desc', ['multiplier' => $ruleConfig['multiplier'] ?? 1]),
                                            'late_arrival'  => __('hrms.employees.rule_late_arrival_desc', ['grace' => $ruleConfig['grace_days'] ?? 3, 'rate' => $ruleConfig['deduction_rate'] ?? 0.5]),
                                            'under_hours'   => __('hrms.employees.rule_under_hours_desc', ['min' => $ruleConfig['min_hours'] ?? 8, 'rate' => $ruleConfig['deduction_rate'] ?? 0.5]),
                                            'missing_logs'  => __('hrms.employees.rule_missing_logs_desc', ['amount' => number_format($ruleConfig['fixed_amount'] ?? 100, 2)]),
                                            default => __('hrms.employees.rule_policy_active')
                                        };
                                    @endphp
                                    <div class="p-3 rounded bg-light border border-light-subtle">
                                        <div class="fw-bold text-dark mb-1">{{ $label }}</div>
                                        <div class="text-muted fs-12">{{ $desc }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-muted fs-13">
                            <i class="feather-alert-circle d-block fs-24 mb-2 text-warning"></i>
                            {{ __('hrms.employees.lbl_no_penalty_policy', ['company' => $employee->company?->company_name ?? __('hrms.employees.lbl_this_company')]) }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="modal fade" id="addAdhocModal" tabindex="-1" aria-labelledby="addAdhocModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addAdhocModalLabel">{{ __('hrms.employees.mdl_add_adhoc_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.adhoc-components.store', $employee->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_adhoc_component') }}" name="salary_component_id" select2-selector="default" :required="true">
                                    <option value="">{{ __('hrms.employees.mdl_select_component') }}</option>
                                    @foreach(($availableAdhocComponents ?? []) as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->name }} [{{ $ac->code }}]</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_amount_inr') }}" name="amount" inputType="number" step="0.01" placeholder="0.00" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_payroll_month') }}" name="payroll_month" select2-selector="default" :required="true">
                                    <option value="">{{ __('hrms.employees.mdl_select_payroll_month') }}</option>
                                    @for ($i = -6; $i <= 6; $i++)
                                        @php
                                            $month = now()->addMonths($i);
                                            $val = $month->format('Y-m');
                                            $monthKey = strtolower($month->format('F'));
                                            $label = __('hrms.months.' . $monthKey) . ' ' . $month->format('Y');
                                            $selected = ($i === 0) ? 'selected' : '';
                                        @endphp
                                        <option value="{{ $val }}" {{ $selected }}>{{ $label }}</option>
                                    @endfor
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_remarks') }}" name="remarks" rows="3" placeholder="{{ __('hrms.employees.mdl_remarks_placeholder') }}" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2 justify-content-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.employees.mdl_btn_close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('hrms.employees.mdl_btn_add_component') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ADD PENALTY MODAL -->
    <div class="modal fade" id="addPenaltyModal" tabindex="-1" aria-labelledby="addPenaltyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addPenaltyModalLabel">{{ __('hrms.employees.mdl_add_penalty_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.penalties.store', $employee->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_rule_violated') }}" name="rule_type" select2-selector="default" :required="true">
                                    <option value="">{{ __('hrms.employees.mdl_select_violation') }}</option>
                                    <option value="no_attendance">{{ __('hrms.employees.mdl_violation_no_attendance') }}</option>
                                    <option value="late_arrival">{{ __('hrms.employees.mdl_violation_late_arrival') }}</option>
                                    <option value="under_hours">{{ __('hrms.employees.mdl_violation_under_hours') }}</option>
                                    <option value="missing_logs">{{ __('hrms.employees.mdl_violation_missing_logs') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_violation_date') }}" name="date" inputType="date" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_deduction_value') }}" name="penalty_amount" inputType="number" step="0.01" placeholder="{{ __('hrms.employees.mdl_deduction_placeholder') }}" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_payroll_month') }}" name="payroll_month" select2-selector="default" :required="true">
                                    <option value="">{{ __('hrms.employees.mdl_select_payroll_month') }}</option>
                                    @for ($i = -6; $i <= 6; $i++)
                                        @php
                                            $month = now()->addMonths($i);
                                            $val = $month->format('Y-m');
                                            $monthKey = strtolower($month->format('F'));
                                            $label = __('hrms.months.' . $monthKey) . ' ' . $month->format('Y');
                                            $selected = ($i === 0) ? 'selected' : '';
                                        @endphp
                                        <option value="{{ $val }}" {{ $selected }}>{{ $label }}</option>
                                    @endfor
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_remarks') }}" name="remarks" rows="3" placeholder="{{ __('hrms.employees.mdl_remarks_penalty_placeholder') }}" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2 justify-content-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.employees.mdl_btn_close') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('hrms.employees.mdl_btn_log_penalty') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

