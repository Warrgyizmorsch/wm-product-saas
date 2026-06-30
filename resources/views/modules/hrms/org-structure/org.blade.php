@extends('layouts.duralux')

@section('title', 'HR SETTINGS | SaaS ERP')
@section('page-title', 'HR Settings')
@section('breadcrumb', 'HRMS / Settings')

@section('page-actions')
    <!-- Tabs Navigation -->
    <div class="col-12">
        <div class="stretch stretch-full mb-0">
            <div class="card-body py-3">
                <ul class="nav gap-2" id="orgTabs" role="tablist">
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
        </div>
    </div>
@endsection

@section('content')
    <style>
        #settingsSubSidebar .nav-link {
            background-color: transparent;
            transition: all 0.2s ease-in-out;
            border-left: 3px solid transparent !important;
            border-radius: 0 6px 6px 0 !important;
        }
        #settingsSubSidebar .nav-link:hover {
            background-color: rgba(0, 0, 0, 0.03);
            color: var(--bs-primary) !important;
        }
        #settingsSubSidebar .nav-link.active {
            background-color: rgba(100, 116, 139, 0.08) !important;
            color: var(--bs-primary) !important;
            border-left-color: var(--bs-primary) !important;
        }

        /* Underlined Horizontal Tabs */
        #orgTabs {
            /* border-bottom: 1px solid #e2e8f0; */
        }
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
    </style>

    <div class="row">
        <!-- Left Subsidebar Column -->
        <div class="col-lg-3 col-md-4 p-0 h-100">
            @include('modules.hrms.partials.settings-sidebar')
        </div>

        <!-- Right Content Column -->
        <div class="col-lg-9 col-md-8">
            <div class="tab-content" id="settingsSubSidebarContent">
                <!-- Org Structure Pane -->
                <div class="tab-pane fade show active" id="org-structure-pane" role="tabpanel" aria-labelledby="org-structure-menu">
                    <div class="row">
                        

                        <!-- Tabs Content -->
                        <div class="col-12">
                            <div class="tab-content" id="orgTabsContent">
                                <!-- Dashboard Tab Pane Commented Out
                                <div class="tab-pane fade" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                                    @include('modules.hrms.org-structure.tabs.dashboard')
                                </div>
                                -->
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
                    <div class="card stretch stretch-full mb-0">
                        <div class="card-body py-5 text-center">
                            <div class="avatar-text avatar-xl bg-soft-primary text-primary mx-auto mb-4" style="width: 60px; height: 60px; min-width: 60px; min-height: 60px;">
                                <i class="feather-dollar-sign fs-24"></i>
                            </div>
                            <h4 class="fw-bold mb-2">Salary Structure Settings</h4>
                            <p class="text-muted mb-0">The salary structure configuration tools and layout will be implemented here in a future update.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                // Ensure Org Structure sub-sidebar menu is active
                const orgMenu = document.getElementById('org-structure-menu');
                if (orgMenu) {
                    orgMenu.click();
                }
                const tabButton = document.getElementById(tabParam + '-tab');
                if (tabButton) {
                    tabButton.click();
                }
            }
        });
    </script>
@endsection