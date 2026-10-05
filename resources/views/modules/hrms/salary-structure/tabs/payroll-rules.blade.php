@php
    $rules = $selectedPayGroup->payroll_rules ?? [];
    $prorationRule = $rules['proration_rule'] ?? 'calendar_days';
    $splicingRule = $rules['lop_splicing_rule'] ?? 'proportionate_gross';
    $attendanceLockDay = $rules['attendance_lock_day'] ?? 25;
    $variableLockDay = $rules['variable_lock_day'] ?? 27;
    $pfWageCeiling = $rules['pf_wage_ceiling'] ?? 15000;
    $esiGrossThreshold = $rules['esi_gross_threshold'] ?? 21000;
@endphp

<style>
    .payroll-rules-wrapper .odoo-form-group {
        display: flex;
        align-items: center;
        margin-bottom: 6px;
        gap: 8px;
    }
    .payroll-rules-wrapper .odoo-form-label {
        width: auto !important;
        min-width: 170px !important;
        max-width: 200px;
        padding-right: 8px;
        flex-shrink: 0 !important;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 0;
        white-space: normal !important;
        line-height: 1.3;
    }
    .payroll-rules-wrapper .odoo-form-control,
    .payroll-rules-wrapper .select2-container {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        width: 100% !important;
    }
    .payroll-rules-wrapper .statutory-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        transition: all 0.2s ease;
    }
    .payroll-rules-wrapper .statutory-card:hover {
        border-color: #cbd5e1;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }
    .payroll-rules-wrapper .statutory-card .odoo-form-label {
        min-width: 160px !important;
    }
    @media (max-width: 768px) {
        .payroll-rules-wrapper .odoo-form-group {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
        .payroll-rules-wrapper .odoo-form-label {
            width: 100% !important;
            min-width: 100% !important;
            max-width: 100% !important;
            margin-bottom: 4px;
        }
    }
</style>

<div class="card border border-light-subtle rounded-3 shadow-sm bg-white p-4 payroll-rules-wrapper">
    <div class="mb-4">
        <h6 class="fw-bold text-dark mb-1"><i class="feather-settings text-primary me-2"></i>{{ __('hrms.salary.configure_rules_title') }}</h6>
        <p class="text-muted fs-12 mb-0">{{ __('hrms.salary.configure_rules_desc', ['name' => $selectedPayGroup->name]) }}</p>
    </div>

    <form action="{{ route('hrms.salary-structure.pay-group.update-rules', $selectedPayGroup->id) }}" method="POST">
        @csrf
        <div class="row g-4">
            <!-- 1. Proration Rule -->
            <div class="col-md-6 col-12">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.salary.proration_rule_lbl') }}" name="proration_rule" select2-selector="default">
                    <option value="calendar_days" {{ $prorationRule === 'calendar_days' ? 'selected' : '' }}>{{ __('hrms.salary.proration_calendar_days') }}</option>
                    <option value="fixed_30_days" {{ $prorationRule === 'fixed_30_days' ? 'selected' : '' }}>{{ __('hrms.salary.proration_fixed_30') }}</option>
                    <option value="working_days" {{ $prorationRule === 'working_days' ? 'selected' : '' }}>{{ __('hrms.salary.proration_working_days') }}</option>
                </x-ui.odoo-form-ui>
                <small class="text-muted d-block mt-1 fs-11">{{ __('hrms.salary.proration_help') }}</small>
            </div>

            <!-- 2. LOP Splicing Rule -->
            <div class="col-md-6 col-12">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.salary.lop_splicing_lbl') }}" name="lop_splicing_rule" select2-selector="default">
                    <option value="proportionate_gross" {{ $splicingRule === 'proportionate_gross' ? 'selected' : '' }}>{{ __('hrms.salary.lop_proportionate_gross') }}</option>
                    <option value="basic_hra_only" {{ $splicingRule === 'basic_hra_only' ? 'selected' : '' }}>{{ __('hrms.salary.lop_basic_hra_only') }}</option>
                </x-ui.odoo-form-ui>
                <small class="text-muted d-block mt-1 fs-11">{{ __('hrms.salary.lop_help') }}</small>
            </div>

            <!-- 3. Attendance Lock Day -->
            <div class="col-md-6 col-12">
                <x-ui.odoo-form-ui type="input" subtype="number" min="1" max="31" label="{{ __('hrms.salary.attendance_lock_day_lbl') }}" name="attendance_lock_day" :required="true" value="{{ $attendanceLockDay }}" />
                <small class="text-muted d-block mt-1 fs-11">{{ __('hrms.salary.attendance_lock_day_help') }}</small>
            </div>

            <!-- 4. Variable Lock Day -->
            <div class="col-md-6 col-12">
                <x-ui.odoo-form-ui type="input" subtype="number" min="1" max="31" label="{{ __('hrms.salary.variable_lock_day_lbl') }}" name="variable_lock_day" :required="true" value="{{ $variableLockDay }}" />
                <small class="text-muted d-block mt-1 fs-11">{{ __('hrms.salary.variable_lock_day_help') }}</small>
            </div>

            <!-- 5. Statutory Calculation Policies (PF & ESI) -->
            <div class="col-12 mt-3 border-top pt-4">
                <h6 class="fw-bold text-dark mb-1"><i class="feather-umbrella text-primary me-2"></i>{{ __('hrms.salary.statutory_policies_title') }}</h6>
                <p class="text-muted fs-12 mb-3">{{ __('hrms.salary.statutory_policies_desc') }}</p>
                <div class="row g-4">
                    <!-- PF Ceiling Section -->
                    <div class="col-md-6 col-12">
                        <div class="statutory-card h-100">
                            <div class="mb-3">
                                <x-ui.checkbox id="restrict_pf_ceiling" name="restrict_pf_ceiling" value="1" :checked="($rules['restrict_pf_ceiling'] ?? true)" label="{{ __('hrms.salary.restrict_pf_ceiling_lbl') }}" />
                            </div>
                            <x-ui.odoo-form-ui type="input" subtype="number" min="0" step="1" label="{{ __('hrms.salary.pf_wage_ceiling_lbl') }}" name="pf_wage_ceiling" id="pf_wage_ceiling_input" value="{{ $pfWageCeiling }}" placeholder="e.g. 15000" />
                            <small class="text-muted d-block mt-2 fs-11">{{ __('hrms.salary.pf_ceiling_help') }}</small>
                        </div>
                    </div>

                    <!-- ESI Threshold Section -->
                    <div class="col-md-6 col-12">
                        <div class="statutory-card h-100">
                            <div class="mb-3">
                                <x-ui.checkbox id="restrict_esi_threshold" name="restrict_esi_threshold" value="1" :checked="($rules['restrict_esi_threshold'] ?? true)" label="{{ __('hrms.salary.restrict_esi_threshold_lbl') }}" />
                            </div>
                            <x-ui.odoo-form-ui type="input" subtype="number" min="0" step="1" label="{{ __('hrms.salary.esi_gross_threshold_lbl') }}" name="esi_gross_threshold" id="esi_gross_threshold_input" value="{{ $esiGrossThreshold }}" placeholder="e.g. 21000" />
                            <small class="text-muted d-block mt-2 fs-11">{{ __('hrms.salary.esi_threshold_help') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
            <x-ui.button type="submit" variant="primary">
                <i class="feather-save me-2"></i>{{ __('hrms.salary.save_rules_btn') }}
            </x-ui.button>
        </div>
    </form>
</div>

<script>
    (function() {
        function initStatutorySync() {
            const pfCheck = document.getElementById('restrict_pf_ceiling');
            const pfInput = document.getElementById('pf_wage_ceiling_input');
            const esiCheck = document.getElementById('restrict_esi_threshold');
            const esiInput = document.getElementById('esi_gross_threshold_input');

            function syncPf() {
                if (pfCheck && pfInput) {
                    pfInput.disabled = !pfCheck.checked;
                    pfInput.closest('.odoo-form-group')?.classList.toggle('opacity-50', !pfCheck.checked);
                }
            }
            function syncEsi() {
                if (esiCheck && esiInput) {
                    esiInput.disabled = !esiCheck.checked;
                    esiInput.closest('.odoo-form-group')?.classList.toggle('opacity-50', !esiCheck.checked);
                }
            }

            if (pfCheck) pfCheck.addEventListener('change', syncPf);
            if (esiCheck) esiCheck.addEventListener('change', syncEsi);
            syncPf();
            syncEsi();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initStatutorySync);
        } else {
            initStatutorySync();
        }
    })();
</script>
