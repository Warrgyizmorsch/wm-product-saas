@extends('layouts.duralux')

@section('title', __('hrms.sidebar.asset_management') . ' | SaaS ERP')
@section('page-title', __('hrms.sidebar.asset_management'))
@section('breadcrumb', 'HRMS / ' . __('hrms.sidebar.asset_management'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <!-- Universal Import/Export Dropdown (Consistent across tabs) -->
        <x-ui.import-export-dropdown 
            type="asset" 
            :exportRoute="route('hrms.assets.export')" 
            :downloadTemplateRoute="route('hrms.assets.import.template')" 
            importModalTarget="#importAssetModal" 
        />
        
        <!-- Tab-specific Add Action Buttons -->
        <x-ui.button 
            id="hdr-btn-add-item" 
            variant="primary" 
            icon="feather-plus" 
            data-bs-toggle="modal" 
            data-bs-target="#addAssetModal" 
            class="fw-bold text-uppercase {{ request('tab') === 'categories-pane' ? 'd-none' : '' }}"
        >
            {{ __('hrms.assets.add_item') }}
        </x-ui.button>
        <x-ui.button 
            id="hdr-btn-add-category" 
            variant="primary" 
            icon="feather-plus" 
            data-bs-toggle="modal" 
            data-bs-target="#addCategoryModal" 
            class="fw-bold text-uppercase {{ request('tab') === 'categories-pane' ? '' : 'd-none' }}"
        >
            {{ __('hrms.assets.add_category') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
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

        .badge-available {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #10b981 !important;
            font-weight: 600;
        }
        .badge-allocated {
            background-color: rgba(13, 110, 253, 0.08) !important;
            color: var(--bs-primary) !important;
            font-weight: 600;
        }
        .badge-maintenance {
            background-color: rgba(245, 158, 11, 0.08) !important;
            color: #f59e0b !important;
            font-weight: 600;
        }
        .badge-scrapped {
            background-color: rgba(100, 116, 139, 0.08) !important;
            color: #64748b !important;
            font-weight: 600;
        }
        .badge-new {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #10b981 !important;
            font-weight: 600;
        }
        .badge-good {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #10b981 !important;
            font-weight: 600;
        }
        .badge-fair {
            background-color: rgba(245, 158, 11, 0.08) !important;
            color: #f59e0b !important;
            font-weight: 600;
        }
        .badge-damaged {
            background-color: rgba(239, 68, 68, 0.08) !important;
            color: #ef4444 !important;
            font-weight: 600;
        }
        #registry-pane .table-responsive {
            min-height: 350px;
        }
        .table-responsive {
            overflow-x: auto !important;
        }

        /* Common UI Elements: Modal & Form Styles */
        .modal .modal-content {
            border: 0 !important;
            border-radius: 12px !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
            overflow: hidden !important;
            background-color: #ffffff !important;
        }
        .modal .modal-header {
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 14px 20px !important;
            background-color: #ffffff !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .modal .modal-header .modal-title {
            font-size: 15px !important;
            font-weight: 700 !important;
            color: #1e293b !important;
            margin: 0 !important;
            display: flex !important;
            align-items: center !important;
        }
        .modal .modal-body {
            padding: 20px !important;
        }
        .modal .modal-footer {
            border-top: 1px solid #e2e8f0 !important;
            padding: 12px 20px !important;
            background-color: #f8fafc !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 8px !important;
        }
        .modal .modal-footer .btn {
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            padding: 8px 18px !important;
            border-radius: 6px !important;
        }

        /* Modal Select2 Styling to match Common UI system */
        .modal .select2-container--bootstrap-5 {
            width: 100% !important;
        }
        .modal .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            background-color: #ffffff !important;
            padding: 6px 12px !important;
            font-size: 13px !important;
            color: #1e293b !important;
            display: flex !important;
            align-items: center !important;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .modal .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        .modal .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--bs-primary) 15%, transparent) !important;
        }
        .modal .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-left: 0 !important;
            font-size: 13px !important;
            color: #1e293b !important;
            line-height: normal !important;
            display: flex !important;
            align-items: center !important;
        }
        .modal .select2-container--bootstrap-5 .select2-dropdown {
            border-color: #cbd5e1 !important;
            border-radius: 6px !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
            z-index: 9999 !important;
            overflow: hidden !important;
            background-color: #ffffff !important;
        }
        .modal .select2-container--bootstrap-5 .select2-results__option {
            font-size: 13px !important;
            padding: 8px 12px !important;
            color: #1e293b !important;
        }
        .modal .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }

        /* Serialized Units Table Inputs & Condition Selects */
        .modal table .form-control-sm {
            height: 32px !important;
            font-size: 12px !important;
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            color: #1e293b !important;
            transition: all 0.15s ease-in-out;
        }
        .modal table .form-control-sm:focus {
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--bs-primary) 15%, transparent) !important;
            background-color: #ffffff !important;
        }
        .modal table .select2-container--bootstrap-5 {
            width: 100% !important;
            min-width: 130px !important;
        }
        .modal table .select2-container--bootstrap-5 .select2-selection {
            min-height: 32px !important;
            height: 32px !important;
            padding: 2px 8px !important;
            font-size: 12px !important;
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff !important;
        }
        .modal table .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            font-size: 12px !important;
            line-height: normal !important;
        }
        .modal table .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered .hstack {
            font-size: 12px !important;
        }
        .modal .select2-results__option .hstack {
            font-size: 12px !important;
            display: flex !important;
            align-items: center !important;
        }
        .wd-7, .modal .wd-7, .select2-dropdown .wd-7 {
            width: 8px !important;
            min-width: 8px !important;
        }
        .ht-7, .modal .ht-7, .select2-dropdown .ht-7 {
            height: 8px !important;
            min-height: 8px !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="settings-container">
        <!-- Right Content Column -->
        <div class="settings-content-col erp-single-panel bg-white flex-grow-1 p-4 shadow-sm rounded border-0 text-dark">

            <!-- Tabs Navigation -->
            <x-ui.horizontal-tabs 
                id="assetModuleTabs" 
                :syncUrl="true"
                syncParam="tab"
                :tabs="[
                    [
                        'id' => 'items-pane',
                        'label' => __('hrms.assets.tab_items'),
                        'icon' => 'feather-box',
                        'active' => request('tab', 'items-pane') === 'items-pane',
                    ],
                    [
                        'id' => 'categories-pane',
                        'label' => __('hrms.assets.tab_categories'),
                        'icon' => 'feather-sliders',
                        'active' => request('tab') === 'categories-pane',
                    ]
                ]" 
            />

            <div class="tab-content" id="assetModuleTabsContent">
                @include('modules.hrms.assets.tabs.items')
                @include('modules.hrms.assets.tabs.categories')
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const allEmployees = {!! json_encode($employees->map(function($e) {
            return [
                'id' => $e->id,
                'company_id' => $e->company_id,
                'display_name' => $e->display_name,
                'employee_id' => $e->employee_id
            ];
        })) !!};

        const allAvailableAssets = {!! json_encode($availableAssets->map(function($a) {
            return [
                'id' => $a->id,
                'name' => $a->name . ' (' . $a->asset_code . ')',
                'asset_code' => $a->asset_code,
                'serial_number' => $a->serial_number,
                'category_id' => $a->asset_category_id,
                'asset_item_id' => $a->asset_item_id,
                'company_id' => $a->company_id,
                'status' => $a->status ?? 'available'
            ];
        })) !!};

        const langAssets = {
            requestedAssetNotAvail: "{{ __('hrms.assets.requested_asset_not_avail') }}",
            autoMatched: "{{ __('hrms.assets.auto_matched') }}",
            noAvailAssetsInCat: "{{ __('hrms.assets.no_avail_assets_in_cat', ['category' => ':category']) }}",
            selectEmployee: "{{ __('hrms.assets.select_employee') }}",
            condGood: "{{ __('hrms.assets.cond_good') }}",
            condNew: "{{ __('hrms.assets.cond_new') }}",
            condFair: "{{ __('hrms.assets.cond_fair') }}",
            condDamaged: "{{ __('hrms.assets.cond_damaged') }}",
            condScrapped: "{{ __('hrms.assets.cond_scrapped') }}",
            alertMinOneUnit: "{{ __('hrms.assets.alert_min_one_unit') }}",
            alertEnterCodePrefix: "{{ __('hrms.assets.alert_enter_code_prefix') }}",
            alertEnterCountRange: "{{ __('hrms.assets.alert_enter_count_range') }}",
            alertEnterValidCount: "{{ __('hrms.assets.alert_enter_valid_count') }}",
            alertSelectEmployeeFirst: "{{ __('hrms.assets.alert_select_employee_first') }}",
            alertNoActiveAllocations: "{{ __('hrms.assets.alert_no_active_allocations') }}",
            alertSelectUnitToReturn: "{{ __('hrms.assets.alert_select_unit_to_return') }}",
            alertSelectUnitToFulfill: "{{ __('hrms.assets.alert_select_unit_to_fulfill') }}",
            alertMaxUnitsExceeded: "{{ __('hrms.assets.alert_max_units_exceeded', ['qty' => ':qty']) }}",
            assetCodeRequired: "{{ __('hrms.assets.asset_code_required') }}",
            serialNumberRequired: "{{ __('hrms.assets.serial_number_required') }}",
            noAllocationLogs: "{{ __('hrms.assets.no_allocation_logs') }}",
            inPossession: "{{ __('hrms.assets.in_possession') }}",
            noAllocationHistoryUnits: "{{ __('hrms.assets.no_allocation_history_units') }}",
            unit: "{{ __('hrms.assets.unit') }}",
            units: "{{ __('hrms.assets.units') }}",
            events: "{{ __('hrms.assets.events') }}",
            noAvailableUnitsItem: "{{ __('hrms.assets.no_available_units_item') }}",
            noAvailableUnitsFoundFor: "{{ __('hrms.assets.no_available_units_found_for', ['item' => ':item']) }}",
            selectUpToUnits: "{{ __('hrms.assets.select_up_to_units', ['qty' => ':qty']) }}",
            noSerial: "{{ __('hrms.assets.no_serial') }}",
            req: "{{ __('hrms.assets.req') }}",
            rem: "{{ __('hrms.assets.rem') }}",
            allocatingQty: "{{ __('hrms.assets.allocating_qty') }}",
            noSerializedUnitsLinked: "{{ __('hrms.assets.no_serialized_units_linked') }}",
            noSpecificReasonProvided: "{{ __('hrms.assets.no_specific_reason_provided') }}",
            fullDescription: "{{ __('hrms.assets.full_description') }}",
            description: "{{ __('hrms.assets.description') }}",
            confirmDirectAllocate: "{{ __('hrms.assets.confirm_direct_allocate', ['asset' => ':asset', 'employee' => ':employee']) }}",
            confirmTitleAllocate: "{{ __('hrms.assets.confirm_allocation') }}",
            btnAllocate: "{{ __('hrms.assets.btn_allocate') }}",
            statusPending: "{{ __('hrms.assets.status_pending') }}",
            statusPartiallyAllocated: "{{ __('hrms.assets.status_partially_allocated') }}",
            statusAllocated: "{{ __('hrms.assets.status_allocated') }}",
            statusRejected: "{{ __('hrms.assets.status_rejected') }}"
        };

        $(document).ready(function() {
            function syncHeaderAddButton() {
                var isCategory = $('#categories-pane-tab').hasClass('active') || 
                                 $('#categories-pane').hasClass('active') ||
                                 $('#assetModuleTabs button[data-bs-target="#categories-pane"]').hasClass('active') ||
                                 $('#assetModuleTabs button.active').attr('data-bs-target') === '#categories-pane';

                if (isCategory) {
                    $('#hdr-btn-add-item').addClass('d-none');
                    $('#hdr-btn-add-category').removeClass('d-none');
                } else {
                    $('#hdr-btn-add-item').removeClass('d-none');
                    $('#hdr-btn-add-category').addClass('d-none');
                }
            }

            // Initial sync on load
            syncHeaderAddButton();
            setTimeout(syncHeaderAddButton, 50);

            // Tab switching sync for header action buttons
            $(document).on('shown.bs.tab', '#assetModuleTabs button, [data-bs-toggle="tab"]', function() {
                syncHeaderAddButton();
            });
            $(document).on('click', '#assetModuleTabs button', function() {
                setTimeout(syncHeaderAddButton, 30);
            });
            window.addEventListener('popstate', function() {
                setTimeout(syncHeaderAddButton, 30);
            });

            // Ensure bgformat and userformat are globally available for Common UI status Select2 dropdowns
            if (typeof window.bgformat !== 'function') {
                window.bgformat = function(state) {
                    if (!state.id) return (state && state.text) ? state.text : '';
                    var el = state.element;
                    var bgClass = $(el).data('bg') || 'bg-primary';
                    return $('<span class="hstack gap-2 align-items-center"><span class="wd-7 ht-7 rounded-circle ' + bgClass + '" style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0;"></span><span>' + (state.text || '') + '</span></span>');
                };
            }

            if (typeof window.userformat !== 'function') {
                window.userformat = function(user) {
                    if (!user || !user.id) return (user && user.text) ? user.text : '';
                    var el = user.element;
                    var avatar = $(el).data('avatar');
                    if (!avatar) {
                        var u = $(el).data('user');
                        avatar = u ? ('/assets/images/avatar/' + u + '.png') : '/assets/images/avatar/default.png';
                    }
                    return $('<span class="hstack gap-2 align-items-center"><img src="' + avatar + '" class="avatar-image avatar-sm object-fit-cover rounded-circle flex-shrink-0" style="width:22px;height:22px;border-radius:50%;" onerror="this.onerror=null;this.src=\'/assets/images/avatar/default.png\';" /><span>' + (user.text || '') + '</span></span>');
                };
            }

            // Helper to generate Common UI status select markup for condition fields
            function getConditionSelectMarkup(name, selectedCondition = 'good') {
                return `<select name="${name}" class="form-select form-select-sm fs-12 unit-condition-select" data-select2-selector="status" required>
                    <option value="good" data-bg="bg-success" ${selectedCondition === 'good' ? 'selected' : ''}>${langAssets.condGood || 'Good'}</option>
                    <option value="new" data-bg="bg-primary" ${selectedCondition === 'new' ? 'selected' : ''}>${langAssets.condNew || 'New'}</option>
                    <option value="fair" data-bg="bg-warning" ${selectedCondition === 'fair' ? 'selected' : ''}>${langAssets.condFair || 'Fair'}</option>
                    <option value="damaged" data-bg="bg-danger" ${selectedCondition === 'damaged' ? 'selected' : ''}>${langAssets.condDamaged || 'Damaged'}</option>
                    <option value="scrapped" data-bg="bg-secondary" ${selectedCondition === 'scrapped' ? 'selected' : ''}>${langAssets.condScrapped || 'Scrapped'}</option>
                </select>`;
            }

            // Dedicated Select2 Initializer with modal parent binding & status dot formatting
            function initSelect2Element($select, parent) {
                if (!$select || !$select.length) return;
                
                var $modal = $select.closest('.modal');
                if (!parent) {
                    var modalContent = $modal.find('.modal-content');
                    parent = modalContent.length ? modalContent : ($modal.length ? $modal : $(document.body));
                }

                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }

                var placeholder = $select.attr('placeholder') || $select.find('option[value=""]').first().text() || "Select Option";
                var selectorType = $select.attr('data-select2-selector') || $select.attr('select2-selector') || ($select.hasClass('unit-condition-select') ? 'status' : 'default');
                var fieldName = $select.attr('name') || '';

                var options = {
                    theme: 'bootstrap-5',
                    dropdownParent: parent,
                    width: '100%',
                    placeholder: placeholder,
                    allowClear: !$select.prop('required')
                };

                if (selectorType === 'status' || $select.hasClass('unit-condition-select') || fieldName === 'condition' || fieldName === 'return_condition' || fieldName.indexOf('[condition]') !== -1) {
                    options.templateResult = bgformat;
                    options.templateSelection = bgformat;
                    options.minimumResultsForSearch = Infinity;
                } else if (selectorType === 'user' && typeof userformat === 'function') {
                    options.templateResult = userformat;
                    options.templateSelection = userformat;
                }

                $select.select2(options);
            }

            function initModalSelects(modal) {
                var $modal = $(modal);
                var modalContent = $modal.find('.modal-content');
                var parent = modalContent.length ? modalContent : $modal;

                $modal.find('select').each(function() {
                    initSelect2Element($(this), parent);
                });
            }

            // Append modals to body root to prevent Bootstrap backdrop overlay issues inside settings flex container
            $('#addAssetModal').appendTo('body');

            $('#addAssetModal').on('show.bs.modal', function() {
                var modal = $(this);
                var modalContent = modal.find('.modal-content');
                // Clear inputs
                modal.find('input[name="name"]').val('');
                modal.find('textarea[name="description"]').val('');
                modal.find('input[name="brand"]').val('');
                modal.find('input[name="model_number"]').val('');
                modal.find('input[name="purchase_date"]').val('');
                modal.find('input[name="purchase_cost"]').val('');
                modal.find('textarea[name="notes"]').val('');
                
                let catSelect = modal.find('select[name="asset_category_id"]');
                if (catSelect.length) {
                    catSelect.val('').trigger('change');
                }
                
                // Reset units table to only have one empty row
                let tbody = $('#bulk-units-tbody');
                tbody.empty();
                unitRowIndex = 1; // start index from 1 for dynamically added rows
                let rowHtml = `
                    <tr>
                        <td class="py-2 px-3 text-start">
                            <input type="text" name="units[0][asset_code]" class="form-control form-control-sm text-center fs-12 fw-semibold" placeholder="e.g. AST-001" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;" required>
                        </td>
                        <td class="py-2">
                            <input type="text" name="units[0][serial_number]" class="form-control form-control-sm text-center fs-12" placeholder="e.g. SN-XXXX" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;" required>
                        </td>
                        <td class="py-2" style="min-width: 140px;">
                            ${getConditionSelectMarkup('units[0][condition]', 'good')}
                        </td>
                        <td class="py-2 text-end px-3">
                            <div class="d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-icon btn-soft-danger btn-remove-unit-row" style="width: 32px; height: 32px; border-radius: 6px;" disabled><i class="feather-trash-2"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
                let $row = $(rowHtml);
                tbody.append($row);
                initSelect2Element($row.find('select'), modalContent);
                toggleRemoveButtons();
            });
            $('#editAssetModal').appendTo('body');
            $('#allocateAssetModal').appendTo('body');
            $('#addCategoryModal').appendTo('body');
            $('#editCategoryModal').appendTo('body');
            $('#addAssetItemModal').appendTo('body');
            $('#editAssetItemModal').appendTo('body');
            $('#returnAssetModal').appendTo('body');
            $('#assetHistoryModal').appendTo('body');
            $('#rejectRequestModal').appendTo('body');
            $('#importAssetModal').appendTo('body');
            $('#importCategoryModal').appendTo('body');

            // Dynamically append search/filter parameters to export links on click
            $(document).on('click', '#btn-export-assets-link', function(e) {
                var search = $('#registry-pane input[name="registry_search"]').val() || '';
                var category = $('#registry-pane select[name="registry_category_id"]').val() || '';
                var status = $('#registry-pane select[name="registry_status"]').val() || '';
                var condition = $('#registry-pane select[name="registry_condition"]').val() || '';
                var sort = $('#registry_sort').val() || '';
                
                var url = '{{ route("hrms.assets.export") }}?registry_search=' + encodeURIComponent(search) +
                          '&registry_category_id=' + encodeURIComponent(category) +
                          '&registry_status=' + encodeURIComponent(status) +
                          '&registry_condition=' + encodeURIComponent(condition) +
                          '&registry_sort=' + encodeURIComponent(sort);
                $(this).attr('href', url);
            });

            // Handle edit category details binding
            $('#editCategoryModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var categoryId = button.data('category-id');
                var companyId = button.data('company-id');
                var name = button.data('name');
                var description = button.data('description');
                var isProductionMachinery = button.data('is-production-machinery') == '1';

                var modal = $(this);
                // Set form action URL dynamically
                modal.find('form').attr('action', '/hrms/assets/category/update/' + categoryId);

                // Bind values
                modal.find('#edit_category_company_id').val(companyId).trigger('change');
                modal.find('#edit_category_name').val(name);
                modal.find('#edit_category_description').val(description);
                modal.find('#edit_category_is_production_machinery').prop('checked', isProductionMachinery);
            });

            // Handle edit item details binding
            $('#editAssetItemModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var categoryId = button.data('category');
                var name = button.data('name');
                var description = button.data('description');
                var brand = button.data('brand');
                var modelNumber = button.data('model-number');
                var purchaseDate = button.data('purchase-date');
                var purchaseCost = button.data('purchase-cost');
                var notes = button.data('notes');
                var encodedUnits = button.data('units');

                var modal = $(this);
                var modalContent = modal.find('.modal-content');
                modal.find('form').attr('action', '/hrms/assets/item/update/' + id);

                modal.find('#edit_item_category_id').val(categoryId).trigger('change');
                modal.find('#edit_item_name').val(name);
                modal.find('#edit_item_description').val(description);
                modal.find('#edit_item_brand').val(brand);
                modal.find('#edit_item_model_number').val(modelNumber);
                modal.find('#edit_item_purchase_date').val(purchaseDate);
                modal.find('#edit_item_purchase_cost').val(purchaseCost);
                modal.find('#edit_item_notes').val(notes);

                var tbody = modal.find('#edit-item-bulk-units-tbody');
                tbody.empty();

                if (encodedUnits) {
                    var units = JSON.parse(atob(encodedUnits));
                    if (units && units.length > 0) {
                        units.forEach(function(unit, index) {
                            var rowHtml = `<tr>
                                <td class="py-2 px-3 text-start">
                                    <input type="hidden" name="units[${index}][id]" value="${unit.id}">
                                    <input type="text" name="units[${index}][asset_code]" class="form-control form-control-sm text-center fs-12 fw-semibold" placeholder="e.g. AST-001" required value="${unit.asset_code}" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;">
                                </td>
                                <td class="py-2">
                                    <input type="text" name="units[${index}][serial_number]" class="form-control form-control-sm text-center fs-12" placeholder="e.g. SN123456" value="${unit.serial_number || ''}" required style="border-radius: 6px; height: 32px; border-color: #cbd5e1;">
                                </td>
                                <td class="py-2" style="min-width: 140px;">
                                    ${getConditionSelectMarkup(`units[${index}][condition]`, unit.condition || 'good')}
                                </td>
                                <td class="py-2 text-end px-3">
                                    <button type="button" class="btn btn-sm btn-icon btn-light text-danger btn-remove-edit-unit-row" style="width: 32px; height: 32px; border-radius: 6px;"><i class="feather-trash-2"></i></button>
                                </td>
                            </tr>`;
                            var $row = $(rowHtml);
                            tbody.append($row);
                            initSelect2Element($row.find('select'), modalContent);
                        });
                    }
                }

                if (tbody.children().length === 0) {
                    var rowHtml = `<tr>
                        <td class="py-2 px-3 text-start">
                            <input type="text" name="units[0][asset_code]" class="form-control form-control-sm text-center fs-12 fw-semibold" placeholder="e.g. AST-001" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;" required>
                        </td>
                        <td class="py-2">
                            <input type="text" name="units[0][serial_number]" class="form-control form-control-sm text-center fs-12" placeholder="e.g. SN123456" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;" required>
                        </td>
                        <td class="py-2" style="min-width: 140px;">
                            ${getConditionSelectMarkup('units[0][condition]', 'good')}
                        </td>
                        <td class="py-2 text-end px-3">
                            <button type="button" class="btn btn-sm btn-icon btn-light text-danger btn-remove-edit-unit-row" style="width: 32px; height: 32px; border-radius: 6px;"><i class="feather-trash-2"></i></button>
                        </td>
                    </tr>`;
                    var $row = $(rowHtml);
                    tbody.append($row);
                    initSelect2Element($row.find('select'), modalContent);
                }
            });

            $(document).on('show.bs.modal', '.modal', function() {
                initModalSelects(this);
            });

            $(document).on('shown.bs.modal', '.modal', function() {
                initModalSelects(this);
            });

            // Add unit row in Edit Modal
            $(document).on('click', '#edit-item-btn-add-unit-row', function() {
                var tbody = $('#edit-item-bulk-units-tbody');
                var index = tbody.children().length;
                var rowHtml = `<tr>
                    <td class="py-2 px-3 text-start">
                        <input type="text" name="units[${index}][asset_code]" class="form-control form-control-sm text-center fs-12 fw-semibold" placeholder="e.g. AST-001" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;" required>
                    </td>
                    <td class="py-2">
                        <input type="text" name="units[${index}][serial_number]" class="form-control form-control-sm text-center fs-12" placeholder="e.g. SN123456" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;" required>
                    </td>
                    <td class="py-2" style="min-width: 140px;">
                        ${getConditionSelectMarkup(`units[${index}][condition]`, 'good')}
                    </td>
                    <td class="py-2 text-end px-3">
                        <button type="button" class="btn btn-sm btn-icon btn-light text-danger btn-remove-edit-unit-row" style="width: 32px; height: 32px; border-radius: 6px;"><i class="feather-trash-2"></i></button>
                    </td>
                </tr>`;
                var $row = $(rowHtml);
                tbody.append($row);
                var modalContent = $('#editAssetItemModal').find('.modal-content');
                initSelect2Element($row.find('select'), modalContent);
            });

            // Remove unit row in Edit Modal
            $(document).on('click', '.btn-remove-edit-unit-row', function() {
                var tbody = $('#edit-item-bulk-units-tbody');
                if (tbody.children().length > 1) {
                    $(this).closest('tr').remove();
                    tbody.children().each(function(index, row) {
                        $(row).find('input[name*="units["], select[name*="units["]').each(function() {
                            var name = $(this).attr('name');
                            var updatedName = name.replace(/units\[\d+\]/, 'units[' + index + ']');
                            $(this).attr('name', updatedName);
                        });
                    });
                } else {
                    alert(langAssets.alertMinOneUnit);
                }
            });

            // Generate units in Edit Modal
            $(document).on('click', '#edit-item-btn-generate-units', function() {
                var prefix = $('#edit_item_gen_prefix').val().trim();
                var count = parseInt($('#edit_item_gen_count').val());
                if (!prefix) {
                    alert(langAssets.alertEnterCodePrefix);
                    return;
                }
                if (isNaN(count) || count < 1 || count > 50) {
                    alert(langAssets.alertEnterCountRange);
                    return;
                }

                var tbody = $('#edit-item-bulk-units-tbody');
                if (tbody.children().length === 1 && !tbody.find('input[name$="[asset_code]"]').val().trim()) {
                    tbody.empty();
                }

                var startIndex = tbody.children().length;
                var currentNumber = startIndex + 1;
                var modalContent = $('#editAssetItemModal').find('.modal-content');

                for (var i = 0; i < count; i++) {
                    var finalIndex = startIndex + i;
                    var code = prefix + String(currentNumber).padStart(3, '0');
                    var rowHtml = `<tr>
                        <td class="py-2 px-3 text-start">
                            <input type="text" name="units[${finalIndex}][asset_code]" class="form-control form-control-sm text-center fs-12 fw-semibold" placeholder="e.g. AST-001" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;" required value="${code}">
                        </td>
                        <td class="py-2">
                            <input type="text" name="units[${finalIndex}][serial_number]" class="form-control form-control-sm text-center fs-12" placeholder="e.g. SN123456" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;" required>
                        </td>
                        <td class="py-2" style="min-width: 140px;">
                            ${getConditionSelectMarkup(`units[${finalIndex}][condition]`, 'good')}
                        </td>
                        <td class="py-2 text-end px-3">
                            <button type="button" class="btn btn-sm btn-icon btn-light text-danger btn-remove-edit-unit-row" style="width: 32px; height: 32px; border-radius: 6px;"><i class="feather-trash-2"></i></button>
                        </td>
                    </tr>`;
                    var $row = $(rowHtml);
                    tbody.append($row);
                    initSelect2Element($row.find('select'), modalContent);
                    currentNumber++;
                }
            });

            // Handle edit asset details binding
            $('#editAssetModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var assetId = button.data('asset-id');
                var itemId = button.data('asset-item-id');
                var assetCode = button.data('asset-code');
                var name = button.data('name');
                var brand = button.data('brand');
                var modelNumber = button.data('model-number');
                var serialNumber = button.data('serial-number');
                var purchaseDate = button.data('purchase-date');
                var purchaseCost = button.data('purchase-cost');
                var condition = button.data('condition');
                var notes = button.data('notes');

                var modal = $(this);
                // Set form action URL dynamically
                modal.find('form').attr('action', '/hrms/assets/update/' + assetId);

                // Bind values
                modal.find('#edit_asset_item_id').val(itemId).trigger('change');
                modal.find('#edit_asset_code').val(assetCode);
                modal.find('#edit_name').val(name);
                modal.find('#edit_brand').val(brand);
                modal.find('#edit_model_number').val(modelNumber);
                modal.find('#edit_serial_number').val(serialNumber);
                modal.find('#edit_purchase_date').val(purchaseDate);
                modal.find('#edit_purchase_cost').val(purchaseCost);
                modal.find('#edit_condition').val(condition).trigger('change');
                modal.find('#edit_notes').val(notes);
            });

            // Dynamic batch logging logic for Serialized Units table
            let unitRowIndex = 1;

            $('#btn-add-unit-row').on('click', function() {
                addUnitRow('', '');
            });

            function addUnitRow(code = '', serial = '', condition = 'good') {
                let tbody = $('#bulk-units-tbody');
                let rowHtml = `
                    <tr>
                        <td class="py-2 px-3 text-start">
                            <input type="text" name="units[${unitRowIndex}][asset_code]" class="form-control form-control-sm text-center fs-12 fw-semibold" placeholder="e.g. AST-001" value="${code}" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;" required>
                        </td>
                        <td class="py-2">
                            <input type="text" name="units[${unitRowIndex}][serial_number]" class="form-control form-control-sm text-center fs-12" placeholder="e.g. SN-XXXX" value="${serial}" style="border-radius: 6px; height: 32px; border-color: #cbd5e1;" required>
                        </td>
                        <td class="py-2" style="min-width: 140px;">
                            ${getConditionSelectMarkup(`units[${unitRowIndex}][condition]`, condition)}
                        </td>
                        <td class="py-2 text-end px-3">
                            <div class="d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-icon btn-soft-danger btn-remove-unit-row" style="width: 32px; height: 32px; border-radius: 6px;"><i class="feather-trash-2"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
                let $row = $(rowHtml);
                tbody.append($row);
                let modalContent = $('#addAssetModal').find('.modal-content');
                initSelect2Element($row.find('select'), modalContent);
                unitRowIndex++;
                toggleRemoveButtons();
            }

            $(document).on('click', '.btn-remove-unit-row', function() {
                $(this).closest('tr').remove();
                toggleRemoveButtons();
            });

            function toggleRemoveButtons() {
                let rows = $('#bulk-units-tbody tr');
                if (rows.length <= 1) {
                    rows.find('.btn-remove-unit-row').prop('disabled', true);
                } else {
                    rows.find('.btn-remove-unit-row').prop('disabled', false);
                }
            }

            // Sequential Code Generator
            $('#btn-generate-units').on('click', function() {
                let prefix = $('#gen_prefix').val().trim();
                let count = parseInt($('#gen_count').val());

                if (!prefix) {
                    alert(langAssets.alertEnterCodePrefix);
                    return;
                }
                if (isNaN(count) || count < 1) {
                    alert(langAssets.alertEnterValidCount);
                    return;
                }

                let tbody = $('#bulk-units-tbody');
                // Clear initial row if it is empty
                let firstRow = tbody.find('tr').first();
                let firstCode = firstRow.find('input[type="text"]').first().val();
                if (tbody.find('tr').length === 1 && !firstCode) {
                    tbody.empty();
                }

                for (let i = 1; i <= count; i++) {
                    let sequentialCode = prefix + String(i).padStart(3, '0');
                    addUnitRow(sequentialCode, '');
                }

                $('#gen_prefix').val('');
                $('#gen_count').val('');
            });

            // Handle return modal details binding
            $('#returnAssetModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var itemId = button.data('item-id');
                var itemName = button.data('item-name');
                var rawAllocations = button.data('allocations');
                var rawAllocatedAssets = button.data('allocated-assets');

                var modal = $(this);
                modal.find('form').attr('action', '/hrms/assets/item/' + itemId + '/return');
                modal.find('#return_asset_name_display').val(itemName);

                var employeeSelect = modal.find('#return_employee_select');
                employeeSelect.empty();
                employeeSelect.append(new Option(langAssets.selectEmployee || 'Select Employee', ''));

                var allocations = [];
                if (rawAllocations) {
                    allocations = JSON.parse(atob(rawAllocations));
                }

                var allocatedAssets = [];
                if (rawAllocatedAssets) {
                    allocatedAssets = JSON.parse(atob(rawAllocatedAssets));
                }

                allocations.forEach(function(alloc) {
                    var label = alloc.employee_name + ' (Holds ' + alloc.count + ' unit(s))';
                    var option = new Option(label, alloc.employee_id);
                    employeeSelect.append(option);
                });

                var checklistDiv = modal.find('#return_assets_checklist');
                checklistDiv.html('<span class="text-muted fs-12">' + (langAssets.alertSelectEmployeeFirst || 'Please select an employee first.') + '</span>');

                employeeSelect.off('change').on('change', function() {
                    var employeeId = $(this).val();
                    checklistDiv.empty();

                    if (!employeeId) {
                        checklistDiv.html('<span class="text-muted fs-12">' + (langAssets.alertSelectEmployeeFirst || 'Please select an employee first.') + '</span>');
                        return;
                    }

                    var empAssets = allocatedAssets.filter(function(asset) {
                        return String(asset.assigned_employee_id) === String(employeeId);
                    });

                    if (empAssets.length === 0) {
                        checklistDiv.html('<span class="text-danger fs-12"><i class="feather-alert-triangle me-1"></i>' + (langAssets.alertNoActiveAllocations || 'No active allocations found.') + '</span>');
                    } else {
                        empAssets.forEach(function(asset) {
                            var checkboxId = 'return_asset_check_' + asset.id;
                            var itemHtml = `
                                <div class="form-check py-1 border-bottom-dashed d-flex align-items-center">
                                    <input class="form-check-input return-allocated-asset-checkbox" type="checkbox" name="allocated_asset_ids[]" value="${asset.id}" id="${checkboxId}" style="cursor: pointer;">
                                    <label class="form-check-label fs-12 ms-2 text-dark mb-0" for="${checkboxId}" style="cursor: pointer;">
                                        <strong>Code:</strong> ${asset.asset_code} | <strong>Serial:</strong> ${asset.serial_number || 'N/A'}
                                    </label>
                                </div>
                            `;
                            checklistDiv.append(itemHtml);
                        });
                    }
                });

                modal.find('form').off('submit').on('submit', function(e) {
                    var checkedCount = modal.find('.return-allocated-asset-checkbox:checked').length;
                    if (checkedCount === 0) {
                        e.preventDefault();
                        alert(langAssets.alertSelectUnitToReturn);
                    }
                });

                if (employeeSelect.hasClass('select2-hidden-accessible')) {
                    employeeSelect.select2('destroy');
                }
                employeeSelect.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: modal
                });
            });

            // Handle asset history log click
            $(document).on('click', '.show-history-btn', function() {
                var btn = $(this);
                var assetName = btn.data('asset-name');
                var rawAllocations = btn.data('allocations');
                
                var allocations = [];
                try {
                    allocations = JSON.parse(atob(rawAllocations));
                } catch(e) {
                    console.error("Failed to parse allocations history", e);
                }

                $('#history_asset_name_display').text(assetName);
                
                var html = '';
                if (allocations.length === 0) {
                    html = '<tr><td colspan="6" class="text-center py-4 text-muted fs-12">' + (langAssets.noAllocationLogs || 'No allocation logs found for this asset.') + '</td></tr>';
                } else {
                    allocations.forEach(function(log) {
                        var empName = log.employee ? (log.employee.full_name || log.employee.display_name) : 'Unknown';
                        var empCode = log.employee && log.employee.employee_id ? ' (' + log.employee.employee_id + ')' : '';
                        var checkInDate = log.returned_at ? log.returned_at.substring(0, 10) : 'Active';
                        var allocCond = log.allocation_condition || 'good';
                        var returnCond = log.return_condition || (log.returned_at ? 'good' : null);
                        var notes = log.notes ? log.notes : '-';
                        
                        function getCondBadge(cond) {
                            if (!cond) return '<span class="text-muted fs-11">-</span>';
                            var c = String(cond).toLowerCase();
                            var cls = 'bg-soft-info text-info';
                            if (c === 'new' || c === 'good') cls = 'bg-soft-success text-success';
                            else if (c === 'fair') cls = 'bg-soft-warning text-warning';
                            else if (c === 'damaged') cls = 'bg-soft-danger text-danger';
                            else if (c === 'scrapped' || c === 'lost') cls = 'bg-soft-secondary text-secondary';
                            return '<span class="badge ' + cls + ' text-capitalize px-2 py-1 fs-11 rounded-pill">' + cond + '</span>';
                        }

                        var returnCondBadge = log.returned_at ? getCondBadge(returnCond) : '<span class="text-muted fs-11">' + (langAssets.inPossession || 'In Possession') + '</span>';
                        
                        html += '<tr>' +
                            '<td class="text-start" style="padding-left: 20px;"><strong>' + empName + '</strong><span class="text-muted fs-11">' + empCode + '</span></td>' +
                            '<td><span class="fs-12">' + (log.allocated_at ? log.allocated_at.substring(0, 10) : '-') + '</span></td>' +
                            '<td><span class="badge ' + (log.returned_at ? 'bg-soft-success text-success' : 'bg-soft-primary text-primary') + '">' + checkInDate + '</span></td>' +
                            '<td>' + getCondBadge(allocCond) + '</td>' +
                            '<td>' + returnCondBadge + '</td>' +
                            '<td class="text-start text-muted fs-11" style="max-width: 180px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; padding-right: 20px;" title="' + notes + '">' + notes + '</td>' +
                            '</tr>';
                    });
                }
                
                $('#history_table_body').html(html);
                
                // Show the modal safely attached to body
                $('#assetHistoryModal').appendTo('body').modal('show');
            });

            // Handle Item Master history click
            $(document).on('click', '.show-item-history-btn', function() {
                var btn = $(this);
                var itemName = btn.data('item-name');
                var rawAllocations = btn.data('item-allocations');
                
                var allocations = [];
                try {
                    allocations = JSON.parse(atob(rawAllocations));
                } catch(e) {
                    console.error("Failed to parse item allocations history", e);
                }

                $('#item_history_name_display').text(itemName);
                $('#item_history_total_count').text((allocations ? allocations.length : 0) + ' ' + (langAssets.events || 'Events'));
                
                var html = '';
                if (!allocations || allocations.length === 0) {
                    html = '<tr><td colspan="6" class="text-center py-4 text-muted fs-12">' + (langAssets.noAllocationHistoryUnits || 'No allocation history recorded for units under this item.') + '</td></tr>';
                } else {
                    allocations.forEach(function(event, index) {
                        var empName = event.employee ? event.employee.display_name : 'Unknown';
                        var empCode = event.employee && event.employee.employee_id ? ' (' + event.employee.employee_id + ')' : '';
                        var checkInDate = event.returned_at ? event.returned_at : 'Active';
                        var returnCondition = event.return_condition ? event.return_condition : '-';
                        
                        var unitsHtml = '';
                        if (event.units && event.units.length > 0) {
                            event.units.forEach(function(u) {
                                unitsHtml += `<span class="badge bg-white text-dark border px-2 py-1 fs-11 me-1 mb-1 shadow-sm"><i class="feather-box text-primary me-1"></i><strong>${u.code}</strong> <small class="text-muted">(${u.serial})</small></span>`;
                            });
                        }

                        html += '<tr>' +
                            '<td class="text-start px-3" style="min-width: 130px;">' +
                                '<button type="button" class="btn btn-sm btn-soft-primary fw-bold py-1 px-2.5 fs-11 toggle-item-units-btn d-inline-flex align-items-center" data-target="#item-units-box-' + index + '">' +
                                    '<i class="feather-box me-1.5"></i>' + event.qty + ' ' + (event.qty > 1 ? (langAssets.units || 'Units') : (langAssets.unit || 'Unit')) +
                                    '<i class="feather-chevron-down ms-1.5 toggle-icon fs-12"></i>' +
                                '</button>' +
                                '<div id="item-units-box-' + index + '" class="d-none mt-2 p-2 bg-light border rounded shadow-sm" style="max-width: 260px;">' +
                                    '<div class="d-flex flex-wrap gap-1">' + unitsHtml + '</div>' +
                                '</div>' +
                            '</td>' +
                            '<td class="text-start px-3"><strong>' + empName + '</strong><span class="text-muted fs-11">' + empCode + '</span></td>' +
                            '<td><span class="fs-12 fw-semibold text-dark">' + event.allocated_at + '</span></td>' +
                            '<td><span class="badge ' + (event.returned_at ? 'bg-soft-success text-success' : 'bg-soft-primary text-primary') + '">' + checkInDate + '</span></td>' +
                            '<td><span class="badge bg-light text-dark text-capitalize">' + event.allocation_condition + '</span></td>' +
                            '<td><span class="badge ' + (event.return_condition ? 'bg-soft-info text-info' : 'bg-light text-secondary') + ' text-capitalize">' + returnCondition + '</span></td>' +
                            '</tr>';
                    });
                }
                
                $('#item_history_table_body').html(html);
                
                // Show the modal safely attached to body
                $('#itemHistoryModal').appendTo('body').modal('show');
            });

            // Toggle unit details box inside Item History table
            $(document).on('click', '.toggle-item-units-btn', function() {
                var btn = $(this);
                var target = $(btn.data('target'));
                var icon = btn.find('.toggle-icon');
                
                target.toggleClass('d-none');
                if (target.hasClass('d-none')) {
                    icon.removeClass('feather-chevron-up').addClass('feather-chevron-down');
                } else {
                    icon.removeClass('feather-chevron-down').addClass('feather-chevron-up');
                }
            });

            // Bind request rejection details dynamically
            $(document).on('click', '.reject-request-btn', function() {
                var btn = $(this);
                var requestId = btn.data('request-id');
                var modal = $('#rejectRequestModal');
                modal.find('form').attr('action', '/hrms/assets/requests/' + requestId + '/reject');
                
                var rejectModal = new bootstrap.Modal(document.getElementById('rejectRequestModal'));
                rejectModal.show();
            });

            // Handle direct allocation button click (never opens modal)
            $(document).on('click', '.allocate-direct-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                var requestId = btn.data('request-id');
                var empName = btn.data('employee-name');
                var assetName = btn.data('asset-name');
                var confirmTemplate = btn.data('confirm-template') || langAssets.confirmDirectAllocate;

                var confirmMsg = confirmTemplate
                    .replace(':asset', assetName)
                    .replace(':employee', empName);

                confirmAction(confirmMsg, function() {
                    var form = $('<form>', {
                        'action': '/hrms/assets/requests/' + requestId + '/allocate-direct',
                        'method': 'POST'
                    });

                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': '_token',
                        'value': $('meta[name="csrf-token"]').attr('content')
                    }));

                    $('body').append(form);
                    form.submit();
                }, { title: langAssets.confirmTitleAllocate || 'Allocate Asset Confirmation', variant: 'success', confirmButtonText: langAssets.btnAllocate || 'Allocate' });
            });

            // Client-side validation: ensure Serial Number & Asset Code show inline error messages below fields
            $('#addAssetModal form, #editAssetItemForm, #editAssetForm').on('submit', function(e) {
                let form = $(this);
                let invalid = false;

                form.find('input[name*="[asset_code]"], input[name="asset_code"]').each(function() {
                    let parent = $(this).parent();
                    if (!$(this).val() || !$(this).val().trim()) {
                        invalid = true;
                        $(this).addClass('is-invalid');
                        if (parent.find('.invalid-feedback').length === 0) {
                            $(this).after('<div class="invalid-feedback fs-11 text-start mt-1">' + (langAssets.assetCodeRequired || 'Asset code is required.') + '</div>');
                        }
                    } else {
                        $(this).removeClass('is-invalid');
                        parent.find('.invalid-feedback').remove();
                    }
                });

                form.find('input[name*="[serial_number]"], input[name="serial_number"]').each(function() {
                    let parent = $(this).parent();
                    if (!$(this).val() || !$(this).val().trim()) {
                        invalid = true;
                        $(this).addClass('is-invalid');
                        if (parent.find('.invalid-feedback').length === 0) {
                            $(this).after('<div class="invalid-feedback fs-11 text-start mt-1">' + (langAssets.serialNumberRequired || 'Serial number is required.') + '</div>');
                        }
                    } else {
                        $(this).removeClass('is-invalid');
                        parent.find('.invalid-feedback').remove();
                    }
                });

                if (invalid) {
                    e.preventDefault();
                    return false;
                }
            });

            // Real-time clearance of inline errors when typing
            $(document).on('input', 'input[name*="[asset_code]"], input[name="asset_code"], input[name*="[serial_number]"], input[name="serial_number"]', function() {
                if ($(this).val() && $(this).val().trim()) {
                    $(this).removeClass('is-invalid');
                    $(this).parent().find('.invalid-feedback').remove();
                }
            });

            // Prevent invalid form submission if no asset is selected in request allocation mode
            $('#allocateAssetForm').on('submit', function(e) {
                var isRequestFlow = !$('#request_checkout_container').hasClass('d-none');
                if (isRequestFlow) {
                    var remainingQty = parseInt($('#allocate_remaining_qty').val()) || parseInt($('#allocate_requested_qty').val());
                    var checkedCount = $('.request-allocated-asset-checkbox:checked').length;
                    if (checkedCount === 0) {
                        e.preventDefault();
                        alert(langAssets.alertSelectUnitToFulfill);
                        return false;
                    }
                    if (checkedCount > remainingQty) {
                        e.preventDefault();
                        alert(langAssets.alertMaxUnitsExceeded.replace(':qty', remainingQty));
                        return false;
                    }
                }
            });

            // Handle allocation details binding
            $('#allocateAssetModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var modal = $(this);
                var form = $('#allocateAssetForm');

                // Determine if allocation is triggered from a Request ticket or from the Registry Directory
                if (button.hasClass('allocate-request-trigger-btn')) {
                    // FLOW 1: REQUEST ALLOCATION
                    var requestId = button.data('request-id');
                    var employeeId = button.data('employee-id');
                    var employeeName = button.data('employee-name');
                    var itemId = button.data('asset-item-id');
                    var qty = button.data('quantity');
                    var allocatedCount = button.data('allocated-count') || 0;
                    var remainingQty = button.data('remaining-qty') || (qty - allocatedCount);

                    // Set active inputs
                    $('#allocate_request_id').val(requestId);
                    $('#registry_checkout_container').addClass('d-none');
                    $('#request_checkout_container').removeClass('d-none');

                    // Configure inputs disabled and required states to prevent double submissions and force selection
                    $('#registry_employee_select').prop('disabled', true);
                    $('#allocate_quantity_input').prop('disabled', true);
                    $('#request_employee_id').prop('disabled', false).val(employeeId);
                    $('#allocate_employee_name_display').val(employeeName);
                    $('#allocate_requested_qty').val(qty);
                    $('#allocate_already_allocated_qty').val(allocatedCount);
                    $('#allocate_remaining_qty').val(remainingQty);
                    $('#max_selectable_count').text(remainingQty);

                    // Rebuild asset checkboxes checklist based on AssetItem match
                    var checklistDiv = $('#request_assets_checklist');
                    checklistDiv.empty();

                    var filteredAssets = allAvailableAssets.filter(function(asset) {
                        return String(asset.asset_item_id) === String(itemId);
                    });

                    if (filteredAssets.length === 0) {
                        checklistDiv.html('<div class="text-danger fs-12"><i class="feather-alert-triangle me-1"></i>' + (langAssets.noAvailableUnitsItem || 'No available units in inventory for this item.') + '</div>');
                    } else {
                        filteredAssets.forEach(function(asset) {
                            var checkboxId = 'allocate_asset_checkbox_' + asset.id;
                            var itemHtml = `
                                <div class="form-check py-1 border-bottom-dashed">
                                    <input class="form-check-input request-allocated-asset-checkbox" type="checkbox" name="allocated_asset_ids[]" value="${asset.id}" id="${checkboxId}">
                                    <label class="form-check-label fs-12 ms-1 text-dark" for="${checkboxId}">
                                        <strong>${asset.name}</strong>
                                    </label>
                                </div>
                            `;
                            checklistDiv.append(itemHtml);
                        });
                    }
                    form.attr('action', '/hrms/assets/requests/' + requestId + '/allocate');
                } else {
                    // FLOW 2: REGISTRY DIRECT ALLOCATION
                    var itemId = button.data('item-id');
                    var itemName = button.data('item-name');
                    var availableQty = button.data('available');
                    var companyId = button.data('company-id');

                    // Set active inputs
                    $('#allocate_request_id').val('');
                    $('#request_checkout_container').addClass('d-none');
                    $('#registry_checkout_container').removeClass('d-none');

                    // Configure inputs disabled and required states
                    $('#request_employee_id').prop('disabled', true);
                    $('#registry_employee_select').prop('disabled', false);
                    $('#allocate_quantity_input').prop('disabled', false);
                    $('#allocate_asset_name_display').val(itemName);
                    $('#allocate_available_qty_display').val(availableQty);

                    var qtyInput = $('#allocate_quantity_input');
                    qtyInput.attr('max', availableQty);
                    qtyInput.val(1);

                    // Re-filter employee options
                    var employeeSelect = $('#registry_employee_select');
                    employeeSelect.empty();
                    employeeSelect.append(new Option(langAssets.selectEmployee || 'Select Employee', ''));

                    var filteredEmployees = allEmployees.filter(function(emp) {
                        return !companyId || String(emp.company_id) === String(companyId);
                    });

                    filteredEmployees.forEach(function(emp) {
                        var label = emp.display_name + (emp.employee_id ? ' (' + emp.employee_id + ')' : '');
                        employeeSelect.append(new Option(label, emp.id));
                    });

                    if (employeeSelect.hasClass('select2-hidden-accessible')) {
                        employeeSelect.select2('destroy');
                    }
                    employeeSelect.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownParent: modal
                    });
                    employeeSelect.val('').trigger('change');
                    form.attr('action', '/hrms/assets/item/' + itemId + '/allocate');
                }
            });

            // Handle search form submission via AJAX (covers Enter key and clicking Apply in filter)
            $(document).on('submit', 'form[action*="assets"][method="GET"], form[action*="assets"][method="get"]', function(e) {
                e.preventDefault();
                var form = $(this);
                var formData = form.serialize();
                var url = form.attr('action') + '?' + formData;
                var tabPaneId = form.closest('.tab-pane').attr('id');
                
                // Close the filter dropdown if open
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');

                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        // Update table
                        var oldTable = $('#' + tabPaneId + ' .table-responsive');
                        var newTable = $(doc).find('#' + tabPaneId + ' .table-responsive');
                        oldTable.html(newTable.html());
                        
                        // Update pagination footer
                        var oldPagination = $('#' + tabPaneId + ' .erp-pagination-container');
                        var newPagination = $(doc).find('#' + tabPaneId + ' .erp-pagination-container');
                        if (newPagination.length) {
                            if (oldPagination.length) {
                                oldPagination.replaceWith(newPagination);
                            } else {
                                $('#' + tabPaneId + ' .card-body').append(newPagination);
                            }
                        } else {
                            oldPagination.remove();
                        }
                        history.pushState(null, '', url);
                    }
                });
            });

            // Live Search (AJAX) as user types with debounce (only trigger for input, NOT dropdown selects)
            var searchTimeout;
            $(document).on('input', 'input[name$="_search"]', function() {
                var $input = $(this);
                var form = $input.closest('form');
                clearTimeout(searchTimeout);
                
                searchTimeout = setTimeout(function() {
                    var formData = form.serialize();
                    var url = form.attr('action') + '?' + formData;

                    $.ajax({
                        url: url,
                        type: 'GET',
                        success: function(response) {
                            var parser = new DOMParser();
                            var doc = parser.parseFromString(response, 'text/html');
                            var tabPaneId = $input.closest('.tab-pane').attr('id');
                            
                            // Update table
                            var oldTable = $('#' + tabPaneId + ' .table-responsive');
                            var newTable = $(doc).find('#' + tabPaneId + ' .table-responsive');
                            oldTable.html(newTable.html());
                            
                            // Update pagination footer
                            var oldPagination = $('#' + tabPaneId + ' .erp-pagination-container');
                            var newPagination = $(doc).find('#' + tabPaneId + ' .erp-pagination-container');
                            if (newPagination.length) {
                                if (oldPagination.length) {
                                    oldPagination.replaceWith(newPagination);
                                } else {
                                    $('#' + tabPaneId + ' .card-body').append(newPagination);
                                }
                            } else {
                                oldPagination.remove();
                            }
                            history.pushState(null, '', url);
                        }
                    });
                }, 300); // 300ms debounce
            });

            // Reset filters via AJAX click
            $(document).on('click', '.erp-filter-dropdown a.btn-light, form a.btn-light', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var url = $btn.attr('href');
                var tabPaneId = $btn.closest('.tab-pane').attr('id');
                
                // Close the filter dropdown if open
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');

                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        // Update card header (to clear search text)
                        var oldHeader = $('#' + tabPaneId + ' .card-header');
                        var newHeader = $(doc).find('#' + tabPaneId + ' .card-header');
                        oldHeader.html(newHeader.html());
                        
                        // Update table
                        var oldTable = $('#' + tabPaneId + ' .table-responsive');
                        var newTable = $(doc).find('#' + tabPaneId + ' .table-responsive');
                        oldTable.html(newTable.html());
                        
                        // Update pagination footer
                        var oldPagination = $('#' + tabPaneId + ' .erp-pagination-container');
                        var newPagination = $(doc).find('#' + tabPaneId + ' .erp-pagination-container');
                        if (newPagination.length) {
                            if (oldPagination.length) {
                                oldPagination.replaceWith(newPagination);
                            } else {
                                $('#' + tabPaneId + ' .card-body').append(newPagination);
                            }
                        } else {
                            oldPagination.remove();
                        }
                        history.pushState(null, '', url);
                    }
                });
            });

            // AJAX Pagination click
            $(document).on('click', '.tab-pane .erp-pagination-container a', function(e) {
                e.preventDefault();
                var $link = $(this);
                var url = $link.attr('href');
                if (!url || url.indexOf('javascript') === 0) return;
                
                var tabPaneId = $link.closest('.tab-pane').attr('id');
                
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        // Update table
                        var oldTable = $('#' + tabPaneId + ' .table-responsive');
                        var newTable = $(doc).find('#' + tabPaneId + ' .table-responsive');
                        oldTable.html(newTable.html());
                        
                        // Update pagination footer
                        var oldPagination = $('#' + tabPaneId + ' .erp-pagination-container');
                        var newPagination = $(doc).find('#' + tabPaneId + ' .erp-pagination-container');
                        if (newPagination.length) {
                            if (oldPagination.length) {
                                oldPagination.replaceWith(newPagination);
                            } else {
                                $('#' + tabPaneId + ' .card-body').append(newPagination);
                            }
                        } else {
                            oldPagination.remove();
                        }
                        history.pushState(null, '', url);
                    }
                });
            });

            // Dynamically adjust dropdown direction (dropup vs dropdown) to prevent clipping
            $(document).on('click', '.dropdown-toggle-custom', function() {
                var dropdown = $(this).closest('.dropdown');
                var menu = dropdown.find('.dropdown-menu');
                if (!menu.length) return;

                var windowHeight = $(window).height();
                var toggleOffset = $(this).offset().top - $(window).scrollTop();
                var dropdownHeight = menu.outerHeight() || 150;

                if (toggleOffset + dropdownHeight > windowHeight - 50) {
                    dropdown.addClass('dropup');
                } else {
                    dropdown.removeClass('dropup');
                }
            });

            // Autofocus active search inputs on load and restore cursor to the end
            var searchInputs = document.querySelectorAll('input[name$="_search"]');
            searchInputs.forEach(function(input) {
                if (input.value) {
                    input.focus();
                    var val = input.value;
                    input.value = '';
                    input.value = val;
                }
            });

            // Toggle assets sub-table collapse
            $(document).on('click', '.toggle-assets-btn', function() {
                let itemId = $(this).data('item-id');
                let targetRow = $('#assets-row-' + itemId);
                let icon = $(this).find('.toggle-icon');
                
                if (targetRow.hasClass('d-none')) {
                    targetRow.removeClass('d-none');
                    icon.removeClass('feather-chevron-right').addClass('feather-chevron-down');
                } else {
                    targetRow.addClass('d-none');
                    icon.removeClass('feather-chevron-down').addClass('feather-chevron-right');
                }
            });

            // Checkbox multi-select logic
            const $selectAll = $('#selectAllRequests');
            const $bulkToolbar = $('#bulkActionsToolbar');
            const $selectedCount = $('#selectedRequestsCount');

            function updateBulkToolbar() {
                const checkedCheckboxes = $('.request-select-checkbox:checked');
                const count = checkedCheckboxes.length;
                if (count > 0) {
                    $bulkToolbar.removeClass('d-none');
                    $selectedCount.text(count);
                } else {
                    $bulkToolbar.addClass('d-none');
                }
            }

            $selectAll.on('change', function() {
                const isChecked = $(this).prop('checked');
                $('.request-select-checkbox').prop('checked', isChecked);
                updateBulkToolbar();
            });

            $(document).on('change', '.request-select-checkbox', function() {
                const total = $('.request-select-checkbox').length;
                const checked = $('.request-select-checkbox:checked').length;
                $selectAll.prop('checked', total === checked);
                updateBulkToolbar();
            });

            // Bulk Reject click handler
            $('#btnBulkReject').on('click', function() {
                const checkedCheckboxes = $('.request-select-checkbox:checked');
                const container = $('#bulk_reject_ids_container');
                container.empty();

                checkedCheckboxes.each(function() {
                    const reqId = $(this).val();
                    container.append(`<input type="hidden" name="request_ids[]" value="${reqId}">`);
                });

                var bulkRejectModal = new bootstrap.Modal(document.getElementById('bulkRejectModal'));
                bulkRejectModal.show();
            });

            // Bulk Allocate click handler (allows multi-unit selection per request)
            $('#btnBulkAllocate').on('click', function() {
                const checkedCheckboxes = $('.request-select-checkbox:checked');
                const tableBody = $('#bulk_allocate_table tbody');
                tableBody.empty();

                checkedCheckboxes.each(function() {
                    const $chk = $(this);
                    const reqId = $chk.val();
                    const empName = $chk.data('employee-name');
                    const catId = $chk.data('category-id');
                    const catName = $chk.data('category-name');
                    const itemId = $chk.data('item-id');
                    const itemName = $chk.data('item-name') || catName;
                    const compId = $chk.data('company-id');
                    const requestedQty = parseInt($chk.data('quantity')) || 1;
                    const allocatedCount = parseInt($chk.data('allocated-count')) || 0;
                    const remainingQty = parseInt($chk.data('remaining-qty')) || (requestedQty - allocatedCount);

                    // Find matching available units
                    const matchedAssets = allAvailableAssets.filter(function(asset) {
                        if (asset.status && asset.status !== 'available') return false;
                        if (itemId && asset.asset_item_id) {
                            return String(asset.asset_item_id) === String(itemId);
                        }
                        if (catId && asset.category_id) {
                            return String(asset.category_id) === String(catId);
                        }
                        return false;
                    });

                    let assetSelectionHtml = '';

                    if (matchedAssets.length > 0) {
                        assetSelectionHtml += `<div class="bg-light p-2 rounded border" style="max-height: 150px; overflow-y: auto;">`;
                        assetSelectionHtml += `<div class="fs-11 text-muted mb-1">${(langAssets.selectUpToUnits || 'Select up to :qty unit(s):').replace(':qty', '<strong>' + remainingQty + '</strong>')}</div>`;
                        matchedAssets.forEach(function(asset) {
                            assetSelectionHtml += `
                                <div class="form-check py-1">
                                    <input type="checkbox" name="allocations[${reqId}][]" value="${asset.id}" class="form-check-input bulk-unit-checkbox" data-req-id="${reqId}" data-rem-qty="${remainingQty}">
                                    <label class="form-check-label fs-12 fw-semibold text-dark">
                                        ${asset.asset_code} <span class="text-muted fs-11">(${asset.serial_number || langAssets.noSerial || 'No Serial'})</span>
                                    </label>
                                </div>
                            `;
                        });
                        assetSelectionHtml += `</div>`;
                    } else {
                        assetSelectionHtml = `<span class="text-danger fw-bold fs-12"><i class="feather-alert-triangle me-1"></i>${(langAssets.noAvailableUnitsFoundFor || 'No available units found for :item.').replace(':item', itemName)}</span>`;
                    }

                    const rowHtml = `
                        <tr>
                            <td class="text-start">
                                <strong class="text-dark fs-13">${empName}</strong>
                            </td>
                            <td class="text-start">
                                <div class="fw-bold text-dark fs-12">${itemName}</div>
                                <span class="badge bg-light text-secondary border px-2 py-0.5 fs-11">${catName}</span>
                                <div class="fs-11 text-muted mt-1">
                                    ${langAssets.req || 'Req'}: <strong class="text-dark">${requestedQty}</strong> | ${langAssets.rem || 'Rem'}: <strong class="text-danger">${remainingQty}</strong>
                                </div>
                                <div class="mt-2 pt-1 border-top d-flex align-items-center justify-content-between">
                                    <span class="fs-11 fw-bold text-muted text-uppercase">${langAssets.allocatingQty || 'Allocating Qty'}:</span>
                                    <span class="badge bg-soft-success text-success border border-success border-opacity-25 px-2 py-1 fs-11" id="bulk_alloc_badge_${reqId}">0 / ${remainingQty} ${langAssets.units || 'unit(s)'}</span>
                                </div>
                            </td>
                            <td class="text-start">
                                ${assetSelectionHtml}
                            </td>
                        </tr>
                    `;
                    tableBody.append(rowHtml);
                });

                const modalEl = document.getElementById('bulkAllocateModal');
                const bulkAllocModal = new bootstrap.Modal(modalEl);
                bulkAllocModal.show();
            });

            // Restrict maximum selected checkboxes in Bulk Allocate Modal to remaining qty and update badge
            $(document).on('change', '.bulk-unit-checkbox', function() {
                const reqId = $(this).data('req-id');
                const remQty = parseInt($(this).data('rem-qty')) || 1;
                let checkedCount = $(`.bulk-unit-checkbox[data-req-id="${reqId}"]:checked`).length;

                if (checkedCount > remQty) {
                    $(this).prop('checked', false);
                    checkedCount = remQty;
                    alert((langAssets.alertMaxUnitsExceeded || 'You can select at most :qty unit(s) for this request.').replace(':qty', remQty));
                }

                const badge = $(`#bulk_alloc_badge_${reqId}`);
                if (checkedCount > 0) {
                    badge.removeClass('bg-soft-secondary text-secondary border-secondary').addClass('bg-soft-success text-success border-success').text(`${checkedCount} / ${remQty} ${(langAssets.units || 'unit(s)')}`);
                } else {
                    badge.removeClass('bg-soft-success text-success border-success').addClass('bg-soft-secondary text-secondary border-secondary').text(`0 / ${remQty} ${(langAssets.units || 'unit(s)')}`);
                }
            });

            function checkTruncatedDescriptions() {
                $('.desc-expandable-container').each(function() {
                    const textEl = $(this).find('.desc-text-truncate')[0];
                    const readMoreBtn = $(this).find('.btn-read-more-dynamic');
                    if (textEl && (textEl.scrollWidth > textEl.clientWidth + 1)) {
                        readMoreBtn.removeClass('d-none');
                        $(textEl).css('cursor', 'pointer');
                    } else {
                        readMoreBtn.addClass('d-none');
                        $(textEl).css('cursor', 'default');
                    }
                });
            }

            setTimeout(checkTruncatedDescriptions, 100);
            $(window).on('resize', checkTruncatedDescriptions);
            $('a[data-bs-toggle="tab"], button[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
                setTimeout(checkTruncatedDescriptions, 50);
            });

            // View full description modal handler (clicking Read More link OR clicking the truncated text)
            $(document).on('click', '.btn-read-more-dynamic, .desc-text-truncate', function() {
                const container = $(this).closest('.desc-expandable-container');
                const readMoreBtn = container.find('.btn-read-more-dynamic');
                if (!readMoreBtn.hasClass('d-none')) {
                    const title = readMoreBtn.data('title') || (langAssets.description || 'Description');
                    const desc = readMoreBtn.data('desc') || '';
                    $('#desc_modal_title').text(title + ' - ' + (langAssets.fullDescription || 'Full Description'));
                    $('#desc_modal_content').text(desc);
                    $('#viewDescriptionModal').appendTo('body').modal('show');
                }
            });

            // View request full details modal handler
            $(document).on('click', '.view-req-details-btn', function() {
                const btn = $(this);
                const status = (btn.data('status-raw') || 'pending').toLowerCase();
                
                $('#req_detail_emp_name').text(btn.data('emp-name'));
                $('#req_detail_emp_id').text(btn.data('emp-id'));
                $('#req_detail_company').text(btn.data('company') || 'Company');
                $('#req_detail_asset_name').text(btn.data('asset-name'));
                $('#req_detail_category').text(btn.data('category'));
                $('#req_detail_req_qty').text(btn.data('req-qty') || 0);
                $('#req_detail_alloc_qty').text(btn.data('alloc-qty') || 0);
                $('#req_detail_rem_qty').text(btn.data('rem-qty') || 0);
                $('#req_detail_date').text(btn.data('date'));
                $('#req_detail_reason').text(btn.data('reason'));

                let badgeHtml = '';
                if (status === 'pending') {
                    badgeHtml = '<span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11">' + (langAssets.statusPending || 'Pending') + '</span>';
                } else if (status === 'partially_allocated' || status === 'partial') {
                    badgeHtml = '<span class="badge bg-soft-info text-info px-2.5 py-1 rounded-pill fs-11">' + (langAssets.statusPartiallyAllocated || 'Partially Allocated') + '</span>';
                } else if (status === 'allocated') {
                    badgeHtml = '<span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11">' + (langAssets.statusAllocated || 'Allocated') + '</span>';
                } else if (status === 'rejected') {
                    badgeHtml = '<span class="badge bg-soft-danger text-danger px-2.5 py-1 rounded-pill fs-11">' + (langAssets.statusRejected || 'Rejected') + '</span>';
                } else {
                    badgeHtml = `<span class="badge bg-light text-secondary px-2.5 py-1 rounded-pill fs-11">${btn.data('status')}</span>`;
                }
                $('#req_detail_status_container').html(badgeHtml);

                // Handle dynamic Fulfillment / Rejection Section
                const actionSection = $('#req_detail_fulfillment_section');
                const allocBox = $('#req_detail_allocation_box');
                const rejectBox = $('#req_detail_rejection_box');

                actionSection.addClass('d-none');
                allocBox.addClass('d-none');
                rejectBox.addClass('d-none');

                const actionDate = btn.data('action-date') || '-';
                const adminNotes = btn.data('admin-notes') || '';
                const rawUnits = btn.data('allocated-units');
                let allocatedUnits = [];

                if (rawUnits) {
                    try {
                        allocatedUnits = JSON.parse(atob(rawUnits));
                    } catch(err) {
                        allocatedUnits = [];
                    }
                }

                if (status === 'allocated' || status === 'partially_allocated' || status === 'partial') {
                    actionSection.removeClass('d-none');
                    allocBox.removeClass('d-none');
                    $('#req_detail_alloc_date').text(actionDate);

                    let unitsHtml = '';
                    if (allocatedUnits && allocatedUnits.length > 0) {
                        allocatedUnits.forEach(function(unit) {
                            let uDate = unit.date || actionDate;
                            unitsHtml += `
                                <div class="d-inline-flex align-items-center bg-white text-dark border rounded px-2.5 py-1.5 fs-11 me-1 mb-1 shadow-sm">
                                    <i class="feather-box text-primary me-1.5 fs-12"></i>
                                    <div class="lh-sm">
                                        <span class="fw-bold text-dark">${unit.code}</span>
                                        <span class="text-muted fs-10 ms-1">(${unit.serial})</span>
                                        ${uDate && uDate !== '-' ? `<span class="badge bg-light text-secondary border fs-9 ms-1.5 py-0.5 px-1">${uDate}</span>` : ''}
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        unitsHtml = '<span class="fs-12 text-muted fst-italic">' + (langAssets.noSerializedUnitsLinked || 'No serialized units linked.') + '</span>';
                    }
                    $('#req_detail_allocated_units_list').html(unitsHtml);

                } else if (status === 'rejected') {
                    actionSection.removeClass('d-none');
                    rejectBox.removeClass('d-none');
                    $('#req_detail_reject_date').text(actionDate);
                    $('#req_detail_reject_notes').text(adminNotes && adminNotes.trim() !== '' ? adminNotes : (langAssets.noSpecificReasonProvided || 'No specific reason provided.'));
                }

                $('#viewRequestDetailsModal').appendTo('body').modal('show');
            });

            $('#bulkAllocateModal').appendTo('body');
            $('#bulkRejectModal').appendTo('body');
            $('#viewDescriptionModal').appendTo('body');
            $('#viewRequestDetailsModal').appendTo('body');
            $('#itemHistoryModal').appendTo('body');
            $('#assetHistoryModal').appendTo('body');
        });

        // Global function for sorting
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
    </script>
@endpush

@include('modules.hrms.partials.hrms-settings-helpers')
