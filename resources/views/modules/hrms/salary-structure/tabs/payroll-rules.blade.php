@php
    $rules = $selectedPayGroup->payroll_rules ?? [];
    $prorationRule = $rules['proration_rule'] ?? 'calendar_days';
    $splicingRule = $rules['lop_splicing_rule'] ?? 'proportionate_gross';
    $attendanceLockDay = $rules['attendance_lock_day'] ?? 25;
    $variableLockDay = $rules['variable_lock_day'] ?? 27;
@endphp

<div class="card border border-light-subtle rounded-3 shadow-sm bg-white p-4">
    <div class="mb-4">
        <h6 class="fw-bold text-dark mb-1"><i class="feather-settings text-primary me-2"></i>Configure Payroll Execution Rules</h6>
        <p class="text-muted fs-12 mb-0">Set calculation preferences and lock cycles for <strong>{{ $selectedPayGroup->name }}</strong>. Changes apply immediately to subsequent payroll computations.</p>
    </div>

    <form action="{{ route('hrms.salary-structure.pay-group.update-rules', $selectedPayGroup->id) }}" method="POST">
        @csrf
        <div class="row g-4">
            <!-- 1. Proration Rule -->
            <div class="col-md-6 col-12">
                <x-ui.odoo-form-ui type="select" label="Salary Proration Basis" name="proration_rule" select2-selector="default">
                    <option value="calendar_days" {{ $prorationRule === 'calendar_days' ? 'selected' : '' }}>Calendar Days (Divisor changes based on days in month, e.g., 30, 31, 28)</option>
                    <option value="fixed_30_days" {{ $prorationRule === 'fixed_30_days' ? 'selected' : '' }}>Fixed 30 Days (Divisor is always standard 30 days)</option>
                    <option value="working_days" {{ $prorationRule === 'working_days' ? 'selected' : '' }}>Working Days (Divisor is actual working days in month, excluding Sundays)</option>
                </x-ui.odoo-form-ui>
                <small class="text-muted d-block mt-1 fs-11">Determines the divisor used to calculate daily wage rates for partial months.</small>
            </div>

            <!-- 2. LOP Splicing Rule -->
            <div class="col-md-6 col-12">
                <x-ui.odoo-form-ui type="select" label="Loss of Pay (LOP) Splicing" name="lop_splicing_rule" select2-selector="default">
                    <option value="proportionate_gross" {{ $splicingRule === 'proportionate_gross' ? 'selected' : '' }}>Proportionate Gross (Deducts LOP proportionally from all earnings)</option>
                    <option value="basic_hra_only" {{ $splicingRule === 'basic_hra_only' ? 'selected' : '' }}>Basic & HRA Only (Deducts LOP only from Basic and HRA components)</option>
                </x-ui.odoo-form-ui>
                <small class="text-muted d-block mt-1 fs-11">Controls which earnings components are reduced when LOP days are applied.</small>
            </div>

            <!-- 3. Attendance Lock Day -->
            <div class="col-md-6 col-12">
                <x-ui.odoo-form-ui type="input" subtype="number" min="1" max="31" label="Monthly Attendance Lock Day" name="attendance_lock_day" :required="true" value="{{ $attendanceLockDay }}" />
                <small class="text-muted d-block mt-1 fs-11">The day of the month after which attendance corrections cannot be submitted for the current run.</small>
            </div>

            <!-- 4. Variable Lock Day -->
            <div class="col-md-6 col-12">
                <x-ui.odoo-form-ui type="input" subtype="number" min="1" max="31" label="Ad-hoc / Variable Inputs Lock Day" name="variable_lock_day" :required="true" value="{{ $variableLockDay }}" />
                <small class="text-muted d-block mt-1 fs-11">The cut-off day for adding ad-hoc bonuses, cash advance deductions, or claims.</small>
            </div>

            <!-- 5. Statutory Configuration (PF & ESI) -->
            <div class="col-12 mt-4 border-top pt-4">
                <h6 class="fw-bold text-dark mb-3"><i class="feather-umbrella text-primary me-2"></i>Statutory Contributions (PF & ESI)</h6>
                <div class="row g-4">
                    <!-- PF Enabled -->
                    <div class="col-md-6 col-12">
                        <div class="pt-2">
                            <x-ui.checkbox id="enable_pf" name="enable_pf" value="1" :checked="($rules['enable_pf'] ?? true)" label="Enable Provident Fund (PF) Deductions" />
                        </div>
                        <small class="text-muted d-block fs-11 mt-1">When enabled, employee and employer PF contributions (12% standard) will be computed.</small>
                    </div>

                    <!-- PF Ceiling -->
                    <div class="col-md-6 col-12">
                        <div class="pt-2">
                            <x-ui.checkbox id="restrict_pf_ceiling" name="restrict_pf_ceiling" value="1" :checked="($rules['restrict_pf_ceiling'] ?? true)" label="Restrict PF Contribution to Wage Ceiling (₹15,000)" />
                        </div>
                        <small class="text-muted d-block fs-11 mt-1">If enabled, the PF calculation basis is capped at a maximum basic salary of ₹15,000 per month (max deduction ₹1,800).</small>
                    </div>

                    <!-- ESI Enabled -->
                    <div class="col-md-6 col-12">
                        <div class="pt-2">
                            <x-ui.checkbox id="enable_esi" name="enable_esi" value="1" :checked="($rules['enable_esi'] ?? true)" label="Enable Employee State Insurance (ESI) Deductions" />
                        </div>
                        <small class="text-muted d-block fs-11 mt-1">When enabled, ESI contribution (0.75% standard) will be calculated on Gross Salary.</small>
                    </div>

                    <!-- ESI Threshold -->
                    <div class="col-md-6 col-12">
                        <div class="pt-2">
                            <x-ui.checkbox id="restrict_esi_threshold" name="restrict_esi_threshold" value="1" :checked="($rules['restrict_esi_threshold'] ?? true)" label="Apply ESI Gross Salary Threshold (₹21,000)" />
                        </div>
                        <small class="text-muted d-block fs-11 mt-1">If enabled, ESI will only be deducted if the employee's monthly gross salary is ₹21,000 or below.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
            <x-ui.button type="submit" variant="primary">
                <i class="feather-save me-2"></i>Save Payroll Rules
            </x-ui.button>
        </div>
    </form>
</div>
