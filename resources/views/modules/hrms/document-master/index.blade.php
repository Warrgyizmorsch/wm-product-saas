@extends('layouts.duralux')

@section('title', 'Document Master | SaaS ERP')
@section('page-title', 'Document Master')
@section('breadcrumb', 'HRMS / Document Master')

@section('page-actions')
    <div id="hdr-btn-add-document" class="d-none d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addDocumentModal" class="fw-bold text-uppercase">
            Add Document Master
        </x-ui.button>
    </div>
    <div id="hdr-btn-add-category" class="d-none d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addCategoryModal" class="fw-bold text-uppercase">
            Add Category
        </x-ui.button>
    </div>
    <div id="hdr-btn-add-template" class="d-none d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addTemplateModal" class="fw-bold text-uppercase">
            Add Document Template
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .tab-pane.is-loading {
            opacity: 0.6;
            pointer-events: none;
            transition: opacity 0.15s ease-in-out;
        }

        .btn-outline-primary {
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
            background-color: transparent !important;
        }
        .btn-outline-primary:hover,
        .btn-outline-primary:focus,
        .btn-outline-primary:active,
        .btn-outline-primary.active,
        .btn-outline-primary.show {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #fff !important;
        }

        /* ── Table Responsive Dropdown Visibility Fix ── */
        .table-responsive {
            position: relative;
        }
        .table-responsive:has(.dropdown-menu.show) {
            overflow: visible !important;
        }

        /* Modern layout container for connected settings sidebar */
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
        }

        @media (max-width: 991.98px) {
            .settings-content-col {
                width: 100%;
                padding: 0 15px;
            }
        }

        /* Tabs styling */
        #docMasterTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 600;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
        }
        #docMasterTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #docMasterTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
        }

        /* Access badge styling */
        .badge-access-yes {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #10b981 !important;
            font-weight: 600;
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .badge-access-no {
            background-color: rgba(239, 68, 68, 0.08) !important;
            color: #ef4444 !important;
            font-weight: 600;
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 4px;
        }

        /* Upload Responsibility Badge */
        .badge-resp-employee {
            background-color: rgba(13, 110, 253, 0.08) !important;
            color: var(--bs-primary) !important;
            font-weight: 600;
        }
        .badge-resp-hr {
            background-color: rgba(245, 158, 11, 0.08) !important;
            color: #f59e0b !important;
            font-weight: 600;
        }
        .badge-resp-both {
            background-color: rgba(139, 92, 246, 0.08) !important;
            color: #8b5cf6 !important;
            font-weight: 600;
        }

        /* Status Badge */
        .badge-status-active {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #10b981 !important;
            font-weight: 600;
        }
        .badge-status-inactive {
            background-color: rgba(100, 116, 139, 0.08) !important;
            color: #64748b !important;
            font-weight: 600;
        }

        /* Expiry configurations layout */
        .expiry-config-section {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 15px;
        }

        /* ── Local Form Style Overrides ── */
        .modal .odoo-form-label {
            width: 160px !important;
        }
        .modal .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-right: 24px !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    @php
        $activeTab = request()->query('active_tab', 'documents');
    @endphp

    <div class="settings-container">
        <div class="settings-content-col erp-single-panel bg-white flex-grow-1 p-4 shadow-sm rounded border-0 text-dark">
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="feather-check-circle me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="feather-alert-triangle me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="feather-alert-triangle me-2"></i> <strong>Validation Errors:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs border-bottom mb-4" id="docMasterTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'documents' ? 'active' : '' }}" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents-pane" type="button" role="tab" aria-controls="documents-pane" aria-selected="{{ $activeTab === 'documents' ? 'true' : 'false' }}">
                        <i class="feather-file-text me-2"></i>Document Masters
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'categories' ? 'active' : '' }}" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-pane" type="button" role="tab" aria-controls="categories-pane" aria-selected="{{ $activeTab === 'categories' ? 'true' : 'false' }}">
                        <i class="feather-sliders me-2"></i>Document Categories
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'templates' ? 'active' : '' }}" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates-pane" type="button" role="tab" aria-controls="templates-pane" aria-selected="{{ $activeTab === 'templates' ? 'true' : 'false' }}">
                        <i class="feather-layout me-2"></i>Document Templates
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="docMasterTabsContent">
                @include('modules.hrms.document-master.tabs.documents')
                @include('modules.hrms.document-master.tabs.categories')
                @include('modules.hrms.document-master.tabs.templates')
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function switchTab(tabId) {
            $('#hdr-btn-add-document').addClass('d-none');
            $('#hdr-btn-add-category').addClass('d-none');
            $('#hdr-btn-add-template').addClass('d-none');

            if (tabId === 'categories') {
                $('#hdr-btn-add-category').removeClass('d-none');
            } else if (tabId === 'templates') {
                $('#hdr-btn-add-template').removeClass('d-none');
            } else {
                $('#hdr-btn-add-document').removeClass('d-none');
            }
        }

        $(document).ready(function() {
            // Append modals to body to fix backdrop/z-index issues
            $('#addCategoryModal').appendTo('body');
            $('#editCategoryModal').appendTo('body');
            $('#addDocumentModal').appendTo('body');
            $('#editDocumentModal').appendTo('body');
            $('#addTemplateModal').appendTo('body');
            $('#editTemplateModal').appendTo('body');
            $('#previewTemplateModal').appendTo('body');

            // Handle bootstrap tab switch shown event
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                var targetId = $(e.target).attr('id');
                var tabName = 'documents';
                if (targetId === 'categories-tab') {
                    tabName = 'categories';
                } else if (targetId === 'templates-tab') {
                    tabName = 'templates';
                }
                
                // Update URL search parameters
                var url = new URL(window.location.href);
                url.searchParams.set('active_tab', tabName);
                window.history.pushState({}, '', url);
                
                switchTab(tabName);
            });

            // Initial switch on page load
            var initialTab = "{{ $activeTab }}";
            switchTab(initialTab);

            // Dynamically initialize select2 within modals
            $(document).on('shown.bs.modal', '.modal', function() {
                var modal = $(this);
                modal.find('select[data-select2-selector]').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                    $(this).select2({
                        theme: "bootstrap-5",
                        dropdownParent: modal.find('.modal-content'),
                        placeholder: $(this).attr('placeholder') || "Select Option",
                        allowClear: false
                    });
                });
            });

            // Toggle Expiry reminder visibility based on checkbox status (Expiry Applicable)
            $(document).on('change', '.expiry-applicable-toggle', function() {
                var isChecked = $(this).is(':checked');
                var modal = $(this).closest('.modal');
                var reminderGroup = modal.find('.reminder-days-group');
                if (isChecked) {
                    reminderGroup.slideDown();
                    reminderGroup.find('input').attr('required', 'required');
                } else {
                    reminderGroup.slideUp();
                    reminderGroup.find('input').removeAttr('required').val('');
                }
            });

            // Handle Category Edit Bindings
            $('#editCategoryModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var categoryId = button.data('category-id');
                var companyId = button.data('company-id');
                var name = button.data('name');
                var description = button.data('description');

                var modal = $(this);
                modal.find('form').attr('action', '/hrms/documents-master/categories/' + categoryId);
                modal.find('#edit_category_company_id').val(companyId).trigger('change');
                modal.find('#edit_category_name').val(name);
                modal.find('#edit_category_description').val(description);
            });

            // Handle Document Edit Bindings
            $('#editDocumentModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var docId = button.data('id');
                var categoryId = button.data('category-id');
                var name = button.data('name');
                var code = button.data('code');
                var description = button.data('description');
                
                var isRequired = button.data('is-required') == 1;
                var uploadResponsibility = button.data('upload-responsibility');
                var approvalRequired = button.data('approval-required') == 1;
                
                var expiryApplicable = button.data('expiry-applicable') == 1;
                var reminderDays = button.data('reminder-days');
                
                var employeeCanView = button.data('employee-can-view') == 1;
                var employeeCanDownload = button.data('employee-can-download') == 1;
                
                var status = button.data('status');

                var modal = $(this);
                modal.find('form').attr('action', '/hrms/documents-master/documents/' + docId);
                
                modal.find('#edit_doc_category_id').val(categoryId).trigger('change');
                modal.find('#edit_doc_name').val(name);
                modal.find('#edit_doc_code').val(code);
                modal.find('#edit_doc_description').val(description);
                
                modal.find('#edit_doc_is_required').prop('checked', isRequired);
                modal.find('#edit_doc_upload_responsibility').val(uploadResponsibility).trigger('change');
                modal.find('#edit_doc_approval_required').prop('checked', approvalRequired);
                
                modal.find('#edit_doc_expiry_applicable').prop('checked', expiryApplicable);
                var reminderGroup = modal.find('.reminder-days-group');
                if (expiryApplicable) {
                    reminderGroup.show();
                    modal.find('#edit_doc_reminder_days_before').val(reminderDays).attr('required', 'required');
                } else {
                    reminderGroup.hide();
                    modal.find('#edit_doc_reminder_days_before').val('').removeAttr('required');
                }
                
                modal.find('#edit_doc_employee_can_view').prop('checked', employeeCanView);
                modal.find('#edit_doc_employee_can_download').prop('checked', employeeCanDownload);
                
                modal.find('#edit_doc_status').val(status).trigger('change');
            });
        });

        var activeRequest = null;
        function refreshDocumentMasterList(url, tabId) {
            if (activeRequest) {
                activeRequest.abort();
            }

            const controller = new AbortController();
            activeRequest = controller;

            const targetIds = {
                'categories': {
                    tbody: 'categoriesTableBody',
                    pagination: 'categoriesPaginationWrapper'
                },
                'documents': {
                    tbody: 'documentsTableBody',
                    pagination: 'documentsPaginationWrapper'
                }
            }[tabId];

            const pane = document.getElementById(tabId + '-pane');
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
                
                if (targetIds) {
                    const newTbody = doc.getElementById(targetIds.tbody);
                    const oldTbody = document.getElementById(targetIds.tbody);
                    const newPagination = doc.getElementById(targetIds.pagination);
                    const oldPagination = document.getElementById(targetIds.pagination);

                    if (newTbody && oldTbody) {
                        oldTbody.innerHTML = newTbody.innerHTML;
                    }
                    if (newPagination && oldPagination) {
                        oldPagination.innerHTML = newPagination.innerHTML;
                    }
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

        // Global sort function
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
                var form = input.closest('form');
                if (form) {
                    $(form).submit();
                }
            }
        }

        $(document).ready(function() {
            // Debounced quick search to avoid needing to press Enter
            var searchTimeout = null;
            $(document).on('input', 'input[name="category_search"], input[name="doc_search"]', function () {
                const input = this;
                const form = input.closest('form');
                if (!form) return;
                
                const tabId = form.querySelector('input[name="active_tab"]').value;
                const url = new URL(form.action || window.location.href);
                
                const formData = new FormData(form);
                for (const [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }

                const pageParam = tabId === 'categories' ? 'category_page' : 'doc_page';
                url.searchParams.delete(pageParam);

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function () {
                    refreshDocumentMasterList(url, tabId);
                }, 250);
            });

            // Intercept GET form submissions (search/filters)
            $(document).on('submit', '#docMasterTabsContent form', function (event) {
                const form = this;
                if (form.method && form.method.toLowerCase() !== 'get') {
                    return;
                }
                event.preventDefault();
                const tabId = form.querySelector('input[name="active_tab"]').value;
                const url = new URL(form.action || window.location.href);
                
                const formData = new FormData(form);
                for (const [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }

                const pageParam = tabId === 'categories' ? 'category_page' : 'doc_page';
                url.searchParams.delete(pageParam);

                refreshDocumentMasterList(url, tabId);
                
                // Close the filter dropdown menu safely
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            });

            // Intercept Sort, Reset, and Pagination links
            $(document).on('click', '#docMasterTabsContent a[href]', function (event) {
                const href = this.getAttribute('href');
                if (!href || href.startsWith('javascript:') || href === '#') return;

                const urlObj = new URL(href, window.location.origin);
                const tabId = urlObj.searchParams.get('active_tab');

                if (tabId !== 'categories' && tabId !== 'documents') return;

                event.preventDefault();
                refreshDocumentMasterList(urlObj, tabId);
            });
        });
    </script>
@endpush

@include('modules.hrms.partials.hrms-settings-helpers')
