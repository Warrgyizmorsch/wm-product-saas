@extends('layouts.duralux')

@section('title', 'My Salary & Payslips | SaaS ERP')
@section('page-title', 'My Salary & Payslips')
@section('breadcrumb', 'HRMS / My Salary')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                <h5 class="mb-0 fs-14 fw-bold text-dark"><i class="feather-file-text text-primary me-2"></i>My Salary History & Released Payslips</h5>
            </div>

            @if(empty($salaryHistory))
                <div class="text-center py-5">
                    <div class="avatar-lg bg-soft-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="feather-info text-secondary fs-24"></i>
                    </div>
                    <h6 class="fw-bold text-dark">No Payslips Released</h6>
                    <p class="text-muted fs-13 max-w-350 mx-auto">Once the finance department releases your monthly payroll, your structured payslips will appear here.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle fs-13 text-dark">
                        <thead class="table-light">
                            <tr>
                                <th>Release Month</th>
                                <th>Calculation Period</th>
                                <th>Bank Account</th>
                                <th class="text-end">Net Payout</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($salaryHistory as $history)
                                @php
                                    $run = $history['run'];
                                    $calc = $history['calc'];
                                    $details = $history['details'];
                                    $carbonMonth = \Carbon\Carbon::parse($run->payroll_month . '-01');
                                @endphp
                                <tr>
                                    <td class="fw-bold text-dark">
                                        {{ $carbonMonth->format('F Y') }}
                                    </td>
                                    <td class="text-muted">
                                        {{ \Carbon\Carbon::parse($run->start_date)->format('d M Y') }} - {{ \Carbon\Carbon::parse($run->end_date)->format('d M Y') }}
                                    </td>
                                    <td>
                                        <div class="fw-medium">{{ $employee->bank_name ?? 'N/A' }}</div>
                                        <span class="fs-11 text-muted">{{ $employee->account_number ?? 'N/A' }}</span>
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        ₹{{ number_format($calc['net_payout'] ?? 0, 2) }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11">Released</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <button type="button" class="btn btn-icon btn-sm btn-soft-primary rounded-circle" data-bs-toggle="modal" data-bs-target="#payslipModal-{{ $run->id }}" title="View Details">
                                                <i class="feather-eye"></i>
                                            </button>
                                            <a href="{{ route('hrms.payroll.payslip.download', [$run->id, $employee->id]) }}" class="btn btn-icon btn-sm btn-soft-secondary rounded-circle" title="Download Payslip">
                                                <i class="feather-download"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- MODALS FOR EACH PAYSLIP -->
                @foreach($salaryHistory as $history)
                    @php
                        $run = $history['run'];
                        $calc = $history['calc'];
                        $details = $history['details'];
                        $carbonMonth = \Carbon\Carbon::parse($run->payroll_month . '-01');
                    @endphp
                    <div class="modal fade" id="payslipModal-{{ $run->id }}" tabindex="-1" aria-labelledby="payslipModalLabel-{{ $run->id }}" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content shadow-lg border-0 rounded-3">
                                <div class="modal-header border-0 text-white py-3 px-4" style="background: linear-gradient(135deg, #3b5bdb 0%, #1c3faa 100%);">
                                    <h5 class="modal-title fw-bold text-white fs-15" id="payslipModalLabel-{{ $run->id }}">
                                        <i class="feather-dollar-sign me-2"></i>Payslip Details - {{ $carbonMonth->format('F Y') }}
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4 text-dark">
                                    <!-- Meta Information Header -->
                                    <div class="row border-bottom pb-3 mb-4 g-3 fs-12">
                                        <div class="col-md-3">
                                            <span class="text-muted d-block">Employee Name</span>
                                            <span class="fw-bold text-dark fs-13">{{ $employee->full_name }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="text-muted d-block">Employee ID & Role</span>
                                            <span class="fw-bold text-dark fs-13">{{ $employee->employee_id }} &bull; {{ $employee->job_title }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="text-muted d-block">Bank & Account</span>
                                            <span class="fw-bold text-dark fs-13">{{ $employee->bank_name ?? 'N/A' }} ({{ $employee->account_number ? substr($employee->account_number, -4) : 'N/A' }})</span>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="text-muted d-block">Salary Days (Paid / Total)</span>
                                            <span class="fw-bold text-dark fs-13">
                                                {{ ($calc['total_days'] ?? 30) - ($calc['lop_days'] ?? 0) }} / {{ $calc['total_days'] ?? 30 }} Days
                                                @if(($calc['lop_days'] ?? 0) > 0)
                                                    <span class="text-danger" style="font-weight: 600;">(-{{ $calc['lop_days'] }} LOP)</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>

                                    <div class="row g-4">
                                        <!-- Earnings Column -->
                                        <div class="col-md-6">
                                            <div class="card border border-light-subtle rounded-3 bg-white p-3 shadow-none h-100">
                                                <h6 class="fs-12 fw-bold text-success border-bottom pb-2 mb-2"><i class="feather-plus-circle me-1"></i> Earnings</h6>
                                                
                                                @foreach($details as $item)
                                                    @if(($item['type'] ?? '') === 'earning')
                                                        <div class="d-flex justify-content-between fs-12 mb-2">
                                                            <span class="text-muted">{{ $item['name'] }}</span>
                                                            <span class="fw-medium text-dark">₹{{ number_format($item['calculated_value'] ?? 0, 2) }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach

                                                @if(($calc['adhoc_earnings'] ?? 0) > 0)
                                                    <div class="d-flex justify-content-between fs-12 mb-2">
                                                        <span class="text-muted">Ad-hoc Earnings (Bonus)</span>
                                                        <span class="fw-medium text-success">+₹{{ number_format($calc['adhoc_earnings'], 2) }}</span>
                                                    </div>
                                                @endif

                                                @if(($calc['retro_lop_reversals'] ?? 0) > 0)
                                                    <div class="d-flex justify-content-between fs-12 mb-2">
                                                        <span class="text-muted">Retroactive Refunds</span>
                                                        <span class="fw-medium text-success">+₹{{ number_format($calc['retro_lop_reversals'], 2) }}</span>
                                                    </div>
                                                @endif

                                                <div class="d-flex justify-content-between border-top pt-2 mt-auto fs-12 fw-bold text-dark">
                                                    <span>Gross Earnings</span>
                                                    <span>₹{{ number_format($calc['total_earnings'] ?? 0, 2) }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Deductions Column -->
                                        <div class="col-md-6">
                                            <div class="card border border-light-subtle rounded-3 bg-white p-3 shadow-none h-100">
                                                <h6 class="fs-12 fw-bold text-danger border-bottom pb-2 mb-2"><i class="feather-minus-circle me-1"></i> Deductions</h6>
                                                
                                                @foreach($details as $item)
                                                    @if(($item['type'] ?? '') === 'deduction')
                                                        <div class="d-flex justify-content-between fs-12 mb-2">
                                                            <span class="text-muted">{{ $item['name'] }}</span>
                                                            <span class="fw-medium text-dark">₹{{ number_format($item['calculated_value'] ?? 0, 2) }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach

                                                @if(($calc['lop_deduction'] ?? 0) > 0)
                                                    <div class="d-flex justify-content-between fs-12 mb-2 align-items-center">
                                                        <span class="text-muted">Loss of Pay ({{ $calc['lop_days'] }} days)</span>
                                                        <span class="badge bg-soft-secondary text-secondary fs-10 fw-semibold px-2 py-0.5" title="Amount is already spliced/deducted directly from the basic salary and other proration-based earnings.">Spliced (-₹{{ number_format($calc['lop_deduction'], 2) }})</span>
                                                    </div>
                                                @endif

                                                @if(($calc['adhoc_deductions'] ?? 0) > 0)
                                                    <div class="d-flex justify-content-between fs-12 mb-2">
                                                        <span class="text-muted">Ad-hoc Deductions</span>
                                                        <span class="fw-medium text-danger">-₹{{ number_format($calc['adhoc_deductions'], 2) }}</span>
                                                    </div>
                                                @endif

                                                @if(($calc['attendance_penalties'] ?? 0) > 0)
                                                    <div class="d-flex justify-content-between fs-12 mb-2">
                                                        <span class="text-muted">Attendance Penalties</span>
                                                        <span class="fw-medium text-danger">-₹{{ number_format($calc['attendance_penalties'], 2) }}</span>
                                                    </div>
                                                @endif

                                                <div class="d-flex justify-content-between border-top pt-2 mt-auto fs-12 fw-bold text-dark">
                                                    <span>Total Deductions</span>
                                                    <span>₹{{ number_format($calc['total_deductions'] ?? 0, 2) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Summary Banner -->
                                    <div class="alert alert-success d-flex align-items-center justify-content-between mt-4 mb-0 py-2.5">
                                        <div class="fs-12 fw-semibold text-success-800">
                                            <i class="feather-check-circle me-1"></i> Net Salary Payout (Paid for {{ ($calc['total_days'] ?? 30) - ($calc['lop_days'] ?? 0) }} Days)
                                        </div>
                                        <div class="fs-16 fw-bold text-success-900">
                                            ₹{{ number_format($calc['net_payout'] ?? 0, 2) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid #f0f2f5;">
                                    <span class="fs-11 text-muted me-auto">Released by Finance on {{ $run->updated_at->format('d M Y H:i') }}</span>
                                    <button type="button" class="btn btn-sm fw-semibold px-4" style="border:1px solid #dee2e6; background:#fff; color:#4a3b32; border-radius:6px;" data-bs-dismiss="modal">CLOSE</button>
                                     <a href="{{ route('hrms.payroll.payslip.download', [$run->id, $employee->id]) }}" class="btn btn-sm fw-semibold px-4 text-white d-flex align-items-center gap-1" style="background:#4a3b32; border-radius:6px;">
                                         <i class="feather-download" style="font-size:12px;"></i> DOWNLOAD
                                     </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Move all payslip modals to document body to prevent blurry backdrop overlay bugs
        const modals = document.querySelectorAll('[id^="payslipModal-"]');
        modals.forEach(function(modal) {
            document.body.appendChild(modal);
        });
    });
</script>
@endpush
