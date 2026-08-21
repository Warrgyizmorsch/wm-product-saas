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
        </div>

        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
            <x-ui.button type="submit" variant="primary">
                <i class="feather-save me-2"></i>Save Payroll Rules
            </x-ui.button>
        </div>
    </form>
</div>
