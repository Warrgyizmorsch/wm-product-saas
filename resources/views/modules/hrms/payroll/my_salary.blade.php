@extends('layouts.duralux')

@section('title', __('hrms.payroll.my_salary_meta'))
@section('page-title', __('hrms.payroll.my_salary_title'))
@section('breadcrumb', __('hrms.payroll.my_salary_breadcrumb'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button type="button" variant="primary" icon="feather-download" data-bs-toggle="modal" data-bs-target="#downloadRangeModal" class="fw-bold text-uppercase" style="height: 38px;">
            {{ __('hrms.payroll.download_range_payslips') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 8px;">
                <i class="feather-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 8px;">
                <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
            <!-- Header Toolbar: Heading + Search, Sort, Filter -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 border-bottom pb-3 mb-4">
                <div>
                    <h5 class="mb-0 fs-15 fw-bold text-dark">
                        <i class="feather-file-text text-primary me-2"></i>{{ __('hrms.payroll.my_salary_history_heading') }}
                    </h5>
                </div>

                <!-- Search & Filters (Standard Theme Toolbar) -->
                <form method="GET" action="{{ route('hrms.payroll.mySalary') }}" id="mySalaryFilterForm" class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="hidden" name="sort" id="salarySortInput" value="{{ $inputs['sort'] ?? 'newest' }}">

                    <!-- Search Field -->
                    <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="form-control border-0 bg-transparent p-0 fs-13" 
                            placeholder="{{ __('hrms.payroll.search_payslips_placeholder') }}" 
                            value="{{ $inputs['search'] ?? '' }}"
                            style="box-shadow: none; width: 190px;"
                        >
                    </div>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                        @php $currentSort = $inputs['sort'] ?? 'newest'; @endphp
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $currentSort === 'newest' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('newest')">
                            <span>{{ __('hrms.payroll.sort_newest') }}</span>
                            @if($currentSort === 'newest') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $currentSort === 'oldest' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('oldest')">
                            <span>{{ __('hrms.payroll.sort_oldest') }}</span>
                            @if($currentSort === 'oldest') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $currentSort === 'payout_high' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('payout_high')">
                            <span>{{ __('hrms.payroll.sort_payout_high') }}</span>
                            @if($currentSort === 'payout_high') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $currentSort === 'payout_low' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('payout_low')">
                            <span>{{ __('hrms.payroll.sort_payout_low') }}</span>
                            @if($currentSort === 'payout_low') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter :label="__('hrms.common.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.payroll.year') }}</label>
                            <x-ui.odoo-form-ui type="select" name="year" select2-selector="default">
                                <option value="">{{ __('hrms.payroll.all_years') }}</option>
                                @if(!empty($availableYears))
                                    @foreach($availableYears as $yr)
                                        <option value="{{ $yr }}" @selected(($inputs['year'] ?? '') == $yr)>{{ $yr }}</option>
                                    @endforeach
                                @else
                                    @for($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--)
                                        <option value="{{ $y }}" @selected(($inputs['year'] ?? '') == $y)>{{ $y }}</option>
                                    @endfor
                                @endif
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.payroll.month') }}</label>
                            <x-ui.odoo-form-ui type="select" name="month" select2-selector="default">
                                <option value="">{{ __('hrms.payroll.all_months') }}</option>
                                @for($m = 1; $m <= 12; $m++)
                                    @php
                                        $monthVal = str_pad($m, 2, '0', STR_PAD_LEFT);
                                        $monthName = \Carbon\Carbon::create(null, $m, 1)->locale(app()->getLocale())->translatedFormat('F');
                                    @endphp
                                    <option value="{{ $monthVal }}" @selected(($inputs['month'] ?? '') == $monthVal)>{{ $monthName }}</option>
                                @endfor
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('hrms.payroll.mySalary') }}" class="btn btn-sm btn-light border">{{ __('hrms.common.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary text-white">{{ __('hrms.common.apply') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
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

<!-- Download Range Modal -->
<div class="modal fade" id="downloadRangeModal" tabindex="-1" aria-labelledby="downloadRangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-download text-primary fs-18"></i>
                    <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="downloadRangeModalLabel">
                        {{ __('hrms.payroll.download_range_modal_title') }}
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.payroll.payslip.download-range') }}" method="GET" target="_blank">
                <div class="modal-body p-4 text-dark">
                    <div class="alert alert-soft-primary d-flex align-items-start gap-2 mb-3 py-2.5 px-3 fs-12 border-0 rounded-3">
                        <i class="feather-info text-primary fs-15 mt-0.5"></i>
                        <div>
                            {{ __('hrms.payroll.download_range_info') }}
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                                {{ __('hrms.payroll.from_month') }} <span class="text-danger">*</span>
                            </label>
                            <x-ui.odoo-form-ui type="select" name="from_month" select2-selector="default" required>
                                @if(!empty($availableMonths))
                                    @foreach($availableMonths as $mCode)
                                        <option value="{{ $mCode }}" @selected($loop->first)>
                                            {{ \Carbon\Carbon::parse($mCode . '-01')->locale(app()->getLocale())->translatedFormat('F Y') }}
                                        </option>
                                    @endforeach
                                @else
                                    @for($i = 5; $i >= 0; $i--)
                                        @php $mCode = now()->subMonths($i)->format('Y-m'); @endphp
                                        <option value="{{ $mCode }}" @selected($i === 2)>
                                            {{ now()->subMonths($i)->locale(app()->getLocale())->translatedFormat('F Y') }}
                                        </option>
                                    @endfor
                                @endif
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                                {{ __('hrms.payroll.to_month') }} <span class="text-danger">*</span>
                            </label>
                            <x-ui.odoo-form-ui type="select" name="to_month" select2-selector="default" required>
                                @if(!empty($availableMonths))
                                    @foreach($availableMonths as $mCode)
                                        <option value="{{ $mCode }}" @selected($loop->last)>
                                            {{ \Carbon\Carbon::parse($mCode . '-01')->locale(app()->getLocale())->translatedFormat('F Y') }}
                                        </option>
                                    @endforeach
                                @else
                                    @for($i = 5; $i >= 0; $i--)
                                        @php $mCode = now()->subMonths($i)->format('Y-m'); @endphp
                                        <option value="{{ $mCode }}" @selected($i === 0)>
                                            {{ now()->subMonths($i)->locale(app()->getLocale())->translatedFormat('F Y') }}
                                        </option>
                                    @endfor
                                @endif
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2.5 px-4">
                    <x-ui.button type="button" variant="light" class="border px-3" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="feather-download" class="px-4">
                        {{ __('hrms.payroll.btn_download') }}
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function submitCleanForm(form) {
        if (!form) return;
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.name && (input.value === null || input.value === undefined || input.value.trim() === '')) {
                input.disabled = true;
            }
            if (input.name === 'sort' && input.value === 'newest') {
                input.disabled = true;
            }
        });
        form.submit();
    }

    function applySort(val) {
        const form = document.getElementById('mySalaryFilterForm');
        document.getElementById('salarySortInput').value = val;
        submitCleanForm(form);
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Move all modals to document body to prevent blurry backdrop overlay bugs
        const modals = document.querySelectorAll('[id^="payslipModal-"], #downloadRangeModal');
        modals.forEach(function(modal) {
            document.body.appendChild(modal);
        });

        // Initialize modal select dropdowns when range modal is shown
        $('#downloadRangeModal').on('shown.bs.modal', function () {
            $(this).find('select').each(function() {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
                $(this).select2({
                    dropdownParent: $('#downloadRangeModal'),
                    width: '100%'
                });
            });
        });

        // Live Search with Debounce and automatic focus restoration
        const form = document.getElementById('mySalaryFilterForm');
        const searchInput = form ? form.querySelector('input[name="search"]') : null;
        if (searchInput && form) {
            if (searchInput.value.length > 0) {
                searchInput.focus();
                const val = searchInput.value;
                searchInput.value = '';
                searchInput.value = val;
            }

            let timeout = null;
            searchInput.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    submitCleanForm(form);
                }, 500);
            });
        }
    });
</script>
@endpush
