@extends('layouts.duralux')

@section('title', 'HR SETTINGS | SaaS ERP')
@section('page-title', 'HR Settings')
@section('breadcrumb', 'HRMS / Settings')

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
            /* Override container paddings to allow flush layout next to main sidebar */
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

        /* Underlined Horizontal Tabs */
        #orgTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 500;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
        }
        #orgTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #orgTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
        }

        /* Colors and theme attributes */
        .bg-soft-purple { background-color: rgba(139, 92, 246, 0.08) !important; }
        .text-purple { color: #8b5cf6 !important; }
        .bg-soft-teal { background-color: rgba(20, 184, 166, 0.08) !important; }
        .text-teal { color: #14b8a6 !important; }
        .bg-soft-indigo { background-color: rgba(99, 102, 241, 0.08) !important; }
        .text-indigo { color: #6366f1 !important; }
        .bg-soft-muted { background-color: rgba(100, 116, 139, 0.08) !important; }
        
        .transition-all {
            transition: all 0.2s ease-in-out !important;
        }
        .bg-soft-light-hover:hover {
            background-color: rgba(13, 110, 253, 0.02) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03) !important;
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
            @if($errors->any())
                <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <div class="tab-content" id="settingsSubSidebarContent">
                <!-- Org Structure Pane -->
                <div class="tab-pane fade show active" id="org-structure-pane" role="tabpanel" aria-labelledby="org-structure-menu">
                    <div class="row">
                        <!-- Horizontal Navigation directly above table content -->
                        <div class="col-12 mb-3">
                            <ul class="nav gap-2 border-bottom pb-2" id="orgTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="legal-entities-tab" data-bs-toggle="tab" data-bs-target="#legal-entities" type="button" role="tab" aria-controls="legal-entities" aria-selected="true">
                                        <i class="feather-home me-2"></i>Legal Entities
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="business-units-tab" data-bs-toggle="tab" data-bs-target="#business-units" type="button" role="tab" aria-controls="business-units" aria-selected="false">
                                        <i class="feather-briefcase me-2"></i>Business Units
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="branches-tab" data-bs-toggle="tab" data-bs-target="#branches" type="button" role="tab" aria-controls="branches" aria-selected="false">
                                        <i class="feather-map-pin me-2"></i>Branches
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments" type="button" role="tab" aria-controls="departments" aria-selected="false">
                                        <i class="feather-users me-2"></i>Departments
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="designations-tab" data-bs-toggle="tab" data-bs-target="#designations" type="button" role="tab" aria-controls="designations" aria-selected="false">
                                        <i class="feather-award me-2"></i>Designations
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Tabs Content -->
                        <div class="col-12">
                            <div class="tab-content" id="orgTabsContent">
                                <div class="tab-pane fade show active" id="legal-entities" role="tabpanel" aria-labelledby="legal-entities-tab">
                                    @include('modules.hrms.org-structure.tabs.legal-entities')
                                </div>
                                <div class="tab-pane fade" id="business-units" role="tabpanel" aria-labelledby="business-units-tab">
                                    @include('modules.hrms.org-structure.tabs.business-units')
                                </div>
                                <div class="tab-pane fade" id="branches" role="tabpanel" aria-labelledby="branches-tab">
                                    @include('modules.hrms.org-structure.tabs.branches')
                                </div>
                                <div class="tab-pane fade" id="departments" role="tabpanel" aria-labelledby="departments-tab">
                                    @include('modules.hrms.org-structure.tabs.departments')
                                </div>
                                <div class="tab-pane fade" id="designations" role="tabpanel" aria-labelledby="designations-tab">
                                    @include('modules.hrms.org-structure.tabs.designations')
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Salary Structure Pane -->
                <div class="tab-pane fade" id="salary-structure-pane" role="tabpanel" aria-labelledby="salary-structure-menu">
                    @include('modules.hrms.salary-structure.tabs.salary-components')
                </div>

                <!-- Leave Structure Pane -->
                <div class="tab-pane fade" id="leave-structure-pane" role="tabpanel" aria-labelledby="leave-structure-menu">
                    <div class="card stretch stretch-full mb-0">
                        <div class="card-body py-5 text-center">
                            <div class="avatar-text avatar-xl bg-soft-warning text-warning mx-auto mb-4" style="width: 60px; height: 60px; min-width: 60px; min-height: 60px;">
                                <i class="feather-calendar fs-24"></i>
                            </div>
                            <h4 class="fw-bold mb-2">Leave Structure Settings</h4>
                            <p class="text-muted mb-0">The leave structure configuration tools and layout will be implemented here in a future update.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include all Unified Modals at body level to prevent parent wrapper blur/backdrop overlay bugs -->
    @include('modules.hrms.partials.modals')

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Move all modal divs to the body root level to prevent stacking context/blur backdrop issues!
            document.querySelectorAll('.modal').forEach(function(modal) {
                document.body.appendChild(modal);
            });

            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                setTimeout(function() {
                    const tabButton = document.getElementById(tabParam + '-tab');
                    if (tabButton) {
                        tabButton.click();
                    }
                }, 100);
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

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush