@extends('layouts.duralux')

@section('title', __('production.edit_machine_asset') . ' | SaaS ERP')
@section('page-title', __('production.edit_machine_asset'))
@section('breadcrumb', __('production.edit_machine_asset'))

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

        <div class="card border mb-4">
            <div class="card-body py-3">
                @if($machine->isLinkedToAsset())
                    @php $linkedAsset = $machine->asset; @endphp
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <div class="fs-11 text-uppercase fw-bold text-muted mb-1">Linked Fixed Asset</div>
                            <a href="{{ route('accounting.fixed-assets.show', $linkedAsset->id) }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                {{ $linkedAsset->asset_code }}
                            </a>
                            <span class="text-dark ms-1">{{ $linkedAsset->name }}</span>
                            <div class="fs-12 text-muted mt-1">
                                {{ $linkedAsset->category->name ?? '—' }} &middot;
                                Cost {{ number_format($linkedAsset->purchase_cost, 2) }} &middot;
                                <x-ui.status-badge :status="$linkedAsset->status" size="sm" />
                            </div>
                        </div>
                        <form action="{{ route('production.machines.unlink-asset', $machine->id) }}" method="POST"
                              onsubmit="return confirm('Unlink this fixed asset from the machine? The asset itself will not be affected.');">
                            @csrf
                            <x-ui.button type="submit" variant="light" icon="feather-link-2" class="border text-danger">
                                Unlink
                            </x-ui.button>
                        </form>
                    </div>
                @else
                    <div class="fs-11 text-uppercase fw-bold text-muted mb-2">Linked Fixed Asset</div>
                    @if($linkableAssets->isEmpty())
                        <div class="fs-12 text-muted">No unregistered machinery assets available to link.</div>
                    @else
                        <form action="{{ route('production.machines.link-asset', $machine->id) }}" method="POST" class="d-flex gap-2 align-items-end">
                            @csrf
                            <div class="flex-grow-1" style="max-width: 420px;">
                                <x-ui.odoo-form-ui type="select" name="asset_id" select2-selector="default">
                                    <option value="">Select a purchased asset...</option>
                                    @foreach($linkableAssets as $asset)
                                        <option value="{{ $asset->id }}">{{ $asset->asset_code }} — {{ $asset->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <x-ui.button type="submit" variant="primary" icon="feather-link">
                                Link
                            </x-ui.button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('production.machines.update', $machine->id) }}" id="machine_asset_form">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.edit_machine_asset_with_name', ['name' => $machine->code]) }}</h4>
                    <x-ui.button :href="route('production.machines.index')" variant="light" class="border">{{ __('production.cancel') }}</x-ui.button>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_name')" name="name" :value="old('name', $machine->name)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_asset_code')" name="code" :value="old('code', $machine->code)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_type')" name="machine_type" :value="old('machine_type', $machine->machine_type)" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.manufacturer')" name="manufacturer" :value="old('manufacturer', $machine->manufacturer)" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.model_number')" name="model_number" :value="old('model_number', $machine->model_number)" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_hourly_capacity')" name="capacity" inputType="number" step="any" min="0.0001" :value="old('capacity', $machine->capacity)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.status')" name="status" :required="true">
                            @foreach($statuses as $k => $v)
                                <option value="{{ $k }}" @selected(old('status', $machine->status) == $k)>{{ __('production.' . $k) ?? $v }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.installation_date')" name="installation_date" inputType="date" :value="old('installation_date', $machine->installation_date ? $machine->installation_date->format('Y-m-d') : '')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.maintenance_details')" name="maintenance_status" :value="old('maintenance_status', $machine->maintenance_status)" />
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
                                <option value="electricity" @selected(old('energy_source', $machine->energy_source ?? 'electricity') == 'electricity')>⚡ Electricity (Grid)</option>
                                <option value="petrol" @selected(old('energy_source', $machine->energy_source) == 'petrol')>⛽ Petrol / Gasoline</option>
                                <option value="diesel" @selected(old('energy_source', $machine->energy_source) == 'diesel')>🛢️ Diesel Fuel</option>
                                <option value="others" @selected(old('energy_source', $machine->energy_source) == 'others')>🔧 Others (Pneumatic / Flat)</option>
                            </x-ui.odoo-form-ui>

                            <div id="consumption_wrapper">
                                <x-ui.odoo-form-ui type="input" :label="__('production.consumption_per_hour')" name="consumption_per_hour" id="consumption_per_hour_input" inputType="number" step="0.0001" min="0" placeholder="0.00" :value="old('consumption_per_hour', $machine->consumption_per_hour ?? '0')" />
                                <small class="form-text text-muted fs-11 d-block mt-n2 mb-3" id="consumption_unit_label">Kilowatt-hours per running hour (kWh/hr)</small>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-6">
                            <div id="rate_wrapper">
                                <x-ui.odoo-form-ui type="input" :label="__('production.energy_rate_per_unit') . ' (' . active_currency_symbol() . ')'" name="energy_rate_per_unit" id="energy_rate_per_unit_input" inputType="number" step="0.0001" min="0" placeholder="0.00" :value="old('energy_rate_per_unit', $machine->energy_rate_per_unit ?? '0')" />
                                <small class="form-text text-muted fs-11 d-block mt-n2 mb-3" id="rate_unit_label">Energy cost per unit ({{ active_currency_symbol() }}/kWh)</small>
                            </div>

                            <div>
                                <x-ui.odoo-form-ui type="input" :label="__('production.operating_cost_per_hour') . ' (' . active_currency_symbol() . '/hr)'" name="operating_cost_per_hour" id="operating_cost_per_hour_input" inputType="number" step="0.0001" min="0" placeholder="0.00" :value="old('operating_cost_per_hour', $machine->operating_cost_per_hour ?? '0')" :required="true" />
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
                    <x-ui.button type="submit" variant="primary" class="px-4">{{ __('production.update_machine_asset') }}</x-ui.button>
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
