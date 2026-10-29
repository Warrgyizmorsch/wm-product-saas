@extends('layouts.duralux')

@section('title', __('production.create_machine') . ' | SaaS ERP')
@section('page-title', __('production.create_machine'))
@section('breadcrumb', __('production.create_machine'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="erp-single-panel">
        <!-- Validation Errors -->
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="{{ __('production.validation_failed') ?? 'Validation Failed' }}: {{ $errors->first() }}" />
        @endif

        <form method="POST" action="{{ route('production.machines.store') }}" id="machine_asset_form">
            @csrf

            @if($linkedAsset ?? null)
                <input type="hidden" name="asset_id" value="{{ $linkedAsset->id }}">
            @endif

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.new_machine_asset') }}</h4>
                    <x-ui.button :href="route('production.machines.index')" variant="light" class="border">{{ __('production.cancel') }}</x-ui.button>
                </div>

                @if($linkedAsset ?? null)
                    <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
                        <i class="feather-link fs-16 mt-1"></i>
                        <div>
                            <div class="fw-bold">Registering purchased asset {{ $linkedAsset->asset_code }}</div>
                            <div class="fs-12 text-muted">
                                {{ $linkedAsset->name }} &middot; {{ $linkedAsset->category->name ?? '—' }} &middot;
                                Cost: {{ number_format($linkedAsset->purchase_cost, 2) }}
                            </div>
                            <div class="fs-11 text-muted mt-1">Enter a name and code below to finish commissioning this machine.</div>
                        </div>
                    </div>
                @endif

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_name')" name="name" placeholder="{{ __('production.machine_name_placeholder') }}" :value="old('name', $linkedAsset->name ?? '')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_asset_code')" name="code" placeholder="{{ __('production.machine_code_placeholder') }}" :value="old('code')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_type')" name="machine_type" placeholder="{{ __('production.machine_type_placeholder') }}" :value="old('machine_type')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.manufacturer')" name="manufacturer" placeholder="{{ __('production.manufacturer_placeholder') }}" :value="old('manufacturer')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.model_number')" name="model_number" placeholder="{{ __('production.model_placeholder') }}" :value="old('model_number')" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_hourly_capacity')" name="capacity" inputType="number" step="any" min="0.0001" placeholder="{{ __('production.capacity_placeholder') }}" :value="old('capacity')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.status')" name="status" :required="true">
                            @foreach($statuses as $k => $v)
                                <option value="{{ $k }}" @selected(old('status', 'active') == $k)>{{ __('production.' . $k) ?? $v }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.installation_date')" name="installation_date" inputType="date" :value="old('installation_date')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.maintenance_details')" name="maintenance_status" placeholder="{{ __('production.maintenance_details_placeholder') }}" :value="old('maintenance_status')" />
                    </div>
                </div>

                <!-- Operating Expense Configuration Section -->
                <div class="mt-4 pt-4 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-text avatar-xs bg-soft-warning text-warning rounded d-flex align-items-center justify-content-center">
                                <i class="feather-zap fs-13"></i>
                            </span>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">{{ __('production.operating_expense_configuration') }}</h6>
                                <small class="text-muted fs-11">{{ __('production.operating_expense_subtitle') }}</small>
                            </div>
                        </div>
                        <span class="badge bg-soft-info text-info font-monospace fs-11">
                            <i class="feather-activity me-1"></i>Hourly Cost Basis
                        </span>
                    </div>

                    <div class="row g-4 fs-13 text-dark">
                        <!-- Left Column -->
                        <div class="col-md-6 border-end">
                            <x-ui.odoo-form-ui type="select" :label="__('production.energy_source')" name="energy_source" id="energy_source_select" :required="true">
                                <option value="electricity" @selected(old('energy_source', 'electricity') == 'electricity')>⚡ Electricity (Grid)</option>
                                <option value="petrol" @selected(old('energy_source') == 'petrol')>⛽ Petrol / Gasoline</option>
                                <option value="diesel" @selected(old('energy_source') == 'diesel')>🛢️ Diesel Fuel</option>
                                <option value="others" @selected(old('energy_source') == 'others')>🔧 Others (Pneumatic / Flat)</option>
                            </x-ui.odoo-form-ui>

                            <div id="consumption_wrapper">
                                <x-ui.odoo-form-ui type="input" :label="__('production.consumption_per_hour')" name="consumption_per_hour" id="consumption_per_hour_input" inputType="number" step="0.0001" min="0" placeholder="0.00" :value="old('consumption_per_hour', '0')" />
                                <small class="form-text text-muted fs-11 d-block mt-n2 mb-3" id="consumption_unit_label">Kilowatt-hours per running hour (kWh/hr)</small>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-6">
                            <div id="rate_wrapper">
                                <x-ui.odoo-form-ui type="input" :label="__('production.energy_rate_per_unit') . ' (' . active_currency_symbol() . ')'" name="energy_rate_per_unit" id="energy_rate_per_unit_input" inputType="number" step="0.0001" min="0" placeholder="0.00" :value="old('energy_rate_per_unit', '0')" />
                                <small class="form-text text-muted fs-11 d-block mt-n2 mb-3" id="rate_unit_label">Energy cost per unit ({{ active_currency_symbol() }}/kWh)</small>
                            </div>

                            <div>
                                <x-ui.odoo-form-ui type="input" :label="__('production.operating_cost_per_hour') . ' (' . active_currency_symbol() . '/hr)'" name="operating_cost_per_hour" id="operating_cost_per_hour_input" inputType="number" step="0.0001" min="0" placeholder="0.00" :value="old('operating_cost_per_hour', '0')" :required="true" />
                                <small class="form-text text-muted fs-11 d-flex align-items-center gap-1 mt-n2 mb-3" id="cost_help_label">
                                    <span>Automatically computed as Consumption × Rate ({{ active_currency_symbol() }}/hr).</span>
                                    <span class="text-primary cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="top" title="Defaults to baseline energy cost (Consumption × Energy Rate). If adjusted higher, the surplus is accounted as an additional operational expense (e.g., tooling wear, lubricants, routine servicing). If adjusted lower, the difference is accounted as an applied operating grant, energy subsidy, or tariff rebate.">
                                        <i class="feather-info"></i>
                                    </span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <x-ui.button type="submit" variant="primary" class="px-4">{{ __('production.save_machine_asset') }}</x-ui.button>
                    <x-ui.button :href="route('production.machines.index')" variant="secondary" class="px-4">{{ __('production.cancel') }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            const $energySource = $('#energy_source_select');
            const $consumptionInput = $('#consumption_per_hour_input');
            const $rateInput = $('#energy_rate_per_unit_input');
            const $costInput = $('#operating_cost_per_hour_input');
            const $consumptionLabel = $('#consumption_unit_label');
            const $rateLabel = $('#rate_unit_label');
            const $costHelpLabel = $('#cost_help_label');
            const $form = $('#machine_asset_form');
            const currency = @json(active_currency_symbol());

            function getSelectedEnergySource() {
                return $energySource.val() || document.getElementById('energy_source_select')?.value || 'electricity';
            }

            function updateUnits() {
                const src = getSelectedEnergySource();
                if (src === 'electricity') {
                    if ($consumptionLabel.length) $consumptionLabel.text('Kilowatt-hours per running hour (kWh/hr)');
                    if ($rateLabel.length) $rateLabel.text(`Energy cost per kilowatt-hour (${currency}/kWh)`);
                } else if (src === 'petrol' || src === 'diesel') {
                    const fuelName = src === 'petrol' ? 'Petrol' : 'Diesel';
                    if ($consumptionLabel.length) $consumptionLabel.text(`${fuelName} fuel consumption in Liters/hr (L/hr)`);
                    if ($rateLabel.length) $rateLabel.text(`Fuel cost per Liter (${currency}/L)`);
                } else {
                    if ($consumptionLabel.length) $consumptionLabel.text('Consumption rate in units per hour (units/hr)');
                    if ($rateLabel.length) $rateLabel.text(`Cost rate per unit (${currency}/unit)`);
                }
                checkAndCalcCost(true);
            }

            function formatNumber(num) {
                const rounded = Math.round(num * 10000) / 10000;
                if (rounded % 1 === 0) {
                    return rounded.toFixed(2);
                }
                const parts = rounded.toString().split('.');
                if (parts.length > 1 && parts[1].length < 2) {
                    return rounded.toFixed(2);
                }
                return rounded.toString();
            }

            function getDefaultFormulaText() {
                const src = getSelectedEnergySource();
                if (src === 'electricity') {
                    return `Auto: kWh/hr × Cost/kWh (${currency}/hr)`;
                } else if (src === 'petrol' || src === 'diesel') {
                    return `Auto: Liters/hr × Cost/Liter (${currency}/hr)`;
                } else {
                    return `Auto: units/hr × Cost/unit (${currency}/hr)`;
                }
            }

            function checkAndCalcCost(isManualCheckOnly = false) {
                const c = parseFloat($consumptionInput.val()) || 0;
                const r = parseFloat($rateInput.val()) || 0;
                const actual = parseFloat($costInput.val()) || 0;

                if (c > 0 && r > 0) {
                    const expected = c * r;
                    const formattedExpected = formatNumber(expected);

                    if (!isManualCheckOnly) {
                        // User typed into consumption or rate => automatically calculate cost
                        $costInput.val(formattedExpected);
                        if ($costHelpLabel.length) {
                            $costHelpLabel.html(`<span class="text-success"><i class="feather-check-circle me-1"></i>${getDefaultFormulaText()} = <strong>${currency}${formattedExpected}/hr</strong></span>`);
                        }
                    } else {
                        // User edited costInput or page loaded / switched dropdown
                        const diff = actual - expected;
                        if (Math.abs(diff) > 0.01) {
                            if ($costHelpLabel.length) {
                                if (diff > 0) {
                                    const formattedDiff = formatNumber(diff);
                                    $costHelpLabel.html(`
                                        <span class="text-primary fw-semibold" title="Surplus is accounted as an additional operational expense (maintenance, tooling, lubricants)">
                                            <i class="feather-info me-1"></i>Additional Expense: +${currency}${formattedDiff}/hr above baseline energy (${currency}${formattedExpected}/hr)
                                        </span>
                                        <a href="javascript:void(0)" class="btn-sync-cost text-secondary text-decoration-underline ms-1 fs-11 fw-bold" title="Reset to baseline energy calculation">Reset</a>
                                    `);
                                } else {
                                    const formattedDiff = formatNumber(Math.abs(diff));
                                    $costHelpLabel.html(`
                                        <span class="text-success fw-semibold" title="Deficit is accounted as an applied operating grant, energy subsidy, or rebate">
                                            <i class="feather-check-circle me-1"></i>Operating Grant / Subsidy: -${currency}${formattedDiff}/hr below baseline energy (${currency}${formattedExpected}/hr)
                                        </span>
                                        <a href="javascript:void(0)" class="btn-sync-cost text-secondary text-decoration-underline ms-1 fs-11 fw-bold" title="Reset to baseline energy calculation">Reset</a>
                                    `);
                                }
                            }
                        } else {
                            if ($costHelpLabel.length) {
                                $costHelpLabel.html(`<span class="text-success"><i class="feather-check-circle me-1"></i>${getDefaultFormulaText()} = <strong>${currency}${formattedExpected}/hr</strong></span>`);
                            }
                        }
                    }
                } else {
                    if ($costHelpLabel.length) {
                        if (actual > 0) {
                            $costHelpLabel.html(`<span class="text-muted"><i class="feather-info me-1"></i>Flat Operating Cost: <strong>${currency}${formatNumber(actual)}/hr</strong></span>`);
                        } else {
                            $costHelpLabel.text(getDefaultFormulaText());
                        }
                    }
                }
            }

            // Listen to dropdown changes via both jQuery, select2 events, and native bubbling
            $(document).on('change select2:select select2:clear', '#energy_source_select', function() {
                updateUnits();
            });
            $energySource.on('change', function() {
                updateUnits();
            });

            // Auto-calculate on input to consumption or rate
            $(document).on('input change', '#consumption_per_hour_input, #energy_rate_per_unit_input', function() {
                checkAndCalcCost(false);
            });

            // Warn / validate if user manually edits operating cost
            $(document).on('input change', '#operating_cost_per_hour_input', function() {
                checkAndCalcCost(true);
            });

            // Quick sync button click
            $(document).on('click', '.btn-sync-cost', function(e) {
                e.preventDefault();
                checkAndCalcCost(false);
            });

            // Submit handler with mismatch confirmation
            let allowCustomOperatingCost = false;
            $form.on('submit', function(e) {
                if (allowCustomOperatingCost) {
                    return true;
                }

                // Check standard HTML5 validation first
                const formEl = this;
                if (formEl.checkValidity && !formEl.checkValidity()) {
                    return true;
                }

                const c = parseFloat($consumptionInput.val()) || 0;
                const r = parseFloat($rateInput.val()) || 0;
                const actual = parseFloat($costInput.val()) || 0;

                if (c > 0 && r > 0) {
                    const expected = c * r;
                    const diff = actual - expected;

                    if (Math.abs(diff) > 0.01) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        const actualDisplay = formatNumber(actual);
                        const expectedDisplay = formatNumber(expected);
                        const absVarianceDisplay = formatNumber(Math.abs(diff));
                        const isAdditionalExpense = diff > 0;

                        const modalTitle = isAdditionalExpense
                            ? 'Additional Operating Expense Specified'
                            : 'Operating Grant / Subsidy Specified';

                        const modalDescription = isAdditionalExpense
                            ? `The specified Operating Cost is higher than baseline energy consumption. The variance of <strong>+${currency}${absVarianceDisplay}/hr</strong> will be treated as an <strong>Additional Operational Expense</strong> (covering maintenance overhead, tooling wear, lubricants, or auxiliary equipment).`
                            : `The specified Operating Cost is lower than baseline energy consumption. The variance of <strong>-${currency}${absVarianceDisplay}/hr</strong> will be treated as an applied <strong>Operating Grant, Subsidy, or Energy Tariff Incentive</strong>.`;

                        const confirmBtnText = isAdditionalExpense
                            ? 'Save with Additional Expense'
                            : 'Save with Applied Subsidy';

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: modalTitle,
                                html: `
                                    <div class="text-start fs-12">
                                        <p class="mb-2 text-secondary">${modalDescription}</p>
                                        <div class="p-3 mb-3 bg-light rounded border font-monospace">
                                            <div class="d-flex justify-content-between py-1 border-bottom">
                                                <span class="text-muted">Hourly Consumption:</span>
                                                <strong>${c}</strong>
                                            </div>
                                            <div class="d-flex justify-content-between py-1 border-bottom">
                                                <span class="text-muted">Energy Rate per Unit:</span>
                                                <strong>${currency}${r}</strong>
                                            </div>
                                            <div class="d-flex justify-content-between py-1 border-bottom">
                                                <span class="text-muted">Baseline Energy Cost:</span>
                                                <strong>${currency}${expectedDisplay}/hr</strong>
                                            </div>
                                            <div class="d-flex justify-content-between py-1 border-bottom">
                                                <span class="text-muted">${isAdditionalExpense ? 'Additional Operating Expense:' : 'Operating Grant / Subsidy:'}</span>
                                                <strong class="${isAdditionalExpense ? 'text-primary' : 'text-success'}">${isAdditionalExpense ? '+' : '-'}${currency}${absVarianceDisplay}/hr</strong>
                                            </div>
                                            <div class="d-flex justify-content-between pt-1 fw-bold fs-13">
                                                <span class="text-dark">Final Operating Cost / Hr:</span>
                                                <span class="text-dark">${currency}${actualDisplay}/hr</span>
                                            </div>
                                        </div>
                                        <p class="mb-0 text-muted fs-11">Would you like to proceed with this custom operating cost, or reset it to the baseline energy calculation?</p>
                                    </div>
                                `,
                                icon: 'info',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: confirmBtnText,
                                cancelButtonText: 'Reset to Energy Base',
                                reverseButtons: true
                            }).then((result) => {
                                const isConfirmed = Boolean(result && (result.isConfirmed || result.value === true || result.value));
                                if (isConfirmed) {
                                    allowCustomOperatingCost = true;
                                    HTMLFormElement.prototype.submit.call(formEl);
                                } else if (result && (result.dismiss === 'cancel' || (typeof Swal !== 'undefined' && result.dismiss === Swal.DismissReason?.cancel))) {
                                    checkAndCalcCost(false);
                                    $costInput.focus();
                                } else {
                                    $costInput.focus();
                                }
                            });
                        } else {
                            const fallbackMsg = isAdditionalExpense
                                ? `Notice: The specified Operating Cost (${currency}${actualDisplay}/hr) exceeds baseline energy calculation (${currency}${expectedDisplay}/hr).\n\nThe variance (+${currency}${absVarianceDisplay}/hr) will be recorded as an Additional Operational Expense.\n\nDo you wish to proceed and save with this custom operating cost?`
                                : `Notice: The specified Operating Cost (${currency}${actualDisplay}/hr) is lower than baseline energy calculation (${currency}${expectedDisplay}/hr).\n\nThe variance (-${currency}${absVarianceDisplay}/hr) will be recorded as an applied Operating Grant or Energy Subsidy.\n\nDo you wish to proceed and save with this custom operating cost?`;

                            if (confirm(fallbackMsg)) {
                                allowCustomOperatingCost = true;
                                HTMLFormElement.prototype.submit.call(formEl);
                            } else {
                                checkAndCalcCost(false);
                                $costInput.focus();
                            }
                        }
                        return false;
                    }
                }
            });

            // Initial setup on page load
            updateUnits();
        });
    </script>
@endpush
