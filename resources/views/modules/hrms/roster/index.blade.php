@extends('layouts.duralux')

@section('title', __('hrms.roster.title') . ' | SaaS ERP')
@section('page-title', __('hrms.roster.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.roster.title'))

@section('page-actions')
    @if($tab === 'shifts')
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addShiftModal">
            {{ __('hrms.roster.add_shift') }}
        </x-ui.button>
    @elseif($tab === 'roster')
        <div class="d-flex gap-2">
            <x-ui.button variant="primary" icon="feather-calendar" data-bs-toggle="modal" data-bs-target="#assignRosterModal">
                {{ __('hrms.roster.assign_roster') }}
            </x-ui.button>
        </div>
    @elseif($tab === 'weekly_patterns')
        <div class="d-flex gap-2">
            <x-ui.button variant="primary" icon="feather-calendar" data-bs-toggle="modal" data-bs-target="#assignWeeklyModal">
                Assign Weekly Defaults
            </x-ui.button>
        </div>
    @endif
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Sidebar and Workspace layout alignment */
        @media (min-width: 992px) {
            .nxl-content {
                padding: 0 !important;
            }
            .page-header {
                padding: 24px 24px 16px 24px !important;
                margin-bottom: 0 !important;
                border-bottom: 1px solid #e5e7eb;
                background-color: #fff;
            }
            .main-content {
                padding: 0 !important;
            }
            .settings-container {
                display: flex;
                min-height: calc(100vh - 120px);
                background-color: #f8fafc;
            }
            .settings-sidebar-col {
                width: 280px;
                min-width: 280px;
                background-color: #fff;
                border-right: 1px solid #e5e7eb;
                display: flex;
                flex-direction: column;
            }
            .settings-content-col {
                flex-grow: 1;
                padding: 24px 30px;
                background-color: #f8fafc;
                min-width: 0;
            }
        }

        @media (max-width: 991.98px) {
            .settings-sidebar-col {
                width: 100%;
                background-color: #fff;
                border-bottom: 1px solid #e5e7eb;
                margin-bottom: 20px;
                padding: 10px;
            }
            .settings-content-col {
                width: 100%;
                padding: 0 15px;
            }
        }

        /* Underlined Horizontal Tabs */
        #rosterTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 500;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
        }
        #rosterTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #rosterTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
        }

        /* Responsive Grid & Size constraints to fit within 90% viewports */
        .roster-grid-table {
            table-layout: fixed !important;
            width: 100% !important;
        }
        .roster-grid-table th.employee-head,
        .roster-grid-table td.employee-cell {
            width: 200px !important;
            min-width: 200px !important;
            max-width: 200px !important;
            text-align: left !important;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .roster-grid-table th.date-head,
        .roster-grid-table td.date-cell {
            width: 108px !important;
            min-width: 108px !important;
            max-width: 108px !important;
            padding: 4px !important;
        }
        .roster-grid-table th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            vertical-align: middle;
        }
        .roster-cell-select, .weekly-pattern-select {
            cursor: pointer;
            font-size: 10px !important;
            font-weight: 700 !important;
            text-align: center !important;
            text-align-last: center !important;
            padding: 2px 14px 2px 4px !important;
            height: 28px !important;
            border: 1px solid transparent !important;
            background-color: transparent !important;
            border-radius: 4px;
            transition: all 0.15s ease-in-out;
            width: 100%;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
            background-position: right 4px center !important;
            background-size: 8px 10px !important;
        }
        .roster-cell-select:hover, .roster-cell-select:focus,
        .weekly-pattern-select:hover, .weekly-pattern-select:focus {
            background-color: rgba(0, 0, 0, 0.04) !important;
            border-color: #cbd5e1 !important;
        }
        .roster-cell-select option, .weekly-pattern-select option, .modal select.form-select option {
            font-family: inherit;
            font-size: 12px;
            color: #000000 !important;
            background-color: #ffffff;
            text-align: left;
        }
        .roster-cell-select option[value="off"], .weekly-pattern-select option[value="off"], .modal select.form-select option[value="off"] {
            color: #dc2626 !important;
        }
        .roster-cell-select option:checked, .weekly-pattern-select option:checked, .modal select.form-select option:checked {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }
        .modal select.form-select:focus {
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.15) !important;
        }

        /* Custom form-validation overrides for border containers */
        .border.is-invalid {
            border-color: #dc3545 !important;
        }

        /* Soft badge colors */
        .bg-soft-primary { background-color: rgba(var(--bs-primary-rgb), 0.08) !important; }
        .text-primary { color: var(--bs-primary) !important; }
        .bg-soft-secondary { background-color: rgba(100, 116, 139, 0.08) !important; }
        .text-secondary { color: #64748b !important; }
        .bg-soft-light { background-color: rgba(241, 245, 249, 0.6) !important; }
        .text-muted { color: #94a3b8 !important; }

        /* Multi-select styling adjustment in Modal */
        .select2-container--bootstrap-5 .select2-selection--multiple {
            border: 1px solid #ced4da !important;
            border-radius: 4px !important;
            padding: 2px 4px !important;
        }
        
        /* Theme Filter Apply and Reset Buttons styling */
        .roster-filter-apply-btn {
            border-radius: 6px !important;
            font-size: 11px !important;
            letter-spacing: 0.05em !important;
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #fff !important;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08) !important;
            transition: all 0.2s ease-in-out !important;
        }
        .roster-filter-apply-btn:hover,
        .roster-filter-apply-btn:focus {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            filter: brightness(0.9) !important;
            color: #fff !important;
        }
        .roster-filter-reset-btn {
            border-radius: 6px !important;
            font-size: 11px !important;
            letter-spacing: 0.05em !important;
            background-color: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            color: #475569 !important;
            transition: all 0.2s ease-in-out !important;
        }
        .roster-filter-reset-btn:hover,
        .roster-filter-reset-btn:focus {
            background-color: #e2e8f0 !important;
            color: #0f172a !important;
        }

        /* Select2 elements inside cells dropdown */
        .roster-grid-table .select2-container--bootstrap-5 {
            width: 100% !important;
        }
        .roster-grid-table .select2-container--bootstrap-5 .select2-selection,
        .roster-grid-table .select2-container .select2-selection--single {
            background-color: transparent !important;
            border: 1px solid transparent !important;
            height: 28px !important;
            padding: 0 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 28px !important;
            line-height: 26px !important;
        }
        .roster-grid-table .select2-container--bootstrap-5 .select2-selection:hover {
            background-color: rgba(0, 0, 0, 0.04) !important;
            border-color: #cbd5e1 !important;
        }
        .roster-grid-table .select2-container .select2-selection__rendered,
        .roster-grid-table .select2-container .select2-selection--single .select2-selection__rendered,
        .roster-grid-table .select2-selection__rendered {
            font-size: 10px !important;
            font-weight: 700 !important;
            color: inherit !important;
            padding: 0 14px 0 4px !important;
            text-align: center;
            line-height: 26px !important;
        }
        .roster-grid-table .select2-container--bootstrap-5 .select2-selection__arrow,
        .roster-grid-table .select2-container .select2-selection--single .select2-selection__arrow {
            width: 12px !important;
            right: 4px !important;
            height: 26px !important;
        }
        .roster-grid-table .select2-container--bootstrap-5 .select2-selection__arrow b {
            border-color: #64748b transparent transparent transparent !important;
            border-width: 4px 3px 0 3px !important;
            margin-left: -3px !important;
            margin-top: -1px !important;
        }
        
        /* Dropdown options styling */
        .select2-container--bootstrap-5 .select2-dropdown {
            border-color: var(--bs-primary) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
        }
        .select2-container--bootstrap-5 .select2-results__option {
            font-size: 11px !important;
            padding: 4px 8px !important;
            font-weight: 700 !important;
        }
        .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }
        .select2-container--bootstrap-5 .select2-results__option[aria-selected=true] {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }
        .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] span {
            color: #ffffff !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="settings-container">
        <!-- Left Subsidebar Column -->
        <div class="settings-sidebar-col">
            @include('modules.hrms.partials.settings-sidebar')
        </div>

        <!-- Right Content Column -->
        <div class="settings-content-col flex-grow-1">
            @if(session('success'))
                <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                    {{ session('success') }}
                </x-ui.alert>
            @endif
            @if(session('error'))
                <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                    {{ session('error') }}
                </x-ui.alert>
            @endif

            <div class="tab-content" id="rosterSettingsContent">
                <div class="tab-pane fade show active" id="roster-pane" role="tabpanel">
                    <div class="row">
                        <!-- Horizontal Navigation directly above content (Shift Master is now First/Default) -->
                        <div class="col-12 mb-3">
                            <ul class="nav gap-2 border-bottom pb-2" id="rosterTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link {{ $tab === 'shifts' ? 'active' : '' }}" href="{{ route('hrms.roster.index', ['tab' => 'shifts']) }}">
                                        <i class="feather-clock me-2"></i>{{ __('hrms.roster.shift_master') }}
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link {{ $tab === 'weekly_patterns' ? 'active' : '' }}" href="{{ route('hrms.roster.index', ['tab' => 'weekly_patterns']) }}">
                                        <i class="feather-repeat me-2"></i>Weekly Patterns
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link {{ $tab === 'roster' ? 'active' : '' }}" href="{{ route('hrms.roster.index', ['tab' => 'roster']) }}">
                                        <i class="feather-calendar me-2"></i>{{ __('hrms.roster.roster_board') }}
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Tab Content Views -->
                        <div class="col-12">
                            @if($tab === 'shifts')
                                <!-- SHIFT MASTER TAB (Default View) -->
                                <div class="row">
                                    <div class="col-12">
                                        <x-ui.card title="{{ __('hrms.roster.shifts') }}" stretch bodyClass="p-0">
                                            <input type="hidden" id="shift_sort_value" value="{{ $shiftSort }}">
                                            <x-slot name="headerAction">
                                                 <div class="d-flex align-items-center gap-2 flex-wrap">
                                                     <!-- Search Input -->
                                                     <form method="GET" action="{{ route('hrms.roster.index') }}" id="shiftSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 240px; height: 38px;">
                                                         <input type="hidden" name="tab" value="shifts">
                                                         <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                                         <input type="text" name="shift_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.roster.search_shifts') }}" value="{{ $shiftSearch }}" style="box-shadow: none; height: 32px; outline: none;">
                                                     </form>

                                                     <!-- Sort Dropdown -->
                                                     <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                                                         <div id="shift_sort_dropdown_menu">
                                                             <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $shiftSort === 'name_asc' ? 'active' : '' }}" href="#" data-sort="name_asc" onclick="changeShiftSort('name_asc', this); event.preventDefault();">
                                                                 <span>{{ __('hrms.common.sort_name_asc') }}</span>
                                                                 @if($shiftSort === 'name_asc') <i class="feather-check ms-3"></i> @endif
                                                             </a>
                                                             <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $shiftSort === 'name_desc' ? 'active' : '' }}" href="#" data-sort="name_desc" onclick="changeShiftSort('name_desc', this); event.preventDefault();">
                                                                 <span>{{ __('hrms.common.sort_name_desc') }}</span>
                                                                 @if($shiftSort === 'name_desc') <i class="feather-check ms-3"></i> @endif
                                                             </a>
                                                             <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $shiftSort === 'code_asc' ? 'active' : '' }}" href="#" data-sort="code_asc" onclick="changeShiftSort('code_asc', this); event.preventDefault();">
                                                                 <span>{{ __('hrms.roster.sort_code_asc') }}</span>
                                                                 @if($shiftSort === 'code_asc') <i class="feather-check ms-3"></i> @endif
                                                             </a>
                                                             <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $shiftSort === 'code_desc' ? 'active' : '' }}" href="#" data-sort="code_desc" onclick="changeShiftSort('code_desc', this); event.preventDefault();">
                                                                 <span>{{ __('hrms.roster.sort_code_desc') }}</span>
                                                                 @if($shiftSort === 'code_desc') <i class="feather-check ms-3"></i> @endif
                                                             </a>
                                                             <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $shiftSort === 'start_asc' ? 'active' : '' }}" href="#" data-sort="start_asc" onclick="changeShiftSort('start_asc', this); event.preventDefault();">
                                                                 <span>{{ __('hrms.roster.sort_start_asc') }}</span>
                                                                 @if($shiftSort === 'start_asc') <i class="feather-check ms-3"></i> @endif
                                                             </a>
                                                             <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $shiftSort === 'start_desc' ? 'active' : '' }}" href="#" data-sort="start_desc" onclick="changeShiftSort('start_desc', this); event.preventDefault();">
                                                                 <span>{{ __('hrms.roster.sort_start_desc') }}</span>
                                                                 @if($shiftSort === 'start_desc') <i class="feather-check ms-3"></i> @endif
                                                             </a>
                                                         </div>
                                                     </x-ui.sort-dropdown>

                                                     <!-- Filter Dropdown -->
                                                     <x-ui.filter label="{{ __('hrms.common.filter') }}">
                                                         <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                                                         <form method="GET" action="{{ route('hrms.roster.index') }}" id="shiftFilterForm">
                                                              <div class="mb-3">
                                                                  <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.roster.companies') }}</label>
                                                                  <x-ui.odoo-form-ui type="select" name="shift_company_id" id="shift_filter_company_id">
                                                                      <option value="">{{ __('hrms.common.all_companies') }}</option>
                                                                      @foreach($companies as $company)
                                                                          <option value="{{ $company->id }}" @selected((string) request('shift_company_id') === (string) $company->id)>{{ $company->company_name }}</option>
                                                                      @endforeach
                                                                  </x-ui.odoo-form-ui>
                                                              </div>

                                                              <div class="mb-3">
                                                                  <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.org.status') }}</label>
                                                                  <x-ui.odoo-form-ui type="select" name="shift_status" id="shift_filter_status">
                                                                      <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                                                      <option value="1" @selected($shiftStatus === '1')>{{ __('hrms.employees.frm_status_active') }}</option>
                                                                      <option value="0" @selected($shiftStatus === '0')>{{ __('hrms.employees.frm_status_inactive') }}</option>
                                                                  </x-ui.odoo-form-ui>
                                                              </div>

                                                              <div class="mb-3">
                                                                  <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">{{ __('hrms.roster.overtime') }}</label>
                                                                  <x-ui.odoo-form-ui type="select" name="shift_overtime" id="shift_filter_overtime">
                                                                      <option value="">{{ __('hrms.common.all') }}</option>
                                                                      <option value="1" @selected($shiftOvertime === '1')>{{ __('hrms.roster.allowed') }}</option>
                                                                      <option value="0" @selected($shiftOvertime === '0')>{{ __('hrms.roster.not_allowed') }}</option>
                                                                  </x-ui.odoo-form-ui>
                                                              </div>

                                                              <div class="d-flex gap-2 justify-content-end mt-4">
                                                                  <a href="#" id="btn-reset-shift-filters" class="btn btn-sm btn-light text-uppercase fw-bold py-2 px-3 border" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em; background-color: #f1f5f9; border-color: #cbd5e1; color: #475569;">{{ __('hrms.common.reset') }}</a>
                                                                  <button type="submit" class="btn btn-sm btn-primary text-uppercase fw-bold py-2 px-3 text-white" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em;">{{ __('hrms.common.apply') }}</button>
                                                              </div>
                                                         </form>
                                                     </x-ui.filter>
</div>
                                             </x-slot>

                                             <div class="table-responsive">
                                                  <table class="table table-hover mb-0 align-middle" id="shiftsTable">
                                                      <thead class="table-light">
                                                          <tr>
                                                              <th width="60">#</th>
                                                              <th>{{ __('hrms.roster.shift_name') }}</th>
                                                              <th>{{ __('hrms.org.company') }}</th>
                                                              <th>{{ __('hrms.roster.shift_timing') }}</th>
                                                              <th>{{ __('hrms.roster.break_duration') }}</th>
                                                              <th>{{ __('hrms.roster.overtime_allowed') }}</th>
                                                              <th>{{ __('hrms.org.status') }}</th>
                                                              <th width="150" class="text-end">{{ __('hrms.org.tbl_actions') }}</th>
                                                          </tr>
                                                      </thead>
                                                      <tbody>
                                                          @foreach($shifts as $sf)
                                                          <tr class="shift-row"
                                                              data-name="{{ strtolower($sf->name) }}"
                                                              data-code="{{ strtolower($sf->code) }}"
                                                              data-status="{{ $sf->active ? 'active' : 'inactive' }}"
                                                              data-overtime="{{ $sf->overtime_allowed ? 'allowed' : 'not_allowed' }}">
                                                              <td class="shift-index-cell">{{ $loop->iteration }}</td>
                                                              <td>
                                                                  <span class="fw-bold text-dark shift-name-label d-block">{{ $sf->name }}</span>
                                                                  <small class="text-muted font-monospace shift-code-label fs-11">{{ $sf->code }}</small>
                                                              </td>
                                                              <td>
                                                                  @if($sf->company)
                                                                      <span class="text-muted fs-12">{{ $sf->company->company_name }}</span>
                                                                  @else
                                                                      <span class="badge bg-soft-secondary text-secondary">{{ __('hrms.roster.shared_all') }}</span>
                                                                  @endif
                                                              </td>
                                                              <td><span class="font-monospace text-dark">{{ substr($sf->start_time, 0, 5) }} - {{ substr($sf->end_time, 0, 5) }}</span></td>
                                                              <td><span>{{ $sf->break_minutes ?? 0 }} {{ __('hrms.roster.mins') }}</span></td>
                                                              <td>
                                                                  @if($sf->overtime_allowed)
                                                                     <x-ui.badge variant="success" soft>{{ __('hrms.common.yes') }}</x-ui.badge>
                                                                 @else
                                                                     <x-ui.badge variant="danger" soft>{{ __('hrms.common.no') }}</x-ui.badge>
                                                                 @endif
                                                             </td>
                                                             <td>
                                                                 @if($sf->active)
                                                                     <x-ui.badge variant="success" soft>{{ __('hrms.employees.frm_status_active') }}</x-ui.badge>
                                                                 @else
                                                                     <x-ui.badge variant="danger" soft>{{ __('hrms.employees.frm_status_inactive') }}</x-ui.badge>
                                                                 @endif
                                                             </td>
                                                             <td class="text-end">
                                                                 <form action="{{ route('hrms.shift.destroy', $sf->id) }}" method="POST" class="d-inline" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.roster.delete_shift_confirm') }}', { title: 'Delete Shift', variant: 'danger', confirmButtonText: 'Delete' });">
                                                                     @csrf
                                                                     @method('DELETE')
                                                                    <div class="hstack gap-2 justify-content-end">
                                                                        <x-ui.action-dropdown>
                                                                            <li>
                                                                                <a class="dropdown-item btn-edit-shift" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editShiftModal" data-shift="{{ base64_encode($sf->toJson()) }}">
                                                                                    <i class="feather feather-edit-3 me-3"></i>
                                                                                    <span>{{ __('hrms.assets.edit') }}</span>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                                                    <i class="feather feather-trash-2 me-3"></i>
                                                                                    <span>{{ __('hrms.common.delete') }}</span>
                                                                                </button>
                                                                            </li>
                                                                        </x-ui.action-dropdown>
                                                                    </div>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                        @if($shifts->isEmpty())
                                                        <tr>
                                                            <td colspan="8" class="text-center py-5 text-muted">
                                                                {{ __('hrms.roster.no_shifts_found') }}
                                                            </td>
                                                        </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                             </div>
                                             @php
                                                 $currentPage = $shifts->currentPage();
                                                 $totalPages = $shifts->lastPage();
                                                 $totalResults = $shifts->total();
                                                 $perPage = $shifts->perPage();
                                             @endphp
                                              <div class="px-4 py-3 border-top bg-light-soft shift-pagination-container">
                                                  <x-ui.pagination 
                                                      :current-page="$currentPage"
                                                      :total-pages="$totalPages"
                                                      :total-results="$totalResults"
                                                      :per-page="$perPage"
                                                      page-param="shift_page"
                                                  />
                                              </div>
                                        </x-ui.card>
                                    </div>
                                </div>
                            @elseif($tab === 'weekly_patterns')
                                <!-- WEEKLY PATTERNS TAB -->
                                 <x-ui.card title="Weekly Shift Patterns" stretch>
                                     <x-slot name="headerAction">
                                         <form method="GET" action="{{ route('hrms.roster.index') }}" id="weeklyPatternFilterForm" class="d-flex align-items-center gap-2">
                                             <input type="hidden" name="tab" value="weekly_patterns">
                                             <input type="hidden" name="sort" id="weeklyPatternSortInput" value="{{ $sortBy }}">
 
                                             <!-- Search Bar -->
                                             <div class="position-relative" style="width: 240px;">
                                                 <i class="feather-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 14px;"></i>
                                                 <input type="text" id="weeklyPatternSearch" name="search" class="form-control form-control-sm ps-5 border-0" placeholder="{{ __('hrms.roster.search_employees') }}" value="{{ $search }}" style="height: 38px; border-radius: 8px; font-size: 13px; color: #475569; background-color: #f1f5f9;">
                                             </div>
 
                                             <!-- SORT Dropdown -->
                                             <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                                                 <button type="button" class="dropdown-item sort-option {{ $sortBy === 'name-asc' ? 'active' : '' }}" data-sort="name-asc">
                                                     {{ __('hrms.common.sort_name_asc') }}
                                                 </button>
                                                 <button type="button" class="dropdown-item sort-option {{ $sortBy === 'name-desc' ? 'active' : '' }}" data-sort="name-desc">
                                                     {{ __('hrms.common.sort_name_desc') }}
                                                 </button>
                                                 <button type="button" class="dropdown-item sort-option {{ $sortBy === 'designation-asc' || $sortBy === 'designation' ? 'active' : '' }}" data-sort="designation-asc">
                                                     {{ __('hrms.roster.sort_designation_asc') }}
                                                 </button>
                                                 <button type="button" class="dropdown-item sort-option {{ $sortBy === 'designation-desc' ? 'active' : '' }}" data-sort="designation-desc">
                                                     {{ __('hrms.roster.sort_designation_desc') }}
                                                 </button>
                                             </x-ui.sort-dropdown>
 
                                             <!-- Filter Dropdown -->
                                             <x-ui.filter label="{{ __('hrms.common.filter') }}">
                                                 <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                                                 
                                                 <div class="mb-3">
                                                     <label class="form-label fw-bold text-secondary fs-10 text-uppercase mb-1" style="letter-spacing: 0.05em; color: #64748b !important;">{{ __('hrms.employees.tbl_company') }}</label>
                                                     <x-ui.odoo-form-ui type="select" name="company_id" id="weekly_filter_company">
                                                         <option value="">{{ __('hrms.common.all_companies') }}</option>
                                                         @foreach($companies as $company)
                                                             <option value="{{ $company->id }}" {{ $selectedCompanyId == $company->id ? 'selected' : '' }}>
                                                                 {{ $company->company_name }}
                                                             </option>
                                                         @endforeach
                                                     </x-ui.odoo-form-ui>
                                                 </div>
 
                                                 <div class="mb-3">
                                                     <label class="form-label fw-bold text-secondary fs-10 text-uppercase mb-1" style="letter-spacing: 0.05em; color: #64748b !important;">{{ __('hrms.employees.tbl_department') }}</label>
                                                     <x-ui.odoo-form-ui type="select" name="department_id" id="weekly_filter_department">
                                                         <option value="">{{ __('hrms.roster.all_departments') }}</option>
                                                         @foreach($departments as $dept)
                                                             <option value="{{ $dept->id }}" data-company-id="{{ $dept->company_id }}" {{ $selectedDepartmentId == $dept->id ? 'selected' : '' }}>
                                                                 {{ $dept->name }}
                                                             </option>
                                                         @endforeach
                                                     </x-ui.odoo-form-ui>
                                                 </div>
 
                                                 <div class="mb-3">
                                                     <label class="form-label fw-bold text-secondary fs-10 text-uppercase mb-1" style="letter-spacing: 0.05em; color: #64748b !important;">{{ __('hrms.employees.tbl_designation') }}</label>
                                                     <x-ui.odoo-form-ui type="select" name="designation_id" id="weekly_filter_designation">
                                                         <option value="">{{ __('hrms.roster.all_designations') }}</option>
                                                         @foreach($designations as $desg)
                                                             <option value="{{ $desg->id }}" {{ $selectedDesignationId == $desg->id ? 'selected' : '' }}>
                                                                 {{ $desg->name }}
                                                             </option>
                                                         @endforeach
                                                     </x-ui.odoo-form-ui>
                                                 </div>
 
                                                 <div class="dropdown-divider my-3"></div>
 
                                                 <div class="d-flex gap-2">
                                                     <button type="submit" class="btn btn-sm roster-filter-apply-btn w-100 fw-bold py-2 text-uppercase">{{ __('hrms.common.apply') }}</button>
                                                     <a href="#" id="btn-reset-weekly-filters" class="btn btn-sm roster-filter-reset-btn w-100 fw-bold py-2 text-center text-uppercase">{{ __('hrms.common.reset') }}</a>
                                                 </div>
                                             </x-ui.filter>
                                         </form>
                                     </x-slot>
 
                                     <!-- Weekly Defaults Grid Matrix -->
                                     <div class="table-responsive border rounded bg-white" id="weeklyPatternsGrid">
                                         <table class="table table-bordered table-hover mb-0 align-middle text-center roster-grid-table">
                                             <thead class="table-light">
                                                 <tr>
                                                     <th class="employee-head">{{ __('hrms.roster.employee_name') }}</th>
                                                     @foreach([1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 0 => 'sunday'] as $dayVal => $dayName)
                                                         <th class="date-head">
                                                             <div class="fw-bold text-dark text-uppercase">
                                                                 {{ \Carbon\Carbon::parse('2026-07-20')->addDays($dayVal === 0 ? 6 : $dayVal - 1)->translatedFormat('D') }}
                                                             </div>
                                                         </th>
                                                     @endforeach
                                                 </tr>
                                             </thead>
                                             <tbody>
                                                 @foreach($employees as $employee)
                                                     <tr>
                                                         <td class="employee-cell">
                                                             <div class="d-flex align-items-center gap-2">
                                                                 <div class="avatar-text avatar-sm bg-soft-primary text-primary fw-bold" style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; font-size: 11px;">
                                                                     {{ strtoupper(substr($employee->full_name, 0, 2)) ?: 'EM' }}
                                                                 </div>
                                                                 <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px;">
                                                                     <div class="fw-bold text-dark fs-12 employee-name-label" style="font-size: 12px; line-height: 1.2;">{{ $employee->full_name }}</div>
                                                                     <div class="text-muted fs-10 employee-designation-label" style="font-size: 10px; line-height: 1.2;">{{ $employee->designation?->name ?? __('hrms.roster.no_designation') }}</div>
                                                                 </div>
                                                             </div>
                                                         </td>
                                                         @foreach([1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 0 => 'sunday'] as $dayVal => $dayName)
                                                             @php
                                                                 $assignedVal = (isset($employee->weekly_pattern) && isset($employee->weekly_pattern[$dayVal])) ? $employee->weekly_pattern[$dayVal] : '';
                                                                 $cellBg = 'bg-transparent';
                                                                 if ($assignedVal === 'off') {
                                                                     $cellBg = 'bg-soft-secondary';
                                                                 } elseif ($assignedVal) {
                                                                     $cellBg = 'bg-soft-primary';
                                                                 } else {
                                                                     $cellBg = 'bg-soft-light';
                                                                 }
                                                             @endphp
                                                             <td class="date-cell {{ $cellBg }}" style="transition: all 0.2s ease;">
                                                                 <select 
                                                                     class="form-select form-select-sm weekly-pattern-select" 
                                                                     data-employee-id="{{ $employee->id }}" 
                                                                     data-day-of-week="{{ $dayVal }}"
                                                                 >
                                                                     <option value="" {{ $assignedVal === '' ? 'selected' : '' }}>
                                                                         {{ $employee->shift?->code ? $employee->shift->code . ' (D)' : __('hrms.roster.off_default') }}
                                                                     </option>
                                                                     <option value="off" class="text-secondary fw-bold" {{ $assignedVal === 'off' ? 'selected' : '' }}>
                                                                         {{ __('hrms.roster.off') }}
                                                                     </option>
                                                                     @foreach($activeShifts->filter(fn($s) => ($s->company_id === null || $s->company_id == $employee->company_id) && $s->id != $employee->shift_id) as $ashift)
                                                                         <option value="{{ $ashift->id }}" class="text-primary fw-bold" {{ (string)$assignedVal === (string)$ashift->id ? 'selected' : '' }}>
                                                                             {{ $ashift->code }}
                                                                         </option>
                                                                     @endforeach
                                                                 </select>
                                                             </td>
                                                         @endforeach
                                                     </tr>
                                                 @endforeach
                                                 @if($employees->isEmpty())
                                                     <tr>
                                                         <td colspan="8" class="text-center py-5 text-muted">
                                                             {{ __('hrms.roster.no_employees_matching') }}
                                                         </td>
                                                     </tr>
                                                 @endif
                                             </tbody>
                                         </table>
                                     </div>
                                     @php
                                         $currentPage = $employees->currentPage();
                                         $totalPages = $employees->lastPage();
                                         $totalResults = $employees->total();
                                         $perPage = $employees->perPage();
                                     @endphp
                                      <div class="px-4 py-3 border-top bg-light-soft weekly-pagination-container">
                                         <x-ui.pagination 
                                             :current-page="$currentPage"
                                             :total-pages="$totalPages"
                                             :total-results="$totalResults"
                                             :per-page="$perPage"
                                             page-param="roster_page"
                                         />
                                      </div>
                                 </x-ui.card>
                            @else
                                <!-- ROSTER BOARD TAB -->
                                 <x-ui.card title="{{ __('hrms.roster.roster_scheduler_grid') }}" stretch>
                                     <x-slot name="headerAction">
                                         <form method="GET" action="{{ route('hrms.roster.index') }}" id="rosterFilterForm" class="d-flex align-items-center gap-2">
                                             <input type="hidden" name="tab" value="roster">
                                             <input type="hidden" name="sort" id="filterSortInput" value="{{ $sortBy }}">
 
                                             <!-- 1. Search Bar (Order 1: Single element, soft grey bg, rounded) -->
                                             <div class="position-relative" style="width: 240px;">
                                                 <i class="feather-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 14px;"></i>
                                                 <input type="text" id="rosterSearch" name="search" class="form-control form-control-sm ps-5 border-0" placeholder="{{ __('hrms.roster.search_employees') }}" value="{{ $search }}" style="height: 38px; border-radius: 8px; font-size: 13px; color: #475569; background-color: #f1f5f9;">
                                             </div>
 
                                             <!-- 2. SORT Button (using Duralux component) -->
                                             <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                                                 <button type="button" class="dropdown-item sort-option {{ $sortBy === 'name-asc' ? 'active' : '' }}" data-sort="name-asc">
                                                     {{ __('hrms.common.sort_name_asc') }}
                                                 </button>
                                                 <button type="button" class="dropdown-item sort-option {{ $sortBy === 'name-desc' ? 'active' : '' }}" data-sort="name-desc">
                                                     {{ __('hrms.common.sort_name_desc') }}
                                                 </button>
                                                 <button type="button" class="dropdown-item sort-option {{ $sortBy === 'designation-asc' || $sortBy === 'designation' ? 'active' : '' }}" data-sort="designation-asc">
                                                     {{ __('hrms.roster.sort_designation_asc') }}
                                                 </button>
                                                 <button type="button" class="dropdown-item sort-option {{ $sortBy === 'designation-desc' ? 'active' : '' }}" data-sort="designation-desc">
                                                     {{ __('hrms.roster.sort_designation_desc') }}
                                                 </button>
                                             </x-ui.sort-dropdown>
 
                                             <!-- 3. FILTER Button (using Duralux component) -->
                                             <x-ui.filter label="{{ __('hrms.common.filter') }}">
                                                 <div class="d-flex align-items-center gap-2 mb-3">
                                                     <i class="feather-sliders text-primary fs-14"></i>
                                                     <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">{{ __('hrms.common.filter_options') }}</h6>
                                                 </div>
 
                                                 <div class="mb-3">
                                                     <label class="form-label fw-bold text-secondary fs-10 text-uppercase mb-1" style="letter-spacing: 0.05em; color: #64748b !important;">{{ __('hrms.employees.tbl_company') }}</label>
                                                     <x-ui.odoo-form-ui type="select" name="company_id" id="roster_filter_company">
                                                         <option value="">{{ __('hrms.common.all_companies') }}</option>
                                                         @foreach($companies as $company)
                                                             <option value="{{ $company->id }}" {{ $selectedCompanyId == $company->id ? 'selected' : '' }}>
                                                                 {{ $company->company_name }}
                                                             </option>
                                                         @endforeach
                                                     </x-ui.odoo-form-ui>
                                                 </div>
 
                                                 <div class="mb-3">
                                                     <label class="form-label fw-bold text-secondary fs-10 text-uppercase mb-1" style="letter-spacing: 0.05em; color: #64748b !important;">{{ __('hrms.employees.tbl_department') }}</label>
                                                     <x-ui.odoo-form-ui type="select" name="department_id" id="roster_filter_department">
                                                         <option value="">{{ __('hrms.roster.all_departments') }}</option>
                                                         @foreach($departments as $dept)
                                                             <option value="{{ $dept->id }}" data-company-id="{{ $dept->company_id }}" {{ $selectedDepartmentId == $dept->id ? 'selected' : '' }}>
                                                                 {{ $dept->name }}
                                                             </option>
                                                         @endforeach
                                                     </x-ui.odoo-form-ui>
                                                 </div>
 
                                                 <div class="mb-3">
                                                     <label class="form-label fw-bold text-secondary fs-10 text-uppercase mb-1" style="letter-spacing: 0.05em; color: #64748b !important;">{{ __('hrms.employees.tbl_designation') }}</label>
                                                     <x-ui.odoo-form-ui type="select" name="designation_id" id="roster_filter_designation">
                                                         <option value="">{{ __('hrms.roster.all_designations') }}</option>
                                                         @foreach($designations as $desg)
                                                             <option value="{{ $desg->id }}" {{ $selectedDesignationId == $desg->id ? 'selected' : '' }}>
                                                                 {{ $desg->name }}
                                                             </option>
                                                         @endforeach
                                                     </x-ui.odoo-form-ui>
                                                 </div>
 
                                                 <div class="mb-3">
                                                     <label class="form-label fw-bold text-secondary fs-10 text-uppercase mb-1" style="letter-spacing: 0.05em; color: #64748b !important;">{{ __('hrms.roster.start_date') }}</label>
                                                     <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate->format('Y-m-d') }}" style="border-color: #cbd5e1; border-radius: 6px;">
                                                 </div>
 
                                                 <div class="dropdown-divider my-3"></div>
 
                                                 <div class="d-flex gap-2">
                                                     <button type="submit" class="btn btn-sm roster-filter-apply-btn w-100 fw-bold py-2 text-uppercase">{{ __('hrms.common.apply') }}</button>
                                                     <a href="#" id="btn-reset-roster-filters" class="btn btn-sm roster-filter-reset-btn w-100 fw-bold py-2 text-center text-uppercase">{{ __('hrms.common.reset') }}</a>
                                                 </div>
                                             </x-ui.filter>
                                         </form>
                                     </x-slot>

                                    <!-- Grid Board Matrix (Fixed Column Width Layout to prevent viewport overflow) -->
                                    <div class="table-responsive border rounded bg-white" id="rosterBoardGrid">
                                        <table class="table table-bordered table-hover mb-0 align-middle text-center roster-grid-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="employee-head">{{ __('hrms.roster.employee_name') }}</th>
                                                    @foreach($dates as $date)
                                                        <th class="date-head">
                                                            <div class="fw-bold text-dark">{{ $date->format('D') }}</div>
                                                            <div class="text-muted" style="font-size: 10px; font-weight: 500;">{{ $date->format('d M') }}</div>
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($employees as $employee)
                                                    <tr>
                                                        <td class="employee-cell">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="avatar-text avatar-sm bg-soft-primary text-primary fw-bold" style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; font-size: 11px;">
                                                                    {{ strtoupper(substr($employee->full_name, 0, 2)) ?: 'EM' }}
                                                                </div>
                                                                <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px;">
                                                                    <div class="fw-bold text-dark fs-12 employee-name-label" style="font-size: 12px; line-height: 1.2;">{{ $employee->full_name }}</div>
                                                                    <div class="text-muted fs-10 employee-designation-label" style="font-size: 10px; line-height: 1.2;">{{ $employee->designation?->name ?? __('hrms.roster.no_designation') }}</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        @foreach($dates as $date)
                                                            @php
                                                                $dateStr = $date->format('Y-m-d');
                                                                $roster = $rosterMap[$employee->id][$dateStr] ?? null;
                                                                $assignedShiftId = $roster ? $roster->shift_id : null;
                                                                
                                                                $dayOfWeek = $date->dayOfWeek;
                                                                $weeklyPatternShiftId = (isset($employee->weekly_pattern) && isset($employee->weekly_pattern[$dayOfWeek])) ? $employee->weekly_pattern[$dayOfWeek] : null;

                                                                $defaultLabel = __('hrms.roster.off_default');
                                                                if ($employee->shift) {
                                                                    $defaultLabel = $employee->shift->code . ' (D)';
                                                                }
                                                                
                                                                if ($weeklyPatternShiftId === 'off') {
                                                                    $defaultLabel = __('hrms.roster.off') . ' (W)';
                                                                } elseif ($weeklyPatternShiftId) {
                                                                    $patternShift = $activeShifts->firstWhere('id', $weeklyPatternShiftId);
                                                                    if ($patternShift) {
                                                                        $defaultLabel = $patternShift->code . ' (W)';
                                                                    }
                                                                }

                                                                $cellBg = 'bg-transparent';
                                                                if ($roster) {
                                                                    if (is_null($roster->shift_id)) {
                                                                        $cellBg = 'bg-soft-secondary';
                                                                    } else {
                                                                        $cellBg = 'bg-soft-primary';
                                                                    }
                                                                } else {
                                                                    if ($weeklyPatternShiftId === 'off') {
                                                                        $cellBg = 'bg-soft-secondary';
                                                                    } else {
                                                                        $cellBg = 'bg-soft-light';
                                                                    }
                                                                }
                                                            @endphp
                                                            <td class="date-cell {{ $cellBg }}" style="transition: all 0.2s ease;">
                                                                <select 
                                                                    class="form-select form-select-sm roster-cell-select" 
                                                                    data-employee-id="{{ $employee->id }}" 
                                                                    data-date="{{ $dateStr }}"
                                                                    data-weekly-bg="{{ $weeklyPatternShiftId === 'off' ? 'bg-soft-secondary' : 'bg-soft-light' }}"
                                                                >
                                                                    <option value="" {{ is_null($assignedShiftId) && !$roster ? 'selected' : '' }}>
                                                                        {{ $defaultLabel }}
                                                                    </option>
                                                                    <option value="off" class="text-secondary fw-bold" {{ $roster && is_null($roster->shift_id) ? 'selected' : '' }}>
                                                                        {{ __('hrms.roster.off') }}
                                                                    </option>
                                                                    @php
                                                                        $resolvedDefaultShiftId = ($weeklyPatternShiftId && $weeklyPatternShiftId !== 'off') ? $weeklyPatternShiftId : ($employee->shift ? $employee->shift->id : null);
                                                                    @endphp
                                                                    @foreach($activeShifts->filter(fn($s) => ($s->company_id === null || $s->company_id == $employee->company_id) && $s->id != $resolvedDefaultShiftId) as $ashift)
                                                                        <option value="{{ $ashift->id }}" class="text-primary fw-bold" {{ $assignedShiftId == $ashift->id ? 'selected' : '' }}>
                                                                            {{ $ashift->code }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                                @if($employees->isEmpty())
                                                    <tr>
                                                        <td colspan="{{ count($dates) + 1 }}" class="text-center py-5 text-muted">
                                                            {{ __('hrms.roster.no_employees_matching') }}
                                                        </td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                     </div>
                                     @php
                                         $currentPage = $employees->currentPage();
                                         $totalPages = $employees->lastPage();
                                         $totalResults = $employees->total();
                                         $perPage = $employees->perPage();
                                     @endphp
                                      <div class="px-4 py-3 border-top bg-light-soft roster-pagination-container">
                                         <x-ui.pagination 
                                             :current-page="$currentPage"
                                             :total-pages="$totalPages"
                                             :total-results="$totalResults"
                                             :per-page="$perPage"
                                             page-param="roster_page"
                                         />
                                     </div>
                                </x-ui.card>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Roster Board Modals -->
    <!-- Bulk Assign Roster Modal -->
    <div class="modal fade" id="assignRosterModal" aria-labelledby="assignRosterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="assignRosterModalLabel"><i class="feather-calendar me-2 text-primary"></i>{{ __('hrms.roster.assign_roster') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.roster.assign') }}" method="POST">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- 1. Cascading Organization Group Multi-Selectors (Vertical, No labels cutoff) -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.companies') }}</label>
                                <select id="assign_company_select" name="bulk_company_ids[]" class="form-control select2-modal" multiple data-placeholder="{{ __('hrms.common.all_companies') }}">
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.business_units') }}</label>
                                <select id="assign_bu_select" name="bulk_business_unit_ids[]" class="form-control select2-modal" multiple data-placeholder="{{ __('hrms.roster.business_units') }}">
                                    @foreach($businessUnits as $bu)
                                        <option value="{{ $bu->id }}" data-company-id="{{ $bu->company_id }}">{{ $bu->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.branches') }}</label>
                                <select id="assign_branch_select" name="bulk_branch_ids[]" class="form-control select2-modal" multiple data-placeholder="{{ __('hrms.roster.branches') }}">
                                    @foreach($branches as $br)
                                        <option value="{{ $br->id }}" data-business-unit-id="{{ $br->business_unit_id }}">{{ $br->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.departments') }}</label>
                                <select id="assign_dept_select" name="bulk_department_ids[]" class="form-control select2-modal" multiple data-placeholder="{{ __('hrms.roster.departments') }}">
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" data-company-id="{{ $dept->company_id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.designations') }}</label>
                                <select id="assign_desg_select" name="bulk_designation_ids[]" class="form-control select2-modal" multiple data-placeholder="{{ __('hrms.roster.designations') }}">
                                    @foreach($designations as $desg)
                                        <option value="{{ $desg->id }}">{{ $desg->name }}</option>
                                    @endforeach
                                </select>
                            </div>
 
                            <!-- 2. Dynamic Search & Checkboxes (Employee List) -->
                            <div class="col-12 mt-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.select_employees') }}</label>
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-light"><i class="feather-search text-muted"></i></span>
                                    <input type="text" id="assignEmpSearch" class="form-control form-control-sm" placeholder="{{ __('hrms.roster.filter_list_placeholder') }}">
                                </div>
                                <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="selectAllAssignEmployees">
                                        <label class="form-check-label fw-bold text-primary" for="selectAllAssignEmployees">{{ __('hrms.roster.select_all_visible') }}</label>
                                    </div>
                                    <hr class="my-2">
                                    <div id="assignEmployeeList">
                                        @foreach($employees as $emp)
                                            <div class="form-check mb-1 assign-emp-item" 
                                                 data-company-id="{{ $emp->company_id }}" 
                                                 data-business-unit-id="{{ $emp->business_unit_id }}"
                                                 data-branch-id="{{ $emp->branch_id }}"
                                                 data-department-id="{{ $emp->department_id }}" 
                                                 data-designation-id="{{ $emp->designation_id }}"
                                                 data-name="{{ strtolower($emp->full_name) }}">
                                                <input class="form-check-input assign-emp-checkbox" type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" id="emp_assign_{{ $emp->id }}">
                                                <label class="form-check-label text-dark fs-12" for="emp_assign_{{ $emp->id }}">
                                                    {{ $emp->full_name }} 
                                                    <span class="text-muted" style="font-size: 10px;">
                                                        ({{ $emp->department?->name ?? __('hrms.roster.no_dept') }} / {{ $emp->designation?->name ?? __('hrms.roster.no_desg') }})
                                                    </span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div id="assignNoEmployeesMsg" class="text-center text-muted py-3 d-none">
                                        {{ __('hrms.roster.no_employees_matching_filters') }}
                                    </div>
                                </div>
                                <div class="text-muted fs-11 mt-1">{{ __('hrms.roster.assign_help') }}</div>
                            </div>
 
                            <!-- 3. Scheduling Settings (Vertical Date Fields, No cut-offs) -->
                            <div class="col-12 mt-4">
                                <hr class="my-2">
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.roster.shift_to_assign')" name="shift_id" :searchable="false">
                                    <option value="">{{ __('hrms.roster.day_off') }}</option>
                                    @foreach($activeShifts as $ashift)
                                        <option value="{{ $ashift->id }}">{{ $ashift->name }} ({{ $ashift->code }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.org.status')" name="status" :searchable="false" :required="true">
                                    <option value="scheduled" selected>{{ __('hrms.roster.scheduled') }}</option>
                                    <option value="approved">{{ __('hrms.roster.approved') }}</option>
                                    <option value="cancelled">{{ __('hrms.roster.cancelled') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-12 mb-1" style="color: #dc3545 !important;">{{ __('hrms.roster.start_date') }} <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control form-control-sm" required value="{{ $startDate->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-12 mb-1" style="color: #dc3545 !important;">{{ __('hrms.roster.end_date') }} <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control form-control-sm" required value="{{ $startDate->copy()->addDays(6)->format('Y-m-d') }}">
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.roster.notes')" name="notes" :placeholder="__('hrms.roster.notes_placeholder')" rows="2" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('hrms.roster.assign_shift') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <!-- Assign Weekly Defaults Modal -->
    <div class="modal fade" id="assignWeeklyModal" aria-labelledby="assignWeeklyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="assignWeeklyModalLabel"><i class="feather-calendar me-2 text-primary"></i>Assign Weekly Defaults</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.roster.assign-weekly') }}" method="POST">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- 1. Cascading Organization Group Multi-Selectors -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.companies') }}</label>
                                <select id="assign_weekly_company_select" name="bulk_company_ids[]" class="form-control select2-modal" multiple data-placeholder="{{ __('hrms.common.all_companies') }}">
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.business_units') }}</label>
                                <select id="assign_weekly_bu_select" name="bulk_business_unit_ids[]" class="form-control select2-modal" multiple data-placeholder="{{ __('hrms.roster.business_units') }}">
                                    @foreach($businessUnits as $bu)
                                        <option value="{{ $bu->id }}" data-company-id="{{ $bu->company_id }}">{{ $bu->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.branches') }}</label>
                                <select id="assign_weekly_branch_select" name="bulk_branch_ids[]" class="form-control select2-modal" multiple data-placeholder="{{ __('hrms.roster.branches') }}">
                                    @foreach($branches as $br)
                                        <option value="{{ $br->id }}" data-business-unit-id="{{ $br->business_unit_id }}">{{ $br->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.departments') }}</label>
                                <select id="assign_weekly_dept_select" name="bulk_department_ids[]" class="form-control select2-modal" multiple data-placeholder="{{ __('hrms.roster.departments') }}">
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" data-company-id="{{ $dept->company_id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.designations') }}</label>
                                <select id="assign_weekly_desg_select" name="bulk_designation_ids[]" class="form-control select2-modal" multiple data-placeholder="{{ __('hrms.roster.designations') }}">
                                    @foreach($designations as $desg)
                                        <option value="{{ $desg->id }}">{{ $desg->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- 2. Dynamic Search & Checkboxes (Employee List) -->
                            <div class="col-12 mt-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.roster.select_employees') }}</label>
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-light"><i class="feather-search text-muted"></i></span>
                                    <input type="text" id="assign_weeklyEmpSearch" class="form-control form-control-sm" placeholder="{{ __('hrms.roster.filter_list_placeholder') }}">
                                </div>
                                <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="selectAllAssignWeeklyEmployees">
                                        <label class="form-check-label fw-bold text-primary" for="selectAllAssignWeeklyEmployees">{{ __('hrms.roster.select_all_visible') }}</label>
                                    </div>
                                    <hr class="my-2">
                                    <div id="assignWeeklyEmployeeList">
                                        @foreach($employees as $emp)
                                            <div class="form-check mb-1 assign_weekly-emp-item" 
                                                 data-company-id="{{ $emp->company_id }}" 
                                                 data-business-unit-id="{{ $emp->business_unit_id }}"
                                                 data-branch-id="{{ $emp->branch_id }}"
                                                 data-department-id="{{ $emp->department_id }}" 
                                                 data-designation-id="{{ $emp->designation_id }}"
                                                 data-name="{{ strtolower($emp->full_name) }}">
                                                <input class="form-check-input assign_weekly-emp-checkbox" type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" id="emp_assign_weekly_{{ $emp->id }}">
                                                <label class="form-check-label text-dark fs-12" for="emp_assign_weekly_{{ $emp->id }}">
                                                    {{ $emp->full_name }} 
                                                    <span class="text-muted" style="font-size: 10px;">
                                                        ({{ $emp->department?->name ?? __('hrms.roster.no_dept') }} / {{ $emp->designation?->name ?? __('hrms.roster.no_desg') }})
                                                    </span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div id="assign_weeklyNoEmployeesMsg" class="text-center text-muted py-3 d-none">
                                        {{ __('hrms.roster.no_employees_matching_filters') }}
                                    </div>
                                </div>
                                <div class="text-muted fs-11 mt-1">{{ __('hrms.roster.assign_help') }}</div>
                            </div>

                            <!-- 3. Weekdays & Shift Settings -->
                            <div class="col-12 mt-4">
                                <hr class="my-2">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold fs-12 mb-2" style="color: #dc3545 !important;">Select Weekdays <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-3 mb-1 border rounded p-3 bg-white">
                                    @foreach([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'] as $val => $label)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="days[]" value="{{ $val }}" id="day_assign_weekly_{{ $val }}">
                                            <label class="form-check-label text-dark fs-12 fw-bold" for="day_assign_weekly_{{ $val }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                             <div class="col-12 mt-3">
                                 <x-ui.odoo-form-ui type="select" label="Shift to Assign" name="shift_id" :searchable="false">
                                     <option value="">Default (Use Profile Default / Reset)</option>
                                     <option value="off">Day Off (OFF)</option>
                                     @foreach($activeShifts as $ashift)
                                         <option value="{{ $ashift->id }}">{{ $ashift->name }} ({{ $ashift->code }})</option>
                                     @endforeach
                                 </x-ui.odoo-form-ui>
                             </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                        <button type="submit" class="btn btn-primary">Assign Weekly Defaults</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include all HRMS Modals (includes shift add, edit, view modals) -->
    @include('modules.hrms.partials.modals')
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Move modals to body root to avoid stacking context issues
            document.querySelectorAll('.modal').forEach(modal => {
                document.body.appendChild(modal);
            });

            // Prevent dropdown menus from closing on clicking inputs inside them (Vanilla JS)
            document.addEventListener('click', function (e) {
                if (e.target.closest('.dropdown-menu')) {
                    e.stopPropagation();
                }
            });

            function initGridSelect2() {
                if (window.jQuery && $.fn.select2) {
                    $('.roster-cell-select, .weekly-pattern-select').each(function() {
                        var select = $(this);
                        if (!select.hasClass('select2-hidden-accessible')) {
                            select.select2({
                                theme: "bootstrap-5",
                                minimumResultsForSearch: -1, // Hide search box
                                width: "100%",
                                templateResult: function(data) {
                                    if (!data.id) return data.text;
                                    var $result = $('<span></span>');
                                    $result.text(data.text);
                                    if (data.id === 'off') {
                                        $result.css('color', '#dc2626');
                                        $result.addClass('fw-bold');
                                    } else {
                                        $result.css('color', '#000000');
                                    }
                                    return $result;
                                },
                                templateSelection: function(data) {
                                    if (data.id === 'off') {
                                        return $('<span class="text-secondary fw-bold">OFF</span>');
                                    }
                                    return data.text;
                                }
                            });
                        }
                    });
                }
            }

            initGridSelect2();

            // 1. ROSTER SEARCH AND SORT AJAX ACTIONS
            function loadRoster(page = 1) {
                var search = $('#rosterSearch').val() || '';
                var sort = $('#filterSortInput').val() || 'name-asc';
                var company = $('#roster_filter_company').val() || '';
                var department = $('#roster_filter_department').val() || '';
                var designation = $('#roster_filter_designation').val() || '';
                var startDate = $('#rosterFilterForm input[name="start_date"]').val() || '';
                
                var url = '{{ route("hrms.roster.index") }}?tab=roster&search=' + encodeURIComponent(search) + 
                          '&sort=' + encodeURIComponent(sort) + 
                          '&company_id=' + encodeURIComponent(company) + 
                          '&department_id=' + encodeURIComponent(department) + 
                          '&designation_id=' + encodeURIComponent(designation) + 
                          '&start_date=' + encodeURIComponent(startDate) + 
                          '&roster_page=' + page;
                          
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        // Update grid container
                        var oldGrid = $('#rosterBoardGrid');
                        var newGrid = $(doc).find('#rosterBoardGrid');
                        if (newGrid.length && oldGrid.length) {
                            oldGrid.html(newGrid.html());
                            initGridSelect2();
                        }
                        
                        // Update pagination
                        var oldPagination = $('.roster-pagination-container');
                        var newPagination = $(doc).find('.roster-pagination-container');
                        if (newPagination.length && oldPagination.length) {
                            oldPagination.replaceWith(newPagination);
                        } else if (newPagination.length) {
                            $('#rosterBoardGrid').parent().append(newPagination);
                        } else {
                            oldPagination.empty();
                        }
                    }
                });
            }

            // 1.1 WEEKLY PATTERNS SEARCH AND SORT AJAX ACTIONS
            function loadWeeklyPatterns(page = 1) {
                var search = $('#weeklyPatternSearch').val() || '';
                var sort = $('#weeklyPatternSortInput').val() || 'name-asc';
                var company = $('#weekly_filter_company').val() || '';
                var department = $('#weekly_filter_department').val() || '';
                var designation = $('#weekly_filter_designation').val() || '';
                
                var url = '{{ route("hrms.roster.index") }}?tab=weekly_patterns&search=' + encodeURIComponent(search) + 
                          '&sort=' + encodeURIComponent(sort) + 
                          '&company_id=' + encodeURIComponent(company) + 
                          '&department_id=' + encodeURIComponent(department) + 
                          '&designation_id=' + encodeURIComponent(designation) + 
                          '&roster_page=' + page;
                          
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        var oldGrid = $('#weeklyPatternsGrid');
                        var newGrid = $(doc).find('#weeklyPatternsGrid');
                        if (newGrid.length && oldGrid.length) {
                            oldGrid.replaceWith(newGrid);
                            initGridSelect2();
                        }
                        
                        var oldPagination = $('.weekly-pagination-container');
                        var newPagination = $(doc).find('.weekly-pagination-container');
                        if (newPagination.length && oldPagination.length) {
                            oldPagination.replaceWith(newPagination);
                        } else if (newPagination.length) {
                            $('#weeklyPatternsGrid').parent().append(newPagination);
                        } else {
                            oldPagination.empty();
                        }
                    }
                });
            }

            let rosterSearchTimeout = null;
            $(document).on('input', '#rosterSearch', function() {
                clearTimeout(rosterSearchTimeout);
                rosterSearchTimeout = setTimeout(() => {
                    loadRoster(1);
                }, 300);
            });

            let weeklySearchTimeout = null;
            $(document).on('input', '#weeklyPatternSearch', function() {
                clearTimeout(weeklySearchTimeout);
                weeklySearchTimeout = setTimeout(() => {
                    loadWeeklyPatterns(1);
                }, 300);
            });

            $(document).on('click', '#rosterFilterForm .sort-option', function(e) {
                e.preventDefault();
                const sortBy = $(this).attr('data-sort');
                $('#filterSortInput').val(sortBy);
                
                var parent = this.closest('.dropdown-menu');
                if (parent) {
                    parent.querySelectorAll('.sort-option').forEach(function(btn) {
                        btn.classList.remove('active');
                    });
                }
                this.classList.add('active');

                loadRoster(1);
            });

            $(document).on('click', '#weeklyPatternFilterForm .sort-option', function(e) {
                e.preventDefault();
                const sortBy = $(this).attr('data-sort');
                $('#weeklyPatternSortInput').val(sortBy);
                
                var parent = this.closest('.dropdown-menu');
                if (parent) {
                    parent.querySelectorAll('.sort-option').forEach(function(btn) {
                        btn.classList.remove('active');
                    });
                }
                this.classList.add('active');

                loadWeeklyPatterns(1);
            });

            $(document).on('submit', '#rosterFilterForm', function(e) {
                e.preventDefault();
                loadRoster(1);
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            });

            $(document).on('submit', '#weeklyPatternFilterForm', function(e) {
                e.preventDefault();
                loadWeeklyPatterns(1);
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            });

            $(document).on('click', '#btn-reset-roster-filters', function(e) {
                e.preventDefault();
                $('#rosterSearch').val('');
                $('#roster_filter_company').val('').trigger('change');
                $('#roster_filter_department').val('').trigger('change');
                $('#roster_filter_designation').val('').trigger('change');
                
                var today = new Date().toISOString().split('T')[0];
                $('#rosterFilterForm input[name="start_date"]').val(today);
                
                $('#filterSortInput').val('name-asc');
                
                var sortMenu = document.querySelector('#rosterFilterForm');
                if (sortMenu) {
                    sortMenu.querySelectorAll('.sort-option').forEach(function(btn) {
                        btn.classList.remove('active');
                    });
                    var defaultBtn = sortMenu.querySelector('.sort-option[data-sort="name-asc"]');
                    if (defaultBtn) {
                        defaultBtn.classList.add('active');
                    }
                }

                loadRoster(1);
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            });

            $(document).on('click', '#btn-reset-weekly-filters', function(e) {
                e.preventDefault();
                $('#weeklyPatternSearch').val('');
                $('#weekly_filter_company').val('').trigger('change');
                $('#weekly_filter_department').val('').trigger('change');
                $('#weekly_filter_designation').val('').trigger('change');
                $('#weeklyPatternSortInput').val('name-asc');
                
                var sortMenu = document.querySelector('#weeklyPatternFilterForm');
                if (sortMenu) {
                    sortMenu.querySelectorAll('.sort-option').forEach(function(btn) {
                        btn.classList.remove('active');
                    });
                    var defaultBtn = sortMenu.querySelector('.sort-option[data-sort="name-asc"]');
                    if (defaultBtn) {
                        defaultBtn.classList.add('active');
                    }
                }

                loadWeeklyPatterns(1);
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            });

            $(document).on('click', '#rosterSettingsContent .roster-pagination-container a', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                if (!url) return;
                var urlParams = new URLSearchParams(url.substring(url.indexOf('?')));
                var page = urlParams.get('roster_page') || 1;
                loadRoster(page);
            });

            $(document).on('click', '#rosterSettingsContent .weekly-pagination-container a', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                if (!url) return;
                var urlParams = new URLSearchParams(url.substring(url.indexOf('?')));
                var page = urlParams.get('roster_page') || 1;
                loadWeeklyPatterns(page);
            });

            // 2. JQUERY MODAL CASCADE AND SELECT2 INITIALIZATION (Wrapped safely)
            if (window.jQuery) {
                (function($) {
                    let originalBUs = [];
                    let originalBranches = [];
                    let originalDepts = [];

                    function cacheOriginalOptions() {
                        originalBUs = Array.from(document.querySelectorAll('#assign_bu_select option')).map(opt => ({
                            value: opt.value,
                            text: opt.textContent,
                            companyId: opt.getAttribute('data-company-id')
                        }));
                        originalBranches = Array.from(document.querySelectorAll('#assign_branch_select option')).map(opt => ({
                            value: opt.value,
                            text: opt.textContent,
                            businessUnitId: opt.getAttribute('data-business-unit-id')
                        }));
                        originalDepts = Array.from(document.querySelectorAll('#assign_dept_select option')).map(opt => ({
                            value: opt.value,
                            text: opt.textContent,
                            companyId: opt.getAttribute('data-company-id')
                        }));
                    }

                    cacheOriginalOptions();

                    function setupCascadeSelectors(prefix) {
                        const compSelect = $(`#${prefix}_company_select`);
                        const buSelect = $(`#${prefix}_bu_select`);
                        const branchSelect = $(`#${prefix}_branch_select`);
                        const deptSelect = $(`#${prefix}_dept_select`);
                        const desgSelect = $(`#${prefix}_desg_select`);

                        compSelect.on('change', function() {
                            const selectedComps = $(this).val() || [];
                            const filteredBUs = originalBUs.filter(bu => selectedComps.length === 0 || selectedComps.includes(bu.companyId));
                            const currentBuVal = buSelect.val() || [];
                            buSelect.empty();
                            filteredBUs.forEach(bu => {
                                const isSelected = currentBuVal.includes(bu.value);
                                const newBuOption = document.createElement('option');
                            newBuOption.text = bu.text;
                            newBuOption.value = bu.value;
                            newBuOption.selected = isSelected;
                            buSelect.append(newBuOption);
                            });
                            buSelect.trigger('change.select2');

                            const filteredDepts = originalDepts.filter(dept => selectedComps.length === 0 || selectedComps.includes(dept.companyId));
                            const currentDeptVal = deptSelect.val() || [];
                            deptSelect.empty();
                            filteredDepts.forEach(dept => {
                                const isSelected = currentDeptVal.includes(dept.value);
                                const newDeptOption = document.createElement('option');
                            newDeptOption.text = dept.text;
                            newDeptOption.value = dept.value;
                            newDeptOption.selected = isSelected;
                            deptSelect.append(newDeptOption);
                            });
                            deptSelect.trigger('change.select2');
                            filterEmployees(prefix);
                        });

                        buSelect.on('change', function() {
                            const selectedBUs = $(this).val() || [];
                            const filteredBranches = originalBranches.filter(br => selectedBUs.length === 0 || selectedBUs.includes(br.businessUnitId));
                            const currentBranchVal = branchSelect.val() || [];
                            branchSelect.empty();
                            filteredBranches.forEach(br => {
                                const isSelected = currentBranchVal.includes(br.value);
                                const newBranchOption = document.createElement('option');
                            newBranchOption.text = br.text;
                            newBranchOption.value = br.value;
                            newBranchOption.selected = isSelected;
                            branchSelect.append(newBranchOption);
                            });
                            branchSelect.trigger('change.select2');
                            filterEmployees(prefix);
                        });

                        branchSelect.on('change', () => filterEmployees(prefix));
                        deptSelect.on('change', () => filterEmployees(prefix));
                        desgSelect.on('change', () => filterEmployees(prefix));
                    }

                    function filterEmployees(prefix) {
                        const compVal = $(`#${prefix}_company_select`).val() || [];
                        const buVal = $(`#${prefix}_bu_select`).val() || [];
                        const branchVal = $(`#${prefix}_branch_select`).val() || [];
                        const deptVal = $(`#${prefix}_dept_select`).val() || [];
                        const desgVal = $(`#${prefix}_desg_select`).val() || [];
                        const searchVal = ($(`#${prefix}EmpSearch`).val() || '').toLowerCase().trim();

                        let visibleCount = 0;
                        document.querySelectorAll(`.${prefix}-emp-item`).forEach(item => {
                            const c = item.dataset.companyId;
                            const u = item.dataset.businessUnitId;
                            const b = item.dataset.branchId;
                            const d = item.dataset.departmentId;
                            const s = item.dataset.designationId;
                            const n = item.dataset.name;

                            const matchesComp = compVal.length === 0 || compVal.includes(c);
                            const matchesBU = buVal.length === 0 || buVal.includes(u);
                            const matchesBranch = branchVal.length === 0 || branchVal.includes(b);
                            const matchesDept = deptVal.length === 0 || deptVal.includes(d);
                            const matchesDesg = desgVal.length === 0 || desgVal.includes(s);
                            const matchesSearch = !searchVal || n.includes(searchVal);

                            if (matchesComp && matchesBU && matchesBranch && matchesDept && matchesDesg && matchesSearch) {
                                item.classList.remove('d-none');
                                visibleCount++;
                            } else {
                                item.classList.add('d-none');
                                const cb = item.querySelector(`.${prefix}-emp-checkbox`);
                                if (cb) cb.checked = false;
                            }
                        });
                        const noMsg = document.getElementById(`${prefix}NoEmployeesMsg`);
                        if (visibleCount === 0) noMsg.classList.remove('d-none'); else noMsg.classList.add('d-none');
                    }

                    setupCascadeSelectors('assign');
                    setupCascadeSelectors('assign_weekly');

                    $(`#assignEmpSearch`).on('input', () => filterEmployees('assign'));
                    $(`#assign_weeklyEmpSearch`).on('input', () => filterEmployees('assign_weekly'));

                    $('#selectAllAssignEmployees').on('change', function() {
                        $('.assign-emp-item:not(.d-none) .assign-emp-checkbox').prop('checked', this.checked);
                    });
                    $('#selectAllAssignWeeklyEmployees').on('change', function() {
                        $('.assign_weekly-emp-item:not(.d-none) .assign_weekly-emp-checkbox').prop('checked', this.checked);
                    });

                    function initModalSelect2() {
                        if ($.fn.select2) {
                            $('.select2-modal').each(function() {
                                var select = $(this);
                                var modal = select.closest('.modal');
                                if (!select.hasClass('select2-hidden-accessible')) {
                                    select.select2({
                                        theme: "bootstrap-5",
                                        dropdownParent: modal.length ? modal : $(document.body),
                                        width: "100%",
                                        placeholder: select.data('placeholder')
                                    });
                                }
                            });
                        }
                    }

                    initModalSelect2();
                    $(document).on('shown.bs.modal', function () {
                        initModalSelect2();
                    });

                    // 5. BULK MODALS VALIDATION (Theme-compliant feedback match)
                    $('#assignRosterModal form, #assignWeeklyModal form').on('submit', function(e) {
                        let isValid = true;
                        const form = $(this);

                        // Clear all previous validation highlights & error messages
                        form.find('.is-invalid').removeClass('is-invalid');
                        form.find('.invalid-feedback.dynamic-error').remove();

                        // 1. Validate weekdays if present
                        const daysInput = form.find('input[name="days[]"]');
                        if (daysInput.length > 0) {
                            const daysChecked = daysInput.filter(':checked').length;
                            const daysContainer = daysInput.closest('.col-12').find('.border');
                            if (daysChecked === 0) {
                                isValid = false;
                                daysContainer.addClass('is-invalid');
                                daysContainer.after('<div class="invalid-feedback dynamic-error d-block fs-11 mt-1">Please select at least one weekday.</div>');
                            }
                        }

                        // 2. Validate start_date and end_date if present
                        const startDateInput = form.find('input[name="start_date"]');
                        const endDateInput = form.find('input[name="end_date"]');
                        if (startDateInput.length > 0 && endDateInput.length > 0) {
                            const startDateVal = startDateInput.val();
                            const endDateVal = endDateInput.val();

                            if (!startDateVal) {
                                isValid = false;
                                startDateInput.addClass('is-invalid');
                                startDateInput.after('<div class="invalid-feedback dynamic-error d-block fs-11 mt-1">Start date is required.</div>');
                            }
                            if (!endDateVal) {
                                isValid = false;
                                endDateInput.addClass('is-invalid');
                                endDateInput.after('<div class="invalid-feedback dynamic-error d-block fs-11 mt-1">End date is required.</div>');
                            } else if (startDateVal && endDateVal && endDateVal < startDateVal) {
                                isValid = false;
                                endDateInput.addClass('is-invalid');
                                endDateInput.after('<div class="invalid-feedback dynamic-error d-block fs-11 mt-1">End date cannot be earlier than start date.</div>');
                            }
                        }

                        if (!isValid) {
                            e.preventDefault();
                            return false;
                        }
                    });

                    const companySelect = document.querySelector('select[name="company_id"]');
                    const departmentSelect = document.querySelector('select[name="department_id"]');

                    if (companySelect && departmentSelect) {
                        const originalDeptOptions = Array.from(departmentSelect.options);
                        function filterDepartments() {
                            const companyId = companySelect.value;
                            departmentSelect.innerHTML = '';
                            const defaultOpt = originalDeptOptions[0];
                            departmentSelect.appendChild(defaultOpt);
                            originalDeptOptions.slice(1).forEach(opt => {
                                const optCompanyId = opt.getAttribute('data-company-id');
                                if (!companyId || optCompanyId === companyId) departmentSelect.appendChild(opt);
                            });
                            const currentSelected = departmentSelect.value;
                            const isValid = Array.from(departmentSelect.options).some(o => o.value === currentSelected);
                            if (!isValid) departmentSelect.value = '';
                        }
                        companySelect.addEventListener('change', filterDepartments);
                        filterDepartments();
                    }
                })(window.jQuery);
            }

            // 3. AJAX CELL SCHEDULE MATRIX UPDATE (Delegated jQuery listener)
            $(document).on('change', '.roster-cell-select', function() {
                const employeeId = this.dataset.employeeId;
                const date = this.dataset.date;
                const rawVal = this.value;
                const shiftId = rawVal === 'off' ? null : (rawVal || null);
                const cellTd = this.closest('td');
                const weeklyBg = this.dataset.weeklyBg || 'bg-soft-light';

                $(this).css('opacity', '0.5').next('.select2-container').css('opacity', '0.5');
                fetch("{{ route('hrms.roster.update-cell') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ employee_id: employeeId, date: date, shift_id: shiftId, value: rawVal })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.message || 'Server error occurred');
                        }).catch(() => {
                            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    $(this).css('opacity', '1').next('.select2-container').css('opacity', '1');
                    if (data.success) {
                        cellTd.className = 'date-cell';
                        if (rawVal === 'off') {
                            cellTd.classList.add('bg-soft-secondary');
                        } else if (rawVal) {
                            cellTd.classList.add('bg-soft-primary');
                        } else {
                            cellTd.classList.add(weeklyBg === 'bg-soft-secondary' ? 'bg-soft-secondary' : 'bg-soft-light');
                        }
                        
                        if (typeof Swal !== 'undefined') {
                            Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true })
                                .fire({ icon: 'success', title: data.message || "Roster updated successfully." });
                        }
                    } else {
                        alert(data.message || "Error updating roster.");
                    }
                })
                .catch(error => {
                    $(this).css('opacity', '1').next('.select2-container').css('opacity', '1');
                    console.error('Error updating roster:', error);
                    alert("Network error: " + error.message);
                });
            });

            // 3.1 AJAX WEEKLY PATTERN UPDATE (Delegated jQuery listener)
            $(document).on('change', '.weekly-pattern-select', function() {
                const employeeId = this.dataset.employeeId;
                const dayOfWeek = this.dataset.dayOfWeek;
                const rawVal = this.value;
                const cellTd = this.closest('td');

                $(this).css('opacity', '0.5').next('.select2-container').css('opacity', '0.5');
                fetch("{{ route('hrms.roster.update-weekly-pattern') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ employee_id: employeeId, day_of_week: dayOfWeek, value: rawVal })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.message || 'Server error occurred');
                        }).catch(() => {
                            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    $(this).css('opacity', '1').next('.select2-container').css('opacity', '1');
                    if (data.success) {
                        cellTd.className = 'date-cell';
                        if (rawVal === 'off') cellTd.classList.add('bg-soft-secondary');
                        else if (rawVal === '') cellTd.classList.add('bg-soft-light');
                        else cellTd.classList.add('bg-soft-primary');

                        if (typeof Swal !== 'undefined') {
                            Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true })
                                .fire({ icon: 'success', title: data.message || "Weekly pattern updated successfully." });
                        }
                    } else alert(data.message || "Error saving weekly pattern.");
                })
                .catch(error => {
                    $(this).css('opacity', '1').next('.select2-container').css('opacity', '1');
                    console.error('Error updating weekly pattern:', error);
                    alert("Network error: " + error.message);
                });
            });

            // 4. SHIFT MASTER VIEW & EDIT BINDINGS (Vanilla JS)

            const btnEditShift = document.querySelector('.btn-edit-shift');
            if (btnEditShift) {
                document.querySelectorAll('.btn-edit-shift').forEach(btn => {
                    btn.addEventListener('click', function() {
                        let shift = JSON.parse(atob(this.dataset.shift));
                        document.getElementById('edit_shift_name').value = shift.name || '';
                        document.getElementById('edit_shift_code').value = shift.code || '';
                        document.getElementById('edit_shift_start').value = shift.start_time ? shift.start_time.substring(0, 5) : '';
                        document.getElementById('edit_shift_end').value = shift.end_time ? shift.end_time.substring(0, 5) : '';
                        document.getElementById('edit_shift_break').value = shift.break_minutes || 0;
                        
                        let companySelect = document.getElementById('edit_shift_company_id');
                        if (companySelect && window.jQuery) {
                            companySelect.value = shift.company_id || '';
                            if (window.jQuery(companySelect).hasClass('select2-hidden-accessible')) window.jQuery(companySelect).trigger('change');
                        }
                        let overtimeSelect = document.getElementById('edit_shift_overtime');
                        if (overtimeSelect && window.jQuery) {
                            overtimeSelect.value = (shift.overtime_allowed ? '1' : '0');
                            if (window.jQuery(overtimeSelect).hasClass('select2-hidden-accessible')) window.jQuery(overtimeSelect).trigger('change');
                        }
                        let activeSelect = document.getElementById('edit_shift_active');
                        if (activeSelect && window.jQuery) {
                            activeSelect.value = (shift.active ? '1' : '0');
                            if (window.jQuery(activeSelect).hasClass('select2-hidden-accessible')) window.jQuery(activeSelect).trigger('change');
                        }
                        let form = document.getElementById('shift_edit_form');
                        if (form) form.action = '/hrms/roster/shift/update/' + shift.id;
                    });
                });
            }

            // AJAX-based search, sort and filter for Shifts
            function loadShifts(page = 1) {
                var search = $('#shiftSearchForm input[name="shift_search"]').val() || '';
                var sort = $('#shift_sort_value').val() || 'name_asc';
                var status = $('#shift_filter_status').val() || '';
                var overtime = $('#shift_filter_overtime').val() || '';
                var companyId = $('#shift_filter_company_id').val() || '';
                
                var url = '{{ route("hrms.roster.index") }}?tab=shifts&shift_search=' + encodeURIComponent(search) + 
                          '&shift_sort=' + encodeURIComponent(sort) + 
                          '&shift_status=' + encodeURIComponent(status) + 
                          '&shift_overtime=' + encodeURIComponent(overtime) + 
                          '&shift_company_id=' + encodeURIComponent(companyId) + 
                          '&shift_page=' + page;
                          
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        // Update table
                        var oldTable = $('#shiftsTable');
                        var newTable = $(doc).find('#shiftsTable');
                        if (newTable.length && oldTable.length) {
                            oldTable.html(newTable.html());
                        }
                        
                        // Update pagination
                        var oldPagination = $('.shift-pagination-container');
                        var newPagination = $(doc).find('.shift-pagination-container');
                        if (newPagination.length && oldPagination.length) {
                            oldPagination.replaceWith(newPagination);
                        } else if (newPagination.length) {
                            $('#shiftsTable').parent().append(newPagination);
                        } else {
                            oldPagination.empty();
                        }
                    }
                });
            }

            let shiftSearchTimeout = null;
            $(document).on('input', '#shiftSearchForm input[name="shift_search"]', function () {
                clearTimeout(shiftSearchTimeout);
                shiftSearchTimeout = setTimeout(function () {
                    loadShifts(1);
                }, 300);
            });

            $(document).on('submit', '#shiftSearchForm, #shiftFilterForm', function (event) {
                event.preventDefault();
                loadShifts(1);
                // Close the filter dropdown if open
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            });

            $(document).on('click', '#btn-reset-shift-filters', function(e) {
                e.preventDefault();
                $('#shiftSearchForm input[name="shift_search"]').val('');
                $('#shift_filter_status').val('').trigger('change');
                $('#shift_filter_overtime').val('').trigger('change');
                $('#shift_filter_company_id').val('').trigger('change');
                $('#shift_sort_value').val('name_asc');
                
                var sortMenu = document.querySelector('#shift_sort_dropdown_menu');
                if (sortMenu) {
                    sortMenu.querySelectorAll('.dropdown-item').forEach(function(item) {
                        item.classList.remove('active');
                        var check = item.querySelector('.feather-check');
                        if (check) check.remove();
                    });
                    var defaultItem = sortMenu.querySelector('[data-sort="name_asc"]');
                    if (defaultItem) {
                        defaultItem.classList.add('active');
                        $(defaultItem).append('<i class="feather-check ms-3"></i>');
                    }
                }
                
                loadShifts(1);
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            });

            $(document).on('click', '#rosterSettingsContent .shift-pagination-container a', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                if (!url) return;
                var urlParams = new URLSearchParams(url.substring(url.indexOf('?')));
                var page = urlParams.get('shift_page') || 1;
                loadShifts(page);
            });

            // Global function for sorting shifts
            window.changeShiftSort = function(criteria, element) {
                var input = document.getElementById('shift_sort_value');
                if (input) {
                    input.value = criteria;
                }

                if (element) {
                    var menu = element.closest('.dropdown-menu');
                    if (menu) {
                        menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                            el.classList.remove('active');
                            var check = el.querySelector('.feather-check');
                            if (check) check.remove();
                        });
                    }
                    element.classList.add('active');
                    $(element).append('<i class="feather-check ms-3"></i>');
                }

                loadShifts(1);
            };
        });
    </script>
@endsection
