@props([
    'type',
    'label' => '',
    'exportRoute' => null,
    'downloadTemplateRoute' => null,
    'importModalTarget' => null,
    'exportColumns' => null,
    'canExport' => true,
    'canImport' => true,
    'canDownloadTemplate' => true,
])

@php
    $modalUniqueId = 'exportModal_' . \Illuminate\Support\Str::slug($type, '_') . '_' . \Illuminate\Support\Str::random(6);
    $resolvedColumns = $exportColumns ?? \App\Exports\ExportRegistry::getColumnsForType($type);
    $hasColumns = !empty($resolvedColumns);
    $modalTitle = \App\Exports\ExportRegistry::getTitleForType($type);
    $exportActionUrl = $exportRoute ?? (Route::has('production.import-export.export') ? route('production.import-export.export', $type) : '#');
    $currentQueryParams = request()->query();
@endphp

@once
    @push('styles')
        <style>
            .erp-import-export-dropdown {
                position: relative !important;
            }
            .erp-import-export-dropdown .dropdown-menu {
                position: absolute !important;
                top: 100% !important;
                right: 0 !important;
                left: auto !important;
                transform: none !important;
                display: none !important;
                min-width: 180px !important;
                padding: 6px !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
                margin-top: 5px !important;
                z-index: 1050 !important;
                background-color: #ffffff !important;
            }
            .erp-import-export-dropdown .dropdown-menu.show {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            .erp-import-export-dropdown .dropdown-item {
                display: flex !important;
                align-items: center !important;
                padding: 8px 12px !important;
                font-size: 13px !important;
                color: #475569 !important;
                border-radius: 6px !important;
                transition: all 0.2s ease-in-out !important;
                background: transparent !important;
                border: none !important;
                width: 100% !important;
                text-align: left !important;
            }
            .erp-import-export-dropdown .dropdown-item:hover {
                background-color: #f1f5f9 !important;
                color: #0f172a !important;
            }
            .export-columns-modal .odoo-table .form-check {
                display: flex;
                justify-content: center;
                align-items: center;
                padding-left: 0 !important;
                margin-bottom: 0 !important;
                min-height: auto;
            }
            .export-columns-modal .odoo-table .form-check .form-check-input {
                float: none !important;
                margin-left: 0 !important;
            }
            .export-columns-modal .col-item-wrapper:hover {
                background-color: #f8fafc;
            }
            .cursor-pointer {
                cursor: pointer !important;
            }
        </style>
    @endpush
@endonce

<div class="dropdown erp-import-export-dropdown d-inline-block" {{ $attributes }}>
    <x-ui.icon-btn type="button" 
                   variant="transparent-dark"
                   title="Import / Export Options"
                   size="md"
                   icon="feather-paperclip"
                   class="import-export-toggle-custom"
                   aria-expanded="false">
        @if($label)
            <span>{{ $label }}</span>
        @endif
    </x-ui.icon-btn>
    <ul class="dropdown-menu dropdown-menu-end fs-13 shadow-lg">
        @if($canExport)
            <li>
                @if($hasColumns)
                    <x-ui.dropdown-item href="javascript:void(0);" icon="feather-download me-2 text-muted fs-12" data-bs-toggle="modal" data-bs-target="#{{ $modalUniqueId }}">
                        {{ __('hrms.common.export_excel') }}
                    </x-ui.dropdown-item>
                @else
                    <x-ui.dropdown-item href="{{ $exportActionUrl . (count($currentQueryParams) ? '?' . http_build_query($currentQueryParams) : '') }}" icon="feather-download me-2 text-muted fs-12">
                        {{ __('hrms.common.export_excel') }}
                    </x-ui.dropdown-item>
                @endif
            </li>
        @endif
        @if($canDownloadTemplate)
            <li>
                <x-ui.dropdown-item href="{{ $downloadTemplateRoute ?? (Route::has('production.import-export.download-template') ? route('production.import-export.download-template', $type) : '#') }}" icon="feather-file-text me-2 text-muted fs-12">
                    {{ __('hrms.common.download_template') }}
                </x-ui.dropdown-item>
            </li>
        @endif
        @if($canImport && ($canExport || $canDownloadTemplate))
            <li><hr class="dropdown-divider"></li>
        @endif
        @if($canImport)
            <li>
                <x-ui.dropdown-item href="javascript:void(0);" icon="feather-upload me-2 text-muted fs-12" data-bs-toggle="modal" data-bs-target="{{ $importModalTarget ?? '#import' . \Illuminate\Support\Str::studly($type) . 'Modal' }}">
                    {{ __('hrms.common.import') }}
                </x-ui.dropdown-item>
            </li>
        @endif
    </ul>
</div>

@if($hasColumns)
<x-ui.modal 
    id="{{ $modalUniqueId }}" 
    :title="'<i class=\'feather-download me-2 text-primary\'></i>Export Options: ' . $modalTitle"
    size="lg" 
    :centered="true"
    formAction="{{ $exportActionUrl }}"
    formMethod="GET"
    class="export-columns-modal">

    {{-- Preserve all current active page filters / query parameters --}}
    @foreach($currentQueryParams as $paramKey => $paramVal)
        @if($paramKey !== 'columns' && $paramKey !== '_token')
            @if(is_array($paramVal))
                @foreach($paramVal as $arrayItem)
                    <input type="hidden" name="{{ $paramKey }}[]" value="{{ $arrayItem }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $paramKey }}" value="{{ $paramVal }}">
            @endif
        @endif
    @endforeach

    <p class="text-muted fs-12 mb-3">
        Select the columns you want to include in the exported Excel spreadsheet.
    </p>

    {{-- Quick Search & Select Controls Toolbar using common components --}}
    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-wrap gap-2">
        <div style="max-width: 260px;">
            <x-ui.odoo-form-ui 
                type="input" 
                name="filter_columns_search" 
                id="col_search_{{ $modalUniqueId }}"
                placeholder="Search columns..." 
                class="form-control form-control-sm bg-white" 
                style="border: 1px solid #ced4da !important; border-radius: 4px !important; padding: 5px 10px !important; font-size: 13px !important;"
                onkeydown="if(event.key === 'Enter'){ event.preventDefault(); return false; }"
                onkeyup="filterExportColumns(this, '{{ $modalUniqueId }}')" />
        </div>
        <div class="d-flex align-items-center">
            <x-ui.badge variant="primary" :soft="true" class="fs-12 px-2 py-1 font-monospace">
                <span class="selected-count-val-{{ $modalUniqueId }}">{{ count($resolvedColumns) }}</span> / {{ count($resolvedColumns) }} Selected
            </x-ui.badge>
        </div>
    </div>

    {{-- Warning alert when 0 selected --}}
    <x-ui.alert variant="warning" icon="feather-alert-triangle" class="py-2 px-3 fs-12 d-none zero-columns-alert-{{ $modalUniqueId }} mb-3">
        Please select at least one column to export.
    </x-ui.alert>

    {{-- Columns Table using odoo-form-ui type="table" --}}
    <div class="table-responsive border rounded" style="max-height: 350px; overflow-y: auto;">
        <x-ui.odoo-form-ui type="table" id="cols-table-{{ $modalUniqueId }}" class="align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center py-2">
                        <x-ui.odoo-form-ui 
                            type="checkbox" 
                            id="check-all-{{ $modalUniqueId }}" 
                            class="export-header-toggle-{{ $modalUniqueId }}"
                            checked
                            onclick="toggleAllExportColumns('{{ $modalUniqueId }}', this.checked)" />
                    </th>
                    <th class="py-2">Column Name</th>
                    <th style="width: 180px;" class="text-muted py-2">Field Key</th>
                </tr>
            </thead>
            <tbody id="cols-container-{{ $modalUniqueId }}">
                @foreach($resolvedColumns as $colKey => $colLabel)
                    <tr class="col-item-wrapper" data-label="{{ strtolower($colLabel) }} {{ strtolower($colKey) }}">
                        <td class="text-center py-2" style="width: 50px;">
                            <x-ui.odoo-form-ui 
                                type="checkbox" 
                                name="columns[]" 
                                value="{{ $colKey }}" 
                                id="check-{{ $modalUniqueId }}-{{ $colKey }}" 
                                class="export-col-check-{{ $modalUniqueId }}"
                                checked
                                onchange="updateExportCount('{{ $modalUniqueId }}')" />
                        </td>
                        <td class="py-2">
                            <label for="check-{{ $modalUniqueId }}-{{ $colKey }}" class="fw-semibold text-dark mb-0 cursor-pointer user-select-none">
                                {{ $colLabel }}
                            </label>
                        </td>
                        <td class="text-muted font-monospace fs-12 py-2">
                            {{ $colKey }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.odoo-form-ui>
    </div>

    {{-- Modal Footer with common x-ui.button --}}
    <x-slot name="footer">
        <div class="d-flex justify-content-between align-items-center w-100">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">
                {{ __('production.cancel') ?? 'Cancel' }}
            </x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="feather-download" class="fw-bold export-submit-btn-{{ $modalUniqueId }}">
                {{ __('hrms.common.export_excel') ?? 'Export Excel' }}
            </x-ui.button>
        </div>
    </x-slot>
</x-ui.modal>
@endif

@once
    @push('scripts')
        <script>
            $(document).ready(function() {
                // Custom toggle handler to prevent Bootstrap double-event conflict
                $(document).on('click', '.import-export-toggle-custom', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var parent = $(this).closest('.erp-import-export-dropdown');
                    var menu = parent.find('.dropdown-menu');
                    
                    // Close other open dropdowns
                    $('.dropdown-menu.show').not(menu).removeClass('show');
                    $('.dropdown.show').not(parent).removeClass('show');
                    
                    parent.toggleClass('show');
                    menu.toggleClass('show');
                });
                
                // Close dropdown when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.erp-import-export-dropdown').length) {
                        $('.erp-import-export-dropdown .dropdown-menu.show').removeClass('show');
                        $('.erp-import-export-dropdown.show').removeClass('show');
                    }
                });

                // Auto-close modal after trigger and validate >= 1 column
                $(document).on('submit', 'form:has(input[name="columns[]"])', function(e) {
                    const checked = this.querySelectorAll('input[name="columns[]"]:checked');
                    if (checked.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one column to export.');
                        return false;
                    }
                    const modalEl = this.closest('.modal');
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        setTimeout(() => {
                            const modalInstance = bootstrap.Modal.getInstance(modalEl);
                            if (modalInstance) modalInstance.hide();
                        }, 500);
                    }
                });
            });

            function toggleAllExportColumns(modalId, selectAll) {
                const checks = document.querySelectorAll('.export-col-check-' + modalId);
                checks.forEach(cb => {
                    const wrapper = cb.closest('.col-item-wrapper');
                    if (!wrapper || wrapper.style.display !== 'none') {
                        cb.checked = selectAll;
                    }
                });
                const headerToggle = document.querySelector('.export-header-toggle-' + modalId);
                if (headerToggle) headerToggle.checked = selectAll;

                updateExportCount(modalId);
            }

            function updateExportCount(modalId) {
                const checks = document.querySelectorAll('.export-col-check-' + modalId);
                const total = checks.length;
                const selected = document.querySelectorAll('.export-col-check-' + modalId + ':checked').length;
                const counter = document.querySelector('.selected-count-val-' + modalId);
                const alertBox = document.querySelector('.zero-columns-alert-' + modalId);
                const submitBtn = document.querySelector('.export-submit-btn-' + modalId);
                const headerToggle = document.querySelector('.export-header-toggle-' + modalId);

                if (counter) counter.textContent = selected;
                if (headerToggle) headerToggle.checked = (selected === total && total > 0);

                if (selected === 0) {
                    if (alertBox) alertBox.classList.remove('d-none');
                    if (submitBtn) submitBtn.disabled = true;
                } else {
                    if (alertBox) alertBox.classList.add('d-none');
                    if (submitBtn) submitBtn.disabled = false;
                }
            }

            function filterExportColumns(input, modalId) {
                const query = input.value.toLowerCase().trim();
                const items = document.querySelectorAll('#cols-container-' + modalId + ' .col-item-wrapper');
                items.forEach(item => {
                    const text = item.getAttribute('data-label') || '';
                    if (!query || text.includes(query)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
        </script>
    @endpush
@endonce
