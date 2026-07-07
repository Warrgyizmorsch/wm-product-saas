@extends('layouts.duralux')

@section('title', 'SALARY STRUCTURE | SaaS ERP')
@section('page-title', 'Salary Structure')
@section('breadcrumb', 'HRMS / Salary Structure')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <style>
        /* Modern layouts for connected settings sidebar */
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

        /* Settings Subsidebar Items */
        #settingsSubSidebar .nav-link {
            background-color: transparent;
            transition: all 0.2s ease-in-out;
            border-radius: 6px !important;
            font-size: 14px;
            font-weight: 500;
            color: #475569 !important;
            padding: 12px 16px !important;
            border: 0 !important;
        }
        #settingsSubSidebar .nav-link:hover {
            background-color: #f1f5f9;
            color: var(--bs-primary) !important;
        }
        #settingsSubSidebar .nav-link.active {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
            font-weight: 600;
        }

        /* Underlined Horizontal Tabs to match Org Structure style */
        #salaryStructureTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 500;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
        }
        #salaryStructureTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #salaryStructureTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
        }

        /* Plans List items styling */
        .plan-item {
            border-left: 4px solid transparent !important;
            transition: all 0.15s ease-in-out;
            border-bottom: 1px solid #f1f5f9 !important;
            color: #475569 !important;
        }
        .plan-item.active {
            background-color: #f1f5f9 !important;
            border-color: #f1f5f9 !important;
            border-left-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
        .plan-item:hover:not(.active) {
            background-color: #f8fafc !important;
        }
    </style>

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

            <div class="col-12">
                <x-ui.card title="Pay Groups" bodyClass="p-0" stretch>
                    <x-slot name="headerAction">
                        <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addPayGroupModal">
                            Add Pay Group
                        </x-ui.button>
                    </x-slot>

                    <div class="row g-0">
                        <!-- LEFT COLUMN: ALL PAY GROUPS LIST -->
                        <div class="col-md-3 col-12 border-end">
                            <div class="list-group list-group-flush rounded-0" style="min-height: 400px; max-height: 600px; overflow-y: auto;">
                                @forelse($payGroups as $pg)
                                    @php
                                        $isActive = $selectedPayGroup && $selectedPayGroup->id === $pg->id;
                                    @endphp
                                    <a href="{{ route('hrms.salary-structure.index', ['pay_group_id' => $pg->id, 'tab' => request()->get('tab', 'structures')]) }}" 
                                       class="list-group-item list-group-item-action py-3 px-4 plan-item {{ $isActive ? 'active' : '' }}">
                                        <span class="fw-bold {{ $isActive ? 'text-primary' : 'text-dark' }}" style="font-size: 14px;">
                                            {{ $pg->name }}
                                        </span>
                                    </a>
                                @empty
                                    <div class="text-center py-5 text-muted px-3">
                                        <i class="feather-grid fs-24 mb-2 d-block text-secondary"></i>
                                        <span>No Pay Groups configured yet.</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: SELECTED PAY GROUP DETAILS & CONFIGURATION TABS -->
                        <div class="col-md-9 col-12">
                            @if($selectedPayGroup)
                                <div class="p-4">
                                    <!-- Selected Pay Group Details -->
                                    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                                        <div>
                                            <h5 class="fw-bold text-dark mb-1" style="font-size: 16px;">{{ $selectedPayGroup->name }}</h5>
                                            <div class="text-muted d-flex align-items-center gap-2" style="font-size: 12px;">
                                                <span><i class="feather-briefcase me-1"></i>{{ $selectedPayGroup->company ? $selectedPayGroup->company->company_name : 'All Companies' }}</span>
                                                <span>&bull;</span>
                                                <span>
                                                    @if($selectedPayGroup->status)
                                                        <span class="text-success"><i class="feather-check-circle me-1"></i>Active</span>
                                                    @else
                                                        <span class="text-danger"><i class="feather-slash me-1"></i>Inactive</span>
                                                    @endif
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Actions Dropdown for Pay Group -->
                                        <form action="{{ route('hrms.salary-structure.pay-group.destroy', $selectedPayGroup->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Pay Group? Linked components and structures will be unassigned.');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.action-dropdown>
                                                <li>
                                                    <a class="dropdown-item edit-pay-group-btn" href="javascript:void(0)" data-pay-group="{{ base64_encode($selectedPayGroup->toJson()) }}">
                                                        <i class="feather feather-edit-3 me-3"></i>
                                                        <span>Edit Pay Group</span>
                                                    </a>
                                                </li>
                                                <li class="dropdown-divider"></li>
                                                <li>
                                                    <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                        <i class="feather feather-trash-2 me-3"></i>
                                                        <span>Delete Pay Group</span>
                                                    </button>
                                                </li>
                                            </x-ui.action-dropdown>
                                        </form>
                                    </div>

                                    @if($selectedPayGroup->description)
                                        <div class="p-3 bg-light rounded mb-4" style="font-size: 13px; color: #475569;">
                                            <i class="feather-info me-2 text-primary"></i>{{ $selectedPayGroup->description }}
                                        </div>
                                    @endif

                                    <!-- Nav tabs inside the selected Pay Group details -->
                                    <ul class="nav gap-2 border-bottom mb-4" id="salaryStructureTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link {{ request()->get('tab', 'structures') === 'structures' ? 'active' : '' }} px-4 py-3" id="structures-tab" data-bs-toggle="tab" data-bs-target="#structures-pane" type="button" role="tab" aria-controls="structures-pane" aria-selected="true">
                                                <i class="feather-grid me-2"></i>Salary Structures (Slabs)
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link {{ request()->get('tab') === 'components' ? 'active' : '' }} px-4 py-3" id="components-tab" data-bs-toggle="tab" data-bs-target="#components-pane" type="button" role="tab" aria-controls="components-pane" aria-selected="false">
                                                <i class="feather-list me-2"></i>Salary Components
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content" id="settingsSubSidebarContent">
                                        <div class="tab-pane fade {{ request()->get('tab', 'structures') === 'structures' ? 'show active' : '' }}" id="structures-pane" role="tabpanel" aria-labelledby="structures-tab">
                                            @include('modules.hrms.salary-structure.tabs.salary-structures')
                                        </div>
                                        <div class="tab-pane fade {{ request()->get('tab') === 'components' ? 'show active' : '' }}" id="components-pane" role="tabpanel" aria-labelledby="components-tab">
                                            @include('modules.hrms.salary-structure.tabs.salary-components')
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-5 text-muted">
                                    <i class="feather-info fs-32 mb-2 d-block text-secondary"></i>
                                    <span>Please select or add a Pay Group to configure its settings.</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>

    <!-- Include all Unified Modals at body level to prevent parent wrapper blur/backdrop overlay bugs -->
    @include('modules.hrms.partials.modals')

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Edit Pay Group Trigger
            $(document).on('click', '.edit-pay-group-btn', function() {
                let dataStr = $(this).attr('data-pay-group');
                if (!dataStr) return;

                let pg = JSON.parse(atob(dataStr));
                
                $('#editPayGroupForm').attr('action', `/hrms/salary-structure/pay-group/update/${pg.id}`);
                $('#edit_pg_name').val(pg.name);
                $('#edit_pg_company_id').val(pg.company_id || '');
                $('#edit_pg_description').val(pg.description || '');
                
                let statusVal = (pg.status === true || pg.status === 1 || pg.status === '1') ? '1' : '0';
                $('#edit_pg_status').val(statusVal);

                $('#editPayGroupModal').modal('show');
            });

            // Move all modal divs to the body root level to prevent stacking context/blur backdrop issues!
            document.querySelectorAll('.modal').forEach(function(modal) {
                document.body.appendChild(modal);
            });

            // Track tab changes in URL params
            $('#salaryStructureTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                const tabId = e.target.id.replace('-tab', '');
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('tab', tabId);
                const newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?' + urlParams.toString();
                window.history.pushState({path:newurl}, '', newurl);
            });

            // Automatically activate the correct tab if passed in query string (handles redirects after submit)
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabButton = document.getElementById(tabParam + '-tab');
                if (tabButton) {
                    tabButton.click();
                }
            }

            const subtabParam = urlParams.get('subtab');
            if (subtabParam) {
                const subtabButton = document.getElementById(subtabParam + '-subtab');
                if (subtabButton) {
                    // Slight delay to ensure the parent tab pane is fully shown first
                    setTimeout(() => {
                        subtabButton.click();
                    }, 50);
                }
            }

            // Generic modal Select2 initializer inside HRMS boundaries
            $(document).on('shown.bs.modal', '.modal', function () {
                var modal = $(this);
                modal.find('select').each(function() {
                    var $select = $(this);
                    if ($select.hasClass("select2-hidden-accessible")) {
                        $select.select2('destroy');
                    }
                    
                    var selectorType = $select.attr('data-select2-selector') || 'default';
                    var options = {
                        theme: 'bootstrap-5',
                        dropdownParent: modal.find('.modal-content'),
                        width: '100%'
                    };
                    
                    if (selectorType === 'status' && typeof bgformat === 'function') {
                        options.templateResult = bgformat;
                        options.templateSelection = bgformat;
                        options.minimumResultsForSearch = Infinity;
                    } else if (selectorType === 'currency' && typeof currencyformat === 'function') {
                        options.templateResult = currencyformat;
                        options.templateSelection = currencyformat;
                    } else if (selectorType === 'country' && typeof countryformat === 'function') {
                        options.templateResult = countryformat;
                        options.templateSelection = countryformat;
                    } else if (selectorType === 'tzone' && typeof tzoneformat === 'function') {
                        options.templateResult = tzoneformat;
                        options.templateSelection = tzoneformat;
                    }
                    
                    $select.select2(options);
                });
            });
        });
    </script>
@endsection


