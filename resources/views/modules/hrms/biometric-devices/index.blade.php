@extends('layouts.duralux')

@section('title', __('hrms.biometric.title') . ' | SaaS ERP')
@section('page-title', __('hrms.biometric.title'))
@section('breadcrumb', __('hrms.biometric.breadcrumb'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addDeviceModal" class="fw-bold text-uppercase">
            {{ __('hrms.biometric.add_device') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
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
            .settings-content-col {
                flex-grow: 1;
                padding: 24px 30px;
                background-color: #f8fafc;
                min-width: 0;
            }
            .biometric-devices-table {
                table-layout: fixed !important;
                width: 100% !important;
            }
            .biometric-table-responsive {
                overflow-x: hidden !important;
            }
        }

        @media (max-width: 991.98px) {
            .settings-content-col {
                width: 100%;
                padding: 0 15px;
            }
            .biometric-table-responsive {
                overflow-x: auto;
            }
        }

        .biometric-devices-table th {
            vertical-align: middle;
        }

        .biometric-devices-table td {
            vertical-align: top !important;
            white-space: normal !important;
            word-wrap: break-word;
            overflow-wrap: break-word;
            padding-top: 14px !important;
            padding-bottom: 14px !important;
        }

        .adms-banner {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert" style="background-color: #e8f5e9; color: #2e7d32;">
            <div class="d-flex align-items-center">
                <i class="feather-check-circle me-2 fs-18"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert" style="background-color: #ffebee; color: #c62828;">
            <div class="d-flex align-items-center">
                <i class="feather-alert-circle me-2 fs-18"></i>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert" style="background-color: #ffebee; color: #c62828;">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(isset($hasBiometricRule) && !$hasBiometricRule)
        <div class="alert alert-warning alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert" style="background-color: #fff8e1; color: #5d4037;">
            <div class="d-flex align-items-center">
                <i class="feather-alert-triangle me-2 fs-18 text-warning"></i>
                <div>
                    <strong>{{ __('hrms.biometric.rule_notice_title') }}</strong> 
                    {!! __('hrms.biometric.rule_notice_desc', ['link' => '<a href="'.route('hrms.penalization-policy.index').'" class="fw-bold text-dark text-decoration-underline">'.__('hrms.biometric.attendance_rules').'</a>']) !!}
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="settings-container">
        <div class="settings-content-col erp-single-panel bg-white flex-grow-1 p-4 shadow-sm rounded border-0 text-dark biometric-pane-wrapper">
            
            <!-- Cloud ADMS & Server Webhook Info Header Banner -->
            <div class="adms-banner p-3 mb-4 rounded-3 d-flex flex-wrap align-items-center justify-content-between gap-3 shadow-none border" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle flex-shrink-0" style="width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="feather-radio fs-18"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark fs-13">{{ __('hrms.biometric.adms_banner_title') }}</div>
                        <div class="text-muted fs-12">{{ __('hrms.biometric.adms_banner_desc') }}</div>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-grow-1 justify-content-lg-end" style="min-width: 380px;">
                    <div class="input-group input-group-sm" style="max-width: 560px; width: 100%;">
                        <span class="input-group-text bg-white text-muted border-end-0 fs-12 px-2.5">
                            <i class="feather-link-2"></i>
                        </span>
                        <input type="text" class="form-control bg-white fs-12 fw-monospace border-start-0 border-end-0 text-dark px-2" id="admsWebhookUrl" value="{{ url('api/hrms/biometric/webhook') }}" readonly style="cursor: text;" onclick="this.select()" title="{{ __('hrms.biometric.copy_url') }}">
                        <button class="btn btn-sm btn-primary fw-semibold px-3 d-inline-flex align-items-center flex-shrink-0" type="button" onclick="copyAdmsWebhookUrl(this)">
                            <i class="feather-copy me-1.5 fs-12"></i>{{ __('hrms.biometric.copy_url') }}
                        </button>
                    </div>
                </div>
            </div>

            @php
                $biometricTabs = [
                    [
                        'id' => 'devices-pane',
                        'label' => __('hrms.biometric.devices_list'),
                        'icon' => 'feather-cpu',
                        'active' => (request('tab', 'devices') === 'devices' || request('tab') === 'devices-pane')
                    ],
                    [
                        'id' => 'simulator-pane',
                        'label' => __('hrms.biometric.simulator'),
                        'icon' => 'feather-play-circle',
                        'active' => (request('tab') === 'simulator' || request('tab') === 'simulator-pane')
                    ]
                ];
            @endphp

            <!-- Navigation Tabs Bar with Common UI Component -->
            <div class="mb-4">
                <x-ui.horizontal-tabs id="biometricTabs" :tabs="$biometricTabs" />
            </div>

            <div class="tab-content" id="biometricTabsContent">
                <!-- Tab 1: Devices List -->
                <div class="tab-pane fade {{ (request('tab', 'devices') === 'devices' || request('tab') === 'devices-pane') ? 'show active' : '' }}" id="devices-pane" role="tabpanel" aria-labelledby="devices-pane-tab">

                    <!-- Devices Toolbar: Title + Search, Sort, Filter -->
                    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                                <i class="feather-cpu me-2 text-primary"></i> {{ __('hrms.biometric.registered_devices') }}
                                <span class="badge bg-light text-dark border ms-2 fs-11 fw-semibold">{{ $devices->total() }}</span>
                            </h5>
                            <p class="text-muted fs-12 mb-0">{{ __('hrms.biometric.registered_devices_desc') }}</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                            <form method="GET" action="{{ route('hrms.biometric-devices.index') }}" class="d-flex align-items-center gap-2 m-0" id="biometricFilterForm">
                                <input type="hidden" name="tab" value="devices">
                                <input type="hidden" name="sort" id="biometric_sort" value="{{ $sort ?? 'name_asc' }}">

                                <!-- Clean Single-Border Search Input (No double box nesting) -->
                                <div class="position-relative" style="min-width: 240px; max-width: 280px;">
                                    <input type="text" name="search" id="biometricSearchInput" 
                                           class="form-control form-control-sm ps-4 pe-4 fs-12 bg-white text-dark" 
                                           placeholder="{{ __('hrms.biometric.search_placeholder') }}" 
                                           value="{{ $search ?? '' }}" 
                                           autocomplete="off" 
                                           style="height: 38px; border: 1px solid #d1d5db; border-radius: 6px; padding-left: 34px !important;">
                                    <i class="feather-search text-muted position-absolute" style="left: 11px; top: 50%; transform: translateY(-50%); font-size: 13px; pointer-events: none;"></i>
                                    @if(!empty($search))
                                        <a href="{{ route('hrms.biometric-devices.index', ['tab' => 'devices']) }}" class="position-absolute text-muted" style="right: 10px; top: 50%; transform: translateY(-50%); font-size: 12px;" title="{{ __('hrms.biometric.clear_search') }}">
                                            <i class="feather-x"></i>
                                        </a>
                                    @endif
                                </div>

                                <div class="d-flex gap-2">
                                    <!-- Sort Dropdown Component -->
                                    <x-ui.sort-dropdown :label="__('hrms.biometric.sort')">
                                        <a class="dropdown-item py-2 {{ ($sort ?? 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('biometric', 'name_asc', this); event.preventDefault();">{{ __('hrms.biometric.sort_name_asc') }}</a>
                                        <a class="dropdown-item py-2 {{ ($sort ?? '') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('biometric', 'name_desc', this); event.preventDefault();">{{ __('hrms.biometric.sort_name_desc') }}</a>
                                        <a class="dropdown-item py-2 {{ ($sort ?? '') == 'serial_asc' ? 'active' : '' }}" href="#" onclick="changeSort('biometric', 'serial_asc', this); event.preventDefault();">{{ __('hrms.biometric.sort_serial_asc') }}</a>
                                        <a class="dropdown-item py-2 {{ ($sort ?? '') == 'serial_desc' ? 'active' : '' }}" href="#" onclick="changeSort('biometric', 'serial_desc', this); event.preventDefault();">{{ __('hrms.biometric.sort_serial_desc') }}</a>
                                    </x-ui.sort-dropdown>

                                    <!-- Filter Dropdown Component -->
                                    <x-ui.filter :label="__('hrms.biometric.filter')" offset="0, 5" :reset-url="route('hrms.biometric-devices.index', ['tab' => 'devices'])">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.biometric.filter_options') }}</h6>
                                        
                                        <div class="mb-3" style="min-width: 260px;">
                                            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.company')" name="company_id" id="filter_company_id">
                                                <option value="">{{ __('hrms.biometric.all_companies') }}</option>
                                                @foreach($companies as $company)
                                                    <option value="{{ $company->id }}" @selected((string)$selectedCompanyId === (string)$company->id)>{{ $company->company_name }}</option>
                                                @endforeach
                                            </x-ui.modal-form-ui>
                                        </div>

                                        <div class="mb-3">
                                            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.business_unit')" name="business_unit_id" id="filter_business_unit_id">
                                                <option value="">{{ __('hrms.biometric.all_business_units') }}</option>
                                                @foreach($businessUnits as $bu)
                                                    <option value="{{ $bu->id }}" @selected((string)$selectedBusinessUnitId === (string)$bu->id)>{{ $bu->name }}</option>
                                                @endforeach
                                            </x-ui.modal-form-ui>
                                        </div>

                                        <div class="mb-3">
                                            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.branch')" name="branch_id" id="filter_branch_id">
                                                <option value="">{{ __('hrms.biometric.all_branches') }}</option>
                                                @foreach($branches as $branch)
                                                    <option value="{{ $branch->id }}" @selected((string)$selectedBranchId === (string)$branch->id)>{{ $branch->name }}</option>
                                                @endforeach
                                            </x-ui.modal-form-ui>
                                        </div>
                                    </x-ui.filter>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Device Table -->
                    <div class="table-responsive biometric-table-responsive">
                        <table class="table table-hover align-middle mb-0 biometric-devices-table" style="font-size: 13px;">
                            <colgroup>
                                <col style="width: 26%;">
                                <col style="width: 14%;">
                                <col style="width: 28%;">
                                <col style="width: 14%;">
                                <col style="width: 12%;">
                                <col style="width: 6%;">
                            </colgroup>
                            <thead class="table-light text-uppercase fs-11 text-muted" style="letter-spacing: .5px;">
                                <tr>
                                    <th class="ps-4 py-3">{{ __('hrms.biometric.device_info') }}</th>
                                    <th>{{ __('hrms.biometric.serial_number') }}</th>
                                    <th>{{ __('hrms.biometric.org_scope') }}</th>
                                    <th>{{ __('hrms.biometric.network_port') }}</th>
                                    <th>{{ __('hrms.biometric.connection_state') }}</th>
                                    <th class="pe-4 text-end">{{ __('hrms.biometric.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="biometricTableBody">
                                @forelse($devices as $dev)
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-start gap-2.5">
                                                <div class="avatar-text avatar-md bg-soft-primary text-primary fw-bold rounded-circle flex-shrink-0 mt-0.5" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;">
                                                    <i class="feather-cpu fs-15"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark lh-sm mb-1">{{ $dev->name }}</div>
                                                    <div class="text-muted fs-11">{{ __('hrms.biometric.registered_on', ['date' => $dev->created_at ? $dev->created_at->format('d M Y') : '—']) }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="px-2 py-1 bg-light rounded text-secondary fw-semibold text-break d-inline-block">{{ $dev->device_serial }}</code>
                                        </td>
                                        <td>
                                            <div class="fs-12">
                                                <div class="fw-bold text-dark mb-1">{{ $dev->company->company_name ?? '—' }}</div>
                                                <div class="text-muted fs-11 lh-sm mb-1">
                                                    <span class="text-secondary fw-semibold">BU:</span> {{ $dev->businessUnit->name ?? __('hrms.biometric.global') }}
                                                </div>
                                                <div class="text-muted fs-11 lh-sm">
                                                    <span class="text-secondary fw-semibold">Branch:</span> {{ $dev->branch->name ?? __('hrms.biometric.global') }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fs-12">
                                                <div class="fw-semibold text-dark mb-1">
                                                    <i class="feather-wifi me-1 text-primary"></i>{{ $dev->ip_address ?: __('hrms.biometric.cloud_adms') }}
                                                </div>
                                                <div class="text-muted fs-11">{{ __('hrms.biometric.port_prefix', ['port' => $dev->port ?? '4370']) }}</div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1 align-items-start">
                                                @if($dev->last_ping_at && $dev->last_ping_at->gt(now()->subMinutes(15)))
                                                    <x-ui.badge variant="success" :soft="true" class="d-inline-flex align-items-center" style="padding: 3px 8px; font-size: 11px;">
                                                        <i class="feather-check-circle me-1"></i>{{ __('hrms.biometric.status_online') }}
                                                    </x-ui.badge>
                                                    <span class="text-muted fs-11">{{ __('hrms.biometric.ping_prefix', ['time' => $dev->last_ping_at->diffForHumans()]) }}</span>
                                                @elseif($dev->last_ping_at)
                                                    <x-ui.badge variant="warning" :soft="true" class="d-inline-flex align-items-center" style="padding: 3px 8px; font-size: 11px;">
                                                        <i class="feather-clock me-1"></i>{{ __('hrms.biometric.status_idle') }}
                                                    </x-ui.badge>
                                                    <span class="text-muted fs-11">{{ __('hrms.biometric.last_prefix', ['time' => $dev->last_ping_at->diffForHumans()]) }}</span>
                                                @else
                                                    <x-ui.badge variant="secondary" :soft="true" class="d-inline-flex align-items-center" style="padding: 3px 8px; font-size: 11px;">
                                                        <i class="feather-cloud me-1"></i>{{ __('hrms.biometric.status_standby') }}
                                                    </x-ui.badge>
                                                    <span class="text-muted fs-11">{{ __('hrms.biometric.awaiting_sync') }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <x-ui.action-dropdown>
                                                <li>
                                                    <a class="dropdown-item py-2" href="#" onclick="testDeviceConnection({{ $dev->id }}, this); event.preventDefault();">
                                                        <i class="feather-activity me-2 text-primary"></i>{{ __('hrms.biometric.test_connection') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item py-2" href="#" 
                                                        data-bs-toggle="modal" data-bs-target="#editDeviceModal" 
                                                        data-id="{{ $dev->id }}"
                                                        data-name="{{ $dev->name }}"
                                                        data-serial="{{ $dev->device_serial }}"
                                                        data-company="{{ $dev->company_id }}"
                                                        data-bu="{{ $dev->business_unit_id }}"
                                                        data-branch="{{ $dev->branch_id }}"
                                                        data-ip="{{ $dev->ip_address }}"
                                                        data-port="{{ $dev->port }}"
                                                        data-status="{{ $dev->status ? '1' : '0' }}"
                                                        onclick="populateEditModal(this); event.preventDefault();">
                                                        <i class="feather-edit me-2 text-muted"></i>{{ __('hrms.biometric.edit_device') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <form action="{{ route('hrms.biometric-devices.destroy', $dev->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <a class="dropdown-item py-2 text-danger fw-semibold" href="#" onclick="confirmDelete(this); event.preventDefault();">
                                                            <i class="feather-trash-2 me-2"></i>{{ __('hrms.biometric.delete_device') }}
                                                        </a>
                                                    </form>
                                                </li>
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-alert-circle fs-30 mb-2 d-block text-secondary"></i>
                                            <div>{{ __('hrms.biometric.empty_devices_hint') }}</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div id="biometricPaginationWrapper">
                        @if($devices->hasPages())
                            <div class="card-footer border-top bg-white p-3">
                                {{ $devices->links() }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Tab 2: Biometric Simulator Testing Panel -->
                <div class="tab-pane fade show {{ request('tab') === 'simulator' ? 'show active' : '' }}" id="simulator-pane" role="tabpanel" aria-labelledby="simulator-tab">
                    <div class="row m-0 border-top">
                        <!-- Instructions Side Panel -->
                        <div class="col-lg-4 p-4 bg-light border-end">
                            <h5 class="fw-bold text-dark fs-14 mb-3"><i class="feather-info me-2 text-primary"></i>{{ __('hrms.biometric.simulator_title') }}</h5>
                            <p class="text-muted fs-12 leading-relaxed">
                                {{ __('hrms.biometric.simulator_intro') }}
                            </p>
                            <ul class="ps-3 fs-12 text-muted leading-relaxed mb-4">
                                <li class="mb-2">{{ __('hrms.biometric.sim_check_in') }}</li>
                                <li class="mb-2">{{ __('hrms.biometric.sim_check_out') }}</li>
                                <li class="mb-2">{{ __('hrms.biometric.sim_grace_period') }}</li>
                                <li class="mb-2">{{ __('hrms.biometric.sim_roster_int') }}</li>
                            </ul>
                            
                            <div class="alert bg-soft-primary text-primary border-0 fs-12 mb-0" style="padding: 12px;">
                                <i class="feather-zap me-1"></i>
                                <strong>{{ __('hrms.biometric.sim_instant_title') }}</strong> {{ __('hrms.biometric.sim_instant_desc') }}
                            </div>
                        </div>

                        <!-- Simulator Form -->
                        <div class="col-lg-8 p-4">
                            <form action="{{ route('hrms.biometric-devices.simulate-punch') }}" method="POST" class="needs-validation" novalidate>
                                @csrf
                                <div class="row g-3">
                                    <!-- Device Select -->
                                    <div class="col-md-6">
                                        <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.target_virtual_device')" name="biometric_device_id" id="sim_biometric_device_id">
                                            <option value="">{{ __('hrms.biometric.virtual_simulator_port') }}</option>
                                            @foreach(($allDevices ?? $devices) as $dev)
                                                <option value="{{ $dev->id }}">{{ $dev->name }} ({{ $dev->device_serial }})</option>
                                            @endforeach
                                        </x-ui.modal-form-ui>
                                    </div>

                                    <!-- Employee Select -->
                                    <div class="col-md-6">
                                        <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.select_employee')" name="employee_id" id="sim_employee_id" required>
                                            <option value="">{{ __('hrms.biometric.choose_employee') }}</option>
                                            @foreach($allEmployeesForSim as $emp)
                                                <option value="{{ $emp->id }}">
                                                    {{ $emp->full_name }} ({{ $emp->employee_id ?? 'No ID' }}) 
                                                </option>
                                            @endforeach
                                        </x-ui.modal-form-ui>
                                    </div>

                                    <!-- Punch Type -->
                                    <div class="col-md-6">
                                        <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.punch_event_type')" name="punch_type" id="sim_punch_type" required>
                                            <option value="auto">{{ __('hrms.biometric.auto_resolve') }}</option>
                                            <option value="in">{{ __('hrms.biometric.check_in') }}</option>
                                            <option value="out">{{ __('hrms.biometric.check_out') }}</option>
                                        </x-ui.modal-form-ui>
                                    </div>

                                    <!-- Timestamp -->
                                    <div class="col-md-6">
                                        <x-ui.modal-form-ui type="input" inputType="datetime-local" :label="__('hrms.biometric.punch_time')" name="punch_time" id="sim_punch_time" value="{{ now()->format('Y-m-d\TH:i') }}" required />
                                    </div>

                                    <div class="col-12 mt-4 text-end">
                                        <x-ui.button variant="primary" icon="feather-zap" type="submit" class="shadow-sm">
                                            {{ __('hrms.biometric.trigger_test_punch') }}
                                        </x-ui.button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- ========================================== -->
<!-- MODALS -->
<!-- ========================================== -->

<!-- Add Device Modal -->
<x-ui.modal 
    id="addDeviceModal" 
    :title="'<i class=\'feather-cpu me-2 text-primary\'></i>' . __('hrms.biometric.register_modal_title')" 
    :submit-text="__('hrms.biometric.save_device')"
    formAction="{{ route('hrms.biometric-devices.store') }}" 
    formMethod="POST"
    centered>
    
    <div class="row g-3">
        <div class="col-md-6">
            <x-ui.modal-form-ui type="input" :label="__('hrms.biometric.device_name')" name="name" id="add_name" :placeholder="__('hrms.biometric.device_name_placeholder')" required />
        </div>
        <div class="col-md-6">
            <x-ui.modal-form-ui type="input" :label="__('hrms.biometric.device_serial')" name="device_serial" id="add_device_serial" :placeholder="__('hrms.biometric.device_serial_placeholder')" required />
        </div>

        <div class="col-md-12">
            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.company_entity')" name="company_id" id="add_company_id" required>
                <option value="">{{ __('hrms.biometric.select_company') }}</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                @endforeach
            </x-ui.modal-form-ui>
        </div>

        <div class="col-md-6">
            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.business_unit_opt')" name="business_unit_id" id="add_business_unit_id">
                <option value="">{{ __('hrms.biometric.global_all_units') }}</option>
                @foreach($businessUnits as $bu)
                    <option value="{{ $bu->id }}">{{ $bu->name }}</option>
                @endforeach
            </x-ui.modal-form-ui>
        </div>

        <div class="col-md-6">
            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.branch_opt')" name="branch_id" id="add_branch_id">
                <option value="">{{ __('hrms.biometric.global_all_branches') }}</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </x-ui.modal-form-ui>
        </div>

        <div class="col-md-8">
            <x-ui.modal-form-ui type="input" :label="__('hrms.biometric.ip_address')" name="ip_address" id="add_ip_address" :placeholder="__('hrms.biometric.ip_address_placeholder')" :helper-text="__('hrms.biometric.ip_helper_text')" />
        </div>

        <div class="col-md-4">
            <x-ui.modal-form-ui type="input" inputType="number" :label="__('hrms.biometric.port')" name="port" id="add_port" value="4370" required min="1" max="65535" />
        </div>

        <div class="col-md-12">
            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.device_status')" name="status" id="add_status" required>
                <option value="1" selected>{{ __('hrms.biometric.status_active_ready') }}</option>
                <option value="0">{{ __('hrms.biometric.status_inactive_disabled') }}</option>
            </x-ui.modal-form-ui>
        </div>
    </div>
</x-ui.modal>

<!-- Edit Device Modal -->
<x-ui.modal 
    id="editDeviceModal" 
    :title="'<i class=\'feather-cpu me-2 text-primary\'></i>' . __('hrms.biometric.edit_modal_title')" 
    :submit-text="__('hrms.biometric.save_changes')"
    formAction="placeholder" 
    formMethod="PUT"
    centered>
    
    <div class="row g-3">
        <div class="col-md-6">
            <x-ui.modal-form-ui type="input" :label="__('hrms.biometric.device_name')" name="name" id="edit_name" required />
        </div>
        <div class="col-md-6">
            <x-ui.modal-form-ui type="input" :label="__('hrms.biometric.device_serial')" name="device_serial" id="edit_serial" required />
        </div>

        <div class="col-md-12">
            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.company_entity')" name="company_id" id="edit_company_id" required>
                <option value="">{{ __('hrms.biometric.select_company') }}</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                @endforeach
            </x-ui.modal-form-ui>
        </div>

        <div class="col-md-6">
            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.business_unit_opt')" name="business_unit_id" id="edit_business_unit_id">
                <option value="">{{ __('hrms.biometric.global_all_units') }}</option>
                @foreach($businessUnits as $bu)
                    <option value="{{ $bu->id }}">{{ $bu->name }}</option>
                @endforeach
            </x-ui.modal-form-ui>
        </div>

        <div class="col-md-6">
            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.branch_opt')" name="branch_id" id="edit_branch_id">
                <option value="">{{ __('hrms.biometric.global_all_branches') }}</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </x-ui.modal-form-ui>
        </div>

        <div class="col-md-8">
            <x-ui.modal-form-ui type="input" :label="__('hrms.biometric.ip_address')" name="ip_address" id="edit_ip_address" :placeholder="__('hrms.biometric.ip_address_placeholder')" :helper-text="__('hrms.biometric.ip_helper_text')" />
        </div>

        <div class="col-md-4">
            <x-ui.modal-form-ui type="input" inputType="number" :label="__('hrms.biometric.port')" name="port" id="edit_port" required min="1" max="65535" />
        </div>

        <div class="col-md-12">
            <x-ui.modal-form-ui type="select" :label="__('hrms.biometric.device_status')" name="status" id="edit_status" required>
                <option value="1">{{ __('hrms.biometric.status_active_ready') }}</option>
                <option value="0">{{ __('hrms.biometric.status_inactive_disabled') }}</option>
            </x-ui.modal-form-ui>
        </div>
    </div>
</x-ui.modal>

<script>
    // Validation bootstrap
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
    })()

    // Ensure Select2 is initialized inside Bootstrap modals
    $(document).on('shown.bs.modal', '#addDeviceModal, #editDeviceModal', function () {
        var modal = $(this);
        modal.find('.form-ui-select2').each(function () {
            var $select = $(this);
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    theme: "bootstrap-5",
                    width: "100%",
                    dropdownParent: modal
                });
            }
        });
    });

    // Reset Add modal on open
    $('#addDeviceModal').on('show.bs.modal', function () {
        var form = $(this).find('form')[0];
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
            $('#add_company_id').val('').trigger('change');
            $('#add_business_unit_id').val('').trigger('change');
            $('#add_branch_id').val('').trigger('change');
            $('#add_status').val('1').trigger('change');
        }
    });

    // Populate dynamic edit modal values
    function populateEditModal(button) {
        var id = button.getAttribute('data-id');
        var name = button.getAttribute('data-name') || '';
        var serial = button.getAttribute('data-serial') || '';
        var company = button.getAttribute('data-company') || '';
        var bu = button.getAttribute('data-bu') || '';
        var branch = button.getAttribute('data-branch') || '';
        var ip = button.getAttribute('data-ip') || '';
        var port = button.getAttribute('data-port') || '4370';
        var status = button.getAttribute('data-status') || '1';

        $('#edit_name').val(name);
        $('#edit_serial').val(serial);
        $('#edit_company_id').val(company).trigger('change');
        $('#edit_business_unit_id').val(bu).trigger('change');
        $('#edit_branch_id').val(branch).trigger('change');
        $('#edit_ip_address').val(ip);
        $('#edit_port').val(port);
        $('#edit_status').val(status).trigger('change');

        var form = document.querySelector('#editDeviceModal form');
        if (form) {
            var baseAction = "{{ route('hrms.biometric-devices.update', 99999999) }}";
            form.action = baseAction.replace('99999999', id);
        }
    }

    // Ping / Test Device Connection
    function testDeviceConnection(deviceId, button) {
        var originalHtml = button.innerHTML;
        button.innerHTML = '<i class="feather-loader me-2 fa-spin"></i>{{ __('hrms.biometric.testing') }}';
        button.disabled = true;

        var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        fetch('{{ url("hrms/biometric-devices") }}/' + deviceId + '/test-connection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            button.innerHTML = originalHtml;
            button.disabled = false;

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: data.connected ? '{{ __('hrms.biometric.device_connected') }}' : '{{ __('hrms.biometric.connection_status') }}',
                    text: data.message || (data.connected ? '{{ __('hrms.biometric.device_responded') }}' : '{{ __('hrms.biometric.unable_to_connect') }}'),
                    icon: data.connected ? 'success' : 'info',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6'
                });
            } else {
                alert(data.message || (data.connected ? '{{ __('hrms.biometric.device_connected') }}' : '{{ __('hrms.biometric.unable_to_connect') }}'));
            }
        })
        .catch(function (err) {
            button.innerHTML = originalHtml;
            button.disabled = false;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '{{ __('hrms.biometric.conn_check_error') }}',
                    text: '{{ __('hrms.biometric.conn_check_error_desc') }}',
                    icon: 'error'
                });
            } else {
                alert('{{ __('hrms.biometric.conn_check_error_desc') }}');
            }
        });
    }

    // Copy Cloud ADMS Webhook URL
    function copyAdmsWebhookUrl(button) {
        var copyText = document.getElementById("admsWebhookUrl");
        if (copyText) {
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);
            
            var originalText = button.innerHTML;
            button.innerHTML = '<i class="feather-check me-1 text-success"></i>{{ __('hrms.biometric.copied') }}';
            setTimeout(function () {
                button.innerHTML = originalText;
            }, 2000);
        }
    }

    // SweetAlert delete confirmation wrapper
    function confirmDelete(button) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '{{ __('hrms.biometric.confirm_delete_title') }}',
                text: "{{ __('hrms.biometric.confirm_delete_text') }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '{{ __('hrms.biometric.yes_delete') }}',
                cancelButtonText: '{{ __('hrms.biometric.cancel') }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    button.closest('form').submit();
                }
            });
        } else {
            if (confirm("{{ __('hrms.biometric.confirm_delete_text') }}")) {
                button.closest('form').submit();
            }
        }
    }

    // Handle sort dropdown options
    function changeSort(tab, criteria, element) {
        var input = document.getElementById(tab + '_sort');
        if (input) {
            input.value = criteria;
        }

        if (element) {
            var menu = element.closest('.dropdown-menu');
            if (menu) {
                menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                    el.classList.remove('active');
                });
            }
            element.classList.add('active');
        }

        if (input) {
            const form = input.closest('form');
            if (form) {
                const url = new URL(form.action || window.location.href);
                const formData = new FormData(form);
                for (const [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }
                url.searchParams.delete('page');
                refreshBiometricList(url);
            }
        }
    }

    var activeRequest = null;
    function refreshBiometricList(url) {
        if (activeRequest) {
            activeRequest.abort();
        }

        const controller = new AbortController();
        activeRequest = controller;

        const pane = document.getElementById('devices-pane');
        if (pane) {
            pane.classList.add('is-loading');
        }

        fetch(url.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to refresh list.');
            }
            return response.text();
        })
        .then(function (html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            
            const newTbody = doc.getElementById('biometricTableBody');
            const oldTbody = document.getElementById('biometricTableBody');
            const newPagination = doc.getElementById('biometricPaginationWrapper');
            const oldPagination = document.getElementById('biometricPaginationWrapper');

            if (newTbody && oldTbody) {
                oldTbody.innerHTML = newTbody.innerHTML;
            }
            if (newPagination && oldPagination) {
                oldPagination.innerHTML = newPagination.innerHTML;
            }

            // Push state to update browser URL
            history.pushState(null, '', url.toString());
        })
        .catch(function (error) {
            if (error.name !== 'AbortError') {
                window.location.href = url.toString();
            }
        })
        .finally(function () {
            if (activeRequest === controller) {
                if (pane) {
                    pane.classList.remove('is-loading');
                }
                activeRequest = null;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Debounced quick search
        var searchTimeout = null;
        $(document).on('input', '#biometricFilterForm input[name="search"]', function () {
            const input = this;
            const form = input.closest('form');
            if (!form) return;
            
            const url = new URL(form.action || window.location.href);
            const formData = new FormData(form);
            for (const [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }
            url.searchParams.delete('page');

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                refreshBiometricList(url);
            }, 250);
        });

        // Intercept GET form submissions (search/filters)
        $(document).on('submit', '#biometricFilterForm', function (event) {
            const form = this;
            event.preventDefault();
            
            const url = new URL(form.action || window.location.href);
            const formData = new FormData(form);
            for (const [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }
            url.searchParams.delete('page');

            refreshBiometricList(url);
            
            // Close the filter dropdown menu safely
            $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
            $('.erp-filter-dropdown.show').removeClass('show');
        });

        // Intercept Pagination link clicks
        $(document).on('click', '#biometricPaginationWrapper a[href]', function (event) {
            const href = this.getAttribute('href');
            if (!href || href.startsWith('javascript:') || href === '#') return;

            event.preventDefault();
            const urlObj = new URL(href, window.location.origin);
            refreshBiometricList(urlObj);
        });
    });
</script>
@endsection
