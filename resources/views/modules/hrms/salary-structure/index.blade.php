@extends('layouts.duralux')

@section('title', 'SALARY STRUCTURE | SaaS ERP')
@section('page-title', 'Salary Structure')
@section('breadcrumb', 'HRMS / Salary Structure')

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

            <!-- Nav tabs -->
            <ul class="nav gap-2 border-bottom mb-4" id="salaryStructureTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active px-4 py-3" id="structures-tab" data-bs-toggle="tab" data-bs-target="#structures-pane" type="button" role="tab" aria-controls="structures-pane" aria-selected="true">
                        <i class="feather-grid me-2"></i>Salary Structures (Slabs)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-3" id="components-tab" data-bs-toggle="tab" data-bs-target="#components-pane" type="button" role="tab" aria-controls="components-pane" aria-selected="false">
                        <i class="feather-list me-2"></i>Salary Components
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="settingsSubSidebarContent">
                <div class="tab-pane fade show active" id="structures-pane" role="tabpanel" aria-labelledby="structures-tab">
                    @include('modules.hrms.salary-structure.tabs.salary-structures')
                </div>
                <div class="tab-pane fade" id="components-pane" role="tabpanel" aria-labelledby="components-tab">
                    @include('modules.hrms.salary-structure.tabs.salary-components')
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

            // Automatically activate the correct tab if passed in query string (handles redirects after submit)
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabButton = document.getElementById(tabParam + '-tab');
                if (tabButton) {
                    tabButton.click();
                }
            }

            // Correctly initialize Select2 inside Salary Component modals when shown (avoiding width: 0px bug)
            $('#addSalaryComponentModal, #editSalaryComponentModal').on('shown.bs.modal', function () {
                $(this).find('select').each(function() {
                    var $select = $(this);
                    if ($select.hasClass("select2-hidden-accessible")) {
                        $select.select2('destroy');
                    }
                    // Format status dropdown with colored status dots
                    if ($select.attr('data-select2-selector') === 'status' && typeof bgformat === 'function') {
                        $select.select2({
                            theme: 'bootstrap-5',
                            dropdownParent: $select.closest('.modal'),
                            width: '100%',
                            templateResult: bgformat,
                            templateSelection: bgformat,
                            minimumResultsForSearch: Infinity
                        });
                    } else {
                        $select.select2({
                            theme: 'bootstrap-5',
                            dropdownParent: $select.closest('.modal'),
                            width: '100%',
                            minimumResultsForSearch: 6
                        });
                    }
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
