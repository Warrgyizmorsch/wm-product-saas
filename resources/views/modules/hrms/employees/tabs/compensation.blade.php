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
                        <x-ui.badge soft variant="primary" class="px-3 py-1.5 rounded-pill fs-12">
                            {{ $salaryStructure->name }}
                        </x-ui.badge>
                    @else
                        <x-ui.badge soft variant="warning" class="px-3 py-1.5 rounded-pill fs-12">
                            {{ __('hrms.employees.lbl_no_slab') }}
                        </x-ui.badge>
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
                                                'percentage_of_ctc'    => $item->value . '% ' . __('hrms.employees.lbl_of_ctc'),
                                                'percentage_of_basic'  => $item->value . '% ' . __('hrms.employees.lbl_of_basic'),
                                                'balancing'            => __('hrms.employees.lbl_calc_balancing'),
                                                default => $item->calculation_type
                                            };
                                            
                                            if (($item->component?->type ?? 'earning') === 'earning') {
                                                $totalEarningsMonthly += $amt / 12;
                                            } else {
                                                $totalDeductionsMonthly += $amt / 12;
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $item->component?->name ?? __('hrms.employees.tbl_component') }}</div>
                                                <code class="fs-12">{{ $item->component?->code ?? __('hrms.common.na') }}</code>
                                            </td>
                                            <td>
                                                @if(($item->component?->type ?? 'earning') === 'earning')
                                                    <x-ui.badge soft variant="success" class="fs-11">
                                                        {{ __('hrms.employees.lbl_type_earning') }}
                                                    </x-ui.badge>
                                                @else
                                                    <x-ui.badge soft variant="danger" class="fs-11">
                                                        {{ __('hrms.employees.lbl_type_deduction') }}
                                                    </x-ui.badge>
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

            <!-- Adhoc Components Card -->
            <div class="card-custom">
                <div class="card-custom-header d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-custom-title mb-0 fs-14 fw-bold"><i class="feather-plus-circle text-success me-1"></i> {{ __('hrms.salary.adhoc_components') }}</h5>
                    @php
                        $authUser = auth()->user();
                        $canManageCompensation = $authUser && app(\App\Services\Access\AccessService::class)->allows($authUser, 'hrms.employees.update', ['tenant_id' => $authUser->tenant_id]);
                    @endphp
                    @if($canManageCompensation)
                        <x-ui.button type="button" variant="light" size="sm" icon="feather-plus" class="rounded-pill fs-11 fw-bold text-primary" data-bs-toggle="modal" data-bs-target="#addAdhocModal">
                            {{ __('hrms.common.add') }}
                        </x-ui.button>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if(($adhocComponents ?? collect())->isEmpty())
                        <div class="p-4 text-center text-muted">
                            <i class="feather-alert-circle fs-24 d-block mb-2 text-secondary"></i>
                            <div class="fs-12">{{ __('hrms.employees.lbl_no_adhoc_components') }}</div>
                        </div>
                    @else
                        <div class="w-100" style="overflow-x: hidden;">
                            <table class="table table-hover align-middle mb-0 fs-12" style="table-layout: fixed; width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-2 ps-3 pe-1 text-muted text-uppercase fw-semibold" style="width: 38%; font-size: 11px;">{{ __('hrms.employees.tbl_component') }}</th>
                                        <th class="py-2 px-1 text-muted text-uppercase fw-semibold" style="width: 20%; font-size: 11px;">{{ __('hrms.employees.tbl_month') }}</th>
                                        <th class="py-2 px-1 text-end text-muted text-uppercase fw-semibold" style="width: 30%; font-size: 11px;">{{ __('hrms.employees.tbl_amount') }}</th>
                                        @if($canManageCompensation)
                                            <th class="py-2 pe-3 ps-1 text-center" style="width: 12%;"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $shortMonths = [
                                            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar',
                                            '04' => 'Apr', '05' => 'May', '06' => 'Jun',
                                            '07' => 'Jul', '08' => 'Aug', '09' => 'Sep',
                                            '10' => 'Oct', '11' => 'Nov', '12' => 'Dec'
                                        ];
                                    @endphp
                                    @foreach($adhocComponents as $adhoc)
                                        @php
                                            $mParts = explode('-', (string) $adhoc->payroll_month);
                                            $mName = count($mParts) === 2 ? ($shortMonths[$mParts[1]] ?? $mParts[1]) . ' ' . $mParts[0] : $adhoc->payroll_month;
                                        @endphp
                                        <tr>
                                            <td class="py-2 ps-3 pe-1" style="white-space: normal; word-break: break-word;">
                                                <div class="fw-bold text-dark" style="line-height: 1.35; white-space: normal; word-break: break-word;">{{ $adhoc->component?->name ?? __('hrms.common.unknown') }}</div>
                                                @if($adhoc->remarks)
                                                    <small class="text-muted d-block mt-0.5" style="line-height: 1.25; white-space: normal; word-break: break-word;" title="{{ $adhoc->remarks }}">{{ $adhoc->remarks }}</small>
                                                @endif
                                            </td>
                                            <td class="py-2 px-1 text-muted fw-medium text-nowrap" style="white-space: nowrap !important; font-size: 11.5px;">
                                                {{ $mName }}
                                            </td>
                                            <td class="py-2 px-1 text-end fw-bold text-nowrap {{ ($adhoc->component?->type === 'deduction') ? 'text-danger' : 'text-success' }}" style="white-space: nowrap !important; font-variant-numeric: tabular-nums;">
                                                {{ ($adhoc->component?->type === 'deduction') ? '-' : '+' }}₹{{ number_format($adhoc->amount, 2) }}
                                            </td>
                                            @if($canManageCompensation)
                                                <td class="py-2 pe-3 ps-1 text-center align-middle">
                                                    <x-ui.button type="button" variant="outline-danger" size="sm" icon="feather-trash-2" class="p-0 rounded d-inline-flex align-items-center justify-content-center btn-delete-adhoc" style="width: 28px; height: 28px; min-height: 28px;" title="{{ __('hrms.common.delete') ?? 'Delete' }}" data-url="{{ route('hrms.employees.adhoc-components.destroy', $adhoc->id) }}" data-name="{{ $adhoc->component?->name ?? __('hrms.salary.adhoc_components') }}" data-bs-toggle="modal" data-bs-target="#deleteAdhocModal" />
                                                </td>
                                            @endif
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

    @if($canManageCompensation)
        <!-- Delete Adhoc Confirmation Modal -->
        <div class="modal fade" id="deleteAdhocModal" tabindex="-1" aria-labelledby="deleteAdhocModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="deleteAdhocModalLabel">
                            <i class="feather-alert-triangle text-danger fs-16"></i>
                            <span>{{ trans('hrms.common.confirm_delete') !== 'hrms.common.confirm_delete' ? trans('hrms.common.confirm_delete') : 'Confirm Delete' }}</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="deleteAdhocForm" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <div class="modal-body p-4">
                            <p class="text-secondary mb-0 fs-13" id="deleteAdhocModalPrompt">
                                {{ trans('hrms.employees.msg_confirm_delete_adhoc') !== 'hrms.employees.msg_confirm_delete_adhoc' ? trans('hrms.employees.msg_confirm_delete_adhoc') : 'Are you sure you want to delete this ad-hoc component? This action cannot be undone.' }}
                            </p>
                        </div>
                        <div class="modal-footer bg-light py-2 px-3 gap-2 justify-content-end">
                            <x-ui.button type="button" variant="light" size="sm" data-bs-dismiss="modal">
                                {{ __('hrms.common.cancel') }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="danger" size="sm" icon="feather-trash-2">
                                {{ __('hrms.common.delete') }}
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
                $(document).on('click', '.btn-delete-adhoc', function () {
                    var url = $(this).attr('data-url');
                    var name = $(this).attr('data-name');
                    var $form = $('#deleteAdhocForm');
                    if ($form.length && url) {
                        $form.attr('action', url);
                    }
                    if (name) {
                        $('#deleteAdhocModalPrompt').text('Are you sure you want to delete "' + name + '"? This action cannot be undone.');
                    }
                });
            </script>
        @endpush
    @endif
</div>
