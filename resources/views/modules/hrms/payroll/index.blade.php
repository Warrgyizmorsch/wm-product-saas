@extends('layouts.duralux')

@section('title', __('hrms.payroll.page_title'))
@section('page-title', __('hrms.payroll.title'))
@section('breadcrumb', __('hrms.payroll.breadcrumb'))

@php
    $authUser = auth()->user();
    $canCreatePayroll = $authUser && (
        $authUser->hasHrPermission('hrms.payroll_runs.create') ||
        $authUser->hasHrPermission('hrms.payroll_runs.process') ||
        $authUser->hasHrPermission('hr.settings.manage') ||
        $authUser->hasHrPermission('hr.payroll.manage') ||
        ($isCompanyAdmin ?? false)
    );
    $canApprovePayroll = $authUser && (
        $authUser->hasHrPermission('hrms.payroll_runs.approve') ||
        $authUser->hasHrPermission('hr.settings.manage') ||
        $authUser->hasHrPermission('hr.payroll.manage') ||
        ($isCompanyAdmin ?? false)
    );
@endphp

@section('page-actions')
    @if($canCreatePayroll)
        <x-ui.button variant="primary" icon="feather-play" data-bs-toggle="modal" data-bs-target="#initiateRunModal">
            {{ __('hrms.payroll.btn_process_new_month') }}
        </x-ui.button>
    @endif
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
    #bulkAdhocModal .odoo-form-label {
        width: 160px !important;
    }
</style>

<div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
    <div class="row g-4">
        <!-- LEFT: Runs History List -->
        <div class="col-md-3 col-12 pe-4" style="border-right: 1px solid #e5e7eb !important;">
            <div class="pb-2.5 mb-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="feather-calendar me-2 text-primary"></i>{{ __('hrms.payroll.payroll_runs') }}</h6>
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
                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-10">{{ __('hrms.payroll.status_draft') }}</span>
                            @elseif($run->status === 'locked')
                                <span class="badge bg-soft-primary text-primary px-2.5 py-1 rounded-pill fs-10">{{ __('hrms.payroll.status_locked') }}</span>
                            @else
                                <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-10">{{ __('hrms.payroll.status_paid') }}</span>
                            @endif
                        </div>
                        <div class="fs-11 text-muted">
                            {{ $run->start_date->format('d M') }} - {{ $run->end_date->format('d M Y') }}
                            @if($run->employee_ids && count($run->employee_ids) > 0)
                                <span class="d-block text-primary mt-1 fw-bold"><i class="feather-user me-1"></i>{{ __('hrms.payroll.individual') }} ({{ count($run->employee_ids) }})</span>
                            @elseif($run->payGroup)
                                <span class="d-block text-info mt-1"><i class="feather-users me-1"></i>{{ $run->payGroup->name }}</span>
                            @else
                                <span class="d-block text-secondary mt-1"><i class="feather-users me-1"></i>{{ __('hrms.payroll.all_pay_groups') }}</span>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="feather-info fs-24 mb-2 d-block text-secondary"></i>
                        <span>{{ __('hrms.payroll.no_runs_initiated') }}</span>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- RIGHT: Active Run Register -->
        <div class="col-md-9 col-12 ps-4" style="max-height: calc(100vh - 180px); overflow-y: auto;">
            @if($selectedRun)
                <div>
                    <!-- Cycle Metadata & Payout Actions -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-3 mb-4 gap-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">
                                {{ __('hrms.payroll.payroll_register') }} &mdash; {{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }}
                                @if($selectedRun->employee_ids && count($selectedRun->employee_ids) > 0)
                                    <span class="badge bg-soft-primary text-primary fs-12 ms-2 fw-semibold"><i class="feather-user me-1"></i>{{ __('hrms.payroll.individual_run') }} ({{ count($selectedRun->employee_ids) }} {{ __('hrms.payroll.employees') }})</span>
                                @elseif($selectedRun->payGroup)
                                    <span class="badge bg-soft-info text-info fs-12 ms-2 fw-semibold">{{ $selectedRun->payGroup->name }}</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary fs-12 ms-2 fw-semibold">{{ __('hrms.payroll.all_pay_groups') }}</span>
                                @endif
                            </h5>
                            <div class="text-muted fs-12">
                                {{ __('hrms.payroll.cycle_dates') }} {{ $selectedRun->start_date->format('F d, Y') }} {{ __('hrms.payroll.to') }} {{ $selectedRun->end_date->format('F d, Y') }}
                            </div>
                        </div>
                        <div class="d-inline-flex gap-2 align-items-center">
                            @if($selectedRun->status !== 'draft')
                                <x-ui.button variant="outline-primary" icon="feather-download-cloud" href="{{ route('hrms.payroll.run.export-bank-file', $selectedRun->id) }}" class="fw-bold">
                                    {{ __('hrms.payroll.btn_export_bank_file') }}
                                </x-ui.button>
                            @endif
                            @if($selectedRun->status === 'draft')
                                @if($canCreatePayroll)
                                    <x-ui.button type="button" variant="light" class="border fw-bold" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#bulkAdhocModal">
                                        {{ __('hrms.payroll.btn_bulk_adhoc') }}
                                    </x-ui.button>
                                @endif
                                @if($canApprovePayroll)
                                    <form action="{{ route('hrms.payroll.run.lock', $selectedRun->id) }}" method="POST" class="d-inline m-0">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary" icon="feather-lock" class="fw-bold">
                                            {{ __('hrms.payroll.btn_lock_register') }}
                                        </x-ui.button>
                                    </form>
                                @endif
                            @elseif($selectedRun->status === 'locked')
                                @if($canApprovePayroll)
                                    <x-ui.button type="button" variant="success" icon="feather-check-circle" class="fw-bold" data-bs-toggle="modal" data-bs-target="#releasePayoutsModal">
                                        {{ __('hrms.payroll.btn_release_payouts') }}
                                    </x-ui.button>
                                @else
                                    <span class="badge bg-soft-primary text-primary px-3 py-2 fw-bold fs-12"><i class="feather-lock me-1"></i>{{ __('hrms.payroll.awaiting_release') }}</span>
                                @endif
                            @else
                                <x-ui.button variant="outline-success" icon="feather-check" class="fw-bold border-2 m-0" disabled>
                                    {{ __('hrms.payroll.payout_completed') }}
                                </x-ui.button>
                            @endif
                            @if($canApprovePayroll)
                                <x-ui.button type="button" variant="light" class="border fw-bold" icon="feather-file-text" data-bs-toggle="modal" data-bs-target="#statutoryDuesModal">
                                    Statutory Dues
                                </x-ui.button>
                            @endif
                        </div>
                    </div>

                    @if($canApprovePayroll)
                        @php
                            $payrollBooks = app(\App\Domains\Accounting\Services\PayrollAccountingService::class);
                            $payingLedgers = $payrollBooks->payingLedgers();
                            $ledgerBalances = $payingLedgers->mapWithKeys(fn ($ledger) => [$ledger->id => $payrollBooks->balance($ledger)]);
                            $defaultPayingLedger = $payingLedgers->firstWhere('system_key', \App\Domains\Accounting\Support\SystemAccount::BANK) ?? $payingLedgers->first();
                            $statutoryDues = $payrollBooks->statutoryDues((int) (tenant_id() ?? auth()->user()->tenant_id));
                        @endphp

                        {{-- Release: which bank/cash ledger the salaries leave from. --}}
                        <x-ui.modal id="releasePayoutsModal" title="Release Payouts" :formAction="route('hrms.payroll.run.release', $selectedRun->id)" submitText="Release Payouts">
                            <p class="fs-13 text-muted mb-3">
                                Posts the net salary booked for {{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }} out of the account below
                                (Dr Salary Payable / Cr the account). Payslips on hold are not paid.
                            </p>
                            <label class="form-label fw-semibold fs-13">Paid From <span class="text-danger">*</span></label>
                            <select name="bank_account_id" class="form-select mb-3" required>
                                @foreach($payingLedgers as $ledger)
                                    <option value="{{ $ledger->id }}" @selected($defaultPayingLedger?->id === $ledger->id)>
                                        {{ $ledger->code }} — {{ $ledger->name }} (balance {{ number_format($ledgerBalances[$ledger->id], 2) }})
                                    </option>
                                @endforeach
                            </select>
                            <label class="form-label fw-semibold fs-13">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payout_date" class="form-control" value="{{ now()->toDateString() }}" required>
                            <div class="form-text fs-11">Cash-in-Hand can't pay more than the cash it holds.</div>
                        </x-ui.modal>

                        {{-- Statutory dues: what payroll left owing, and paying a challan. --}}
                        <x-ui.modal id="statutoryDuesModal" title="Statutory Dues" :formAction="route('hrms.payroll.statutory.pay')" submitText="Record Payment">
                            <table class="table table-sm fs-13 mb-3">
                                <thead><tr><th>Due</th><th class="text-end">Owed</th></tr></thead>
                                <tbody>
                                    @foreach($statutoryDues as $dueKey => $due)
                                        <tr>
                                            <td>{{ $due['label'] }} <span class="text-muted">({{ $due['account']->code }})</span></td>
                                            <td class="text-end fw-semibold">{{ number_format($due['balance'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">Pay <span class="text-danger">*</span></label>
                                    <select name="due" class="form-select" required>
                                        @foreach($statutoryDues as $dueKey => $due)
                                            <option value="{{ $dueKey }}" @disabled($due['balance'] <= 0)>{{ $due['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">Amount <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">Paid From <span class="text-danger">*</span></label>
                                    <select name="bank_account_id" class="form-select" required>
                                        @foreach($payingLedgers as $ledger)
                                            <option value="{{ $ledger->id }}" @selected($defaultPayingLedger?->id === $ledger->id)>{{ $ledger->code }} — {{ $ledger->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold fs-13">Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" name="payment_date" class="form-control" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold fs-13">Challan / Reference No.</label>
                                    <input type="text" name="reference" maxlength="100" class="form-control" placeholder="e.g. EPF TRRN, ESIC challan, CIN">
                                </div>
                            </div>
                        </x-ui.modal>
                    @endif

                    <!-- Search, Sort, Filter Bar -->
                    <div class="d-flex justify-content-end align-items-center mb-3 pb-3 border-bottom gap-2 w-100 flex-wrap" id="payrollRegisterFiltersBar">
                        <!-- Search Form -->
                        <form method="GET" action="{{ route('hrms.payroll.index') }}" id="payrollSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 260px; height: 36px !important; box-sizing: border-box !important;">
                            <input type="hidden" name="run_id" value="{{ $selectedRun->id }}">
                            <input type="hidden" name="status" id="payroll_status_input" value="{{ $filters['status'] ?? '' }}">
                            <input type="hidden" name="department_id" id="payroll_dept_input" value="{{ $filters['department_id'] ?? '' }}">
                            <input type="hidden" name="sort" id="payroll_sort_input" value="{{ $filters['sort'] ?? 'name_asc' }}">
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input type="text" name="search" id="payrollSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.payroll.search_placeholder') }}" value="{{ $filters['search'] ?? '' }}" style="box-shadow: none; height: 100%; outline: none;">
                        </form>

                        <!-- Sort Dropdown -->
                        <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                            <a class="dropdown-item payroll-sort-link d-flex justify-content-between align-items-center py-2 {{ ($filters['sort'] ?? '') === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_asc']) }}" data-sort="name_asc">
                                <span>{{ __('hrms.payroll.sort_name_asc') }}</span>
                                @if(($filters['sort'] ?? '') === 'name_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item payroll-sort-link d-flex justify-content-between align-items-center py-2 {{ ($filters['sort'] ?? '') === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_desc']) }}" data-sort="name_desc">
                                <span>{{ __('hrms.payroll.sort_name_desc') }}</span>
                                @if(($filters['sort'] ?? '') === 'name_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item payroll-sort-link d-flex justify-content-between align-items-center py-2 {{ ($filters['sort'] ?? '') === 'net_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'net_desc']) }}" data-sort="net_desc">
                                <span>{{ __('hrms.payroll.sort_net_desc') }}</span>
                                @if(($filters['sort'] ?? '') === 'net_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item payroll-sort-link d-flex justify-content-between align-items-center py-2 {{ ($filters['sort'] ?? '') === 'net_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'net_asc']) }}" data-sort="net_asc">
                                <span>{{ __('hrms.payroll.sort_net_asc') }}</span>
                                @if(($filters['sort'] ?? '') === 'net_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item payroll-sort-link d-flex justify-content-between align-items-center py-2 {{ ($filters['sort'] ?? '') === 'lop_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'lop_desc']) }}" data-sort="lop_desc">
                                <span>{{ __('hrms.payroll.sort_lop_desc') }}</span>
                                @if(($filters['sort'] ?? '') === 'lop_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Filter Dropdown -->
                        <x-ui.filter :label="__('hrms.common.filter')">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.payroll.filter_options') }}</h6>
                            <form id="payrollFilterForm" method="GET" action="{{ route('hrms.payroll.index') }}">
                                <input type="hidden" name="run_id" value="{{ $selectedRun->id }}">
                                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'name_asc' }}">
                                
                                <div class="mb-3" style="min-width: 200px;">
                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.payroll.filter_payout_status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="status" id="payrollFilterStatus">
                                        <option value="">{{ __('hrms.payroll.filter_all_statuses') }}</option>
                                        <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>{{ __('hrms.payroll.filter_approved_paid') }}</option>
                                        <option value="held" @selected(($filters['status'] ?? '') === 'held')>{{ __('hrms.payroll.filter_withheld') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 200px;">
                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.payroll.filter_department') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="department_id" id="payrollFilterDept">
                                        <option value="">{{ __('hrms.payroll.filter_all_departments') }}</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                
                                <div class="d-flex gap-2 justify-content-between mt-3">
                                    <x-ui.button type="submit" variant="primary" class="flex-grow-1 py-2 fs-12">
                                        {{ __('hrms.payroll.filter_apply') }}
                                    </x-ui.button>
                                    <x-ui.button href="{{ route('hrms.payroll.index', ['run_id' => $selectedRun->id]) }}" variant="light" class="border flex-grow-1 py-2 fs-12">
                                        {{ __('hrms.payroll.filter_reset') }}
                                    </x-ui.button>
                                </div>
                            </form>
                        </x-ui.filter>


                    </div>

                    <!-- PENDING APPROVALS CHECKLIST -->
                    @if($selectedRun->status === 'draft' && $pendingIssues['total'] > 0)
                        <div class="alert border border-danger-subtle bg-danger-subtle bg-opacity-10 rounded-3 p-4 mb-4 animate__animated animate__fadeIn">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 40px; height: 40px; background-color: rgba(220, 53, 69, 0.12); color: #dc3545;">
                                        <i class="feather-alert-triangle fs-18"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1">{{ __('hrms.payroll.alert_pending_title') }}</h6>
                                        <p class="fs-12 text-muted mb-2">{!! __('hrms.payroll.alert_pending_desc', ['count' => '<strong>' . $pendingIssues['total'] . '</strong>']) !!}</p>
                                        <div class="d-flex flex-wrap gap-2 fs-11 text-dark fw-semibold">
                                            @if(($pendingIssues['leaves'] ?? 0) > 0)
                                                <span class="badge bg-soft-warning text-warning px-2.5 py-1.5"><i class="feather-minus-circle me-1"></i>{{ __('hrms.payroll.leaves') }}: {{ $pendingIssues['leaves'] }}</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary px-2.5 py-1.5"><i class="feather-minus-circle me-1"></i>{{ __('hrms.payroll.leaves') }}: 0</span>
                                            @endif
                                            @if(($pendingIssues['corrections'] ?? 0) > 0)
                                                <span class="badge bg-soft-primary text-primary px-2.5 py-1.5"><i class="feather-clock me-1"></i>{{ __('hrms.payroll.corrections') }}: {{ $pendingIssues['corrections'] }}</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary px-2.5 py-1.5"><i class="feather-clock me-1"></i>{{ __('hrms.payroll.corrections') }}: 0</span>
                                            @endif
                                            @if(($pendingIssues['overtime'] ?? 0) > 0)
                                                <span class="badge bg-soft-success text-success px-2.5 py-1.5"><i class="feather-watch me-1"></i>{{ __('hrms.payroll.overtime') }}: {{ $pendingIssues['overtime'] }}</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary px-2.5 py-1.5"><i class="feather-watch me-1"></i>{{ __('hrms.payroll.overtime') }}: 0</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <x-ui.button variant="warning" icon="feather-check-square" class="fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#resolveIssuesModal">
                                        {{ __('hrms.payroll.btn_resolve_issues') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- PENDING PRIOR HOLDS ALERTS -->
                    @if(!empty($pendingPriorHolds))
                        <div class="alert alert-warning border-warning-subtle rounded-3 p-4 mb-4 animate__animated animate__fadeIn">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="feather-alert-triangle text-warning fs-18"></i>
                                <h6 class="fw-bold text-dark mb-0">{{ __('hrms.payroll.alert_holds_title') }}</h6>
                            </div>
                            <p class="fs-12 text-muted mb-3">{!! __('hrms.payroll.alert_holds_desc', ['month' => '<strong>' . Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') . '</strong>']) !!}</p>
                            <div class="list-group list-group-flush border rounded-3 bg-white overflow-hidden" style="max-width: 650px;">
                                @foreach($pendingPriorHolds as $prior)
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3 fs-12 text-dark">
                                        <div>
                                            <span class="fw-bold">{{ $prior['employee']->full_name }}</span> 
                                            <span class="text-muted">({{ $prior['employee']->employee_id }}) &bull; {{ __('hrms.payroll.withheld_for') }} {{ Carbon\Carbon::parse($prior['hold']->payroll_month . '-01')->format('F Y') }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="fw-bold text-primary">₹{{ number_format($prior['net_payout'], 2) }}</span>
                                            @if($selectedRun->status === 'draft')
                                                <form action="{{ route('hrms.payroll.hold.toggle', [$prior['employee']->id, $prior['hold']->payroll_month]) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <input type="hidden" name="target_month" value="{{ $selectedRun->payroll_month }}">
                                                    <x-ui.button type="submit" size="xs" variant="primary" icon="feather-plus" class="fw-bold fs-11">
                                                        {{ __('hrms.payroll.btn_release_to_payout', ['month' => Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('M')]) }}
                                                    </x-ui.button>
                                                </form>
                                            @else
                                                <span class="text-muted fs-11"><i class="feather-lock me-1"></i>{{ __('hrms.payroll.cycle_locked') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Calculations Register Table -->
                    <div class="table-responsive" id="payrollRegisterTableWrapper">
                        <table class="table table-hover align-middle mb-0 text-dark">
                            <thead class="table-light fs-11 text-uppercase tracking-wider">
                                <tr>
                                    <th>{{ __('hrms.payroll.tbl_employee_details') }}</th>
                                    <th class="text-center">{{ __('hrms.payroll.tbl_lop_days') }}</th>
                                    <th class="text-end">{{ __('hrms.payroll.tbl_base_gross') }}</th>
                                    <th class="text-end">{{ __('hrms.payroll.tbl_salary_deductions') }}</th>
                                    <th class="text-end">{{ __('hrms.payroll.tbl_adhoc') }}</th>
                                    <th class="text-end">{{ __('hrms.payroll.tbl_overtime') }}</th>
                                    <th class="text-end">{{ __('hrms.payroll.tbl_retro_refunds') }}</th>
                                    <th class="text-end">{{ __('hrms.payroll.tbl_net_payout') }}</th>
                                    <th class="text-center">{{ __('hrms.payroll.tbl_payout_status') }}</th>
                                    <th class="text-center">{{ __('hrms.payroll.tbl_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="payrollRegisterTableBody">
                                @forelse($registerData as $row)
                                    @php
                                        $summary = $row['calc'];
                                        $prorationText = (($summary['total_days'] ?? 30) - ($summary['lop_days'] ?? 0)) . ' / ' . ($summary['total_days'] ?? 30) . ' ' . __('hrms.payroll.days') . (($summary['lop_days'] ?? 0) > 0 ? ' (-' . $summary['lop_days'] . ' ' . __('hrms.payroll.lop') . ')' : '');
                                        $itemsJson = json_encode($row['items'] ?? []);
                                        $summaryJson = json_encode($row['calc'] ?? []);
                                    @endphp
                                    <tr class="payroll-register-row" 
                                        data-name="{{ strtolower($row['employee']->full_name) }}" 
                                        data-id="{{ strtolower($row['employee']->employee_id) }}" 
                                        data-department="{{ $row['employee']->department_id }}"
                                        data-held="{{ ($selectedRun->status === 'paid' ? $row['hold_status'] === 'on_hold' : $row['is_held']) ? 'held' : 'approved' }}"
                                        data-net="{{ (float)($summary['net_payout'] ?? 0) }}"
                                        data-lop="{{ (float)($summary['lop_days'] ?? 0) }}">
                                        <td>
                                            <div class="fw-bold text-dark">
                                                {{ $row['employee']->full_name }}
                                                @if($summary['is_fnf'] ?? false)
                                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fs-10 ms-1">{{ __('hrms.payroll.fnf_settlement') }}</span>
                                                @endif
                                            </div>
                                            <span class="fs-12 text-muted">{{ $row['employee']->employee_id }} &bull; {{ $row['employee']->job_title }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ ($summary['lop_days'] ?? 0) > 0 ? 'bg-soft-danger text-danger' : 'bg-soft-secondary text-secondary' }} px-2 py-1">
                                                {{ $summary['lop_days'] ?? 0 }} {{ __('hrms.payroll.days') }}
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
                                                <div class="fs-10 text-muted" style="font-size: 10px;">{{ number_format($summary['overtime_hours'] ?? 0, 1) }} {{ __('hrms.payroll.hrs') }}</div>
                                            @else
                                                <span class="text-secondary">₹0.00</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-primary">₹{{ number_format($summary['retro_lop_reversals'] ?? 0, 2) }}</td>
                                        <td class="text-end fw-bold text-primary">₹{{ number_format($summary['net_payout'] ?? 0, 2) }}</td>
                                        <td class="text-center text-nowrap">
                                            @if($selectedRun->status === 'paid')
                                                @if($row['hold_status'] === 'on_hold')
                                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1.5 rounded-pill fs-11">{{ __('hrms.payroll.filter_withheld') }}</span>
                                                @elseif($row['hold_status'] === 'released')
                                                    <span class="badge bg-soft-info text-info px-2.5 py-1.5 rounded-pill fs-11">{{ __('hrms.payroll.released_paid') }}</span>
                                                @else
                                                    <span class="badge bg-soft-success text-success px-2.5 py-1.5 rounded-pill fs-11">{{ __('hrms.payroll.status_paid') }}</span>
                                                @endif
                                            @else
                                                @if($row['is_held'])
                                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1.5 rounded-pill fs-11">{{ __('hrms.payroll.filter_withheld') }}</span>
                                                @else
                                                    <span class="badge bg-soft-success text-success px-2.5 py-1.5 rounded-pill fs-11">{{ __('hrms.payroll.approved') }}</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <x-ui.icon-btn type="button" 
                                                        variant="soft-primary" 
                                                        icon="feather-eye" 
                                                        size="sm" 
                                                        class="show-salary-details" 
                                                        data-employee-name="{{ $row['employee']->full_name }}" 
                                                        data-employee-id="{{ $row['employee']->employee_id }}"
                                                        data-employee-db-id="{{ $row['employee']->id }}"
                                                        data-run-id="{{ $selectedRun->id }}"
                                                        data-job-title="{{ $row['employee']->job_title }}"
                                                        data-bank="{{ $row['employee']->bank_name ?? '—' }}"
                                                        data-bank-account="{{ $row['employee']->account_number ?? '—' }}"
                                                        data-proration-days="{{ $prorationText }}"
                                                        data-run-status="{{ $selectedRun->status }}"
                                                        data-released-by="{{ $selectedRun->processedBy?->name ?? __('hrms.payroll.role_finance') }}"
                                                        data-released-at="{{ $selectedRun->updated_at ? $selectedRun->updated_at->format('d M Y H:i') : '' }}"
                                                        data-month="{{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->locale(app()->getLocale())->translatedFormat('F Y') }}" 
                                                        :data-items="$itemsJson"
                                                        :data-summary="$summaryJson"
                                                        :title="__('hrms.payroll.view_breakdown')" />
                                                @if($selectedRun->status === 'draft' || ($selectedRun->status === 'paid' && $row['hold_status'] === 'on_hold'))
                                                    <form action="{{ route('hrms.payroll.hold.toggle', [$row['employee']->id, $selectedRun->payroll_month]) }}" method="POST" class="m-0">
                                                        @csrf
                                                        @if($selectedRun->status === 'paid')
                                                             <input type="hidden" name="target_month" value="{{ $selectedRun->payroll_month }}">
                                                        @endif
                                                        <x-ui.icon-btn type="submit" variant="{{ $row['is_held'] ? 'soft-success' : 'soft-danger' }}" icon="feather-{{ $row['is_held'] ? 'play' : 'pause' }}" size="sm" :title="$row['is_held'] ? __('hrms.payroll.release_payout') : __('hrms.payroll.hold')" />
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-5 text-muted">
                                            <i class="feather-info fs-24 mb-2 d-block text-secondary"></i>
                                            <span>{{ __('hrms.payroll.no_matching_records') }}</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Component -->
                    <div id="payrollRegisterPagination" class="mt-3">
                        @if($registerData instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $registerData->total() > 0)
                            @if($registerData->lastPage() > 1)
                                <x-ui.pagination 
                                    :paginator="$registerData" 
                                    :currentPage="$registerData->currentPage()" 
                                    :totalPages="$registerData->lastPage()" 
                                    :totalResults="$registerData->total()" 
                                    :perPage="$registerData->perPage()" 
                                />
                            @else
                                <div class="erp-pagination-container">
                                    <ul class="erp-pagination">
                                        <li class="page-item disabled">
                                            <a class="page-link" href="javascript:void(0);" aria-label="Previous">
                                                <i class="feather-chevron-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item active">
                                            <a class="page-link" href="javascript:void(0);">1</a>
                                        </li>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="javascript:void(0);" aria-label="Next">
                                                <i class="feather-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                    <div class="erp-pagination-info">
                                        {{ __('hrms.common.showing_records', ['first' => 1, 'last' => $registerData->total(), 'total' => $registerData->total()]) }}
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @else
                <div class="p-5 text-center">
                    <i class="feather-info fs-36 text-secondary mb-3"></i>
                    <h6 class="fw-bold text-dark">{{ __('hrms.payroll.no_active_run_title') }}</h6>
                    <p class="text-muted fs-13 mb-0">{{ __('hrms.payroll.no_active_run_desc') }}</p>
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
                <h5 class="modal-title fw-bold" id="initiateRunModalLabel"><i class="feather-play me-2 text-primary"></i>{{ __('hrms.payroll.modal_initiate_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.payroll.run.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="month" :label="__('hrms.payroll.modal_payroll_month')" name="payroll_month" id="initiate_payroll_month" :required="true" placeholder="e.g. 2026-08" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.payroll.modal_selection_mode')" name="selection_mode" id="initiate_selection_mode" :required="true">
                                <option value="pay_group">{{ __('hrms.payroll.by_pay_group') }}</option>
                                <option value="individual">{{ __('hrms.payroll.by_selected_employees') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12" id="pay_group_selection_div">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.payroll.pay_group')" name="pay_group_id" id="initiate_pay_group_id" :helperText="__('hrms.payroll.pay_group_helper')">
                                <option value="">{{ __('hrms.payroll.all_pay_groups_option') }}</option>
                                @foreach($payGroups as $pg)
                                    <option value="{{ $pg->id }}">{{ $pg->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12 d-none" id="employee_selection_div">
                            <label class="form-label fw-bold fs-12 mb-1">{{ __('hrms.payroll.select_employees') }} <span class="text-danger">*</span></label>
                            <select name="employee_ids[]" id="initiate_employee_ids" class="form-control odoo-select2" multiple style="width: 100%;">
                                @foreach($allEmployees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                @endforeach
                            </select>
                            <div class="form-text fs-10 mt-1">{{ __('hrms.payroll.select_employees_helper') }}</div>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.payroll.start_date')" name="start_date" id="initiate_start_date" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.payroll.end_date')" name="end_date" id="initiate_end_date" :required="true" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-ui.button type="button" variant="light" class="border" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary">{{ __('hrms.payroll.btn_start_calculations') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SALARY DETAILS MODAL -->
<div class="modal fade" id="salaryDetailsModal" tabindex="-1" aria-labelledby="salaryDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-file-text text-primary fs-18"></i>
                    <h5 class="modal-title fw-bold text-dark fs-15 mb-0" id="salaryDetailsModalLabel">
                        {{ __('hrms.payroll.payslip_details') }} — <span id="modalPayrollMonth"></span>
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 text-dark">

                <!-- Employee Info Row -->
                <div class="row g-3 pb-3 mb-3 border-bottom">
                    <div class="col-6 col-md-3">
                        <div class="text-muted fs-11 fw-semibold text-uppercase">{{ __('hrms.payroll.employee_name') }}</div>
                        <div class="fw-bold text-dark mt-1 fs-13" id="modalEmployeeName"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted fs-11 fw-semibold text-uppercase">{{ __('hrms.payroll.employee_id_role') }}</div>
                        <div class="fw-bold text-dark mt-1 fs-13" id="modalEmployeeIdRole"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted fs-11 fw-semibold text-uppercase">{{ __('hrms.payroll.bank_account') }}</div>
                        <div class="fw-bold text-dark mt-1 fs-13" id="modalBankAccount"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted fs-11 fw-semibold text-uppercase">{{ __('hrms.payroll.proration_days') }}</div>
                        <div class="fw-bold text-dark mt-1 fs-13" id="modalProrationDays"></div>
                    </div>
                </div>

                <!-- Earnings & Deductions Two-Column -->
                <div class="row g-4">
                    <!-- Earnings -->
                    <div class="col-md-6">
                        <h6 class="fs-13 fw-bold text-success mb-3 d-flex align-items-center gap-2">
                            <i class="feather-plus-circle"></i>
                            <span>{{ __('hrms.payroll.earnings') }}</span>
                        </h6>
                        <div id="earningsTableBody" class="d-flex flex-column gap-2" style="min-height:60px;"></div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <span class="fw-bold text-dark fs-13">{{ __('hrms.payroll.gross_earnings') }}</span>
                            <span class="fw-bold text-dark fs-13" id="modalGrossEarnings">₹0.00</span>
                        </div>
                    </div>
                    <!-- Deductions -->
                    <div class="col-md-6">
                        <h6 class="fs-13 fw-bold text-danger mb-3 d-flex align-items-center gap-2">
                            <i class="feather-minus-circle"></i>
                            <span>{{ __('hrms.payroll.deductions') }}</span>
                        </h6>
                        <div id="deductionsTableBody" class="d-flex flex-column gap-2" style="min-height:60px;"></div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <span class="fw-bold text-dark fs-13">{{ __('hrms.payroll.total_deductions') }}</span>
                            <span class="fw-bold text-dark fs-13" id="modalTotalDeductions">₹0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Net Payout Alert -->
                <div class="alert alert-success d-flex align-items-center justify-content-between mt-4 mb-0 py-3 px-4 rounded-3 border">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-check-circle fs-18 text-success"></i>
                        <div id="modalNetLabel" class="d-flex align-items-center gap-2 fs-13 fw-semibold text-success">
                            {{ __('hrms.payroll.net_salary_payout') }}
                        </div>
                    </div>
                    <span class="fw-bold fs-18 text-success" id="modalNetPayout">₹0.00</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer bg-light border-top px-4 py-3 d-flex align-items-center justify-content-between">
                <div class="text-muted fs-11" id="modalReleasedBy"></div>
                <div class="d-flex gap-2">
                    <x-ui.button href="#" id="modalDownloadPayslipBtn" variant="primary" icon="feather-download" class="px-4">
                        {{ __('hrms.payroll.btn_download_payslip') }}
                    </x-ui.button>
                    <x-ui.button type="button" variant="light" class="border px-4" data-bs-dismiss="modal">
                        {{ __('hrms.common.close') }}
                    </x-ui.button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- RESOLVE PENDING ISSUES MODAL -->
<div class="modal fade" id="resolveIssuesModal" tabindex="-1" aria-labelledby="resolveIssuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow text-dark">
            <div class="modal-header border-bottom px-4 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 34px; height: 34px; background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class="feather-alert-triangle fs-16"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark mb-0 fs-15" id="resolveIssuesModalLabel">
                        {{ __('hrms.payroll.modal_resolve_title') }}
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            @if($selectedRun)
            <div class="modal-body p-4">
                <p class="fs-13 text-muted mb-4">
                    {!! __('hrms.payroll.modal_resolve_desc', ['month' => '<strong>' . Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') . '</strong>']) !!}
                </p>

                <div class="row g-3 mb-2">
                    <!-- Leaves Card -->
                    <div class="col-md-4 col-12">
                        @if(($pendingIssues['leaves'] ?? 0) > 0)
                            <a href="{{ route('hrms.leaves.index', ['leaves_status' => 'pending', 'payroll_month' => $selectedRun->payroll_month]) }}" class="card border border-warning-subtle rounded-3 p-3 text-decoration-none h-100 hover-shadow transition-all" style="background: #fffdf5; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 44px; height: 44px; background-color: rgba(255, 193, 7, 0.15); color: #d97706;">
                                        <i class="feather-minus-circle fs-20"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0.5" style="font-size: 14px;">{{ __('hrms.payroll.card_leave_requests') }}</h6>
                                        <div class="fs-11 text-muted mb-1.5">{{ __('hrms.payroll.card_resolve_leaves') }}</div>
                                        <span class="badge bg-soft-warning text-warning rounded-pill px-2.5 py-1" style="font-size: 11px;">{{ __('hrms.payroll.pending_count', ['count' => $pendingIssues['leaves']]) }}</span>
                                    </div>
                                </div>
                            </a>
                        @else
                            <div class="card border border-light bg-light rounded-3 p-3 h-100" style="opacity: 0.75; cursor: not-allowed; background-color: #f8fafc; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 44px; height: 44px; background-color: #f1f5f9; color: #94a3b8;">
                                        <i class="feather-minus-circle fs-20"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-muted mb-0.5" style="font-size: 14px;">{{ __('hrms.payroll.card_leave_requests') }}</h6>
                                        <div class="fs-11 text-muted mb-1.5">{{ __('hrms.payroll.card_no_leaves') }}</div>
                                        <span class="badge bg-soft-secondary text-secondary rounded-pill px-2.5 py-1" style="font-size: 10px;">{{ __('hrms.payroll.pending_count', ['count' => 0]) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Attendance Corrections Card -->
                    <div class="col-md-4 col-12">
                        @if(($pendingIssues['corrections'] ?? 0) > 0)
                            <a href="{{ route('hrms.attendance.index', ['view' => 'corrections', 'corrections_status' => 'pending', 'payroll_month' => $selectedRun->payroll_month]) }}" class="card border border-primary-subtle rounded-3 p-3 text-decoration-none h-100 hover-shadow transition-all" style="background: #f5f9ff; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 44px; height: 44px; background-color: rgba(13, 110, 253, 0.12); color: #0d6efd;">
                                        <i class="feather-clock fs-20"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0.5" style="font-size: 14px;">{{ __('hrms.payroll.card_attendance_corrections') }}</h6>
                                        <div class="fs-11 text-muted mb-1.5">{{ __('hrms.payroll.card_resolve_checkins') }}</div>
                                        <span class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-1" style="font-size: 11px;">{{ __('hrms.payroll.pending_count', ['count' => $pendingIssues['corrections']]) }}</span>
                                    </div>
                                </div>
                            </a>
                        @else
                            <div class="card border border-light bg-light rounded-3 p-3 h-100" style="opacity: 0.75; cursor: not-allowed; background-color: #f8fafc; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 44px; height: 44px; background-color: #f1f5f9; color: #94a3b8;">
                                        <i class="feather-clock fs-20"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-muted mb-0.5" style="font-size: 14px;">{{ __('hrms.payroll.card_attendance_corrections') }}</h6>
                                        <div class="fs-11 text-muted mb-1.5">{{ __('hrms.payroll.card_no_corrections') }}</div>
                                        <span class="badge bg-soft-secondary text-secondary rounded-pill px-2.5 py-1" style="font-size: 10px;">{{ __('hrms.payroll.pending_count', ['count' => 0]) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Overtime Requests Card -->
                    <div class="col-md-4 col-12">
                        @if(($pendingIssues['overtime'] ?? 0) > 0)
                            <a href="{{ route('hrms.shift-overtime.index', ['tab' => 'overtime', 'overtime_status' => 'pending', 'payroll_month' => $selectedRun->payroll_month]) }}" class="card border border-success-subtle rounded-3 p-3 text-decoration-none h-100 hover-shadow transition-all" style="background: #f5fdf7; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 44px; height: 44px; background-color: rgba(25, 135, 84, 0.12); color: #198754;">
                                        <i class="feather-watch fs-20"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0.5" style="font-size: 14px;">{{ __('hrms.payroll.card_overtime_requests') }}</h6>
                                        <div class="fs-11 text-muted mb-1.5">{{ __('hrms.payroll.card_resolve_overtime') }}</div>
                                        <span class="badge bg-soft-success text-success rounded-pill px-2.5 py-1" style="font-size: 11px;">{{ __('hrms.payroll.pending_count', ['count' => $pendingIssues['overtime']]) }}</span>
                                    </div>
                                </div>
                            </a>
                        @else
                            <div class="card border border-light bg-light rounded-3 p-3 h-100" style="opacity: 0.75; cursor: not-allowed; background-color: #f8fafc; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 44px; height: 44px; background-color: #f1f5f9; color: #94a3b8;">
                                        <i class="feather-watch fs-20" style="color: #94a3b8;"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-muted mb-0.5" style="font-size: 14px;">{{ __('hrms.payroll.card_overtime_requests') }}</h6>
                                        <div class="fs-11 text-muted mb-1.5">{{ __('hrms.payroll.card_no_overtime') }}</div>
                                        <span class="badge bg-soft-secondary text-secondary rounded-pill px-2.5 py-1" style="font-size: 10px;">{{ __('hrms.payroll.pending_count', ['count' => 0]) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Bulk Actions Tab Card -->
                    <div class="col-12 mt-3">
                        <div class="card border rounded-3 bg-light shadow-none p-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1.5 fs-14"><i class="feather-zap me-2 text-warning"></i>{{ __('hrms.payroll.card_bulk_resolve_title') }}</h6>
                                    <p class="fs-12 text-muted mb-0">{{ __('hrms.payroll.card_bulk_resolve_desc') }}</p>
                                </div>
                                <form action="{{ route('hrms.payroll.run.resolve-pending', $selectedRun->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <div class="d-flex gap-2">
                                        <x-ui.button type="submit" name="resolution_action" value="approve_all" variant="success" icon="feather-check-circle" class="fw-bold px-3.5 py-2 fs-12">
                                            {{ __('hrms.payroll.btn_auto_approve_all') }}
                                        </x-ui.button>
                                        <x-ui.button type="submit" name="resolution_action" value="reject_all" variant="outline-danger" icon="feather-x-circle" class="fw-bold px-3.5 py-2 fs-12">
                                            {{ __('hrms.payroll.btn_auto_reject_all') }}
                                        </x-ui.button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            
            <div class="modal-footer bg-light border-top">
                <x-ui.button type="button" variant="light" class="border fw-semibold px-4" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Ad-hoc Adjustments Modal -->
@if($selectedRun)
<div class="modal fade" id="bulkAdhocModal" tabindex="-1" aria-labelledby="bulkAdhocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="bulkAdhocModalLabel"><i class="feather-plus-circle me-2 text-primary"></i>{{ __('hrms.payroll.btn_bulk_adhoc') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.payroll.run.bulk-adhoc') }}" method="POST">
                @csrf
                <input type="hidden" name="payroll_month" value="{{ $selectedRun->payroll_month }}">
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.payroll.adhoc_component')" name="salary_component_id" :required="true">
                                <option value="">{{ __('hrms.payroll.select_component') }}</option>
                                @foreach($salaryComponents as $component)
                                    <option value="{{ $component->id }}">{{ $component->name }} ({{ strtoupper($component->type) }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.payroll.target_pay_group')" name="pay_group_id" :helperText="__('hrms.payroll.target_pay_group_helper')">
                                <option value="">{{ __('hrms.payroll.all_pay_groups') }}</option>
                                @foreach($payGroups as $pg)
                                    <option value="{{ $pg->id }}" {{ $selectedRun->pay_group_id == $pg->id ? 'selected' : '' }}>{{ $pg->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold fs-12 mb-1 text-dark">{{ __('hrms.payroll.recipient_employees') }} <span class="text-danger">*</span></label>
                            <input type="text" id="employeeSearchInput" class="form-control mb-2" placeholder="{{ __('hrms.payroll.search_employee_checklist') }}" style="font-size: 12px; height: 34px;">
                            
                            <div class="border rounded-3 p-3 bg-white" style="max-height: 180px; overflow-y: auto; border-color: #dee2e6;">
                                <div class="form-check mb-2 pb-2 border-bottom">
                                    <input class="form-check-input" type="checkbox" id="selectAllEmployees" checked>
                                    <label class="form-check-label fw-bold text-dark fs-12" for="selectAllEmployees">
                                        {{ __('hrms.payroll.select_all_filtered') }}
                                    </label>
                                </div>
                                <div id="employeeChecklistContainer">
                                    @foreach($allEmployees as $emp)
                                        <div class="form-check mb-1.5 employee-check-item" data-paygroup-id="{{ $emp->pay_group_id }}" data-name="{{ strtolower($emp->full_name) }}" data-id="{{ strtolower($emp->employee_id) }}">
                                            <input class="form-check-input employee-checkbox" type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" id="empCheck_{{ $emp->id }}" checked>
                                            <label class="form-check-label fs-12 text-dark" for="empCheck_{{ $emp->id }}">
                                                <span class="fw-semibold text-dark">{{ $emp->full_name }}</span> 
                                                <span class="text-muted fs-11">({{ $emp->employee_id }})</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.payroll.amount_inr')" name="amount" :required="true" placeholder="0.00" step="0.01" />
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.payroll.remarks_description')" name="remarks" :required="true" placeholder="{{ __('hrms.payroll.remarks_placeholder') }}" rows="3" />
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info rounded-3 mb-0" style="font-size: 11px;">
                                <i class="feather-info me-1.5 text-primary"></i>{!! __('hrms.payroll.bulk_adhoc_info', ['month' => '<strong>' . Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') . '</strong>']) !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <x-ui.button type="button" variant="light" class="border" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary">{{ __('hrms.payroll.btn_apply_adjustments') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let payrollSearchTimeout = null;
        let activePayrollRequest = null;

        function syncPayrollForms(params) {
            const fields = ['search', 'status', 'department_id', 'sort'];
            fields.forEach(name => {
                document.querySelectorAll(`#payrollRegisterFiltersBar [name="${name}"]`).forEach(field => {
                    // Skip the active search input so we don't disrupt active typing focus
                    if (field === document.activeElement) return;

                    field.value = params.get(name) || '';
                    if (field.tagName === 'SELECT' && $(field).hasClass('select2-hidden-accessible')) {
                        $(field).trigger('change.select2');
                    }
                });
            });

            // Sync the hidden inputs on the search form
            const statusInput = document.getElementById('payroll_status_input');
            if (statusInput) statusInput.value = params.get('status') || '';

            const deptInput = document.getElementById('payroll_dept_input');
            if (deptInput) deptInput.value = params.get('department_id') || '';

            const sortInput = document.getElementById('payroll_sort_input');
            if (sortInput) sortInput.value = params.get('sort') || 'name_asc';
        }

        function syncPayrollSortLinks(params) {
            const currentSort = params.get('sort') || 'name_asc';
            const links = document.querySelectorAll('.payroll-sort-link');
            links.forEach(link => {
                const sortVal = link.getAttribute('data-sort');
                link.classList.remove('active');
                const checkIcon = link.querySelector('.feather-check');
                if (checkIcon) {
                    checkIcon.remove();
                }

                if (sortVal === currentSort) {
                    link.classList.add('active');
                    const icon = document.createElement('i');
                    icon.className = 'feather-check ms-3';
                    link.appendChild(icon);
                }
            });
        }

        function refreshPayrollRegisterList(url) {
            const targetUrl = url instanceof URL ? url : new URL(url, window.location.origin);

            if (activePayrollRequest) {
                activePayrollRequest.abort();
            }

            const controller = new AbortController();
            activePayrollRequest = controller;

            const tableWrapper = document.getElementById('payrollRegisterTableWrapper');
            if (tableWrapper) {
                tableWrapper.style.opacity = '0.5';
            }

            fetch(targetUrl.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to refresh payroll register.');
                }
                return response.text();
            })
            .then(function (html) {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                
                const newTbody = doc.getElementById('payrollRegisterTableBody');
                const oldTbody = document.getElementById('payrollRegisterTableBody');
                if (newTbody && oldTbody) {
                    oldTbody.innerHTML = newTbody.innerHTML;
                }

                const newPagination = doc.getElementById('payrollRegisterPagination');
                const oldPagination = document.getElementById('payrollRegisterPagination');
                if (newPagination && oldPagination) {
                    oldPagination.innerHTML = newPagination.innerHTML;
                }

                // Sync forms and active sorting icons without disrupting active typing focus
                syncPayrollForms(targetUrl.searchParams);
                syncPayrollSortLinks(targetUrl.searchParams);

                // Push state to update browser URL
                history.pushState(null, '', targetUrl.toString());
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    window.location.href = targetUrl.toString();
                }
            })
            .finally(function () {
                if (activePayrollRequest === controller) {
                    if (tableWrapper) {
                        tableWrapper.style.opacity = '1';
                    }
                    activePayrollRequest = null;
                }
            });
        }

        // Debounced search input handler
        $(document).on('input', '#payrollSearchInput', function () {
            const form = this.closest('form');
            if (!form) return;
            const url = new URL(form.action || window.location.href);
            
            const formData = new FormData(form);
            for (const [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }

            if (payrollSearchTimeout) clearTimeout(payrollSearchTimeout);
            payrollSearchTimeout = setTimeout(function() {
                refreshPayrollRegisterList(url);
            }, 400);
        });

        // Sort click handler
        $(document).on('click', '.payroll-sort-link', function (e) {
            e.preventDefault();
            const sortVal = this.getAttribute('data-sort');
            const form = document.getElementById('payrollSearchForm');
            if (!form) return;
            
            const url = new URL(form.action || window.location.href);
            const formData = new FormData(form);
            for (const [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }
            url.searchParams.set('sort', sortVal);

            refreshPayrollRegisterList(url);
        });

        // Filter Form submit handler
        $(document).on('submit', '#payrollFilterForm', function (e) {
            e.preventDefault();
            const form = this;
            const url = new URL(form.action || window.location.href);
            
            const formData = new FormData(form);
            for (const [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }

            refreshPayrollRegisterList(url);
            
            // Close Bootstrap dropdown menu manually by triggering click on the filter toggle button
            const dropdownToggle = this.closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]');
            if (dropdownToggle) {
                dropdownToggle.click();
            }
        });

        // Reset Button and Clear All links click handlers
        $(document).on('click', '.payroll-reset-btn, .payroll-clear-all', function (e) {
            e.preventDefault();
            const url = new URL(this.getAttribute('href') || window.location.href);
            refreshPayrollRegisterList(url);
            
            // Close Bootstrap dropdown menu if clicked reset inside dropdown
            const dropdown = this.closest('.dropdown');
            if (dropdown) {
                const dropdownToggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
                if (dropdownToggle) {
                    dropdownToggle.click();
                }
            }
        });

        // Pagination links click handler for seamless AJAX pagination
        $(document).on('click', '#payrollRegisterPagination .page-link', function(e) {
            const href = this.getAttribute('href');
            if (href && href !== 'javascript:void(0);' && !this.closest('.disabled') && !this.closest('.active')) {
                e.preventDefault();
                const url = new URL(href, window.location.origin);
                
                // Preserve run_id, search, status, and department_id from the active forms
                const searchForm = document.getElementById('payrollSearchForm');
                if (searchForm) {
                    const formData = new FormData(searchForm);
                    for (const [key, val] of formData.entries()) {
                        if (val && !url.searchParams.has(key)) {
                            url.searchParams.set(key, val);
                        }
                    }
                }

                refreshPayrollRegisterList(url);
            }
        });

        // Initialize Select2 on dropdown display or page load
        if (typeof initOdooComponents === 'function') {
            initOdooComponents();
        }
        $(document).on('show.bs.dropdown', '.dropdown', function() {
            if (typeof initOdooComponents === 'function') {
                initOdooComponents();
            }
        });

        const modal = document.getElementById('initiateRunModal');
        if (modal) {
            document.body.appendChild(modal);
        }

        const salaryModal = document.getElementById('salaryDetailsModal');
        if (salaryModal) {
            document.body.appendChild(salaryModal);
        }

        const resolveModal = document.getElementById('resolveIssuesModal');
        if (resolveModal) {
            document.body.appendChild(resolveModal);
        }

        const bulkAdhocModal = document.getElementById('bulkAdhocModal');
        if (bulkAdhocModal) {
            document.body.appendChild(bulkAdhocModal);
            
            const payGroupSelect = bulkAdhocModal.querySelector('select[name="pay_group_id"]');
            const searchInput = document.getElementById('employeeSearchInput');
            const selectAllCheck = document.getElementById('selectAllEmployees');
            const checkItems = bulkAdhocModal.querySelectorAll('.employee-check-item');
            
            const filterEmployees = function() {
                const selectedGroupId = payGroupSelect ? $(payGroupSelect).val() : '';
                const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
                
                checkItems.forEach(item => {
                    const pgId = item.getAttribute('data-paygroup-id');
                    const name = item.getAttribute('data-name');
                    const id = item.getAttribute('data-id');
                    
                    const matchesGroup = !selectedGroupId || pgId === selectedGroupId;
                    const matchesSearch = !query || name.includes(query) || id.includes(query);
                    
                    if (matchesGroup && matchesSearch) {
                        item.style.setProperty('display', 'block', 'important');
                    } else {
                        item.style.setProperty('display', 'none', 'important');
                        // Auto uncheck hidden ones if pay group changed and it does not match
                        if (!matchesGroup) {
                            const checkbox = item.querySelector('.employee-checkbox');
                            if (checkbox) checkbox.checked = false;
                        }
                    }
                });
                
                updateSelectAllState();
            };

            const updateSelectAllState = function() {
                const visibleCheckboxes = Array.from(checkItems)
                    .filter(item => item.style.display !== 'none')
                    .map(item => item.querySelector('.employee-checkbox'))
                    .filter(checkbox => checkbox);
                
                if (visibleCheckboxes.length === 0) {
                    if (selectAllCheck) selectAllCheck.checked = false;
                    return;
                }
                
                const allChecked = visibleCheckboxes.every(checkbox => checkbox.checked);
                if (selectAllCheck) selectAllCheck.checked = allChecked;
            };

            if (payGroupSelect) {
                $(payGroupSelect).on('change', function() {
                    const selectedGroupId = this.value;
                    checkItems.forEach(item => {
                        const pgId = item.getAttribute('data-paygroup-id');
                        const checkbox = item.querySelector('.employee-checkbox');
                        if (checkbox) {
                            if (!selectedGroupId || pgId === selectedGroupId) {
                                checkbox.checked = true;
                            } else {
                                checkbox.checked = false;
                            }
                        }
                    });
                    filterEmployees();
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterEmployees);
            }

            if (selectAllCheck) {
                selectAllCheck.addEventListener('change', function() {
                    const isChecked = selectAllCheck.checked;
                    checkItems.forEach(item => {
                        if (item.style.display !== 'none') {
                            const checkbox = item.querySelector('.employee-checkbox');
                            if (checkbox) checkbox.checked = isChecked;
                        }
                    });
                });
            }

            // Individual checkbox click updates "Select All"
            bulkAdhocModal.addEventListener('change', function(e) {
                if (e.target.classList.contains('employee-checkbox')) {
                    updateSelectAllState();
                }
            });

            // Initial trigger to align checkboxes with default pay group filter if selected
            if (payGroupSelect && $(payGroupSelect).val()) {
                const selectedGroupId = $(payGroupSelect).val();
                checkItems.forEach(item => {
                    const pgId = item.getAttribute('data-paygroup-id');
                    const checkbox = item.querySelector('.employee-checkbox');
                    if (checkbox) {
                        checkbox.checked = (pgId === selectedGroupId);
                    }
                });
                filterEmployees();
            }
        }

        // Selection Mode Toggle inside Initiate Run Modal
        const selectionMode = document.getElementById('initiate_selection_mode');
        const payGroupDiv = document.getElementById('pay_group_selection_div');
        const employeeDiv = document.getElementById('employee_selection_div');

        if (selectionMode) {
            $(selectionMode).on('change', function() {
                if (this.value === 'individual') {
                    if (payGroupDiv) payGroupDiv.classList.add('d-none');
                    if (employeeDiv) {
                        employeeDiv.classList.remove('d-none');
                        // Trigger select2 initialization inside modal if not yet done
                        if (typeof initOdooComponents === 'function') {
                            initOdooComponents();
                        }
                    }
                } else {
                    if (payGroupDiv) payGroupDiv.classList.remove('d-none');
                    if (employeeDiv) employeeDiv.classList.add('d-none');
                }
            });
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
            const btn      = this;
            const name     = btn.getAttribute('data-employee-name') || '';
            const empId    = btn.getAttribute('data-employee-id') || '';
            const empDbId  = btn.getAttribute('data-employee-db-id') || '';
            const runId    = btn.getAttribute('data-run-id') || '';
            const jobTitle = btn.getAttribute('data-job-title') || '';
            const bank     = btn.getAttribute('data-bank') || '—';
            const bankAcc  = btn.getAttribute('data-bank-account') || '—';
            const proration = btn.getAttribute('data-proration-days') || '—';
            const month    = btn.getAttribute('data-month') || '';
            const runStatus = btn.getAttribute('data-run-status') || '';
            const releasedBy = btn.getAttribute('data-released-by') || '';
            const releasedAt = btn.getAttribute('data-released-at') || '';
            const items    = JSON.parse(btn.getAttribute('data-items') || '{}');
            const summary  = JSON.parse(btn.getAttribute('data-summary') || '{}');

            const downloadBtn = document.getElementById('modalDownloadPayslipBtn');
            if (downloadBtn) {
                downloadBtn.setAttribute('href', `/hrms/payroll/payslip/${runId}/${empDbId}/download`);
            }

            // Populate header info
            document.getElementById('modalPayrollMonth').textContent  = month;
            document.getElementById('modalEmployeeName').textContent   = name;
            document.getElementById('modalEmployeeIdRole').textContent = empId + (jobTitle ? ' • ' + jobTitle : '');
            document.getElementById('modalBankAccount').textContent    = bank !== '—' ? bank + ' (' + bankAcc + ')' : '—';
            document.getElementById('modalProrationDays').textContent  = proration;

            // Released / status label
            const netLabel = document.getElementById('modalNetLabel');
            const paidDaysVal = (summary.total_days || 30) - (summary.lop_days || 0);
            if (runStatus === 'paid') {
                netLabel.innerHTML = `<span>{{ __('hrms.payroll.net_salary_payout') }} (${paidDaysVal} {{ __('hrms.payroll.days') }})</span> <span class="badge bg-soft-success text-success ms-2">{{ __('hrms.payroll.released') }}</span>`;
            } else if (runStatus === 'locked') {
                netLabel.innerHTML = `<span>{{ __('hrms.payroll.net_salary_payout') }} (${paidDaysVal} {{ __('hrms.payroll.days') }})</span> <span class="badge bg-soft-warning text-warning ms-2">{{ __('hrms.payroll.status_locked') }}</span>`;
            } else {
                netLabel.innerHTML = `<span>{{ __('hrms.payroll.net_salary_payout') }} (${paidDaysVal} {{ __('hrms.payroll.days') }})</span> <span class="badge bg-soft-primary text-primary ms-2">{{ __('hrms.payroll.status_draft') }}</span>`;
            }

            const fmt = v => '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            let earningsHtml = '';
            let deductionsHtml = '';
            let grossEarnings = 0;
            let totalDeductions = 0;

            const codes = Object.keys(items);

            codes.forEach(code => {
                const item      = items[code];
                const calcVal   = parseFloat(item.calculated_value || 0);

                if (item.type === 'earning') {
                    grossEarnings += calcVal;
                    earningsHtml += `<div class="d-flex justify-content-between align-items-center fs-13 mb-1">
                        <span class="text-secondary">${item.name || ''}</span>
                        <span class="fw-semibold ${code.startsWith('ADHOC') || code.startsWith('BONUS') ? 'text-success' : 'text-dark'}">${code.startsWith('ADHOC') || code.startsWith('BONUS') ? '+' : ''}${fmt(calcVal)}</span>
                    </div>`;
                } else {
                    totalDeductions += calcVal;
                    deductionsHtml += `<div class="d-flex justify-content-between align-items-center fs-13 mb-1">
                        <span class="text-secondary">${item.name || ''}</span>
                        <span class="fw-semibold text-dark">${fmt(calcVal)}</span>
                    </div>`;
                }
            });

            if (!earningsHtml) {
                earningsHtml = '<div class="text-center text-muted py-3 fs-12">{{ __('hrms.payroll.no_earnings_defined') }}</div>';
            }
            if (!deductionsHtml) {
                deductionsHtml = '<div class="text-center text-muted py-3 fs-12">{{ __('hrms.payroll.no_deductions_defined') }}</div>';
            }

            document.getElementById('earningsTableBody').innerHTML   = earningsHtml;
            document.getElementById('deductionsTableBody').innerHTML = deductionsHtml;
            // Use PHP pre-computed summary totals — JS accumulation can miss items due to encoding
            document.getElementById('modalGrossEarnings').textContent  = fmt(summary.total_earnings || 0);
            document.getElementById('modalTotalDeductions').textContent = fmt(summary.total_deductions || 0);
            document.getElementById('modalNetPayout').textContent = fmt(summary.net_payout || 0);

            // Footer release info
            const releasedByEl = document.getElementById('modalReleasedBy');
            if (runStatus === 'paid' && releasedBy) {
                const template = @json(__('hrms.payroll.released_by_on', ['name' => '__NAME__', 'date' => '__DATE__']));
                releasedByEl.textContent = template.replace('__NAME__', releasedBy).replace('__DATE__', releasedAt ? releasedAt : '');
            } else {
                releasedByEl.textContent = '';
            }

            $('#salaryDetailsModal').modal('show');
        });
    });
</script>
@endpush
