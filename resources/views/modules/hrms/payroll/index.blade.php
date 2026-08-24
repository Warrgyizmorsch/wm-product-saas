@extends('layouts.duralux')

@section('title', 'Payroll Operations | SaaS ERP')
@section('page-title', 'Payroll Operations')
@section('breadcrumb', 'HRMS / Payroll Runs')

@section('page-actions')
    <x-ui.button variant="primary" icon="feather-play" data-bs-toggle="modal" data-bs-target="#initiateRunModal">
        Process New Month
    </x-ui.button>
@endsection

@section('content')
<style>
    .run-item {
        border-left: 4px solid transparent !important;
        transition: all 0.15s ease-in-out;
        border-bottom: 1px solid #f1f5f9 !important;
    }
    .run-item.active {
        background-color: #f1f5f9 !important;
        border-left-color: var(--bs-primary) !important;
    }
    .run-item:hover:not(.active) {
        background-color: #f8fafc !important;
    }
</style>

<div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
    <div class="row g-4">
        <!-- LEFT: Runs History List -->
        <div class="col-md-3 col-12 border-end pe-4">
            <div class="pb-2.5 mb-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="feather-calendar me-2 text-primary"></i>Payroll Runs</h6>
            </div>
            <div class="list-group list-group-flush border rounded-3 overflow-hidden">
                @forelse($runs as $run)
                    @php $isActive = $selectedRun && $selectedRun->id === $run->id; @endphp
                    <a href="{{ route('hrms.payroll.index', ['run_id' => $run->id]) }}" 
                       class="list-group-item list-group-item-action py-3 px-4 run-item {{ $isActive ? 'active' : '' }}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark fs-14">
                                {{ Carbon\Carbon::parse($run->payroll_month . '-01')->format('F Y') }}
                            </span>
                            @if($run->status === 'draft')
                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-10">Draft</span>
                            @elseif($run->status === 'locked')
                                <span class="badge bg-soft-primary text-primary px-2.5 py-1 rounded-pill fs-10">Locked</span>
                            @else
                                <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-10">Paid</span>
                            @endif
                        </div>
                        <div class="fs-11 text-muted">
                            {{ $run->start_date->format('d M') }} - {{ $run->end_date->format('d M Y') }}
                        </div>
                    </a>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="feather-info fs-24 mb-2 d-block text-secondary"></i>
                        <span>No payroll runs initiated.</span>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- RIGHT: Active Run Register -->
        <div class="col-md-9 col-12 ps-4">
            @if($selectedRun)
                <div>
                    <!-- Cycle Metadata & Payout Actions -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-3 mb-4 gap-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">
                                Payroll Register &mdash; {{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }}
                                @if($selectedRun->payGroup)
                                    <span class="badge bg-soft-info text-info fs-12 ms-2 fw-semibold">{{ $selectedRun->payGroup->name }}</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary fs-12 ms-2 fw-semibold">All Pay Groups</span>
                                @endif
                            </h5>
                            <div class="text-muted fs-12">
                                Cycle dates: {{ $selectedRun->start_date->format('F d, Y') }} to {{ $selectedRun->end_date->format('F d, Y') }}
                            </div>
                        </div>
                        <div>
                            @if($selectedRun->status === 'draft')
                                <form action="{{ route('hrms.payroll.run.lock', $selectedRun->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-warning text-white fw-bold"><i class="feather-lock me-2"></i>Lock Payroll Register</button>
                                </form>
                            @elseif($selectedRun->status === 'locked')
                                <form action="{{ route('hrms.payroll.run.release', $selectedRun->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success fw-bold"><i class="feather-check-circle me-2"></i>Release Payouts</button>
                                </form>
                            @else
                                <button class="btn btn-outline-success fw-bold border-2" disabled><i class="feather-check me-2"></i>Payout Completed</button>
                            @endif
                        </div>
                    </div>

                    <!-- PENDING PRIOR HOLDS ALERTS -->
                    @if(!empty($pendingPriorHolds))
                        <div class="alert alert-warning border-warning-subtle rounded-3 p-3.5 mb-4 animate__animated animate__fadeIn">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="feather-alert-triangle text-warning fs-18"></i>
                                <h6 class="fw-bold text-dark mb-0">Pending Withheld Salaries Detected</h6>
                            </div>
                            <p class="fs-12 text-muted mb-3">The following employees have active holds on their payouts from previous months. You can release them directly into the current <strong>{{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }}</strong> payroll cycle as Arrears:</p>
                            <div class="list-group list-group-flush border rounded-3 bg-white overflow-hidden" style="max-width: 650px;">
                                @foreach($pendingPriorHolds as $prior)
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3 fs-12 text-dark">
                                        <div>
                                            <span class="fw-bold">{{ $prior['employee']->full_name }}</span> 
                                            <span class="text-muted">({{ $prior['employee']->employee_id }}) &bull; Withheld for {{ Carbon\Carbon::parse($prior['hold']->payroll_month . '-01')->format('F Y') }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="fw-bold text-primary">₹{{ number_format($prior['net_payout'], 2) }}</span>
                                            @if($selectedRun->status === 'draft')
                                                <form action="{{ route('hrms.payroll.hold.toggle', [$prior['employee']->id, $prior['hold']->payroll_month]) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <input type="hidden" name="target_month" value="{{ $selectedRun->payroll_month }}">
                                                    <button type="submit" class="btn btn-xs btn-primary text-white fw-bold px-2.5 py-1 fs-11">
                                                        <i class="feather-plus me-1"></i>Release to {{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('M') }} Payout
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted fs-11"><i class="feather-lock me-1"></i>Cycle Locked</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Calculations Register Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-dark">
                            <thead class="table-light fs-11 text-uppercase tracking-wider">
                                <tr>
                                    <th>Employee Details</th>
                                    <th class="text-center">LOP Days</th>
                                    <th class="text-end">Base Gross</th>
                                    <th class="text-end">Salary Deductions</th>
                                    <th class="text-end">Ad-hoc</th>
                                    <th class="text-end">Overtime</th>
                                    <th class="text-end">Retro Refunds</th>
                                    <th class="text-end">Net Payout</th>
                                    <th class="text-center">Payout Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($registerData as $row)
                                    @php $summary = $row['calc']; @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $row['employee']->full_name }}</div>
                                            <span class="fs-12 text-muted">{{ $row['employee']->employee_id }} &bull; {{ $row['employee']->job_title }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ ($summary['lop_days'] ?? 0) > 0 ? 'bg-soft-danger text-danger' : 'bg-soft-secondary text-secondary' }} px-2 py-1">
                                                {{ $summary['lop_days'] ?? 0 }} days
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold">₹{{ number_format($summary['base_gross_earnings'] ?? 0, 2) }}</td>
                                        <td class="text-end text-danger">₹{{ number_format($summary['base_deductions'] ?? 0, 2) }}</td>
                                        @php
                                            $netAdhoc = ($summary['adhoc_earnings'] ?? 0) - ($summary['adhoc_deductions'] ?? 0);
                                        @endphp
                                        <td class="text-end fw-semibold @if($netAdhoc > 0) text-success @elseif($netAdhoc < 0) text-danger @else text-secondary @endif">
                                            @if($netAdhoc > 0)
                                                +₹{{ number_format($netAdhoc, 2) }}
                                            @elseif($netAdhoc < 0)
                                                -₹{{ number_format(abs($netAdhoc), 2) }}
                                            @else
                                                ₹0.00
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold text-success">
                                            @if(($summary['overtime_payout'] ?? 0) > 0)
                                                ₹{{ number_format($summary['overtime_payout'], 2) }}
                                                <div class="fs-10 text-muted" style="font-size: 10px;">{{ number_format($summary['overtime_hours'] ?? 0, 1) }} hrs</div>
                                            @else
                                                <span class="text-secondary">₹0.00</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-primary">₹{{ number_format($summary['retro_lop_reversals'] ?? 0, 2) }}</td>
                                        <td class="text-end fw-bold text-primary">₹{{ number_format($summary['net_payout'] ?? 0, 2) }}</td>
                                        <td class="text-center text-nowrap">
                                            @if($selectedRun->status === 'paid')
                                                @if($row['hold_status'] === 'on_hold')
                                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1.5 rounded-pill fs-11">Withheld</span>
                                                @elseif($row['hold_status'] === 'released')
                                                    <span class="badge bg-soft-info text-info px-2.5 py-1.5 rounded-pill fs-11">Released (Paid)</span>
                                                @else
                                                    <span class="badge bg-soft-success text-success px-2.5 py-1.5 rounded-pill fs-11">Paid</span>
                                                @endif
                                            @else
                                                @if($row['is_held'])
                                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1.5 rounded-pill fs-11">Withheld</span>
                                                @else
                                                    <span class="badge bg-soft-success text-success px-2.5 py-1.5 rounded-pill fs-11">Approved</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <button type="button" class="btn btn-icon btn-sm btn-soft-primary rounded-circle show-salary-details" 
                                                        data-employee-name="{{ $row['employee']->full_name }}" 
                                                        data-month="{{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }}" 
                                                        data-items='@json($row['items'] ?? [])'
                                                        title="View salary components breakdown">
                                                    <i class="feather-eye"></i>
                                                </button>

                                                @if($selectedRun->status === 'draft' || ($selectedRun->status === 'paid' && $row['hold_status'] === 'on_hold'))
                                                    <form action="{{ route('hrms.payroll.hold.toggle', [$row['employee']->id, $selectedRun->payroll_month]) }}" method="POST" class="m-0">
                                                        @csrf
                                                        @if($selectedRun->status === 'paid')
                                                            <input type="hidden" name="target_month" value="{{ $selectedRun->payroll_month }}">
                                                        @endif
                                                        <button type="submit" class="btn btn-icon btn-sm {{ $row['is_held'] ? 'btn-soft-success' : 'btn-soft-danger' }} rounded-circle" title="{{ $row['is_held'] ? 'Release Payout' : 'Hold' }}">
                                                            <i class="feather-{{ $row['is_held'] ? 'play' : 'pause' }}"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="p-5 text-center">
                    <i class="feather-info fs-36 text-secondary mb-3"></i>
                    <h6 class="fw-bold text-dark">No Active Payroll Run</h6>
                    <p class="text-muted fs-13 mb-0">Select an existing cycle from the history panel or click "Process New Month" to begin calculations.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- INITIATE RUN MODAL -->
<div class="modal fade" id="initiateRunModal" tabindex="-1" aria-labelledby="initiateRunModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="initiateRunModalLabel"><i class="feather-play me-2 text-primary"></i>Initiate Payroll Run</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.payroll.run.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="month" label="Payroll Month (YYYY-MM)" name="payroll_month" id="initiate_payroll_month" :required="true" placeholder="e.g. 2026-08" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Pay Group" name="pay_group_id" id="initiate_pay_group_id" helperText="Select a specific pay group to process or leave as all pay groups.">
                                <option value="">All Pay Groups (Process All Employees)</option>
                                @foreach($payGroups as $pg)
                                    <option value="{{ $pg->id }}">{{ $pg->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" id="initiate_start_date" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" id="initiate_end_date" :required="true" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary text-white">Start Calculations</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SALARY DETAILS MODAL -->
<div class="modal fade" id="salaryDetailsModal" tabindex="-1" aria-labelledby="salaryDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="salaryDetailsModalLabel"><i class="feather-info me-2 text-primary"></i>Salary Breakdown - <span id="modalEmployeeName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3 text-muted fs-12">
                    Payroll Month: <strong id="modalPayrollMonth"></strong>
                </div>
                <div class="row g-4">
                    <!-- Earnings Column -->
                    <div class="col-md-6">
                        <h6 class="fw-bold text-success border-bottom pb-2 mb-3"><i class="feather-trending-up me-1"></i>Earnings</h6>
                        <table class="table table-sm align-middle fs-12 text-dark">
                            <thead>
                                <tr class="table-light">
                                    <th>Component</th>
                                    <th class="text-end">Base</th>
                                    <th class="text-end">LOP Ded.</th>
                                    <th class="text-end">Net</th>
                                </tr>
                            </thead>
                            <tbody id="earningsTableBody">
                                <!-- Filled dynamically -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Deductions Column -->
                    <div class="col-md-6">
                        <h6 class="fw-bold text-danger border-bottom pb-2 mb-3"><i class="feather-trending-down me-1"></i>Deductions</h6>
                        <table class="table table-sm align-middle fs-12 text-dark">
                            <thead>
                                <tr class="table-light">
                                    <th>Component</th>
                                    <th class="text-end">Base</th>
                                    <th class="text-end">Net</th>
                                </tr>
                            </thead>
                            <tbody id="deductionsTableBody">
                                <!-- Filled dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById('initiateRunModal');
        if (modal) {
            document.body.appendChild(modal);
        }

        const salaryModal = document.getElementById('salaryDetailsModal');
        if (salaryModal) {
            document.body.appendChild(salaryModal);
        }

        const payrollMonthInput = document.getElementById('initiate_payroll_month');
        if (payrollMonthInput) {
            const updateDates = function() {
                const value = payrollMonthInput.value.trim();
                if (/^\d{4}-\d{2}$/.test(value)) {
                    const parts = value.split('-');
                    const year = parseInt(parts[0], 10);
                    const month = parseInt(parts[1], 10);

                    const startDateStr = `${year}-${String(month).padStart(2, '0')}-01`;
                    const lastDay = new Date(year, month, 0).getDate();
                    const endDateStr = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;

                    const startDateEl = document.getElementById('initiate_start_date');
                    const endDateEl = document.getElementById('initiate_end_date');
                    
                    if (startDateEl) startDateEl.value = startDateStr;
                    if (endDateEl) endDateEl.value = endDateStr;
                }
            };
            payrollMonthInput.addEventListener('input', updateDates);
            payrollMonthInput.addEventListener('change', updateDates);
        }

        // Handle salary breakdown click
        $(document).on('click', '.show-salary-details', function() {
            const employeeName = $(this).data('employee-name');
            const month = $(this).data('month');
            const items = $(this).data('items') || {};

            $('#modalEmployeeName').text(employeeName);
            $('#modalPayrollMonth').text(month);

            let earningsHtml = '';
            let deductionsHtml = '';

            Object.keys(items).forEach(code => {
                const item = items[code];
                const base = parseFloat(item.base_monthly || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const calculated = parseFloat(item.calculated_value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                
                if (item.type === 'earning') {
                    const deduction = parseFloat(item.deduction || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    earningsHtml += `
                        <tr>
                            <td><strong>${code}</strong> <span class="text-muted d-block fs-10">${item.name}</span></td>
                            <td class="text-end">₹${base}</td>
                            <td class="text-end text-danger">-₹${deduction}</td>
                            <td class="text-end fw-bold">₹${calculated}</td>
                        </tr>
                    `;
                } else {
                    deductionsHtml += `
                        <tr>
                            <td><strong>${code}</strong> <span class="text-muted d-block fs-10">${item.name}</span></td>
                            <td class="text-end">₹${base}</td>
                            <td class="text-end fw-bold text-danger">₹${calculated}</td>
                        </tr>
                    `;
                }
            });

            if (!earningsHtml) {
                earningsHtml = '<tr><td colspan="4" class="text-center text-muted py-3">No earnings items defined.</td></tr>';
            }
            if (!deductionsHtml) {
                deductionsHtml = '<tr><td colspan="3" class="text-center text-muted py-3">No deductions items defined.</td></tr>';
            }

            $('#earningsTableBody').html(earningsHtml);
            $('#deductionsTableBody').html(deductionsHtml);

            $('#salaryDetailsModal').modal('show');
        });
    });
</script>
@endpush
