<div class="tab-pane fade {{ $activeTabName === 'compensation' ? 'show active' : '' }}" id="compensation-pane" role="tabpanel" aria-labelledby="compensation-tab">
    <div class="row g-4">
        <!-- Left: Slab & Structure Breakdown -->
        <div class="col-lg-8 col-12">
            <div class="card-custom">
                <div class="card-custom-header">
                    <div>
                        <h5 class="card-custom-title"><i class="feather-dollar-sign text-primary"></i> {{ __('hrms.employees.lbl_computed_salary') }}</h5>
                        <small class="text-muted d-block mt-1">{{ __('hrms.employees.lbl_computed_salary_desc') }}</small>
                    </div>
                    @if(isset($salaryStructure) && $salaryStructure)
                        <span class="badge bg-soft-primary text-primary px-3 py-2 rounded-pill fs-12">
                            {{ $salaryStructure->name }}
                        </span>
                    @else
                        <span class="badge bg-soft-warning text-warning px-3 py-2 rounded-pill fs-12">
                            {{ __('hrms.employees.lbl_no_slab') }}
                        </span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if(!$employee->payGroup)
                        <div class="p-5 text-center text-muted">
                            <i class="feather-alert-circle fs-32 d-block mb-3 text-warning"></i>
                            <div class="fw-bold mb-1">{{ __('hrms.employees.lbl_no_pay_group') }}</div>
                            <div>{{ __('hrms.employees.lbl_no_pay_group_desc') }}</div>
                        </div>
                    @elseif(!isset($salaryStructure) || !$salaryStructure)
                        <div class="p-5 text-center text-muted">
                            <i class="feather-alert-octagon fs-32 d-block mb-3 text-danger"></i>
                            <div class="fw-bold mb-1">{{ __('hrms.employees.lbl_no_slab_match') }}</div>
                            <div>{{ __('hrms.employees.lbl_no_slab_match_desc', ['salary' => number_format($employee->current_salary, 2), 'paygroup' => $employee->payGroup->name]) }}</div>
                        </div>
                    @else
                        <!-- Component Table -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('hrms.employees.tbl_code_component') }}</th>
                                        <th>{{ __('hrms.employees.tbl_status') }}</th>
                                        <th>{{ __('hrms.employees.tbl_formula') }}</th>
                                        <th class="text-end">{{ __('hrms.employees.tbl_monthly') }}</th>
                                        <th class="text-end">{{ __('hrms.employees.tbl_yearly') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $totalEarningsMonthly = 0;
                                        $totalDeductionsMonthly = 0;
                                    @endphp
                                    @foreach(($computedComponents ?? []) as $compId => $compData)
                                        @php
                                            $item = $compData['item'];
                                            $amt = $compData['amount'];
                                            $calcTypeLabel = match($item->calculation_type) {
                                                'fixed'                => __('hrms.employees.lbl_calc_fixed'),
                                                'percentage_of_ctc'    => $item->value . '% of CTC',
                                                'percentage_of_basic'  => $item->value . '% of Basic',
                                                'balancing'            => __('hrms.employees.lbl_calc_balancing'),
                                                default => $item->calculation_type
                                            };
                                            
                                            if ($item->component->type === 'earning') {
                                                $totalEarningsMonthly += $amt / 12;
                                            } else {
                                                $totalDeductionsMonthly += $amt / 12;
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $item->component->name }}</div>
                                                <code class="fs-12">{{ $item->component->code }}</code>
                                            </td>
                                            <td>
                                                @if($item->component->type === 'earning')
                                                    <span class="badge bg-soft-success text-success">{{ __('hrms.employees.lbl_type_earning') }}</span>
                                                @else
                                                    <span class="badge bg-soft-danger text-danger">{{ __('hrms.employees.lbl_type_deduction') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-muted fs-13">{{ $calcTypeLabel }}</td>
                                            <td class="text-end fw-semibold">₹{{ number_format($amt / 12, 2) }}</td>
                                            <td class="text-end fw-semibold text-primary">₹{{ number_format($amt, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light border-top-2">
                                    <tr>
                                        <td colspan="3" class="fw-bold">{{ __('hrms.employees.lbl_total_earnings') }}</td>
                                        <td class="text-end fw-bold text-success">₹{{ number_format($totalEarningsMonthly, 2) }}</td>
                                        <td class="text-end fw-bold text-success">₹{{ number_format($totalEarningsMonthly * 12, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="fw-bold">{{ __('hrms.employees.lbl_total_deductions') }}</td>
                                        <td class="text-end fw-bold text-danger">₹{{ number_format($totalDeductionsMonthly, 2) }}</td>
                                        <td class="text-end fw-bold text-danger">₹{{ number_format($totalDeductionsMonthly * 12, 2) }}</td>
                                    </tr>
                                    <tr class="table-primary">
                                        <td colspan="3" class="fw-bold">{{ __('hrms.employees.lbl_net_salary') }}</td>
                                        <td class="text-end fw-extrabold text-primary">₹{{ number_format(max(0, $totalEarningsMonthly - $totalDeductionsMonthly), 2) }}</td>
                                        <td class="text-end fw-extrabold text-primary">₹{{ number_format(max(0, $totalEarningsMonthly - $totalDeductionsMonthly) * 12, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right: Summary & Adhoc components -->
        <div class="col-lg-4 col-12">
            <div class="card-custom mb-4">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-info text-primary"></i> {{ __('hrms.employees.lbl_master_summary') }}</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <div class="info-label">{{ __('hrms.employees.lbl_pay_group') }}</div>
                            <div class="info-value text-dark">{{ $employee->payGroup?->name ?? __('hrms.employees.lbl_not_assigned') }}</div>
                        </div>
                        <div>
                            <div class="info-label">{{ __('hrms.employees.lbl_annual_ctc') }}</div>
                            <div class="info-value text-primary fs-18 fw-bold">₹{{ number_format($employee->current_salary, 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-custom">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-plus-circle text-primary"></i> {{ __('hrms.employees.lbl_monthly_adhoc') }}</h5>
                    <x-ui.button 
                        type="button" 
                        variant="soft-primary" 
                        size="sm" 
                        data-bs-toggle="modal" 
                        data-bs-target="#addAdhocModal" 
                        :disabled="!$employee->pay_group_id"
                    >
                        {{ __('hrms.common.add') }}
                    </x-ui.button>
                </div>
                <div class="card-body p-0">
                    @if(!isset($adhocComponents) || $adhocComponents->isEmpty())
                        <div class="p-4 text-center text-muted fs-13">
                            {{ __('hrms.employees.lbl_no_adhoc_components') }}
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                                 <thead class="table-light">
                                     <tr>
                                         <th>{{ __('hrms.employees.tbl_component') }}</th>
                                         <th class="text-end">{{ __('hrms.employees.tbl_amount') }}</th>
                                         <th class="text-end">{{ __('hrms.employees.tbl_action') }}</th>
                                     </tr>
                                 </thead>
                                 <tbody>
                                     @foreach($adhocComponents as $adhoc)
                                         <tr>
                                             <td>
                                                 <div class="fw-bold">{{ $adhoc->component->name }}</div>
                                                 <div class="d-flex align-items-center gap-2 mt-1">
                                                     <span class="badge bg-soft-info text-info fs-10">{{ $adhoc->status }}</span>
                                                     <span class="text-muted fs-11"><i class="feather-calendar me-1"></i>{{ $adhoc->payroll_month }}</span>
                                                 </div>
                                             </td>
                                             <td class="text-end fw-semibold">₹{{ number_format($adhoc->amount, 2) }}</td>
                                             <td class="text-end">
                                                 <form action="{{ route('hrms.employees.adhoc-components.destroy', $adhoc->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.employees.confirm_delete_adhoc') }}', { title: '{{ __('hrms.employees.lbl_delete_adhoc') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.common.delete') }}' });">
                                                     @csrf
                                                     @method('DELETE')
                                                     <button type="submit" class="btn btn-link text-danger p-1 m-0" style="text-decoration: none !important; box-shadow: none !important;"><i class="feather-trash-2"></i></button>
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
    </div>
</div>
