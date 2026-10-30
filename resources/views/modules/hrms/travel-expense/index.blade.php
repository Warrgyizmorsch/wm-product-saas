@extends('layouts.duralux')

@section('title', __('hrms.travel_expense.meta_title'))
@section('page-title', __('hrms.travel_expense.page_title'))
@section('breadcrumb', __('hrms.travel_expense.breadcrumb'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Clean Odoo style table layouts */
        #claimsLinesTable {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 15px !important;
            font-size: 13px !important;
            border: none !important;
        }
        #claimsLinesTable thead {
            background-color: #f8fafc !important;
        }
        #claimsLinesTable th {
            background-color: #f8fafc !important;
            border: none !important;
            border-bottom: 2px solid #e2e8f0 !important;
            padding: 10px 12px !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }
        #claimsLinesTable td {
            padding: 8px 6px !important;
            border: none !important;
            border-bottom: 1px solid #e9ecef !important;
            vertical-align: middle !important;
            background-color: transparent !important;
        }

        /* Enterprise Expense Claim Items Table Styling */
        .expense-claims-table {
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            overflow: hidden !important;
        }
        .expense-claims-table thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            padding: 10px 12px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            vertical-align: middle !important;
        }
        .expense-claims-table tbody td {
            padding: 10px 10px !important;
            vertical-align: top !important;
            border-bottom: 1px solid #f1f5f9 !important;
            background-color: #ffffff !important;
        }
        .expense-claims-table tbody tr:hover td {
            background-color: #fafbfc !important;
        }
        .expense-claims-table .form-control-sm,
        .expense-claims-table .form-select-sm,
        .expense-claims-table select.category-select {
            font-size: 13px !important;
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            height: 36px !important;
            min-height: 36px !important;
            padding: 6px 12px !important;
            line-height: 1.5 !important;
            color: #1e293b !important;
            box-shadow: none !important;
            transform: none !important;
            box-sizing: border-box !important;
            vertical-align: middle !important;
        }
        .expense-claims-table .form-control-sm:focus,
        .expense-claims-table .form-select-sm:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
            outline: none !important;
            transform: none !important;
        }
        /* Select2 Common UI elements for Expense Table */
        .expense-claims-table .select2-container--bootstrap-5 {
            width: 100% !important;
            display: block !important;
        }
        .expense-claims-table .select2-container--bootstrap-5 .select2-selection--single {
            height: 36px !important;
            min-height: 36px !important;
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            padding: 0 10px !important;
            display: flex !important;
            align-items: center !important;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
        }
        .expense-claims-table .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #1e293b !important;
            font-size: 13px !important;
            line-height: 34px !important;
            padding-left: 0 !important;
            padding-right: 20px !important;
        }
        .expense-claims-table .select2-container--bootstrap-5.select2-container--focus .select2-selection--single,
        .expense-claims-table .select2-container--bootstrap-5 .select2-selection--single:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
        }
        .expense-claims-table .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            top: 50% !important;
            transform: translateY(-50%) !important;
            right: 8px !important;
            width: 20px !important;
            height: 20px !important;
        }
        body .select2-container--bootstrap-5 .select2-dropdown {
            border-color: #cbd5e1 !important;
            border-radius: 6px !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
            z-index: 1065 !important;
        }
        body .select2-container--bootstrap-5 .select2-results__option {
            font-size: 13px !important;
            padding: 8px 12px !important;
            color: #1e293b !important;
        }
        body .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: var(--bs-primary, #4f46e5) !important;
            color: #ffffff !important;
        }
        body .select2-container--bootstrap-5 .select2-results__option[aria-selected="true"] {
            background-color: color-mix(in srgb, var(--bs-primary, #4f46e5) 12%, #ffffff) !important;
            color: var(--bs-primary, #4f46e5) !important;
            font-weight: 600 !important;
        }
        .erp-custom-file-upload .file-upload-label {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 6px 10px !important;
            border: 1px dashed #cbd5e1 !important;
            border-radius: 6px !important;
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            width: 100% !important;
            height: 36px !important;
            justify-content: center !important;
            box-sizing: border-box !important;
            line-height: 22px !important;
        }
        .erp-custom-file-upload .file-upload-label:hover {
            background-color: #f1f5f9 !important;
            border-color: #94a3b8 !important;
            color: #1e293b !important;
        }
        /* Travel & Expense Tables: Prevent horizontal scrollbar & wrap long content to next line */
        .travel-expense-table {
            width: 100% !important;
            table-layout: fixed !important;
        }
        .travel-expense-table th,
        .travel-expense-table td {
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word !important;
            white-space: normal !important;
        }
        .travel-expense-table th.text-end,
        .travel-expense-table td.text-end {
            white-space: nowrap !important;
            overflow: visible !important;
        }
        .travel-expense-table th.text-center,
        .travel-expense-table td.text-center {
            white-space: nowrap !important;
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addTravelModal" id="btnRequestTravel" class="fw-bold text-uppercase">
            {{ __('hrms.travel_expense.btn_request_travel') }}
        </x-ui.button>
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addAdvanceModal" id="btnRequestAdvance" class="fw-bold text-uppercase d-none">
            {{ __('hrms.travel_expense.btn_request_advance') }}
        </x-ui.button>
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addReportModal" id="btnFileExpenseClaim" class="fw-bold text-uppercase d-none">
            {{ __('hrms.travel_expense.btn_file_expense_claim') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
    @php
        $activeTab = $activeTab ?? request('tab', 'travel');
        $workflowService = app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class);
        $expenseTabs = [
            [
                'id' => 'travel',
                'label' => __('hrms.travel_expense.tab_travel_requests'),
                'active' => ($activeTab === 'travel'),
                'icon' => 'feather-map-pin',
                'badge' => $travelRequests->total() ?: null,
            ],
            [
                'id' => 'advance',
                'label' => __('hrms.travel_expense.tab_cash_advances'),
                'active' => ($activeTab === 'advance'),
                'icon' => 'feather-dollar-sign',
                'badge' => $cashAdvances->total() ?: null,
            ],
            [
                'id' => 'report',
                'label' => __('hrms.travel_expense.tab_expense_claims'),
                'active' => ($activeTab === 'report'),
                'icon' => 'feather-file-text',
                'badge' => $expenseReports->total() ?: null,
            ],
        ];
    @endphp
    @if(session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-4">
            {{ session('success') }}
        </x-ui.alert>
    @endif
    @if(session('error'))
        <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    {{-- Tabs Workspace --}}
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <x-ui.horizontal-tabs id="expenseTabs" :tabs="$expenseTabs" :syncUrl="true" syncParam="tab" class="mb-3" />

        <div class="tab-content pt-2" id="expenseTabsContent">
            {{-- TRAVEL REQUESTS TAB PANE --}}
            <div class="tab-pane fade {{ $activeTab === 'travel' ? 'show active' : '' }}" id="travel" role="tabpanel" aria-labelledby="travel-tab">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">{{ __('hrms.travel_expense.tab_travel_requests') }}</h5>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" action="{{ route('hrms.travel-expense.index') }}" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                            <input type="hidden" name="tab" value="travel">
                            <input type="hidden" name="travel_sort" id="travel_sort_input" value="{{ $travelSort ?? 'newest' }}">
                            
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="travel_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.travel_expense.search_requests') }}" value="{{ $travelSearch ?? '' }}" style="box-shadow: none; height: 32px;">
                            </div>

                            <x-ui.sort-dropdown :label="__('hrms.travel_expense.sort')">
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($travelSort ?? 'newest') === 'newest' ? 'active' : '' }}" href="#" onclick="setSortParam('travel', 'newest'); event.preventDefault();">
                                    <span>{{ __('hrms.travel_expense.sort_newest') }}</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($travelSort ?? '') === 'oldest' ? 'active' : '' }}" href="#" onclick="setSortParam('travel', 'oldest'); event.preventDefault();">
                                    <span>{{ __('hrms.travel_expense.sort_oldest') }}</span>
                                </a>
                            </x-ui.sort-dropdown>

                            <x-ui.filter :label="__('hrms.travel_expense.filter')" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.travel_expense.filter_options') }}</h6>
                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.travel_expense.status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="travel_status">
                                        <option value="" {{ ($travelStatus ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.travel_expense.all_statuses') }}</option>
                                        <option value="pending" {{ ($travelStatus ?? '') === 'pending' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_pending') }}</option>
                                        <option value="approved" {{ ($travelStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_approved') }}</option>
                                        <option value="rejected" {{ ($travelStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_rejected') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="dropdown-divider my-3"></div>
                                <div class="d-flex gap-2">
                                    <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.travel_expense.apply') }}</x-ui.button>
                                    <x-ui.button href="{{ route('hrms.travel-expense.index', ['tab' => 'travel']) }}" variant="light" size="sm" class="border flex-grow-1 text-center">{{ __('hrms.travel_expense.reset') }}</x-ui.button>
                                </div>
                            </x-ui.filter>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 travel-expense-table" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 16%;">{{ __('hrms.travel_expense.th_employee') }}</th>
                                <th style="width: 18%;">{{ __('hrms.travel_expense.th_purpose') }}</th>
                                <th style="width: 13%;">{{ __('hrms.travel_expense.th_destination') }}</th>
                                <th style="width: 13%;">{{ __('hrms.travel_expense.th_dates') }}</th>
                                <th style="width: 12%;">{{ __('hrms.travel_expense.th_budget') }}</th>
                                <th class="text-center" style="width: 13%;">{{ __('hrms.travel_expense.th_status') }}</th>
                                <th class="text-end pe-3" style="width: 15%; min-width: 150px; white-space: nowrap;">{{ __('hrms.travel_expense.th_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="travelTableBody">
                            @forelse($travelRequests as $req)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark">{{ $req->employee->full_name }}</div>
                                        <div class="text-muted fs-11">{{ __('hrms.travel_expense.emp_id') }}: {{ $req->employee->employee_id }}</div>
                                    </td>
                                    <td>{{ $req->purpose }}</td>
                                    <td>{{ $req->destination }}</td>
                                    <td>
                                        <div class="text-dark fw-medium">{{ $req->start_date->format('M d, Y') }} - {{ $req->end_date->format('M d, Y') }}</div>
                                        <div class="text-muted fs-11">{{ $req->start_date->diffInDays($req->end_date) + 1 }} {{ __('hrms.travel_expense.days') }}</div>
                                    </td>
                                    <td class="fw-semibold">
                                        @if($req->status === 'approved' && $req->approved_budget)
                                            @if($req->approved_budget < $req->estimated_budget)
                                                <div class="text-success">{{ $currencySymbol }}{{ number_format($req->approved_budget, 2) }}</div>
                                                <div class="text-muted fs-11 text-decoration-line-through">{{ __('hrms.travel_expense.requested') }}: {{ $currencySymbol }}{{ number_format($req->estimated_budget, 2) }}</div>
                                            @else
                                                <div>{{ $currencySymbol }}{{ number_format($req->approved_budget, 2) }}</div>
                                            @endif
                                        @else
                                            <div>{{ $currencySymbol }}{{ number_format($req->estimated_budget, 2) }}</div>
                                        @endif

                                        @php
                                            $activeReport = $req->expenseReports->where('status', '!=', 'rejected')->first();
                                        @endphp
                                        @if($activeReport)
                                            <div class="mt-1 fs-11 text-primary fw-bold" title="{{ __('hrms.travel_expense.expense_claim_filed_tip') }}">
                                                <i class="feather-file-text me-0.5"></i>{{ __('hrms.travel_expense.claimed') }}: {{ $currencySymbol }}{{ number_format($activeReport->approved_amount ?: $activeReport->total_amount, 2) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <x-ui.status-badge :status="$req->status" :label="__('hrms.travel_expense.status_' . $req->status)" />

                                        @php
                                            $linkedAdv = $req->cashAdvances->first();
                                        @endphp
                                        @if($linkedAdv)
                                            <div class="mt-1 d-flex justify-content-center">
                                                @if($linkedAdv->status === 'pending' || $linkedAdv->status === 'l1_approved')
                                                    <span class="badge bg-soft-warning text-dark border border-warning-subtle px-2 py-0.5 fs-10 fw-semibold d-inline-flex align-items-center" title="{{ __('hrms.travel_expense.cash_advance_requested') }}"><i class="feather-dollar-sign me-0.5"></i>{{ __('hrms.travel_expense.advance_badge') }}: {{ $currencySymbol }}{{ number_format($linkedAdv->amount, 2) }}</span>
                                                @elseif($linkedAdv->status === 'approved' || $linkedAdv->status === 'disbursed')
                                                    <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 fs-10 fw-semibold d-inline-flex align-items-center" title="{{ __('hrms.travel_expense.cash_advance_approved') }}"><i class="feather-check-circle me-0.5"></i>{{ __('hrms.travel_expense.advance_approved_badge') }}</span>
                                                @elseif($linkedAdv->status === 'rejected')
                                                    <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-0.5 fs-10 fw-semibold d-inline-flex align-items-center" title="{{ __('hrms.travel_expense.cash_advance_rejected') }}"><i class="feather-x-circle me-0.5"></i>{{ __('hrms.travel_expense.advance_rejected_badge') }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3" style="white-space: nowrap;">
                                        @php
                                            $isTrOwner = $employee && $req->employee_id == $employee->id;
                                            $canApproveTr = $workflowService->canApprove(auth()->user(), $req->employee, $req, 'hrms.travel_expenses.approve');
                                        @endphp
                                        @if(in_array($req->status, ['pending', 'l1_approved']))
                                            @if($canApproveTr)
                                                <div class="d-flex justify-content-end align-items-center gap-1 flex-nowrap" style="white-space: nowrap;">
                                                    <x-ui.button type="button" variant="soft-success" size="sm" class="py-1 px-2.5 fw-bold fs-11 text-nowrap" data-bs-toggle="modal" data-bs-target="#approveTravelModal_{{ $req->id }}">
                                                        {{ __('hrms.travel_expense.btn_approve') }}
                                                    </x-ui.button>
                                                    <form method="POST" action="{{ route('hrms.travel-expense.travel.reject', $req) }}" class="m-0 d-inline-block">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="soft-danger" size="sm" class="py-1 px-2.5 fw-bold fs-11 text-nowrap">{{ __('hrms.travel_expense.btn_reject') }}</x-ui.button>
                                                    </form>
                                                </div>
                                                <!-- Modal to Approve Travel Request with Custom Budget & Linked Cash Advance -->
                                                <div class="modal fade text-start" id="approveTravelModal_{{ $req->id }}" tabindex="-1" aria-labelledby="approveTravelModalLabel_{{ $req->id }}" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                                                        <div class="modal-content text-dark">
                                                            <form method="POST" action="{{ route('hrms.travel-expense.travel.approve', $req) }}">
                                                                @csrf
                                                                <div class="modal-header">
                                                                    <h6 class="modal-title fw-bold" id="approveTravelModalLabel_{{ $req->id }}"><i class="feather-check-circle text-success me-1"></i> {{ __('hrms.travel_expense.approve_travel_title') }}</h6>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body d-flex flex-column gap-3">
                                                                    <p class="fs-12 text-muted mb-0">{{ __('hrms.travel_expense.approve_travel_desc') }}</p>
                                                                    
                                                                    <div class="p-3 bg-light rounded border">
                                                                        <div class="row g-2 fs-12">
                                                                            <div class="col-6">
                                                                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.requested_budget') }}</span>
                                                                                <strong class="text-dark">{{ $currencySymbol }}{{ number_format($req->estimated_budget, 2) }}</strong>
                                                                            </div>
                                                                            @php
                                                                                $pendingAdv = $req->cashAdvances->where('status', 'pending')->first();
                                                                            @endphp
                                                                            @if($pendingAdv)
                                                                                <div class="col-6">
                                                                                    <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.requested_advance') }}</span>
                                                                                    <strong class="text-primary">{{ $currencySymbol }}{{ number_format($pendingAdv->amount, 2) }}</strong>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>

                                                                    <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.travel_expense.approved_budget')" name="approved_budget" value="{{ $req->estimated_budget }}" step="0.01" min="0.01" :required="true" />

                                                                    @if($pendingAdv)
                                                                        <div class="p-3 bg-soft-info rounded border border-info-subtle mt-1">
                                                                            <div class="form-check ps-0 d-flex align-items-center gap-2 mb-2">
                                                                                <input class="form-check-input mt-0 ms-0" type="checkbox" name="approve_cash_advance" value="1" id="approve_advance_cb_{{ $req->id }}" checked style="float: none;" onchange="document.getElementById('advance_amt_wrapper_{{ $req->id }}').classList.toggle('d-none', !this.checked)">
                                                                                <label class="form-check-label fw-bold fs-12 text-dark mb-0" for="approve_advance_cb_{{ $req->id }}">
                                                                                    <i class="feather-dollar-sign text-info me-1"></i>{{ __('hrms.travel_expense.also_approve_advance') }}
                                                                                </label>
                                                                            </div>
                                                                            <div id="advance_amt_wrapper_{{ $req->id }}">
                                                                                <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.travel_expense.approved_advance_amount')" name="approved_advance_amount" value="{{ $pendingAdv->amount }}" step="0.01" min="0.01" />
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <x-ui.button type="button" variant="light" size="sm" class="border text-uppercase fw-bold fs-11" data-bs-dismiss="modal">{{ __('hrms.travel_expense.btn_cancel') }}</x-ui.button>
                                                                    <x-ui.button type="submit" variant="success" size="sm" class="text-uppercase fw-bold fs-11">{{ __('hrms.travel_expense.btn_approve') }}</x-ui.button>
                                                                </div>
                                                             </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @elseif($isTrOwner && in_array($req->status, ['pending', 'rejected']))
                                                <form method="POST" action="{{ route('hrms.travel-expense.travel.destroy', $req) }}" class="m-0" onsubmit="return confirm('{{ __('hrms.travel_expense.confirm_cancel_travel') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" class="py-1 fw-bold fs-11 text-uppercase" title="{{ __('hrms.travel_expense.title_cancel_request') }}">{{ __('hrms.travel_expense.btn_cancel') }}</x-ui.button>
                                                </form>
                                            @endif
                                        @elseif($req->status === 'approved')
                                            @php
                                                $activeUnpaidReport = $req->expenseReports->whereIn('status', ['draft', 'submitted', 'partially_approved', 'approved'])->first();
                                                $paidReport = $req->expenseReports->where('status', 'paid')->first();
                                                $approvedAdv = $req->cashAdvances->where('status', 'approved')->first();
                                            @endphp
                                            <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                                @if($approvedAdv && $isAdmin)
                                                    <form method="POST" action="{{ route('hrms.travel-expense.advance.disburse', $approvedAdv) }}" class="m-0">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="success" size="sm" icon="feather-credit-card" class="py-1 fw-bold fs-11 text-uppercase" title="{{ __('hrms.travel_expense.title_disburse_advance') }} ({{ $currencySymbol }}{{ number_format($approvedAdv->approved_amount ?? $approvedAdv->amount, 2) }})">
                                                            {{ __('hrms.travel_expense.btn_disburse_advance') }}
                                                        </x-ui.button>
                                                    </form>
                                                @endif

                                                @if($activeUnpaidReport)
                                                    @if($activeUnpaidReport->status === 'draft')
                                                        <span class="badge bg-secondary text-white px-2 py-1 fs-11 rounded-pill"><i class="feather-file-text me-1"></i>{{ __('hrms.travel_expense.claim_drafted') }}</span>
                                                    @elseif($activeUnpaidReport->status === 'submitted')
                                                        <span class="badge bg-soft-warning text-warning px-2 py-1 fs-11 rounded-pill"><i class="feather-clock me-1"></i>{{ __('hrms.travel_expense.claim_submitted') }}</span>
                                                    @elseif($activeUnpaidReport->status === 'partially_approved')
                                                        <span class="badge bg-soft-info text-info px-2 py-1 fs-11 rounded-pill"><i class="feather-alert-circle me-1"></i>{{ __('hrms.travel_expense.partially_approved') }}</span>
                                                    @elseif($activeUnpaidReport->status === 'approved')
                                                        <span class="badge bg-soft-success text-success px-2 py-1 fs-11 rounded-pill"><i class="feather-check-circle me-1"></i>{{ __('hrms.travel_expense.claim_approved') }}</span>
                                                    @endif
                                                @elseif($paidReport)
                                                    <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill"><i class="feather-check-circle me-1"></i>{{ __('hrms.travel_expense.claim_paid') }}</span>
                                                    @php
                                                        $hasPaidSupplementaryReq = $req->expenseReports->where('status', 'paid')->filter(function($r) {
                                                            return str_contains(strtolower($r->title), 'supplementary');
                                                        })->isNotEmpty();
                                                        $hasRejectedInMainReq = $paidReport->claims->where('status', 'rejected')->count() > 0;
                                                        $showReqSupplementary = $hasRejectedInMainReq && !$hasPaidSupplementaryReq;
                                                    @endphp
                                                    @if($showReqSupplementary && ($isTrOwner || $isAdmin))
                                                        <x-ui.button type="button" 
                                                                variant="soft-primary" size="sm" icon="feather-plus"
                                                                class="py-1 fw-bold fs-11 claim-travel-expense-btn"
                                                                data-travel-id="{{ $req->id }}"
                                                                data-cash-advance-id="">
                                                            {{ __('hrms.travel_expense.btn_supplementary_claim') }}
                                                        </x-ui.button>
                                                    @endif
                                                @elseif($isTrOwner || $isAdmin)
                                                    <x-ui.button type="button" 
                                                            variant="soft-primary" size="sm" icon="feather-dollar-sign"
                                                            class="py-1 fw-bold fs-11 claim-travel-expense-btn"
                                                            data-travel-id="{{ $req->id }}"
                                                            data-cash-advance-id="{{ $req->cashAdvances->whereIn('status', ['approved', 'disbursed'])->whereNull('expense_report_id')->first()?->id ?? '' }}">
                                                        {{ __('hrms.travel_expense.btn_claim_expense') }}
                                                    </x-ui.button>
                                                @endif
                                            </div>
                                        @elseif($req->status === 'rejected' && ($isTrOwner || $isAdmin))
                                            <div class="d-flex justify-content-end gap-1">
                                                <form method="POST" action="{{ route('hrms.travel-expense.travel.destroy', $req) }}" class="m-0" onsubmit="return confirm('{{ __('hrms.travel_expense.confirm_delete_travel') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" class="py-1 fw-bold fs-11 text-uppercase" title="{{ __('hrms.travel_expense.title_delete_request') }}">{{ __('hrms.travel_expense.btn_delete') }}</x-ui.button>
                                                </form>
                                            </div>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">{{ __('hrms.travel_expense.no_travel_requests') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3" id="travelPaginationWrapper">
                    <x-ui.pagination :paginator="$travelRequests" pageParam="travel_page" tab="travel" />
                </div>
            </div>

            {{-- CASH ADVANCES TAB PANE --}}
            <div class="tab-pane fade {{ $activeTab === 'advance' ? 'show active' : '' }}" id="advance" role="tabpanel" aria-labelledby="advance-tab">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">{{ __('hrms.travel_expense.cash_advances') }}</h5>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" action="{{ route('hrms.travel-expense.index') }}" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                            <input type="hidden" name="tab" value="advance">
                            <input type="hidden" name="advance_sort" id="advance_sort_input" value="{{ $advanceSort ?? 'newest' }}">
                            
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="advance_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.travel_expense.search_advances') }}" value="{{ $advanceSearch ?? '' }}" style="box-shadow: none; height: 32px;">
                            </div>

                            <x-ui.sort-dropdown :label="__('hrms.travel_expense.sort')">
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($advanceSort ?? 'newest') === 'newest' ? 'active' : '' }}" href="#" onclick="setSortParam('advance', 'newest'); event.preventDefault();">
                                    <span>{{ __('hrms.travel_expense.newest_first') }}</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($advanceSort ?? '') === 'oldest' ? 'active' : '' }}" href="#" onclick="setSortParam('advance', 'oldest'); event.preventDefault();">
                                    <span>{{ __('hrms.travel_expense.oldest_first') }}</span>
                                </a>
                            </x-ui.sort-dropdown>

                            <x-ui.filter :label="__('hrms.travel_expense.filter')" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.travel_expense.filter_options') }}</h6>
                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.travel_expense.status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="advance_status">
                                        <option value="" {{ ($advanceStatus ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.travel_expense.all_statuses') }}</option>
                                        <option value="pending" {{ ($advanceStatus ?? '') === 'pending' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_pending') }}</option>
                                        <option value="approved" {{ ($advanceStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_approved') }}</option>
                                        <option value="disbursed" {{ ($advanceStatus ?? '') === 'disbursed' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_disbursed') }}</option>
                                        <option value="settled" {{ ($advanceStatus ?? '') === 'settled' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_settled') }}</option>
                                        <option value="rejected" {{ ($advanceStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_rejected') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="dropdown-divider my-3"></div>
                                <div class="d-flex gap-2">
                                    <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.travel_expense.btn_apply') }}</x-ui.button>
                                    <x-ui.button href="{{ route('hrms.travel-expense.index', ['tab' => 'advance']) }}" variant="light" size="sm" class="border flex-grow-1 text-center">{{ __('hrms.travel_expense.btn_reset') }}</x-ui.button>
                                </div>
                            </x-ui.filter>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 travel-expense-table" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 20%;">{{ __('hrms.travel_expense.col_employee') }}</th>
                                <th style="width: 15%;">{{ __('hrms.travel_expense.col_amount') }}</th>
                                <th style="width: 35%;">{{ __('hrms.travel_expense.col_purpose_trip') }}</th>
                                <th class="text-center" style="width: 12%;">{{ __('hrms.travel_expense.col_status') }}</th>
                                <th class="text-end pe-3" style="width: 18%; min-width: 150px; white-space: nowrap;">{{ __('hrms.travel_expense.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="advanceTableBody">
                            @forelse($cashAdvances as $adv)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark">{{ $adv->employee->full_name }}</div>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        @if(($adv->status === 'approved' || $adv->status === 'disbursed' || $adv->status === 'settled') && $adv->approved_amount)
                                            @if($adv->approved_amount != $adv->amount)
                                                <div class="text-success">{{ $currencySymbol }}{{ number_format($adv->approved_amount, 2) }}</div>
                                                <div class="text-muted fs-11 text-decoration-line-through">{{ __('hrms.travel_expense.requested') }}: {{ $currencySymbol }}{{ number_format($adv->amount, 2) }}</div>
                                            @else
                                                <div>{{ $currencySymbol }}{{ number_format($adv->approved_amount, 2) }}</div>
                                            @endif
                                        @else
                                            <div>{{ $currencySymbol }}{{ number_format($adv->amount, 2) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="text-dark">{{ $adv->purpose }}</div>
                                        @if($adv->travelRequest)
                                            <div class="text-muted fs-11"><i class="feather-map me-1"></i>{{ __('hrms.travel_expense.trip_to') }} {{ $adv->travelRequest->destination }} ({{ $adv->travelRequest->purpose }})</div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <x-ui.status-badge :status="$adv->status" :label="__('hrms.travel_expense.status_' . $adv->status)" />
                                    </td>
                                    <td class="text-end pe-3" style="white-space: nowrap;">
                                        @php
                                            $isAdvOwner = $employee && $adv->employee_id == $employee->id;
                                            $canApproveAdv = $workflowService->canApprove(auth()->user(), $adv->employee, $adv, 'hrms.travel_expenses.approve');
                                        @endphp
                                        @if(in_array($adv->status, ['pending', 'l1_approved']))
                                            <div class="d-flex justify-content-end align-items-center gap-1 flex-nowrap" style="white-space: nowrap;">
                                                @if($canApproveAdv)
                                                    <x-ui.button type="button" variant="soft-success" size="sm" class="py-1 px-2.5 fw-bold fs-11 text-nowrap" data-bs-toggle="modal" data-bs-target="#approveAdvanceModal_{{ $adv->id }}">
                                                        {{ __('hrms.travel_expense.btn_approve') }}
                                                    </x-ui.button>
                                                    <form method="POST" action="{{ route('hrms.travel-expense.advance.reject', $adv) }}" class="m-0 d-inline-block">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="soft-danger" size="sm" class="py-1 px-2.5 fw-bold fs-11 text-nowrap">{{ __('hrms.travel_expense.btn_reject') }}</x-ui.button>
                                                    </form>
                                                @elseif($isAdvOwner && $adv->status === 'pending')
                                                    <form method="POST" action="{{ route('hrms.travel-expense.advance.destroy', $adv) }}" class="m-0 d-inline-block" onsubmit="return confirm('{{ __('hrms.travel_expense.confirm_cancel_advance') }}');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-ui.button type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" class="py-1 px-2.5 fw-bold fs-11 text-uppercase text-nowrap" title="{{ __('hrms.travel_expense.title_cancel_advance') }}">{{ __('hrms.travel_expense.btn_cancel') }}</x-ui.button>
                                                    </form>
                                                @endif
                                            </div>

                                            @if($canApproveAdv)
                                                <!-- Modal to Approve Cash Advance with Custom Amount -->
                                                <div class="modal fade text-start" id="approveAdvanceModal_{{ $adv->id }}" tabindex="-1" aria-labelledby="approveAdvanceModalLabel_{{ $adv->id }}" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
                                                        <div class="modal-content text-dark">
                                                            <form method="POST" action="{{ route('hrms.travel-expense.advance.approve', $adv) }}">
                                                                @csrf
                                                                <div class="modal-header">
                                                                    <h6 class="modal-title fw-bold" id="approveAdvanceModalLabel_{{ $adv->id }}"><i class="feather-check-circle text-success me-1"></i> {{ __('hrms.travel_expense.approve_advance_title') }}</h6>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body d-flex flex-column gap-3">
                                                                    <p class="fs-12 text-muted mb-0">{{ __('hrms.travel_expense.approve_advance_desc') }}</p>
                                                                    
                                                                    <div class="p-3 bg-light rounded border">
                                                                        <div class="fs-11 text-muted">{{ __('hrms.travel_expense.requested_amount') }}</div>
                                                                        <div class="fw-bold fs-15 text-dark">{{ $currencySymbol }}{{ number_format($adv->amount, 2) }}</div>
                                                                    </div>

                                                                    <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.travel_expense.approved_amount')" name="approved_amount" value="{{ $adv->amount }}" step="0.01" min="1" :required="true" />
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <x-ui.button type="button" variant="light" size="sm" class="border text-uppercase fw-bold fs-11" data-bs-dismiss="modal">{{ __('hrms.travel_expense.btn_cancel') }}</x-ui.button>
                                                                    <x-ui.button type="submit" variant="success" size="sm" class="text-uppercase fw-bold fs-11">{{ __('hrms.travel_expense.btn_approve') }}</x-ui.button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @elseif($adv->status === 'approved')
                                            @if($isAdmin)
                                                <div class="d-flex justify-content-end gap-1">
                                                    <form method="POST" action="{{ route('hrms.travel-expense.advance.disburse', $adv) }}" class="m-0">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="soft-primary" size="sm" icon="feather-dollar-sign" class="py-1 fw-bold fs-11">{{ __('hrms.travel_expense.btn_disburse') }}</x-ui.button>
                                                    </form>
                                                </div>
                                            @else
                                                -
                                            @endif
                                        @elseif($adv->status === 'rejected' && ($isAdvOwner || $isAdmin))
                                            <div class="d-flex justify-content-end gap-1">
                                                <form method="POST" action="{{ route('hrms.travel-expense.advance.destroy', $adv) }}" class="m-0" onsubmit="return confirm('{{ __('hrms.travel_expense.confirm_delete_advance') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" class="py-1 fw-bold fs-11 text-uppercase" title="{{ __('hrms.travel_expense.title_delete_advance') }}">{{ __('hrms.travel_expense.btn_delete') }}</x-ui.button>
                                                </form>
                                            </div>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">{{ __('hrms.travel_expense.no_cash_advances') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3" id="advancePaginationWrapper">
                    <x-ui.pagination :paginator="$cashAdvances" pageParam="advance_page" tab="advance" />
                </div>
            </div>

            {{-- EXPENSE REPORTS TAB PANE --}}
            <div class="tab-pane fade {{ $activeTab === 'report' ? 'show active' : '' }}" id="report" role="tabpanel" aria-labelledby="report-tab">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">{{ __('hrms.travel_expense.expense_claims') }}</h5>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" action="{{ route('hrms.travel-expense.index') }}" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                            <input type="hidden" name="tab" value="report">
                            <input type="hidden" name="report_sort" id="report_sort_input" value="{{ $reportSort ?? 'newest' }}">
                            
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="report_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.travel_expense.search_claims') }}" value="{{ $reportSearch ?? '' }}" style="box-shadow: none; height: 32px;">
                            </div>

                            <x-ui.sort-dropdown :label="__('hrms.travel_expense.sort')">
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($reportSort ?? 'newest') === 'newest' ? 'active' : '' }}" href="#" onclick="setSortParam('report', 'newest'); event.preventDefault();">
                                    <span>{{ __('hrms.travel_expense.newest_first') }}</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($reportSort ?? '') === 'oldest' ? 'active' : '' }}" href="#" onclick="setSortParam('report', 'oldest'); event.preventDefault();">
                                    <span>{{ __('hrms.travel_expense.oldest_first') }}</span>
                                </a>
                            </x-ui.sort-dropdown>

                            <x-ui.filter :label="__('hrms.travel_expense.filter')" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.travel_expense.filter_options') }}</h6>
                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.travel_expense.status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="report_status">
                                        <option value="" {{ ($reportStatus ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.travel_expense.all_statuses') }}</option>
                                        <option value="draft" {{ ($reportStatus ?? '') === 'draft' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_draft') }}</option>
                                        <option value="submitted" {{ ($reportStatus ?? '') === 'submitted' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_submitted') }}</option>
                                        <option value="approved" {{ ($reportStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_approved') }}</option>
                                        <option value="paid" {{ ($reportStatus ?? '') === 'paid' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_paid') }}</option>
                                        <option value="rejected" {{ ($reportStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.travel_expense.status_rejected') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="dropdown-divider my-3"></div>
                                <div class="d-flex gap-2">
                                    <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.travel_expense.btn_apply') }}</x-ui.button>
                                    <x-ui.button href="{{ route('hrms.travel-expense.index', ['tab' => 'report']) }}" variant="light" size="sm" class="border flex-grow-1 text-center">{{ __('hrms.travel_expense.btn_reset') }}</x-ui.button>
                                </div>
                            </x-ui.filter>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 travel-expense-table" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 22%;">{{ __('hrms.travel_expense.col_employee_title') }}</th>
                                <th style="width: 28%;">{{ __('hrms.travel_expense.col_claim_items') }}</th>
                                <th style="width: 24%;">{{ __('hrms.travel_expense.col_totals') }}</th>
                                <th class="text-center" style="width: 12%;">{{ __('hrms.travel_expense.col_status') }}</th>
                                <th class="text-end pe-3" style="width: 14%; min-width: 160px; white-space: nowrap;">{{ __('hrms.travel_expense.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody">
                            @forelse($expenseReports as $rep)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark">{{ $rep->title }}</div>
                                        <div class="text-muted fs-11">{{ __('hrms.travel_expense.submitted_by') }}: {{ $rep->employee->full_name }}</div>
                                        @if($rep->travelRequest)
                                            <span class="badge bg-soft-secondary text-secondary mt-1 fs-10"><i class="feather-map me-1"></i>{{ __('hrms.travel_expense.trip_id') }}: TR-{{ $rep->travelRequest->id }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($rep->claims as $claim)
                                                <div class="fs-12 text-muted">
                                                    <span class="fw-semibold text-primary">• {{ $claim->category->name }}</span>: {{ $currencySymbol }}{{ number_format($claim->amount, 2) }}
                                                    @if($claim->receipt_paths)
                                                        @foreach($claim->receipt_paths as $k => $rPath)
                                                            <a href="{{ asset('storage/' . $rPath) }}" target="_blank" class="ms-1 text-info fs-10 fw-bold me-1"><i class="feather-image"></i> {{ __('hrms.travel_expense.receipt') }} {{ count($claim->receipt_paths) > 1 ? ($k + 1) : '' }}</a>
                                                        @endforeach
                                                    @endif
                                                    @if(($claim->status ?? '') === 'rejected')
                                                        <div class="ms-2 mt-0.5 fs-11 text-danger fw-semibold">
                                                            <i class="feather-x-circle me-1"></i>{{ __('hrms.travel_expense.status_rejected') }}: <span class="fw-normal text-danger">{{ $claim->rejection_reason ?? __('hrms.travel_expense.reason_not_specified') }}</span>
                                                        </div>
                                                    @elseif(($claim->status ?? '') === 'approved' && $rep->status === 'partially_approved')
                                                        <div class="ms-2 mt-0.5 fs-10 text-success fw-semibold">
                                                            <i class="feather-check-circle me-1"></i>{{ __('hrms.travel_expense.status_approved') }}: {{ $currencySymbol }}{{ number_format($claim->approved_amount ?? $claim->amount, 2) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $trAdvances = $rep->travelRequest ? $rep->travelRequest->cashAdvances : collect();
                                            $fullAdvance = $trAdvances->whereIn('status', ['approved', 'disbursed', 'settled', 'paid'])->sum(fn($ca) => floatval($ca->approved_amount ?? $ca->amount));
                                            if ($fullAdvance <= 0 && $rep->cashAdvance) {
                                                $fullAdvance = floatval($rep->cashAdvance->approved_amount ?? $rep->cashAdvance->amount);
                                            }

                                            $tripReports = $rep->travelRequest ? $rep->travelRequest->expenseReports->sortBy('id') : collect([$rep]);
                                            
                                            // Calculate cumulative advance adjusted before this report
                                            $prevAdjusted = $tripReports->filter(function($r) use ($rep) {
                                                return $r->id < $rep->id && in_array($r->status, ['approved', 'partially_approved', 'paid']);
                                            })->sum(function($r) use ($fullAdvance) {
                                                $rApprovedVal = in_array($r->status, ['approved', 'paid']) ? floatval($r->approved_amount ?? $r->total_amount) : floatval($r->total_amount);
                                                $adv = floatval($r->advance_adjusted);
                                                if ($adv <= 0 && $fullAdvance > 0) {
                                                    $adv = min($fullAdvance, $rApprovedVal);
                                                }
                                                return $adv;
                                            });

                                            $repApprovedVal = in_array($rep->status, ['approved', 'paid']) ? floatval($rep->approved_amount ?? $rep->total_amount) : floatval($rep->total_amount);
                                            
                                            // Effective adjusted advance & reimbursement
                                            $remAdv = max($fullAdvance - $prevAdjusted, 0.00);
                                            $dispAdjusted = floatval($rep->advance_adjusted);
                                            if ($dispAdjusted <= 0 && $remAdv > 0 && in_array($rep->status, ['approved', 'paid', 'submitted', 'partially_approved', 'draft'])) {
                                                $dispAdjusted = min($remAdv, $repApprovedVal);
                                            }
                                            $dispReimbursement = max($repApprovedVal - $dispAdjusted, 0.00);

                                            // Total adjusted across ALL reports for this trip
                                            $totalTripAdjusted = $tripReports->whereIn('status', ['approved', 'partially_approved', 'paid', 'submitted'])->sum(function($r) use ($rep, $dispAdjusted, $fullAdvance) {
                                                if ($r->id === $rep->id) {
                                                    return $dispAdjusted;
                                                }
                                                $rApprovedVal = in_array($r->status, ['approved', 'paid']) ? floatval($r->approved_amount ?? $r->total_amount) : floatval($r->total_amount);
                                                $adv = floatval($r->advance_adjusted);
                                                if ($adv <= 0 && $fullAdvance > 0) {
                                                    $adv = min($fullAdvance, $rApprovedVal);
                                                }
                                                return $adv;
                                            });

                                            $netUnadjustedAdvance = max($fullAdvance - $totalTripAdjusted, 0.00);
                                            $surplus = $netUnadjustedAdvance;
                                            $currentExpense = $repApprovedVal;
                                        @endphp

                                        @if(($rep->status === 'approved' || $rep->status === 'paid') && $rep->approved_amount)
                                            @if($rep->approved_amount != $rep->total_amount)
                                                <div class="fs-12 text-muted">{{ __('hrms.travel_expense.approved_expense') }}: <span class="fw-bold text-success">{{ $currencySymbol }}{{ number_format($rep->approved_amount, 2) }}</span></div>
                                                <div class="fs-11 text-muted text-decoration-line-through">{{ __('hrms.travel_expense.requested') }}: {{ $currencySymbol }}{{ number_format($rep->total_amount, 2) }}</div>
                                            @else
                                                <div class="fs-12 text-muted">{{ __('hrms.travel_expense.approved_expense') }}: <span class="fw-bold text-dark">{{ $currencySymbol }}{{ number_format($rep->approved_amount, 2) }}</span></div>
                                            @endif
                                            <div class="fs-12 text-muted">{{ __('hrms.travel_expense.advance_adjusted') }}: <span class="text-dark">{{ $currencySymbol }}{{ number_format($dispAdjusted, 2) }}</span></div>
                                            <div class="fs-12 fw-bold text-primary mt-1">{{ __('hrms.travel_expense.reimbursement') }}: {{ $currencySymbol }}{{ number_format($dispReimbursement, 2) }}</div>
                                        @else
                                            <div class="fs-12 text-muted">{{ __('hrms.travel_expense.total_expense') }}: <span class="fw-bold text-dark">{{ $currencySymbol }}{{ number_format($rep->total_amount, 2) }}</span></div>
                                            <div class="fs-12 text-muted">{{ __('hrms.travel_expense.advance_adjusted') }}: <span class="text-dark">{{ $currencySymbol }}{{ number_format($dispAdjusted, 2) }}</span></div>
                                            <div class="fs-12 fw-bold text-primary mt-1">{{ __('hrms.travel_expense.reimbursement') }}: {{ $currencySymbol }}{{ number_format($dispReimbursement, 2) }}</div>
                                        @endif

                                        @if($surplus > 0)
                                            <x-ui.badge variant="warning" :soft="true" class="mt-1.5 fs-10 px-2 py-1 rounded-pill d-inline-flex align-items-center"><i class="feather-alert-triangle me-1"></i>{{ __('hrms.travel_expense.refund_due') }}: {{ $currencySymbol }}{{ number_format($surplus, 2) }}</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <x-ui.status-badge :status="$rep->status" :label="__('hrms.travel_expense.status_' . $rep->status)" />

                                        @if($rep->status === 'approved' || $rep->status === 'paid' || $rep->status === 'partially_approved')
                                            <div class="mt-1">
                                                @if(($rep->payout_channel ?? 'accounting') === 'payroll')
                                                    <x-ui.badge variant="info" :soft="true" class="px-1.5 py-0.5 fs-10"><i class="feather-user me-0.5"></i>{{ __('hrms.travel_expense.payroll') }}</x-ui.badge>
                                                @else
                                                    <x-ui.badge variant="primary" :soft="true" class="px-1.5 py-0.5 fs-10"><i class="feather-dollar-sign me-0.5"></i>{{ __('hrms.travel_expense.accounting') }}</x-ui.badge>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-flex justify-content-end gap-1">
                                            <x-ui.icon-btn type="button" variant="soft-info" size="sm" class="btn-view-report"
                                                icon="feather-eye"
                                                :title="__('hrms.travel_expense.title_view_details')"
                                                data-id="{{ $rep->id }}"
                                                data-title="{{ $rep->title }}"
                                                data-employee="{{ $rep->employee->full_name }}"
                                                data-travel="{{ $rep->travelRequest ? $rep->travelRequest->destination . ' (' . $rep->travelRequest->purpose . ')' : 'None' }}"
                                                data-advance="{{ $rep->cashAdvance ? $currencySymbol . number_format($rep->cashAdvance->approved_amount ?? $rep->cashAdvance->amount, 2) . ' - ' . $rep->cashAdvance->purpose : ($fullAdvance > 0 ? $currencySymbol . number_format($fullAdvance, 2) . ' - Travel Trip Advance' : 'None') }}"
                                                data-total="{{ $currencySymbol }}{{ number_format($rep->total_amount, 2) }}"
                                                data-adjusted="{{ $currencySymbol }}{{ number_format($dispAdjusted, 2) }}"
                                                data-net="{{ $currencySymbol }}{{ number_format($dispReimbursement, 2) }}"
                                                data-status="{{ ucfirst(str_replace('_', ' ', $rep->status)) }}"
                                                data-payout-channel="{{ $rep->payout_channel ?? 'accounting' }}"
                                                data-claims="{{ $rep->claims->load('category') }}"
                                                data-full-advance="{{ $fullAdvance }}"
                                                data-current-expense="{{ $currentExpense }}"
                                                data-surplus="{{ $surplus }}"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewReportModal"
                                            />

                                            @php
                                                $isOwner = $employee && $rep->employee_id == $employee->id;
                                                $canApproveRep = $workflowService->canApprove(auth()->user(), $rep->employee, $rep, 'hrms.travel_expenses.approve');
                                            @endphp

                                            {{-- EMPLOYEE / OWNER ACTIONS --}}
                                            @if(($isOwner || $isAdmin) && (in_array($rep->status, ['draft', 'partially_approved']) || ($rep->status === 'rejected' && !$rep->travel_request_id)))
                                                <x-ui.icon-btn type="button" variant="soft-primary" size="sm" class="btn-edit-report"
                                                    icon="feather-edit-3"
                                                    :title="in_array($rep->status, ['partially_approved', 'rejected']) ? __('hrms.travel_expense.title_fix_resubmit') : __('hrms.travel_expense.title_edit_report')"
                                                    data-id="{{ $rep->id }}"
                                                    data-title="{{ $rep->title }}"
                                                    data-status="{{ $rep->status }}"
                                                    data-travel-id="{{ $rep->travel_request_id }}"
                                                    data-advance-id="{{ $rep->cash_advance_id }}"
                                                    data-advance-text="{{ $rep->cashAdvance ? ($currencySymbol . number_format($rep->cashAdvance->approved_amount ?? $rep->cashAdvance->amount, 2) . ' - ' . $rep->cashAdvance->purpose) : '' }}"
                                                    data-claims="{{ $rep->claims->load('category') }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editReportModal"
                                                />
                                            @endif

                                            @if(($isOwner || $isAdmin) && $rep->status === 'draft')
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.submit', $rep) }}" class="m-0">
                                                    @csrf
                                                    <x-ui.button type="submit" variant="primary" size="sm" class="py-1 fw-bold fs-11 text-uppercase">{{ __('hrms.travel_expense.btn_submit') }}</x-ui.button>
                                                </form>
                                            @endif

                                            @php
                                                $tripReports = $rep->travelRequest ? $rep->travelRequest->expenseReports : collect();
                                                $hasUnpaidReportForTrip = $tripReports->whereIn('status', ['draft', 'submitted', 'partially_approved', 'approved'])->where('id', '!=', $rep->id)->isNotEmpty();
                                                $hasPaidSupplementaryForTrip = $tripReports->where('id', '!=', $rep->id)->where('status', 'paid')->filter(function($r) {
                                                    return str_contains(strtolower($r->title), 'supplementary');
                                                })->isNotEmpty();
                                                $isSupplementaryReport = str_contains(strtolower($rep->title), 'supplementary');
                                                $hasRejectedItems = $rep->claims->where('status', 'rejected')->count() > 0;

                                                $showSupplementary = $rep->travel_request_id 
                                                    && !$isSupplementaryReport 
                                                    && !$hasPaidSupplementaryForTrip 
                                                    && !$hasUnpaidReportForTrip 
                                                    && (
                                                        $rep->status === 'rejected' ||
                                                        ($rep->status === 'paid' && $hasRejectedItems)
                                                    );
                                            @endphp

                                            @if(($isOwner || $isAdmin) && $showSupplementary)
                                                <x-ui.button type="button" 
                                                        variant="soft-primary"
                                                        size="sm"
                                                        icon="feather-plus"
                                                        class="py-1 fw-bold fs-11 claim-supplementary-btn"
                                                        data-travel-id="{{ $rep->travel_request_id }}"
                                                        data-cash-advance-id=""
                                                        data-title="Supplementary Claim - {{ $rep->travelRequest->destination ?? $rep->title }}"
                                                        title="{{ __('hrms.travel_expense.title_file_supplementary') }}">
                                                    {{ __('hrms.travel_expense.btn_supplementary') }}
                                                </x-ui.button>
                                            @endif

                                            {{-- ADMIN ACTIONS --}}
                                            @if($canApproveRep)
                                                @if(in_array($rep->status, ['submitted', 'pending', 'l1_approved']))
                                                    <x-ui.button type="button" variant="soft-success" size="sm" class="py-1 px-2.5 fw-bold fs-11 text-nowrap text-uppercase" data-bs-toggle="modal" data-bs-target="#approveReportModal_{{ $rep->id }}">
                                                        {{ __('hrms.travel_expense.btn_review_approve') }}
                                                    </x-ui.button>

                                                    <form method="POST" action="{{ route('hrms.travel-expense.report.reject', $rep) }}" class="m-0 d-inline-block">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="soft-danger" size="sm" class="py-1 px-2.5 fw-bold fs-11 text-nowrap text-uppercase">{{ __('hrms.travel_expense.btn_reject') }}</x-ui.button>
                                                    </form>

                                                    <!-- Modal to Approve Expense Report with Custom Amount & Itemized Partial Approval -->
                                                    <div class="modal fade text-start" id="approveReportModal_{{ $rep->id }}" data-rem-advance="{{ $remAdv }}" tabindex="-1" aria-labelledby="approveReportModalLabel_{{ $rep->id }}" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <form method="POST" action="{{ route('hrms.travel-expense.report.approve', $rep) }}">
                                                                    @csrf
                                                                    <input type="hidden" name="approved_amount" id="approve_amount_input_{{ $rep->id }}" value="{{ $rep->total_amount }}">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title fw-bold" id="approveReportModalLabel_{{ $rep->id }}"><i class="feather-check-square text-success me-1"></i> {{ __('hrms.travel_expense.review_approve_report_title') }}</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body d-flex flex-column gap-3 text-start">
                                                                        <div class="alert alert-info border-info-subtle fs-12 mb-0 d-flex gap-2 align-items-center">
                                                                            <i class="feather-info text-info fs-15"></i>
                                                                            <div>{{ __('hrms.travel_expense.review_approve_report_desc') }}</div>
                                                                        </div>
                                                                        <x-ui.odoo-form-ui type="table">
                                                                             <thead>
                                                                                 <tr>
                                                                                     <th style="width: 30%;">{{ __('hrms.travel_expense.col_item_category') }}</th>
                                                                                     <th style="width: 15%;">{{ __('hrms.travel_expense.col_date') }}</th>
                                                                                     <th style="width: 15%;">{{ __('hrms.travel_expense.col_claimed_amount') }}</th>
                                                                                     <th style="width: 20%;">{{ __('hrms.travel_expense.col_decision') }}</th>
                                                                                     <th style="width: 20%;">{{ __('hrms.travel_expense.col_rejection_reason') }}</th>
                                                                                 </tr>
                                                                             </thead>
                                                                             <tbody>
                                                                                 @foreach($rep->claims as $cItem)
                                                                                     <tr>
                                                                                         <td>
                                                                                             <div class="fw-bold text-dark">{{ $cItem->category->name ?? 'Claim Item' }}</div>
                                                                                         </td>
                                                                                         <td>{{ $cItem->expense_date ? \Carbon\Carbon::parse($cItem->expense_date)->format('M d, Y') : '' }}</td>
                                                                                         <td class="fw-bold text-dark">{{ $currencySymbol }}{{ number_format($cItem->amount, 2) }}</td>
                                                                                         <td>
                                                                                              <select name="items[{{ $cItem->id }}][decision]" 
                                                                                                          id="item_decision_sel_{{ $cItem->id }}"
                                                                                                          class="odoo-table-select claim-item-decision-select fw-semibold" 
                                                                                                          data-claim-id="{{ $cItem->id }}"
                                                                                                          data-amount="{{ $cItem->amount }}"
                                                                                                          data-full-advance="{{ $fullAdvance }}"
                                                                                                          onchange="handleItemDecisionChange(this, {{ $rep->id }}, {{ $fullAdvance }})">
                                                                                                  <option value="approved" @selected(($cItem->status ?? 'approved') !== 'rejected')>{{ __('hrms.travel_expense.btn_approve') }}</option>
                                                                                                  <option value="rejected" @selected(($cItem->status ?? '') === 'rejected')>{{ __('hrms.travel_expense.btn_reject') }}</option>
                                                                                              </select>
                                                                                              <input type="hidden" name="items[{{ $cItem->id }}][approved_amount]" id="item_approved_amt_{{ $cItem->id }}" value="{{ (($cItem->status ?? '') === 'rejected') ? 0 : $cItem->amount }}">
                                                                                          </td>
                                                                                          <td>
                                                                                              <input type="text" 
                                                                                                     name="items[{{ $cItem->id }}][rejection_reason]" 
                                                                                                     id="item_rejection_reason_{{ $cItem->id }}" 
                                                                                                     class="odoo-table-input item-rejection-input {{ (($cItem->status ?? '') === 'rejected') ? '' : 'd-none' }}" 
                                                                                                     placeholder="{{ __('hrms.travel_expense.reason_placeholder') }}" 
                                                                                                     value="{{ $cItem->rejection_reason ?? '' }}" />
                                                                                          </td>
                                                                                     </tr>
                                                                                 @endforeach
                                                                             </tbody>
                                                                         </x-ui.odoo-form-ui>
                                                                        <div id="surplus_payroll_notice_{{ $rep->id }}" class="alert alert-warning border-warning-subtle fs-11 py-2 px-2.5 mt-1 mb-0 d-none animate__animated animate__fadeIn">
                                                                            <i class="feather-alert-triangle me-1"></i> <strong>{{ __('hrms.travel_expense.surplus_refund_label') }}:</strong> {{ __('hrms.travel_expense.surplus_refund_desc') }}
                                                                        </div>

                                                                        <div class="bg-light rounded border fs-12 d-flex flex-column text-dark mt-3" style="padding: 16px; gap: 10px;">
                                                                            <div class="d-flex justify-content-between align-items-center">
                                                                                <span class="text-muted fw-medium">{{ __('hrms.travel_expense.calculated_approved_total') }}:</span>
                                                                                <strong id="calc_payout_{{ $rep->id }}" class="text-primary fs-13">{{ $currencySymbol }}0.00</strong>
                                                                            </div>
                                                                            <div id="calc_surplus_wrapper_{{ $rep->id }}" class="d-flex justify-content-between align-items-center d-none text-warning fw-bold">
                                                                                <span>{{ __('hrms.travel_expense.refund_due_to_company') }}:</span>
                                                                                <span id="calc_surplus_{{ $rep->id }}" class="fs-13">{{ $currencySymbol }}0.00</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer bg-light border-top">
                                                                        <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal" class="fw-bold text-uppercase">{{ __('hrms.travel_expense.btn_cancel') }}</x-ui.button>
                                                                        <x-ui.button type="submit" variant="success" icon="feather-check-circle" class="fw-bold text-uppercase">{{ __('hrms.travel_expense.btn_approve_expense_report') }}</x-ui.button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endif

                                            @if($isAdmin && in_array($rep->status, ['approved', 'partially_approved']))
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.pay', $rep) }}" class="m-0">
                                                    @csrf
                                                    <x-ui.button type="submit" variant="success" size="sm" icon="feather-credit-card" class="py-1 fw-bold fs-11 text-uppercase" title="{{ __('hrms.travel_expense.title_pay_out') }} ({{ $currencySymbol }}{{ number_format($rep->approved_amount ?? $rep->total_amount, 2) }})">{{ __('hrms.travel_expense.btn_pay_out') }}</x-ui.button>
                                                </form>
                                            @endif

                                            @if(in_array($rep->status, ['draft', 'rejected']) && ($isOwner || $isAdmin))
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.destroy', $rep) }}" class="m-0" onsubmit="return confirm('{{ __('hrms.travel_expense.confirm_delete_report') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" class="py-1 fw-bold fs-11 text-uppercase" title="{{ __('hrms.travel_expense.title_delete_report') }}">{{ __('hrms.travel_expense.btn_delete') }}</x-ui.button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">{{ __('hrms.travel_expense.no_expense_reports') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3" id="reportPaginationWrapper">
                    <x-ui.pagination :paginator="$expenseReports" pageParam="report_page" tab="report" />
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: REQUEST TRAVEL --}}
    <div class="modal fade" id="addTravelModal" tabindex="-1" aria-labelledby="addTravelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content text-dark">
                <form method="POST" action="{{ route('hrms.travel-expense.travel.store') }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addTravelModalLabel">{{ __('hrms.travel_expense.submit_travel_request') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        @if(!$employee)
                            <x-ui.alert variant="danger" class="fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>{{ __('hrms.travel_expense.session_not_linked_employee') }}</x-ui.alert>
                        @else
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.travel_expense.purpose')" name="purpose" placeholder="{{ __('hrms.travel_expense.placeholder_travel_purpose') }}" :required="true" :errorText="$errors->first('purpose')" :value="old('purpose')" />
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.travel_expense.destination')" name="destination" placeholder="{{ __('hrms.travel_expense.placeholder_travel_destination') }}" :required="true" :errorText="$errors->first('destination')" :value="old('destination')" />
                            <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.travel_expense.start_date')" name="start_date" :required="true" :errorText="$errors->first('start_date')" :value="old('start_date')" />
                            <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.travel_expense.end_date')" name="end_date" :required="true" :errorText="$errors->first('end_date')" :value="old('end_date')" />
                            <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.travel_expense.estimated_budget') . ' (' . $currencySymbol . ')'" name="estimated_budget" placeholder="0.00" step="0.01" min="0" :required="true" :errorText="$errors->first('estimated_budget')" :value="old('estimated_budget')" />

                            <!-- Integrated Cash Advance Option -->
                            <div class="border-top pt-3 mt-1">
                                <div class="form-check mb-2 d-flex align-items-center gap-2 ps-0">
                                    <input class="form-check-input mt-0 ms-0" type="checkbox" id="request_advance_toggle" name="request_advance" value="1" onchange="toggleTravelAdvanceAmount(this)" style="float: none;">
                                    <label class="form-check-label fw-bold fs-12 text-dark mb-0" for="request_advance_toggle">{{ __('hrms.travel_expense.request_cash_advance_checkbox') }}</label>
                                </div>
                                <div id="travel_advance_amount_wrapper" class="d-none mt-3">
                                    <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.travel_expense.advance_amount')" name="advance_amount" id="travel_advance_amount_input" placeholder="0.00" step="0.01" min="1" />
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <x-ui.button type="button" variant="light" class="border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">{{ __('hrms.travel_expense.btn_cancel') }}</x-ui.button>
                        @if($employee)
                            <x-ui.button type="submit" variant="primary" class="text-uppercase fw-bold fs-12">{{ __('hrms.travel_expense.btn_submit_request') }}</x-ui.button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: REQUEST ADVANCE --}}
    <div class="modal fade" id="addAdvanceModal" tabindex="-1" aria-labelledby="addAdvanceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content text-dark">
                <form method="POST" action="{{ route('hrms.travel-expense.advance.store') }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addAdvanceModalLabel">{{ __('hrms.travel_expense.submit_advance_request') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        @if(!$employee)
                            <x-ui.alert variant="danger" class="fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>{{ __('hrms.travel_expense.session_not_linked_employee') }}</x-ui.alert>
                        @else
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.travel_expense.travel_request')" name="travel_request_id" select2-selector="default">
                                <option value="">{{ __('hrms.travel_expense.standalone_business_expense') }}</option>
                                @foreach($myApprovedTravelRequests as $tr)
                                    @php
                                        $hasActiveAdv = $tr->cashAdvances->whereIn('status', ['pending', 'l1_approved', 'approved', 'disbursed'])->isNotEmpty();
                                    @endphp
                                    @if(!$hasActiveAdv)
                                        <option value="{{ $tr->id }}">
                                            {{ $tr->purpose }} ({{ __('hrms.travel_expense.trip_to') }} {{ $tr->destination }}) - {{ __('hrms.travel_expense.budget') }}: {{ $currencySymbol }}{{ number_format($tr->approved_budget ?? $tr->estimated_budget, 2) }}
                                        </option>
                                    @endif
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.travel_expense.col_amount')" name="amount" placeholder="0.00" step="0.01" min="1" :required="true" :errorText="$errors->first('amount')" :value="old('amount')" />
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.travel_expense.purpose')" name="purpose" placeholder="{{ __('hrms.travel_expense.placeholder_advance_purpose') }}" :required="true" :errorText="$errors->first('purpose')" :value="old('purpose')" />
                        @endif
                    </div>
                    <div class="modal-footer">
                        <x-ui.button type="button" variant="light" class="border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">{{ __('hrms.travel_expense.btn_cancel') }}</x-ui.button>
                        @if($employee)
                            <x-ui.button type="submit" variant="primary" class="text-uppercase fw-bold fs-12">{{ __('hrms.travel_expense.btn_request_advance') }}</x-ui.button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: FILE EXPENSE REPORT --}}
    <div class="modal fade" id="addReportModal" tabindex="-1" aria-labelledby="addReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content text-dark">
                <form method="POST" action="{{ route('hrms.travel-expense.report.store') }}" enctype="multipart/form-data"
                    id="addReportForm"
                    data-policy-url="{{ $employee ? route('hrms.travel-expense.employee-policy', $employee->id) : '' }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addReportModalLabel">{{ __('hrms.travel_expense.file_expense_report') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        @if(!$employee)
                            <x-ui.alert variant="danger" class="fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>{{ __('hrms.travel_expense.session_not_linked_employee') }}</x-ui.alert>
                        @else
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.travel_expense.report_title')" name="title" placeholder="{{ __('hrms.travel_expense.placeholder_report_title') }}" :required="true" :errorText="$errors->first('title')" :value="old('title')" />
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.travel_expense.travel_trip')" name="travel_request_id" id="report_travel_request" select2-selector="default">
                                        <option value="">{{ __('hrms.travel_expense.none') }}</option>
                                        @foreach($myApprovedTravelRequests as $tr)
                                            @php
                                                $inProgressClaim = $tr->expenseReports->whereIn('status', ['draft', 'submitted', 'l1_approved', 'approved', 'partially_approved'])->first();
                                                $paidClaim = $tr->expenseReports->where('status', 'paid')->first();
                                                $hasRejectedItemsInPaid = $paidClaim && $paidClaim->claims->where('status', 'rejected')->count() > 0;
                                                $hasPaidSupplementary = $tr->expenseReports->where('status', 'paid')->filter(function($r) {
                                                    return str_contains(strtolower($r->title ?? ''), 'supplementary');
                                                })->isNotEmpty();
                                                $canClaim = !$inProgressClaim && (!$paidClaim || ($hasRejectedItemsInPaid && !$hasPaidSupplementary));
                                                $statusNote = '';
                                                if ($paidClaim && $hasRejectedItemsInPaid && !$hasPaidSupplementary) {
                                                    $statusNote = ' — (' . __('hrms.travel_expense.btn_supplementary_claim') . ')';
                                                }
                                            @endphp
                                            @if($canClaim)
                                                <option value="{{ $tr->id }}">
                                                    {{ $tr->destination }} ({{ $tr->purpose }}){{ $statusNote }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.travel_expense.cash_advances')" name="cash_advance_id" id="report_cash_advance" select2-selector="default">
                                        <option value="">{{ __('hrms.travel_expense.none') }}</option>
                                        @foreach($myOpenCashAdvances as $ca)
                                            <option value="{{ $ca->id }}">{{ $currencySymbol }}{{ number_format($ca->approved_amount ?? $ca->amount, 2) }} - {{ $ca->purpose }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>

                            {{-- Claims items array dynamically added --}}
                            <div class="border-top pt-3 mt-2">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">{{ __('hrms.travel_expense.expense_claim_items') }} *</h6>
                                        <small class="text-muted fs-11">{{ __('hrms.travel_expense.attach_receipts_desc') }}</small>
                                    </div>
                                    <x-ui.button type="button" variant="soft-primary" size="sm" class="px-2.5 py-1 text-uppercase fw-bold fs-11" id="btnAddClaimLine" icon="feather-plus">
                                        {{ __('hrms.travel_expense.btn_add_row') }}
                                    </x-ui.button>
                                </div>

                                <div class="table-responsive">
                                    <table class="expense-claims-table" id="claimsLinesTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 25%; min-width: 190px;">{{ __('hrms.travel_expense.col_category') }} *</th>
                                                <th style="width: 14%; min-width: 130px;">{{ __('hrms.travel_expense.col_date') }} *</th>
                                                <th style="width: 15%; min-width: 120px;">{{ __('hrms.travel_expense.col_amount') }} *</th>
                                                <th style="width: 23%; min-width: 170px;">{{ __('hrms.travel_expense.col_merchant_notes') }}</th>
                                                <th style="width: 19%; min-width: 160px;">{{ __('hrms.travel_expense.col_receipts') }}</th>
                                                <th style="width: 4%; min-width: 40px;" class="text-center">{{ __('hrms.travel_expense.col_action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="claimsLinesContainer">
                                            <tr class="claim-line-row">
                                                <td>
                                                    <select name="claims[0][category_id]" class="form-select form-select-sm text-dark category-select odoo-select2" data-select2-selector="default" required>
                                                        <option value="" disabled selected>{{ __('hrms.travel_expense.choose_category') }}</option>
                                                        @foreach($categories as $cat)
                                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <!-- Client-side policy limit warning inline badge -->
                                                    <div class="policy-limit-badge text-danger fw-semibold mt-1 fs-11 d-none" style="letter-spacing: 0.2px;"><i class="feather-alert-triangle"></i> {{ __('hrms.travel_expense.exceeds_limit') }}</div>
                                                </td>
                                                <td>
                                                    <input type="date" name="claims[0][date]" class="form-control form-control-sm text-dark text-center" value="{{ date('Y-m-d') }}" required>
                                                </td>
                                                <td>
                                                    <input type="number" name="claims[0][amount]" class="form-control form-control-sm text-dark text-end claim-amount-input fw-semibold" placeholder="0.00" step="0.01" min="0.01" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="claims[0][merchant]" class="form-control form-control-sm text-dark mb-1" placeholder="{{ __('hrms.travel_expense.placeholder_merchant') }}">
                                                    <input type="text" name="claims[0][desc]" class="form-control form-control-sm text-dark" placeholder="{{ __('hrms.travel_expense.placeholder_notes') }}">
                                                </td>
                                                <td>
                                                    <div class="erp-custom-file-upload">
                                                        <label class="file-upload-label mb-0 cursor-pointer">
                                                            <i class="feather-upload-cloud text-primary fs-14"></i>
                                                            <span class="file-label-text text-muted">{{ __('hrms.travel_expense.upload_files') }}</span>
                                                            <input type="file" name="claims[0][receipts][]" multiple class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
                                                        </label>
                                                        <div class="new-files-container d-flex flex-wrap gap-1 mt-1 justify-content-start"></div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-soft-danger btn-remove-line p-0" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;" title="{{ __('hrms.travel_expense.remove_row') }}">
                                                        <i class="feather-trash-2 fs-13"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Policy Violations Banner (shown on submit if exceeded) --}}
                            <div id="addReportPolicyWarning" class="d-none">
                                <x-ui.alert variant="warning" class="border border-warning-subtle fs-12 mb-0 d-flex gap-2 align-items-start">
                                    <i class="feather-alert-triangle text-warning mt-0.5 fs-14"></i>
                                    <div>
                                        <div class="fw-bold text-dark mb-1">{{ __('hrms.travel_expense.policy_limit_exceeded') }}</div>
                                        <ul id="addReportPolicyViolationList" class="mb-0 ps-3 text-dark"></ul>
                                    </div>
                                </x-ui.alert>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <x-ui.button type="button" variant="light" class="border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">{{ __('hrms.travel_expense.btn_cancel') }}</x-ui.button>
                        @if($employee)
                            <x-ui.button type="submit" variant="primary" class="text-uppercase fw-bold fs-12">{{ __('hrms.travel_expense.btn_save_draft') }}</x-ui.button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: EDIT EXPENSE REPORT --}}
    <div class="modal fade" id="editReportModal" tabindex="-1" aria-labelledby="editReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content text-dark">
                <form method="POST" action="" enctype="multipart/form-data"
                    id="editReportForm"
                    data-policy-url="{{ $employee ? route('hrms.travel-expense.employee-policy', $employee->id) : '' }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="editReportModalLabel">{{ __('hrms.travel_expense.edit_expense_report') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        @if(!$employee)
                            <x-ui.alert variant="danger" class="fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>{{ __('hrms.travel_expense.session_not_linked_employee') }}</x-ui.alert>
                        @else
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.travel_expense.report_title')" name="title" id="edit_report_title" placeholder="{{ __('hrms.travel_expense.placeholder_report_title') }}" :required="true" />
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.travel_expense.travel_trip')" name="travel_request_id" id="edit_report_travel_request" select2-selector="default">
                                        <option value="">{{ __('hrms.travel_expense.none') }}</option>
                                        @foreach($myApprovedTravelRequests as $tr)
                                            <option value="{{ $tr->id }}">{{ $tr->destination }} ({{ $tr->purpose }})</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.travel_expense.cash_advances')" name="cash_advance_id" id="edit_report_cash_advance" select2-selector="default">
                                        <option value="">{{ __('hrms.travel_expense.none') }}</option>
                                        @foreach($myOpenCashAdvances as $ca)
                                            <option value="{{ $ca->id }}">{{ $currencySymbol }}{{ number_format($ca->approved_amount ?? $ca->amount, 2) }} - {{ $ca->purpose }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>

                            {{-- Claims items array dynamically added --}}
                            <div class="border-top pt-3 mt-2">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">{{ __('hrms.travel_expense.expense_claim_items') }} *</h6>
                                        <small class="text-muted fs-11">{{ __('hrms.travel_expense.attach_receipts_desc') }}</small>
                                    </div>
                                    <x-ui.button type="button" variant="soft-primary" size="sm" class="px-2.5 py-1 text-uppercase fw-bold fs-11" id="btnEditAddClaimLine" icon="feather-plus">
                                        {{ __('hrms.travel_expense.btn_add_row') }}
                                    </x-ui.button>
                                </div>

                                <div class="table-responsive">
                                    <table class="expense-claims-table" id="editClaimsLinesTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 25%; min-width: 190px;">{{ __('hrms.travel_expense.col_category') }} *</th>
                                                <th style="width: 14%; min-width: 130px;">{{ __('hrms.travel_expense.col_date') }} *</th>
                                                <th style="width: 15%; min-width: 120px;">{{ __('hrms.travel_expense.col_amount') }} *</th>
                                                <th style="width: 23%; min-width: 170px;">{{ __('hrms.travel_expense.col_merchant_notes') }}</th>
                                                <th style="width: 19%; min-width: 160px;">{{ __('hrms.travel_expense.col_receipts') }}</th>
                                                <th style="width: 4%; min-width: 40px;" class="text-center">{{ __('hrms.travel_expense.col_action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="editClaimsLinesContainer">
                                            <!-- Dynamic Edit Rows Populated by Javascript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Policy Violations Banner (shown on submit if exceeded) --}}
                            <div id="editReportPolicyWarning" class="d-none">
                                <x-ui.alert variant="warning" class="border border-warning-subtle fs-12 mb-0 d-flex gap-2 align-items-start">
                                    <i class="feather-alert-triangle text-warning mt-0.5 fs-14"></i>
                                    <div>
                                        <div class="fw-bold text-dark mb-1">{{ __('hrms.travel_expense.policy_limit_exceeded') }}</div>
                                        <ul id="editReportPolicyViolationList" class="mb-0 ps-3 text-dark"></ul>
                                    </div>
                                </x-ui.alert>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <x-ui.button type="button" variant="light" class="border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">{{ __('hrms.travel_expense.btn_cancel') }}</x-ui.button>
                        @if($employee)
                            <x-ui.button type="submit" variant="primary" class="text-uppercase fw-bold fs-12">{{ __('hrms.travel_expense.btn_update_report') }}</x-ui.button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: VIEW EXPENSE REPORT DETAILS --}}
    <div class="modal fade text-start text-dark" id="viewReportModal" tabindex="-1" aria-labelledby="viewReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="viewReportModalLabel"><i class="feather-file-text text-primary me-1"></i> {{ __('hrms.travel_expense.expense_claim_details') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex flex-column gap-3">
                    <div class="p-3 bg-light rounded border text-dark">
                        <div class="row g-3 fs-13">
                            <div class="col-md-6">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.report_title') }}</span>
                                <strong id="view_report_title" class="text-dark fs-14"></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.col_employee') }}</span>
                                <strong id="view_report_employee" class="text-dark"></strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.travel_trip') }}</span>
                                <span id="view_report_travel" class="text-dark fw-semibold"></span>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.cash_advances') }}</span>
                                <span id="view_report_advance" class="text-dark fw-semibold"></span>
                            </div>
                            <div class="col-md-6">
                                 <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.status') }}</span>
                                 <span id="view_report_status" class="badge"></span>
                             </div>
                             <div class="col-md-6" id="view_report_channel_wrapper">
                                 <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.payout_channel') }}</span>
                                 <span id="view_report_channel" class="badge"></span>
                             </div>
                        </div>
                    </div>

                    <div class="p-3 bg-soft-primary rounded border border-primary-subtle text-dark">
                        <div class="row text-center g-3 fs-13">
                            <div class="col-md-4">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.total_expenses') }}</span>
                                <strong id="view_report_total" class="text-dark fs-15"></strong>
                            </div>
                            <div class="col-md-4 border-start border-end border-primary-subtle">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.advance_adjusted') }}</span>
                                <strong id="view_report_adjusted" class="text-dark fs-15"></strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold mb-0.5">{{ __('hrms.travel_expense.net_reimbursement') }}</span>
                                <strong id="view_report_net" class="text-primary fs-16"></strong>
                            </div>
                        </div>
                    </div>

                    <div id="view_report_surplus_wrapper" class="p-3 bg-soft-warning rounded border border-warning-subtle text-dark d-none">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-alert-triangle text-warning fs-18"></i>
                            <div>
                                <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-0.5">{{ __('hrms.travel_expense.returnable_surplus') }}</span>
                                <strong id="view_report_surplus" class="text-warning fs-15"></strong>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h6 class="fw-bold mb-3 fs-13"><i class="feather-list me-1 text-primary"></i> {{ __('hrms.travel_expense.col_claim_items') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 text-dark fs-12" id="viewClaimsLinesTable" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('hrms.travel_expense.col_category') }}</th>
                                        <th>{{ __('hrms.travel_expense.col_date') }}</th>
                                        <th>{{ __('hrms.travel_expense.col_amount') }}</th>
                                        <th>{{ __('hrms.travel_expense.col_merchant') }}</th>
                                        <th>{{ __('hrms.travel_expense.col_reason_description') }}</th>
                                        <th>{{ __('hrms.travel_expense.col_status') }}</th>
                                        <th>{{ __('hrms.travel_expense.receipt') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Dynamically populated rows --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-ui.button type="button" variant="light" class="border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">{{ __('hrms.travel_expense.btn_close') }}</x-ui.button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        var travelTrans = {
            amount: "{{ __('hrms.travel_expense.col_amount') }}",
            exceedsCategoryLimit: "{{ __('hrms.travel_expense.policy_exceeds_category_limit') }}",
            receiptRequired: "{{ __('hrms.travel_expense.policy_receipt_required') }}",
            above: "{{ __('hrms.travel_expense.above') }}",
            row: "{{ __('hrms.travel_expense.row') }}",
            fieldRequired: "{{ __('hrms.travel_expense.field_required') }}",
            chooseCategory: "{{ __('hrms.travel_expense.choose_category') }}",
            exceedsLimit: "{{ __('hrms.travel_expense.exceeds_limit') }}",
            placeholderMerchant: "{{ __('hrms.travel_expense.placeholder_merchant') }}",
            placeholderNotes: "{{ __('hrms.travel_expense.placeholder_notes') }}",
            uploadFiles: "{{ __('hrms.travel_expense.upload_files') }}",
            addFiles: "{{ __('hrms.travel_expense.btn_add_files') }}",
            filesSelected: "{{ __('hrms.travel_expense.files_selected') }}",
            fileSelected: "{{ __('hrms.travel_expense.file_selected') }}",
            removeRow: "{{ __('hrms.travel_expense.remove_row') }}",
            removeFile: "{{ __('hrms.travel_expense.remove_file') }}",
            file: "{{ __('hrms.travel_expense.file') }}",
            minOneClaimRequired: "{{ __('hrms.travel_expense.min_one_claim_required') }}",
            rejectedByHr: "{{ __('hrms.travel_expense.rejected_by_hr') }}",
            correctDetailsOrAmount: "{{ __('hrms.travel_expense.correct_details_or_amount') }}",
            none: "{{ __('hrms.travel_expense.none') }}",
            payroll: "{{ __('hrms.travel_expense.payroll') }}",
            accounting: "{{ __('hrms.travel_expense.accounting') }}",
            statusDraft: "{{ __('hrms.travel_expense.status_draft') }}",
            statusSubmitted: "{{ __('hrms.travel_expense.status_submitted') }}",
            statusApproved: "{{ __('hrms.travel_expense.status_approved') }}",
            statusPaid: "{{ __('hrms.travel_expense.status_paid') }}",
            statusRejected: "{{ __('hrms.travel_expense.status_rejected') }}",
            statusPending: "{{ __('hrms.travel_expense.status_pending') }}",
            partiallyApproved: "{{ __('hrms.travel_expense.partially_approved') }}",
            receipt: "{{ __('hrms.travel_expense.receipt') }}"
        };

        // =========================================================
        // EXPENSE POLICY VALIDATION ENGINE
        // Fetches employee policy once, validates claim rows on submit
        // Shows inline per-row warnings + blocks form if violations exist
        // =========================================================
        var expensePolicyCache = null;

        function fetchEmployeePolicy(policyUrl, callback) {
            if (expensePolicyCache !== null) {
                callback(expensePolicyCache);
                return;
            }
            if (!policyUrl) {
                callback({ rules: {} });
                return;
            }
            fetch(policyUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    expensePolicyCache = data;
                    callback(data);
                })
                .catch(() => callback({ rules: {} }));
        }

        function clearRowPolicyWarnings(tableSelector) {
            document.querySelectorAll(tableSelector + ' .policy-warning-badge').forEach(el => el.remove());
            document.querySelectorAll(tableSelector + ' .policy-warning-input').forEach(el => {
                el.classList.remove('policy-warning-input');
                el.style.borderBottomColor = '';
            });
        }

        function validateClaimRowsAgainstPolicy(tableSelector, policyRules, warningBannerEl, violationListEl) {
            clearRowPolicyWarnings(tableSelector);
            var violations = [];
            var currSym = '{{ $currencySymbol }}';

            document.querySelectorAll(tableSelector + ' tbody tr').forEach(function(row, idx) {
                var categorySelect = row.querySelector('select[name*="category_id"]');
                var amountInput   = row.querySelector('input[name*="amount"]');
                var receiptInput  = row.querySelector('input[type="file"]');
                var existingReceipt = row.querySelector('input[name*="existing_receipt"]');

                if (!categorySelect || !amountInput) return;

                var categoryId = categorySelect.value;
                var amount = parseFloat(amountInput.value) || 0;
                var rule = policyRules[categoryId];

                if (!rule) return;

                var rowViolations = [];

                // Check max_limit_per_claim
                if (rule.max_limit_per_claim && amount > rule.max_limit_per_claim) {
                    rowViolations.push(travelTrans.amount + ' ' + currSym + amount.toFixed(2) + ' ' + travelTrans.exceedsCategoryLimit.replace(':category', rule.category_name) + ' ' + currSym + rule.max_limit_per_claim.toFixed(2));
                    amountInput.style.borderBottomColor = '#f59e0b';
                    amountInput.classList.add('policy-warning-input');
                }

                // Check receipt required
                var rowId = receiptInput ? receiptInput.getAttribute('data-row-id') : null;
                var hasNewFiles = (receiptInput && receiptInput.files && receiptInput.files.length > 0)
                                 || (rowId && window.rowFileStores && window.rowFileStores[rowId] && window.rowFileStores[rowId].length > 0)
                                 || (row.querySelectorAll('.new-file-chip').length > 0);
                var hasExistingFiles = (existingReceipt && existingReceipt.value)
                                    || (row.querySelectorAll('.existing-receipt-chip').length > 0);
                var hasReceipt = hasNewFiles || hasExistingFiles;

                var needsReceipt = rule.receipt_required
                    || (rule.receipt_required_threshold && amount > rule.receipt_required_threshold);
                if (needsReceipt && !hasReceipt) {
                    rowViolations.push(travelTrans.receiptRequired.replace(':category', rule.category_name) +
                        (rule.receipt_required_threshold ? ' ' + travelTrans.above + ' ' + currSym + rule.receipt_required_threshold.toFixed(2) : ''));
                }

                if (rowViolations.length > 0) {
                    // Show inline badge under the amount cell
                    var badge = document.createElement('div');
                    badge.className = 'policy-warning-badge text-warning fw-semibold mt-0.5';
                    badge.style.cssText = 'font-size: 10px; line-height: 1.2;';
                    badge.innerHTML = '<i class="feather-alert-triangle" style="font-size: 10px;"></i> ' + rowViolations[0];
                    amountInput.parentNode.appendChild(badge);

                    rowViolations.forEach(msg => violations.push(travelTrans.row + ' ' + (idx + 1) + ': ' + msg));
                }
            });

            // Show or hide the banner
            if (violations.length > 0) {
                violationListEl.innerHTML = violations.map(v => '<li>' + v + '</li>').join('');
                warningBannerEl.classList.remove('d-none');
            } else {
                warningBannerEl.classList.add('d-none');
            }

            return violations.length === 0;
        }

        function validateFormRequiredFields(form) {
            let hasErrors = false;
            let firstErrEl = null;

            // Clear previous dynamic errors inside this form
            form.querySelectorAll('.dynamic-error-feedback').forEach(el => el.remove());
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            let requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
            requiredFields.forEach(field => {
                if (field.disabled || field.readOnly || field.type === 'hidden') return;

                let val = field.value;
                let isEmpty = false;

                if (field.type === 'checkbox') {
                    isEmpty = !field.checked;
                } else if (field.type === 'file') {
                    let existingReceipt = field.closest('tr')?.querySelector('input[name*="[existing_receipt]"]');
                    isEmpty = (!field.files || field.files.length === 0) && (!existingReceipt || !existingReceipt.value);
                } else {
                    isEmpty = !val || !val.trim();
                }

                if (isEmpty) {
                    hasErrors = true;
                    field.classList.add('is-invalid');

                    let errorContainer = field.parentElement;
                    if (field.tagName === 'SELECT' && $(field).data('select2')) {
                        let s2Container = field.nextElementSibling;
                        if (s2Container && s2Container.classList.contains('select2-container')) {
                            s2Container.querySelector('.select2-selection')?.classList.add('is-invalid');
                            errorContainer = s2Container.parentElement;
                        }
                    } else if (field.classList.contains('odoo-table-file-input')) {
                        let fileLabel = field.closest('.odoo-table-file-label');
                        if (fileLabel) fileLabel.classList.add('is-invalid', 'border-danger', 'text-danger');
                    }

                    let labelName = '';
                    let odooFormGroup = field.closest('.odoo-form-group');
                    if (odooFormGroup) {
                        let labelEl = odooFormGroup.querySelector('.odoo-form-label');
                        if (labelEl) labelName = labelEl.textContent.replace('*', '').trim();
                    }
                    if (!labelName) {
                        let td = field.closest('td');
                        let tr = field.closest('tr');
                        let table = field.closest('table');
                        if (td && tr && table) {
                            let colIndex = Array.from(tr.children).indexOf(td);
                            let th = table.querySelector(`thead tr th:nth-child(${colIndex + 1})`);
                            if (th) labelName = th.textContent.trim();
                        }
                    }
                    if (!labelName) {
                        labelName = field.getAttribute('placeholder') || field.getAttribute('name') || 'This field';
                    }

                    let errorEl = document.createElement('div');
                    errorEl.className = 'invalid-feedback dynamic-error-feedback d-block text-danger fs-11 mt-1 fw-semibold';
                    errorEl.innerHTML = `<i class="feather-alert-circle me-1"></i>${labelName} ${travelTrans.fieldRequired}`;

                    if (field.tagName === 'SELECT' && $(field).data('select2')) {
                        let s2Container = field.nextElementSibling;
                        if (s2Container && s2Container.classList.contains('select2-container')) {
                            s2Container.parentNode.insertBefore(errorEl, s2Container.nextSibling);
                        } else {
                            field.parentNode.insertBefore(errorEl, field.nextSibling);
                        }
                    } else {
                        field.parentNode.insertBefore(errorEl, field.nextSibling);
                    }

                    if (!firstErrEl) firstErrEl = field;
                }
            });

            if (hasErrors && firstErrEl) {
                firstErrEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstErrEl.focus();
            }

            return !hasErrors;
        }

        // Intercept Add Report form submit
        $(document).on('submit', '#addReportForm', function(e) {
            var form = this;

            if (!validateFormRequiredFields(form)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            var policyUrl = form.dataset.policyUrl;
            var warningBanner = document.getElementById('addReportPolicyWarning');
            var violationList = document.getElementById('addReportPolicyViolationList');

            fetchEmployeePolicy(policyUrl, function(data) {
                var valid = validateClaimRowsAgainstPolicy('#claimsLinesTable', data.rules || {}, warningBanner, violationList);
                if (valid) {
                    form.submit();
                } else {
                    warningBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            e.preventDefault();
        });

        // Intercept Edit Report form submit
        $(document).on('submit', '#editReportForm', function(e) {
            var form = this;

            if (!validateFormRequiredFields(form)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            var policyUrl = form.dataset.policyUrl;
            var warningBanner = document.getElementById('editReportPolicyWarning');
            var violationList = document.getElementById('editReportPolicyViolationList');

            fetchEmployeePolicy(policyUrl, function(data) {
                var valid = validateClaimRowsAgainstPolicy('#editClaimsLinesTable', data.rules || {}, warningBanner, violationList);
                if (valid) {
                    form.submit();
                } else {
                    warningBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            e.preventDefault();
        });

        // Clear warnings when amount, category or file attachment changes
        $(document).on('change input', '#claimsLinesTable select[name*="category_id"], #claimsLinesTable input[name*="amount"], #claimsLinesTable .odoo-table-file-input', function() {
            clearRowPolicyWarnings('#claimsLinesTable');
            var warningBanner = document.getElementById('addReportPolicyWarning');
            if (warningBanner) warningBanner.classList.add('d-none');
        });
        $(document).on('change input', '#editClaimsLinesTable select[name*="category_id"], #editClaimsLinesTable input[name*="amount"], #editClaimsLinesTable .odoo-table-file-input', function() {
            clearRowPolicyWarnings('#editClaimsLinesTable');
            var warningBanner = document.getElementById('editReportPolicyWarning');
            if (warningBanner) warningBanner.classList.add('d-none');
        });

        document.addEventListener('DOMContentLoaded', function () {
            // Append modals to body to bypass stacking context / backdrop blur freeze issues
            ['addTravelModal', 'addAdvanceModal', 'addReportModal', 'editReportModal', 'viewReportModal'].forEach(id => {
                const modal = document.getElementById(id);
                if (modal) document.body.appendChild(modal);
            });

            // Append all dynamic approval modals to body as well
            document.querySelectorAll('[id^="approveTravelModal_"], [id^="approveAdvanceModal_"], [id^="approveReportModal_"]').forEach(modal => {
                document.body.appendChild(modal);
            });

            var activeRequest = null;
            var searchTimeout = null;

            function refreshPanel(url) {
                if (activeRequest) {
                    activeRequest.abort();
                }
                var controller = new AbortController();
                activeRequest = controller;

                var panel = document.querySelector('.erp-single-panel');
                if (panel) panel.style.opacity = '0.7';

                fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: controller.signal
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('Error reloading page.');
                    return response.text();
                })
                .then(function(html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    
                    const newUrlParams = new URLSearchParams(url.search);
                    const activeTabName = newUrlParams.get('tab') || 'travel';
                    
                    // Determine active tab body container to replace (identical to Org Structure tabs)
                    const targetTbodyId = {
                        'travel': 'travelTableBody',
                        'advance': 'advanceTableBody',
                        'report': 'reportTableBody'
                    }[activeTabName];

                    if (targetTbodyId) {
                        var newTbody = doc.getElementById(targetTbodyId);
                        var oldTbody = document.getElementById(targetTbodyId);
                        if (newTbody && oldTbody) {
                            oldTbody.innerHTML = newTbody.innerHTML;
                        }
                    } else {
                        // Fallback: replace whole panel if active tab is not mapped
                        var newPanel = doc.querySelector('.erp-single-panel');
                        var oldPanel = document.querySelector('.erp-single-panel');
                        if (newPanel && oldPanel) {
                            oldPanel.innerHTML = newPanel.innerHTML;
                        }
                    }
                    
                    // Push history state to keep search/sort params in browser URL
                    history.pushState(null, '', url.toString());
                })
                .catch(function(err) {
                    if (err.name !== 'AbortError') {
                        window.location.href = url.toString();
                    }
                })
                .finally(function() {
                    if (panel) panel.style.opacity = '1';
                });
            }

            // Real-time search debouncing for all three tabs
            $(document).on('input keyup search', 'input[name="travel_search"], input[name="advance_search"], input[name="report_search"]', function() {
                var form = this.closest('form');
                var url = new URL(form.action || window.location.href);
                var formData = new FormData(form);
                for (var [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    refreshPanel(url);
                }, 300);
            });

            // Intercept all GET forms submission (Search & Filter applies)
            $(document).on('submit', 'form[action*="travel-expense"]', function(e) {
                var method = this.getAttribute('method') || 'GET';
                if (method.toUpperCase() === 'GET') {
                    e.preventDefault();
                    var url = new URL(this.action || window.location.href);
                    var formData = new FormData(this);
                    for (var [key, val] of formData.entries()) {
                        url.searchParams.set(key, val);
                    }
                    refreshPanel(url);
                    
                    // Close the Bootstrap filter dropdown automatically
                    var dropdownToggle = $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]');
                    if (dropdownToggle.length > 0) {
                        bootstrap.Dropdown.getOrCreateInstance(dropdownToggle[0]).hide();
                    }
                }
            });

            // Intercept filter Reset button link click to fetch via AJAX
            $(document).on('click', 'form[action*="travel-expense"] a.btn-light[href*="tab="]', function(e) {
                e.preventDefault();
                var url = new URL(this.href, window.location.origin);
                refreshPanel(url);
            });

            // Keep tabs active on page refresh
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabButton = document.querySelector(`#${tabParam}-tab`);
                if (tabButton) {
                    const activeTab = bootstrap.Tab.getOrCreateInstance(tabButton);
                    activeTab.show();
                }
            }

            // Sync Header Action Buttons helper
            function syncHeaderButtons() {
                const activeTabEl = document.querySelector('button[data-bs-toggle="tab"].active');
                if (activeTabEl) {
                    const targetId = activeTabEl.id;
                    const travelBtn = document.getElementById('btnRequestTravel');
                    const advanceBtn = document.getElementById('btnRequestAdvance');
                    const claimBtn = document.getElementById('btnFileExpenseClaim');

                    if (travelBtn) travelBtn.classList.add('d-none');
                    if (advanceBtn) advanceBtn.classList.add('d-none');
                    if (claimBtn) claimBtn.classList.add('d-none');

                    if (targetId === 'travel-tab' && travelBtn) {
                        travelBtn.classList.remove('d-none');
                    } else if (targetId === 'advance-tab' && advanceBtn) {
                        advanceBtn.classList.remove('d-none');
                    } else if (targetId === 'report-tab' && claimBtn) {
                        claimBtn.classList.remove('d-none');
                    }
                }
            }

            // Toggle Header action buttons on tab switch
            const tabElList = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabElList.forEach(tabEl => {
                tabEl.addEventListener('shown.bs.tab', () => {
                    syncHeaderButtons();
                });
            });
 
            // Initial button synchronization
            syncHeaderButtons();

            // Set Sort Param helper
            window.setSortParam = function (tab, value) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                url.searchParams.set(tab + '_sort', value);
                refreshPanel(url);
            };

            // Safe Select2 initializer helper to prevent Bootstrap modal closing issue
            window.safeInitSelect2 = function(modalSelector) {
                if (window.$ && $.fn.select2) {
                    var $modal = $(modalSelector);
                    if (!$modal.length) return;
                    $modal.find('.odoo-select2, [data-select2-selector]').each(function() {
                        var $select = $(this);
                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.select2('destroy');
                        }
                        $select.select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            dropdownParent: $modal
                        });
                    });
                }
            };

            // Initialize all dropdowns inside travel-expense modals
            safeInitSelect2('#addTravelModal');
            safeInitSelect2('#addAdvanceModal');
            safeInitSelect2('#addReportModal');
            safeInitSelect2('#editReportModal');

            // Re-initialize select2 with correct dimensions on modal show
            $(document).on('shown.bs.modal', '#addTravelModal, #addAdvanceModal, #addReportModal, #editReportModal', function() {
                safeInitSelect2(this);
            });

            // Dynamic claims row addition
            let lineIndex = 1;
            const btnAddLine = document.getElementById('btnAddClaimLine');
            const tableBody = document.querySelector('#claimsLinesTable tbody');

            if (btnAddLine) {
                btnAddLine.addEventListener('click', function () {
                    const rowTemplate = `
                        <tr class="claim-line-row">
                            <td>
                                <select name="claims[${lineIndex}][category_id]" class="form-select form-select-sm text-dark category-select odoo-select2" data-select2-selector="default" required>
                                    <option value="" disabled selected>${travelTrans.chooseCategory}</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                                <div class="policy-limit-badge text-danger fw-semibold mt-1 fs-11 d-none" style="letter-spacing: 0.2px;"><i class="feather-alert-triangle"></i> ${travelTrans.exceedsLimit}</div>
                            </td>
                            <td>
                                <input type="date" name="claims[${lineIndex}][date]" class="form-control form-control-sm text-dark text-center" required value="{{ date('Y-m-d') }}">
                            </td>
                            <td>
                                <input type="number" name="claims[${lineIndex}][amount]" step="0.01" min="0.01" class="form-control form-control-sm text-dark text-end claim-amount-input fw-semibold" placeholder="0.00" required>
                            </td>
                            <td>
                                <input type="text" name="claims[${lineIndex}][merchant]" class="form-control form-control-sm text-dark mb-1" placeholder="${travelTrans.placeholderMerchant}">
                                <input type="text" name="claims[${lineIndex}][desc]" class="form-control form-control-sm text-dark" placeholder="${travelTrans.placeholderNotes}">
                            </td>
                            <td>
                                <div class="erp-custom-file-upload">
                                    <label class="file-upload-label mb-0 cursor-pointer">
                                        <i class="feather-upload-cloud text-primary fs-14"></i>
                                        <span class="file-label-text text-muted">${travelTrans.uploadFiles}</span>
                                        <input type="file" name="claims[${lineIndex}][receipts][]" multiple class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
                                    </label>
                                    <div class="new-files-container d-flex flex-wrap gap-1 mt-1 justify-content-start"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-soft-danger btn-remove-line p-0" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;" title="${travelTrans.removeRow}">
                                    <i class="feather-trash-2 fs-13"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', rowTemplate);
                    safeInitSelect2('#addReportModal');
                    lineIndex++;
                });
            }

            // Remove claim line
            $(document).on('click', '.btn-remove-line', function () {
                const rows = document.querySelectorAll('.claim-line-row');
                if (rows.length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert(travelTrans.minOneClaimRequired);
                }
            });

            // Accumulative multi-file store with DataTransfer
            window.rowFileStores = {};

            function formatBytes(bytes) {
                if (!bytes || bytes === 0) return '0 B';
                var k = 1024, sizes = ['B', 'KB', 'MB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + (sizes[i] || 'MB');
            }

            window.renderRowFiles = function(input, rowId) {
                var $input = $(input);
                var $td = $input.closest('td');
                var $chipsContainer = $td.find('.new-files-container');
                if ($chipsContainer.length === 0) {
                    $td.find('.erp-custom-file-upload').append('<div class="new-files-container d-flex flex-wrap gap-1 mt-1 justify-content-start"></div>');
                    $chipsContainer = $td.find('.new-files-container');
                }
                $chipsContainer.empty();

                var files = window.rowFileStores[rowId] || [];
                files.forEach(function(file, idx) {
                    var nameStr = file.name.length > 16 ? file.name.substring(0, 13) + '...' : file.name;
                    var sizeStr = formatBytes(file.size);
                    var chipHtml = `
                        <span class="badge bg-white text-dark border shadow-xs px-2 py-1 fs-11 rounded-pill d-inline-flex align-items-center gap-1 mb-1 new-file-chip" style="border-color: #cbd5e1 !important;">
                            <i class="feather-file-text text-primary fs-11"></i>
                            <span class="fw-medium" title="${file.name}">${nameStr}</span>
                            <span class="text-muted fs-10">(${sizeStr})</span>
                            <i class="feather-x text-danger cursor-pointer ms-1" style="font-size: 11px;" title="${travelTrans.removeFile}" onclick="removeNewSelectedFile('${rowId}', ${idx}, this)"></i>
                        </span>
                    `;
                    $chipsContainer.append(chipHtml);
                });

                var fileTextSpan = $input.siblings('.file-label-text');
                var totalNewCount = files.length;
                var hasExistingChips = $td.find('.existing-receipt-chip').length > 0;

                if (totalNewCount > 0) {
                    fileTextSpan.text(totalNewCount + ' ' + (totalNewCount > 1 ? travelTrans.filesSelected : travelTrans.fileSelected));
                } else {
                    fileTextSpan.text(hasExistingChips ? travelTrans.addFiles : travelTrans.uploadFiles);
                }
            };

            window.updateRowFileName = function(input) {
                if (!input) return;
                var rowId = input.getAttribute('data-row-id');
                if (!rowId) {
                    rowId = 'row_' + Math.random().toString(36).substring(2, 9);
                    input.setAttribute('data-row-id', rowId);
                }

                if (!window.rowFileStores[rowId]) {
                    window.rowFileStores[rowId] = [];
                }

                // Accumulate newly picked files into rowFileStores
                if (input.files && input.files.length > 0) {
                    Array.from(input.files).forEach(function(f) {
                        var exists = window.rowFileStores[rowId].some(function(existing) {
                            return existing.name === f.name && existing.size === f.size;
                        });
                        if (!exists) {
                            window.rowFileStores[rowId].push(f);
                        }
                    });
                }

                // Sync accumulated files back to input element using DataTransfer
                var dt = new DataTransfer();
                window.rowFileStores[rowId].forEach(function(f) {
                    dt.items.add(f);
                });
                try { input.files = dt.files; } catch(e) {}

                renderRowFiles(input, rowId);
            };

            window.removeNewSelectedFile = function(rowId, fileIdx, btn) {
                if (window.rowFileStores[rowId]) {
                    window.rowFileStores[rowId].splice(fileIdx, 1);
                    var $td = $(btn).closest('td');
                    var input = $td.find('.odoo-table-file-input')[0];
                    if (input) {
                        var dt = new DataTransfer();
                        window.rowFileStores[rowId].forEach(function(f) {
                            dt.items.add(f);
                        });
                        try { input.files = dt.files; } catch(e) {}
                        renderRowFiles(input, rowId);
                    }
                }
            };

            window.removeExistingReceipt = function(btn) {
                var $td = $(btn).closest('td');
                $(btn).closest('.existing-receipt-chip').remove();
                var input = $td.find('.odoo-table-file-input')[0];
                if (input) {
                    var rowId = input.getAttribute('data-row-id');
                    if (rowId) renderRowFiles(input, rowId);
                }
            };

            // Toggle Cash Advance Amount input visibility and pre-populate budget value
            window.toggleTravelAdvanceAmount = function(checkbox) {
                var wrapper = document.getElementById('travel_advance_amount_wrapper');
                var input = document.getElementById('travel_advance_amount_input');
                if (checkbox.checked) {
                    wrapper.classList.remove('d-none');
                    if (input) {
                        input.setAttribute('required', 'required');
                        var budgetInput = document.querySelector('input[name="estimated_budget"]');
                        if (budgetInput && budgetInput.value && !input.value) {
                            input.value = budgetInput.value;
                        }
                    }
                } else {
                    wrapper.classList.add('d-none');
                    if (input) {
                        input.removeAttribute('required');
                        input.value = '';
                    }
                }
            };

            // Let's keep track of edit line indexes
            let editLineIndex = 0;

            // Populate Report Edit Modal
            $(document).on('click', '.btn-edit-report', function() {
                var id = this.getAttribute('data-id');
                var title = this.getAttribute('data-title');
                var travelId = this.getAttribute('data-travel-id');
                var advanceId = this.getAttribute('data-advance-id');
                var claims = JSON.parse(this.getAttribute('data-claims') || '[]');

                var form = document.querySelector('#editReportModal form');
                if (form) form.action = '{{ url('hrms/travel-expense/report') }}/' + id + '/update';

                document.getElementById('edit_report_title').value = title || '';
                var advanceText = this.getAttribute('data-advance-text') || '';
                if (advanceId && !$('#edit_report_cash_advance option[value="' + advanceId + '"]').length) {
                    $('#edit_report_cash_advance').append(new Option(advanceText || ('Advance #' + advanceId), advanceId, true, true));
                }
                $('#edit_report_travel_request').val(travelId || '').trigger('change.select2');
                $('#edit_report_cash_advance').val(advanceId || '').trigger('change.select2');

                var editTableBody = document.querySelector('#editClaimsLinesTable tbody');
                editTableBody.innerHTML = ''; // Clear previous rows

                editLineIndex = 0;

                claims.forEach(function(claim) {
                    var dateStr = claim.expense_date;
                    if (dateStr && dateStr.includes('T')) {
                        dateStr = dateStr.split('T')[0];
                    }
                    
                    var receiptLabelHtml = '';
                    let rPaths = [];
                    if (claim.receipt_path) {
                        if (typeof claim.receipt_path === 'string' && claim.receipt_path.startsWith('[')) {
                            try { rPaths = JSON.parse(claim.receipt_path); } catch(e) { rPaths = [claim.receipt_path]; }
                        } else if (Array.isArray(claim.receipt_path)) {
                            rPaths = claim.receipt_path;
                        } else {
                            rPaths = [claim.receipt_path];
                        }
                    }
                    if (rPaths.length > 0) {
                        receiptLabelHtml = `<div class="existing-receipts-container mb-1 d-flex flex-wrap gap-1 align-items-center justify-content-start">`;
                        rPaths.forEach(function(p, pIdx) {
                            receiptLabelHtml += `
                                <span class="badge bg-white text-success border border-success-subtle shadow-xs px-2 py-1 fs-11 rounded-pill d-inline-flex align-items-center me-1 mb-1 existing-receipt-chip">
                                    <a href="/storage/${p}" target="_blank" class="text-success text-decoration-none me-1"><i class="feather-paperclip me-1"></i>${travelTrans.file} ${rPaths.length > 1 ? (pIdx + 1) : ''}</a>
                                    <i class="feather-x cursor-pointer text-danger ms-1" style="font-size: 11px;" title="${travelTrans.removeFile}" onclick="removeExistingReceipt(this)"></i>
                                    <input type="hidden" name="claims[${editLineIndex}][existing_receipts][]" value="${p}">
                                </span>
                            `;
                        });
                        receiptLabelHtml += `</div>`;
                    }
                    var rejectionAlertHtml = '';
                    if ((claim.status || '').toLowerCase() === 'rejected') {
                        rejectionAlertHtml = `
                            <div class="alert alert-danger py-1 px-2 mb-1 fs-11 rounded" style="border-left: 3px solid #dc3545;">
                                <i class="feather-alert-triangle me-1"></i><strong>${travelTrans.rejectedByHr}</strong> ${claim.rejection_reason || travelTrans.correctDetailsOrAmount}
                            </div>
                        `;
                    }

                    var rowHtml = `
                        <tr class="edit-claim-line-row">
                            <input type="hidden" name="claims[${editLineIndex}][id]" value="${claim.id || ''}">
                            <input type="hidden" name="claims[${editLineIndex}][status]" value="${claim.status || ''}">
                            <input type="hidden" name="claims[${editLineIndex}][approved_amount]" value="${claim.approved_amount || ''}">
                            <td>
                                <select name="claims[${editLineIndex}][category_id]" class="form-select form-select-sm text-dark category-select odoo-select2" data-select2-selector="default" required>
                                    <option value="" disabled>${travelTrans.chooseCategory}</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" ${claim.expense_category_id == {{ $cat->id }} ? 'selected' : ''}>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                                <div class="policy-limit-badge text-danger fw-semibold mt-1 fs-11 d-none" style="letter-spacing: 0.2px;"><i class="feather-alert-triangle"></i> ${travelTrans.exceedsLimit}</div>
                            </td>
                            <td>
                                <input type="date" name="claims[${editLineIndex}][date]" class="form-control form-control-sm text-dark text-center" required value="${dateStr}">
                            </td>
                            <td>
                                <input type="number" name="claims[${editLineIndex}][amount]" step="0.01" min="0.01" class="form-control form-control-sm text-dark text-end claim-amount-input fw-semibold" placeholder="0.00" required value="${claim.amount}">
                            </td>
                            <td>
                                ${rejectionAlertHtml}
                                <input type="text" name="claims[${editLineIndex}][merchant]" class="form-control form-control-sm text-dark mb-1" placeholder="${travelTrans.placeholderMerchant}" value="${claim.merchant || ''}">
                                <input type="text" name="claims[${editLineIndex}][desc]" class="form-control form-control-sm text-dark" placeholder="${travelTrans.placeholderNotes}" value="${claim.description || ''}">
                            </td>
                            <td>
                                ${receiptLabelHtml}
                                <div class="erp-custom-file-upload">
                                    <label class="file-upload-label mb-0 cursor-pointer">
                                        <i class="feather-upload-cloud text-primary fs-14"></i>
                                        <span class="file-label-text text-muted">${rPaths.length > 0 ? travelTrans.addFiles : travelTrans.uploadFiles}</span>
                                        <input type="file" name="claims[${editLineIndex}][receipts][]" multiple class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
                                    </label>
                                    <div class="new-files-container d-flex flex-wrap gap-1 mt-1 justify-content-start"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-soft-danger btn-remove-edit-line p-0" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;" title="${travelTrans.removeRow}">
                                    <i class="feather-trash-2 fs-13"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    editTableBody.insertAdjacentHTML('beforeend', rowHtml);
                    editLineIndex++;
                });
                safeInitSelect2('#editReportModal');
            });

            // Add row inside Edit modal
            $(document).on('click', '#btnEditAddClaimLine', function() {
                var editTableBody = document.querySelector('#editClaimsLinesTable tbody');
                var rowHtml = `
                    <tr class="edit-claim-line-row">
                        <td>
                            <select name="claims[${editLineIndex}][category_id]" class="form-select form-select-sm text-dark category-select odoo-select2" data-select2-selector="default" required>
                                <option value="" disabled selected>${travelTrans.chooseCategory}</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <div class="policy-limit-badge text-danger fw-semibold mt-1 fs-11 d-none" style="letter-spacing: 0.2px;"><i class="feather-alert-triangle"></i> ${travelTrans.exceedsLimit}</div>
                        </td>
                        <td>
                            <input type="date" name="claims[${editLineIndex}][date]" class="form-control form-control-sm text-dark text-center" required value="{{ date('Y-m-d') }}">
                        </td>
                        <td>
                            <input type="number" name="claims[${editLineIndex}][amount]" step="0.01" min="0.01" class="form-control form-control-sm text-dark text-end claim-amount-input fw-semibold" placeholder="0.00" required>
                        </td>
                        <td>
                            <input type="text" name="claims[${editLineIndex}][merchant]" class="form-control form-control-sm text-dark mb-1" placeholder="${travelTrans.placeholderMerchant}">
                            <input type="text" name="claims[${editLineIndex}][desc]" class="form-control form-control-sm text-dark" placeholder="${travelTrans.placeholderNotes}">
                        </td>
                        <td>
                            <div class="erp-custom-file-upload">
                                <label class="file-upload-label mb-0 cursor-pointer">
                                    <i class="feather-upload-cloud text-primary fs-14"></i>
                                    <span class="file-label-text text-muted">${travelTrans.uploadFiles}</span>
                                    <input type="file" name="claims[${editLineIndex}][receipts][]" multiple class="d-none odoo-table-file-input" accept="image/*,application/pdf" onchange="updateRowFileName(this)">
                                </label>
                                <div class="new-files-container d-flex flex-wrap gap-1 mt-1 justify-content-start"></div>
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-soft-danger btn-remove-edit-line p-0" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;" title="${travelTrans.removeRow}">
                                <i class="feather-trash-2 fs-13"></i>
                            </button>
                        </td>
                    </tr>
                `;
                editTableBody.insertAdjacentHTML('beforeend', rowHtml);
                safeInitSelect2('#editReportModal');
                editLineIndex++;
            });

            // Remove row inside Edit modal
            $(document).on('click', '.btn-remove-edit-line', function() {
                var rows = document.querySelectorAll('.edit-claim-line-row');
                if (rows.length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert(travelTrans.minOneClaimRequired);
                }
            });

            // Populate Report View Modal
            $(document).on('click', '.btn-view-report', function() {
                var id = this.getAttribute('data-id');
                var title = this.getAttribute('data-title');
                var employee = this.getAttribute('data-employee');
                var travel = this.getAttribute('data-travel');
                var advance = this.getAttribute('data-advance');
                var total = this.getAttribute('data-total');
                var adjusted = this.getAttribute('data-adjusted');
                var net = this.getAttribute('data-net');
                var status = this.getAttribute('data-status');
                var claims = JSON.parse(this.getAttribute('data-claims') || '[]');
                var fullAdvance = parseFloat(this.getAttribute('data-full-advance')) || 0;
                var currentExpense = parseFloat(this.getAttribute('data-current-expense')) || 0;
                var surplusVal = parseFloat(this.getAttribute('data-surplus')) || 0;

                var payoutChannel = this.getAttribute('data-payout-channel') || 'accounting';
                
                document.getElementById('view_report_title').innerText = title || '';
                document.getElementById('view_report_employee').innerText = employee || '';
                document.getElementById('view_report_travel').innerText = travel || travelTrans.none;
                document.getElementById('view_report_advance').innerText = advance || travelTrans.none;
                var currSym = '{{ $currencySymbol }}';
                document.getElementById('view_report_total').innerText = total || (currSym + '0.00');
                document.getElementById('view_report_adjusted').innerText = adjusted || (currSym + '0.00');
                document.getElementById('view_report_net').innerText = net || (currSym + '0.00');

                // Display Refund warning if employee has surplus advance funds across trip
                var surplusWrapper = document.getElementById('view_report_surplus_wrapper');
                var surplusValEl = document.getElementById('view_report_surplus');
                if (surplusVal > 0) {
                    surplusValEl.innerText = currSym + surplusVal.toFixed(2);
                    surplusWrapper.classList.remove('d-none');
                } else {
                    surplusWrapper.classList.add('d-none');
                }

                // Render Payout Channel wrapper only if approved or paid
                var channelWrapper = document.getElementById('view_report_channel_wrapper');
                var channelEl = document.getElementById('view_report_channel');
                if (status.toLowerCase() === 'approved' || status.toLowerCase() === 'partially_approved' || status.toLowerCase() === 'paid') {
                    channelWrapper.classList.remove('d-none');
                    channelEl.className = 'badge px-2 py-1 fs-10 ';
                    if (payoutChannel === 'payroll') {
                        channelEl.className += 'bg-soft-info text-info';
                        channelEl.innerText = travelTrans.payroll;
                    } else {
                        channelEl.className += 'bg-soft-primary text-primary';
                        channelEl.innerText = travelTrans.accounting;
                    }
                } else {
                    channelWrapper.classList.add('d-none');
                }

                // Render status badge
                var badgeEl = document.getElementById('view_report_status');
                badgeEl.className = 'badge px-2.5 py-1 fs-11 rounded-pill ';
                if (status.toLowerCase() === 'draft') {
                    badgeEl.className += 'bg-secondary text-white';
                } else if (status.toLowerCase() === 'submitted') {
                    badgeEl.className += 'bg-soft-warning text-warning';
                } else if (status.toLowerCase() === 'approved') {
                    badgeEl.className += 'bg-soft-success text-success';
                } else if (status.toLowerCase() === 'partially_approved') {
                    badgeEl.className += 'bg-soft-info text-info';
                } else if (status.toLowerCase() === 'rejected') {
                    badgeEl.className += 'bg-soft-danger text-danger';
                } else {
                    badgeEl.className += 'bg-soft-primary text-primary';
                }
                var statusMap = {
                    'draft': travelTrans.statusDraft,
                    'submitted': travelTrans.statusSubmitted,
                    'approved': travelTrans.statusApproved,
                    'partially_approved': travelTrans.partiallyApproved,
                    'rejected': travelTrans.statusRejected,
                    'paid': travelTrans.statusPaid
                };
                badgeEl.innerText = statusMap[status.toLowerCase()] || status.replace('_', ' ');

                // Render claims table rows
                var viewTableBody = document.querySelector('#viewClaimsLinesTable tbody');
                viewTableBody.innerHTML = '';

                claims.forEach(function(claim) {
                    var categoryName = claim.category ? claim.category.name : 'Unknown';
                    var dateStr = claim.expense_date;
                    if (dateStr && dateStr.includes('T')) {
                        dateStr = dateStr.split('T')[0];
                    }

                    var receiptHtml = '-';
                    if (claim.receipt_path) {
                        let rPaths = [];
                        if (typeof claim.receipt_path === 'string' && claim.receipt_path.startsWith('[')) {
                            try { rPaths = JSON.parse(claim.receipt_path); } catch(e) { rPaths = [claim.receipt_path]; }
                        } else if (Array.isArray(claim.receipt_path)) {
                            rPaths = claim.receipt_path;
                        } else {
                            rPaths = [claim.receipt_path];
                        }
                        if (rPaths.length > 0) {
                            receiptHtml = rPaths.map((p, idx) => `<a href="/storage/${p}" target="_blank" class="btn btn-xs btn-soft-info py-0.5 px-2 fw-bold fs-10 me-1 mb-1"><i class="feather-image me-1"></i>${travelTrans.receipt} ${rPaths.length > 1 ? (idx + 1) : ''}</a>`).join('');
                        }
                    }

                    var itemStatusHtml = '';
                    var itemStatusStr = (claim.status || 'pending').toLowerCase();
                    if (itemStatusStr === 'approved' || itemStatusStr === 'paid') {
                        var appAmt = (claim.approved_amount !== null && claim.approved_amount !== undefined) ? parseFloat(claim.approved_amount) : parseFloat(claim.amount);
                        var labelText = itemStatusStr === 'paid' ? travelTrans.statusPaid : travelTrans.statusApproved;
                        var badgeClass = itemStatusStr === 'paid' ? 'bg-soft-primary text-primary' : 'bg-soft-success text-success';
                        itemStatusHtml = `<span class="badge ${badgeClass} px-2 py-1 fs-10"><i class="feather-check-circle me-1"></i>${labelText} (${currSym}${appAmt.toFixed(2)})</span>`;
                    } else if (itemStatusStr === 'rejected') {
                        itemStatusHtml = `<span class="badge bg-soft-danger text-danger px-2 py-1 fs-10" title="${claim.rejection_reason || ''}"><i class="feather-x-circle me-1"></i>${travelTrans.statusRejected}</span>`;
                        if (claim.rejection_reason) {
                            itemStatusHtml += `<br><small class="text-danger fs-11" style="word-break: break-word;">${claim.rejection_reason}</small>`;
                        }
                    } else {
                        itemStatusHtml = `<span class="badge bg-soft-warning text-warning px-2 py-1 fs-10"><i class="feather-clock me-1"></i>${travelTrans.statusPending}</span>`;
                    }

                    var rowHtml = `
                        <tr>
                            <td class="fw-semibold text-dark">${categoryName}</td>
                            <td>${dateStr}</td>
                            <td class="fw-bold text-dark">${currSym}${parseFloat(claim.amount).toFixed(2)}</td>
                            <td>${claim.merchant || '-'}</td>
                            <td>${claim.description || '-'}</td>
                            <td>${itemStatusHtml}</td>
                            <td>${receiptHtml}</td>
                        </tr>
                    `;
                    viewTableBody.insertAdjacentHTML('beforeend', rowHtml);
                });
            });

            // Real-time Item Decision Change Handler for Expense Report Approval
            window.handleItemDecisionChange = function(select, reportId, fullAdvance) {
                var claimId = select.getAttribute('data-claim-id');
                var amount = parseFloat(select.getAttribute('data-amount')) || 0;
                var decision = select.value;
                var rejectionInput = document.getElementById('item_rejection_reason_' + claimId);
                var approvedAmtInput = document.getElementById('item_approved_amt_' + claimId);

                if (decision === 'rejected') {
                    if (rejectionInput) {
                        rejectionInput.classList.remove('d-none');
                        rejectionInput.focus();
                    }
                    if (approvedAmtInput) approvedAmtInput.value = '0';
                } else {
                    if (rejectionInput) rejectionInput.classList.add('d-none');
                    if (approvedAmtInput) approvedAmtInput.value = amount.toString();
                }

                var modal = select.closest('.modal');
                if (modal) {
                    var totalApproved = 0;
                    var itemSelects = modal.querySelectorAll('.claim-item-decision-select');
                    itemSelects.forEach(function(sel) {
                        var itemAmt = parseFloat(sel.getAttribute('data-amount')) || 0;
                        if (sel.value === 'approved') {
                            totalApproved += itemAmt;
                        }
                    });

                    var mainApprovedInput = document.getElementById('approve_amount_input_' + reportId);
                    if (mainApprovedInput) {
                        mainApprovedInput.value = totalApproved.toFixed(2);
                        var effectiveAdv = modal.getAttribute('data-rem-advance') !== null 
                            ? parseFloat(modal.getAttribute('data-rem-advance')) 
                            : fullAdvance;
                        calculateApprovalNet(reportId, isNaN(effectiveAdv) ? fullAdvance : effectiveAdv);
                    }
                }
            };

            // Real-time Approval Net Payout/Refund Calculation
            window.calculateApprovalNet = function(id, advance) {
                var input = document.getElementById('approve_amount_input_' + id);
                if (!input) return;
                var approvedAmount = parseFloat(input.value) || 0;
                var payoutEl = document.getElementById('calc_payout_' + id);
                var surplusEl = document.getElementById('calc_surplus_' + id);
                var surplusWrapper = document.getElementById('calc_surplus_wrapper_' + id);
                var surplusNotice = document.getElementById('surplus_payroll_notice_' + id);
                var selectEl = document.getElementById('payout_channel_' + id);

                var payout = Math.max(approvedAmount - advance, 0);
                var currSym = '{{ $currencySymbol }}';
                payoutEl.innerText = currSym + payout.toFixed(2);

                if (approvedAmount < advance) {
                    var surplus = advance - approvedAmount;
                    surplusEl.innerText = currSym + surplus.toFixed(2);
                    surplusWrapper.classList.remove('d-none');
                    if (surplusNotice) surplusNotice.classList.remove('d-none');

                    // Auto-select payroll if employee owes refund (surplus exists)
                    if (selectEl && selectEl.value !== 'payroll') {
                        selectEl.value = 'payroll';
                    }
                } else {
                    surplusWrapper.classList.add('d-none');
                    if (surplusNotice) surplusNotice.classList.add('d-none');

                    // Default to accounting if net reimbursement is due
                    if (selectEl && selectEl.value !== 'accounting') {
                        selectEl.value = 'accounting';
                    }
                }

                // Dynamically refresh channel warning visibility
                togglePayoutChannelWarning(id);
            };

            window.togglePayoutChannelWarning = function(id) {
                var select = document.getElementById('payout_channel_' + id);
                var warning = document.getElementById('payroll_warning_' + id);
                var surplusWrapper = document.getElementById('calc_surplus_wrapper_' + id);
                if (select && warning) {
                    // Hide general payroll warning if there is a surplus refund notice visible instead
                    var hasSurplus = surplusWrapper && !surplusWrapper.classList.contains('d-none');
                    if (select.value === 'payroll' && !hasSurplus) {
                        warning.classList.remove('d-none');
                    } else {
                        warning.classList.add('d-none');
                    }
                }
            };

            // Trigger initial calculation when approval modal opens
            $(document).on('shown.bs.modal', '[id^="approveReportModal_"]', function () {
                var modalId = this.id;
                var id = modalId.replace('approveReportModal_', '');
                var remAdvAttr = this.getAttribute('data-rem-advance');
                var effectiveAdv = remAdvAttr !== null && !isNaN(parseFloat(remAdvAttr)) ? parseFloat(remAdvAttr) : 0;
                var firstSelect = this.querySelector('.claim-item-decision-select');
                if (firstSelect) {
                    handleItemDecisionChange(firstSelect, id, effectiveAdv);
                } else {
                    var input = document.getElementById('approve_amount_input_' + id);
                    if (input) calculateApprovalNet(id, effectiveAdv);
                }
                var select = document.getElementById('payout_channel_' + id);
                if (select) {
                    select.dispatchEvent(new Event('change'));
                }
            });
            // Handle "Claim Expense" / "Supplementary Claim" button clicks across tabs
            $(document).on('click', '.claim-travel-expense-btn, .claim-supplementary-btn', function (e) {
                e.preventDefault();
                var travelId = $(this).data('travel-id');
                var advanceId = $(this).data('cash-advance-id');
                var customTitle = $(this).data('title');

                // Open File Expense Claim Modal (#addReportModal) in current tab
                var addReportModalEl = document.getElementById('addReportModal');
                if (addReportModalEl) {
                    var modalInstance = bootstrap.Modal.getOrCreateInstance(addReportModalEl);
                    modalInstance.show();

                    // Pre-select Travel Trip, Title & Cash Advance dropdowns
                    setTimeout(function () {
                        if (customTitle) {
                            var titleInput = document.querySelector('#addReportModal input[name="title"]');
                            if (titleInput) titleInput.value = customTitle;
                        }
                        if (travelId && $('#report_travel_request').length) {
                            $('#report_travel_request').val(travelId).trigger('change');
                        }
                        if (advanceId && $('#report_cash_advance').length) {
                            $('#report_cash_advance').val(advanceId).trigger('change');
                        }
                    }, 200);
                }
            });

            @if($errors->any())
                @if($errors->has('destination') || $errors->has('start_date') || $errors->has('end_date') || $errors->has('estimated_budget'))
                    var modal = new bootstrap.Modal(document.getElementById('addTravelModal'));
                    modal.show();
                @elseif($errors->has('amount'))
                    var modal = new bootstrap.Modal(document.getElementById('addAdvanceModal'));
                    modal.show();
                @elseif($errors->has('title') || $errors->has('claims') || $errors->has('claims.*'))
                    var modal = new bootstrap.Modal(document.getElementById('addReportModal'));
                    modal.show();
                @endif
            @endif
        });
    </script>
@endpush
