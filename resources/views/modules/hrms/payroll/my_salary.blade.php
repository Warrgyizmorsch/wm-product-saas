@extends('layouts.duralux')

@section('title', __('hrms.payroll.my_salary_meta'))
@section('page-title', __('hrms.payroll.my_salary_title'))
@section('breadcrumb', __('hrms.payroll.my_salary_breadcrumb'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                <h5 class="mb-0 fs-14 fw-bold text-dark"><i class="feather-file-text text-primary me-2"></i>{{ __('hrms.payroll.my_salary_history_heading') }}</h5>
            </div>

            @if($salaryHistory->isEmpty())
                <div class="text-center py-5">
                    <div class="avatar-lg bg-soft-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="feather-info text-secondary fs-24"></i>
                    </div>
                    <h6 class="fw-bold text-dark">{{ __('hrms.payroll.no_payslips_released') }}</h6>
                    <p class="text-muted fs-13 max-w-350 mx-auto">{{ __('hrms.payroll.no_payslips_released_desc') }}</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle fs-13 text-dark">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('hrms.payroll.tbl_release_month') }}</th>
                                <th>{{ __('hrms.payroll.tbl_calculation_period') }}</th>
                                <th>{{ __('hrms.payroll.tbl_bank_account') }}</th>
                                <th class="text-end">{{ __('hrms.payroll.tbl_net_payout') }}</th>
                                <th class="text-center">{{ __('hrms.common.status') }}</th>
                                <th class="text-center">{{ __('hrms.common.actions') }}</th>
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
                                        {{ $carbonMonth->locale(app()->getLocale())->translatedFormat('F Y') }}
                                    </td>
                                    <td class="text-muted">
                                        {{ \Carbon\Carbon::parse($run->start_date)->format('d M Y') }} - {{ \Carbon\Carbon::parse($run->end_date)->format('d M Y') }}
                                    </td>
                                    <td>
                                        <div class="fw-medium">{{ $employee->bank_name ?? __('hrms.common.na') }}</div>
                                        <span class="fs-11 text-muted">{{ $employee->account_number ?? __('hrms.common.na') }}</span>
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        ₹{{ number_format($calc['net_payout'] ?? 0, 2) }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11">{{ __('hrms.payroll.released') }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <x-ui.icon-btn variant="soft-primary" icon="feather-eye" size="sm" data-bs-toggle="modal" data-bs-target="#payslipModal-{{ $run->id }}" title="{{ __('hrms.payroll.view_details') }}" />
                                            <x-ui.icon-btn href="{{ route('hrms.payroll.payslip.download', [$run->id, $employee->id]) }}" variant="secondary" icon="feather-download" size="sm" title="{{ __('hrms.payroll.download_payslip') }}" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Component -->
                <div class="mt-3">
                    <x-ui.pagination :paginator="$salaryHistory" />
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
                                <div class="modal-header border-bottom py-3 px-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="feather-file-text text-primary fs-18"></i>
                                        <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="payslipModalLabel-{{ $run->id }}">
                                            {{ __('hrms.payroll.payslip_details_month', ['month' => $carbonMonth->locale(app()->getLocale())->translatedFormat('F Y')]) }}
                                        </h5>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4 text-dark">
                                    <!-- Meta Information Header -->
                                    <div class="row border-bottom pb-3 mb-4 g-3 fs-12">
                                        <div class="col-md-3">
                                            <span class="text-muted d-block">{{ __('hrms.payroll.employee_name') }}</span>
                                            <span class="fw-bold text-dark fs-13">{{ $employee->full_name }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="text-muted d-block">{{ __('hrms.payroll.employee_id_role') }}</span>
                                            <span class="fw-bold text-dark fs-13">{{ $employee->employee_id }} &bull; {{ $employee->job_title ?? $employee->designation?->name ?? __('hrms.common.na') }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="text-muted d-block">{{ __('hrms.payroll.bank_account') }}</span>
                                            <span class="fw-bold text-dark fs-13">{{ $employee->bank_name ?? __('hrms.common.na') }} ({{ $employee->account_number ? substr($employee->account_number, -4) : __('hrms.common.na') }})</span>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="text-muted d-block">{{ __('hrms.payroll.salary_days_paid_total') }}</span>
                                            <span class="fw-bold text-dark fs-13">
                                                {{ $calc['paid_days'] ?? (($calc['total_days'] ?? 30) - ($calc['lop_days'] ?? 0)) }} / {{ $calc['total_days'] ?? 30 }} {{ __('hrms.payroll.days') }}
                                                @if(($calc['lop_days'] ?? 0) > 0)
                                                    <span class="text-danger" style="font-weight: 600;">(-{{ $calc['lop_days'] }} {{ __('hrms.payroll.lop') }})</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>

                                    <div class="row g-4">
                                        <!-- Earnings Column -->
                                        <div class="col-md-6">
                                            <div class="card border border-light-subtle rounded-3 bg-white p-3 shadow-none h-100">
                                                <h6 class="fs-12 fw-bold text-success border-bottom pb-2 mb-2"><i class="feather-plus-circle me-1"></i> {{ __('hrms.payroll.earnings') }}</h6>
                                                
                                                @foreach($details as $item)
                                                    @if(($item['type'] ?? '') === 'earning')
                                                        <div class="d-flex justify-content-between fs-12 mb-2">
                                                            <span class="text-muted">{{ $item['name'] }}</span>
                                                            <span class="fw-medium text-dark">₹{{ number_format($item['calculated_value'] ?? 0, 2) }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach

                                                <div class="d-flex justify-content-between border-top pt-2 mt-auto fs-12 fw-bold text-dark">
                                                    <span>{{ __('hrms.payroll.gross_earnings') }}</span>
                                                    <span>₹{{ number_format($calc['total_earnings'] ?? 0, 2) }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Deductions Column -->
                                        <div class="col-md-6">
                                            <div class="card border border-light-subtle rounded-3 bg-white p-3 shadow-none h-100">
                                                <h6 class="fs-12 fw-bold text-danger border-bottom pb-2 mb-2"><i class="feather-minus-circle me-1"></i> {{ __('hrms.payroll.deductions') }}</h6>
                                                
                                                @foreach($details as $item)
                                                    @if(($item['type'] ?? '') === 'deduction')
                                                        <div class="d-flex justify-content-between fs-12 mb-2">
                                                            <span class="text-muted">{{ $item['name'] }}</span>
                                                            <span class="fw-medium text-dark">₹{{ number_format($item['calculated_value'] ?? 0, 2) }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach

                                                <div class="d-flex justify-content-between border-top pt-2 mt-auto fs-12 fw-bold text-dark">
                                                    <span>{{ __('hrms.payroll.total_deductions') }}</span>
                                                    <span>₹{{ number_format($calc['total_deductions'] ?? 0, 2) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Summary Banner -->
                                    <div class="alert alert-success d-flex align-items-center justify-content-between mt-4 mb-0 py-2.5">
                                        <div class="fs-12 fw-semibold text-success-800">
                                            <i class="feather-check-circle me-1"></i> {{ __('hrms.payroll.net_salary_payout') }} ({{ __('hrms.payroll.paid_for_days', ['days' => $calc['paid_days'] ?? (($calc['total_days'] ?? 30) - ($calc['lop_days'] ?? 0))]) }})
                                        </div>
                                        <div class="fs-16 fw-bold text-success-900">
                                            ₹{{ number_format($calc['net_payout'] ?? 0, 2) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light border-top">
                                    <span class="fs-11 text-muted me-auto">{{ __('hrms.payroll.released_by_finance_on', ['date' => $run->updated_at ? $run->updated_at->format('d M Y H:i') : '—']) }}</span>
                                    <x-ui.button type="button" variant="light" class="border px-4" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                                    <x-ui.button href="{{ route('hrms.payroll.payslip.download', [$run->id, $employee->id]) }}" variant="primary" icon="feather-download" class="px-4">
                                        {{ __('hrms.payroll.btn_download') }}
                                    </x-ui.button>
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
