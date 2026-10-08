@extends('layouts.duralux')

@section('title', __('hrms.document_master.meta_title'))
@section('page-title', __('hrms.document_master.title'))
@section('breadcrumb', __('hrms.document_master.breadcrumb'))

@section('page-actions')
    <div id="hdr-btn-add-document" class="d-none d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addDocumentModal" class="fw-bold text-uppercase">
            {{ __('hrms.document_master.add_document') }}
        </x-ui.button>
    </div>
    <div id="hdr-btn-add-category" class="d-none d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addCategoryModal" class="fw-bold text-uppercase">
            {{ __('hrms.document_master.add_category') }}
        </x-ui.button>
    </div>
    <div id="hdr-btn-add-template" class="d-none d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addTemplateModal" class="fw-bold text-uppercase">
            {{ __('hrms.document_master.add_template') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/quill.min.css') }}">
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
    <script src="{{ asset('assets/vendors/js/quill.min.js') }}"></script>
@endpush

@section('content')
    @php
        $activeTab = request()->query('active_tab', 'documents');
        $docMasterTabs = [
            [
                'id' => 'documents-pane',
                'label' => __('hrms.document_master.tabs_documents'),
                'active' => $activeTab === 'documents',
                'icon' => 'feather-file-text',
                'badge' => $documents->total(),
            ],
            [
                'id' => 'categories-pane',
                'label' => __('hrms.document_master.tabs_categories'),
                'active' => $activeTab === 'categories',
                'icon' => 'feather-sliders',
                'badge' => $categories->total(),
            ],
            [
                'id' => 'templates-pane',
                'label' => __('hrms.document_master.tabs_templates'),
                'active' => $activeTab === 'templates',
                'icon' => 'feather-layout',
                'badge' => $templates->total(),
            ],
        ];
    @endphp

    <div class="settings-container">
        <div class="settings-content-col erp-single-panel bg-white flex-grow-1 p-4 shadow-sm rounded border-0 text-dark">
            
            @if(session('success'))
                <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-3">
                    {{ session('success') }}
                </x-ui.alert>
            @endif

            @if(session('error'))
                <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-3">
                    {{ session('error') }}
                </x-ui.alert>
            @endif

            @if($errors->any())
                <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-3">
                    <strong>{{ __('hrms.document_master.validation_errors') }}</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <!-- Common Horizontal Tabs Navigation -->
            <x-ui.horizontal-tabs id="docMasterTabs" :tabs="$docMasterTabs" />

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
            $(document).on('shown.bs.tab', 'button[data-bs-toggle="tab"]', function (e) {
                var targetId = $(e.target).attr('id') || '';
                var tabName = 'documents';
                if (targetId.indexOf('categories') !== -1) {
                    tabName = 'categories';
                } else if (targetId.indexOf('templates') !== -1) {
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
                        placeholder: $(this).attr('placeholder') || "{{ __('hrms.document_master.select_option') }}",
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

            // Helper to dynamically toggle Portal Access editable checkbox vs automatic note based on Upload Responsibility
            function updatePortalAccessVisibility($modal, responsibility, employeeCanView) {
                var $editable = $modal.find('.portal-access-editable');
                var $auto = $modal.find('.portal-access-auto');
                var $checkbox = $modal.find('.employee-can-view-checkbox');

                if (responsibility === 'hr') {
                    $auto.hide();
                    $editable.show();
                    if (employeeCanView !== undefined) {
                        $checkbox.prop('checked', employeeCanView);
                    }
                } else {
                    $editable.hide();
                    $auto.show();
                    $checkbox.prop('checked', true);
                }
            }

            // Listen to Upload Responsibility dropdown changes
            $(document).on('change', 'select[name="upload_responsibility"]', function() {
                var val = $(this).val();
                var $modal = $(this).closest('.modal');
                updatePortalAccessVisibility($modal, val);
            });

            // On Add Document modal open, initialize portal access section
            $('#addDocumentModal').on('show.bs.modal', function() {
                var val = $(this).find('select[name="upload_responsibility"]').val() || 'employee';
                updatePortalAccessVisibility($(this), val, true);
            });

            // Handle Category Edit Bindings
            $('#editCategoryModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var categoryId = button.data('category-id');
                var companyId = button.data('company-id');
                var name = button.data('name');
                var description = button.data('description');

                var modal = $(this);
                modal.find('form').attr('action', '{{ url("hrms/documents-master/categories") }}/' + categoryId);
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
                var requiresSignature = button.data('requires-signature') == 1;
                
                var expiryApplicable = button.data('expiry-applicable') == 1;
                var reminderDays = button.data('reminder-days');
                
                var employeeCanView = button.data('employee-can-view') == 1;
                
                var status = button.data('status');

                var modal = $(this);
                modal.find('form').attr('action', '{{ url("hrms/documents-master/documents") }}/' + docId);
                
                modal.find('#edit_doc_category_id').val(categoryId).trigger('change');
                modal.find('#edit_doc_name').val(name);
                modal.find('#edit_doc_code').val(code);
                modal.find('#edit_doc_description').val(description);
                
                modal.find('#edit_doc_is_required').prop('checked', isRequired);
                modal.find('#edit_doc_upload_responsibility').val(uploadResponsibility).trigger('change');
                updatePortalAccessVisibility(modal, uploadResponsibility, employeeCanView);

                modal.find('#edit_doc_approval_required').prop('checked', approvalRequired);
                modal.find('#edit_doc_requires_signature').prop('checked', requiresSignature);
                
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
                },
                'templates': {
                    tbody: 'templatesTableBody',
                    pagination: 'templatesPaginationWrapper'
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
            $(document).on('input', 'input[name="category_search"], input[name="doc_search"], input[name="template_search"]', function () {
                const input = this;
                const form = input.closest('form');
                if (!form) return;
                
                const tabId = form.querySelector('input[name="active_tab"]').value;
                const url = new URL(form.action || window.location.href);
                
                const formData = new FormData(form);
                for (const [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }

                const pageParam = tabId === 'categories' ? 'category_page' : (tabId === 'templates' ? 'template_page' : 'doc_page');
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

                const pageParam = tabId === 'categories' ? 'category_page' : (tabId === 'templates' ? 'template_page' : 'doc_page');
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

                if (tabId !== 'categories' && tabId !== 'documents' && tabId !== 'templates') return;

                event.preventDefault();
                refreshDocumentMasterList(urlObj, tabId);
            });
        });
    </script>
@endpush

@include('modules.hrms.partials.hrms-settings-helpers')
