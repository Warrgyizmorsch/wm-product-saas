

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



