@props([
    'type' => 'input',
    'label' => null,
    'name' => null,
    'id' => null,
    'inputType' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'readonly' => false,
    'disabled' => false,
    'rows' => 3,
    'searchable' => true,
    'multiple' => false,
    'select2Selector' => null,
    'helperText' => null,
    'errorText' => null,
    'editorHeight' => 'ht-200'
])

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/vendors/css/quill.min.css') }}">
        <style>
            .odoo-sheet {
                background: #ffffff;
            }
            .odoo-form-group {
                display: flex;
                align-items: center;
                margin-bottom: 10px;
            }
            .odoo-form-group > .flex-grow-1 {
                min-width: 0 !important;
            }
            .odoo-form-label {
                width: 130px;
                flex-shrink: 0 !important;
                font-size: 13px;
                font-weight: 700;
                color: #495057;
                margin-bottom: 0;
            }
            .odoo-form-control {
                border: none;
                border-bottom: 1px solid #ced4da;
                border-radius: 0;
                padding: 2px 0;
                background-color: transparent;
                font-size: 13px;
                color: #212529;
                width: 100%;
                transition: border-color 0.2s ease-in-out;
            }
            .odoo-form-control:focus {
                border-color: var(--bs-primary) !important;
                outline: none;
                box-shadow: none;
            }
            .odoo-form-control[readonly] {
                border-bottom: none;
                background-color: transparent;
                font-weight: bold;
            }

            /* Textarea custom styles */
            textarea.odoo-form-control {
                border: 1px solid #ced4da !important;
                border-radius: 4px !important;
                padding: 6px 8px !important;
                background-color: #ffffff;
            }
            textarea.odoo-form-control:focus {
                border-color: var(--bs-primary) !important;
            }

            /* Custom Styled Checkboxes & Radios Globally */
            .form-check-input {
                width: 18px !important;
                height: 18px !important;
                cursor: pointer;
                border: 2px solid #ced4da !important;
                transition: all 0.2s ease-in-out;
            }
            .form-check-input:checked {
                background-color: var(--bs-primary) !important;
                border-color: var(--bs-primary) !important;
            }
            .form-check-input:focus {
                border-color: var(--bs-primary) !important;
                box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.15) !important;
            }
            .form-check-label {
                font-size: 13px;
                color: #333333;
                cursor: pointer;
                user-select: none;
            }

            /* File input style overrides */
            .erp-custom-file-upload {
                display: block;
                width: 100%;
            }
            .erp-custom-file-upload .file-upload-label {
                display: flex;
                align-items: center;
                justify-content: center;
                border: 2px dashed #ced4da;
                border-radius: 6px;
                padding: 10px 15px;
                background-color: #f8fafc;
                color: #495057;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease-in-out;
                width: 100%;
            }
            .erp-custom-file-upload .file-upload-label:hover {
                background-color: #f1f5f9;
                border-color: var(--bs-primary);
                color: var(--bs-primary);
            }

            /* Borderless Select2 theme custom override for Odoo Look */
            .select2-container--bootstrap-5 .select2-selection {
                border: none !important;
                border-bottom: 1px solid #ced4da !important;
                border-radius: 0 !important;
                background-color: transparent !important;
                padding-left: 2px !important;
                height: auto !important;
                min-height: 25px !important;
            }
            .select2-container--bootstrap-5 .select2-selection:focus,
            .select2-container--bootstrap-5.select2-container--focus .select2-selection {
                border-bottom-color: var(--bs-primary) !important;
                box-shadow: none !important;
            }
            .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
                padding-left: 0 !important;
                font-size: 13px !important;
                color: #212529 !important;
            }
            
            /* Borderless Select2 theme custom override for Odoo Look in Tables */
            .odoo-table td .select2-container--bootstrap-5 .select2-selection {
                border: none !important;
                border-bottom: 1px solid transparent !important;
                border-radius: 0 !important;
                background-color: transparent !important;
                padding-left: 2px !important;
            }
            .odoo-table td .select2-container--bootstrap-5 .select2-selection:hover,
            .odoo-table td .select2-container--bootstrap-5.select2-container--focus .select2-selection {
                border-bottom-color: var(--bs-primary) !important;
            }
            
            /* Odoo style Table */
            .odoo-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
                font-size: 13px;
            }
            .odoo-table th {
                border-bottom: 2px solid #dee2e6;
                padding: 8px 4px;
                color: #6c757d;
                font-weight: 600;
                text-transform: capitalize;
            }
            .odoo-table td {
                padding: 6px 4px;
                border-bottom: 1px solid #e9ecef;
                vertical-align: middle;
            }
            .odoo-table-input {
                border: none;
                border-bottom: 1px solid transparent;
                background: transparent;
                border-radius: 0;
                padding: 4px 2px;
                width: 100%;
                font-size: 13px;
                transition: border-bottom-color 0.2s ease-in-out;
            }
            .odoo-table-input:focus {
                border-bottom-color: var(--bs-primary) !important;
                outline: none;
                box-shadow: none;
                background: transparent !important;
            }
            .odoo-table-select {
                border: none;
                border-bottom: 1px solid transparent;
                background: transparent;
                padding: 4px 2px;
                width: 100%;
                font-size: 13px;
                cursor: pointer;
                transition: border-bottom-color 0.2s ease-in-out;
            }
            .odoo-table-select:focus {
                border-bottom-color: var(--bs-primary) !important;
                outline: none;
            }

            /* Quill rich text editor custom theme for Odoo Form look */
            .odoo-editor-wrapper {
                border: 1px solid #ced4da;
                border-radius: 4px;
                background-color: #ffffff;
                transition: border-color 0.2s ease-in-out;
                width: 100%;
            }
            .odoo-editor-wrapper:focus-within {
                border-color: var(--bs-primary) !important;
            }
            .odoo-editor-wrapper .ql-toolbar.ql-snow {
                border: none;
                border-bottom: 1px solid #ced4da;
                background-color: #f8fafc;
                padding: 6px 10px;
                border-top-left-radius: 3px;
                border-top-right-radius: 3px;
            }
            .odoo-editor {
                display: flex !important;
                flex-direction: column !important;
                overflow: hidden !important;
            }
            .odoo-editor-wrapper .ql-container.ql-snow {
                border: none !important;
                font-family: inherit;
                font-size: 13px;
                color: #212529;
                display: flex !important;
                flex-direction: column !important;
                overflow: hidden !important;
                flex: 1 1 auto !important;
            }
            .odoo-editor-wrapper .ql-editor {
                min-height: 120px;
                padding: 10px;
                white-space: normal !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
                flex: 1 1 auto !important;
                overflow-y: auto !important;
                height: 100% !important;
                max-height: 100% !important;
            }

            /* Premium styled tag pills in Select2 multiple choices */
            .select2-container--bootstrap-5 .select2-selection--multiple {
                border: none !important;
                border-bottom: 1px solid #ced4da !important;
                border-radius: 0 !important;
                background-color: transparent !important;
                padding: 2px 0 !important;
                min-height: 25px !important;
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
            }
            .select2-container--bootstrap-5 .select2-selection--multiple:focus,
            .select2-container--bootstrap-5.select2-container--focus .select2-selection--multiple {
                border-bottom-color: var(--bs-primary) !important;
                box-shadow: none !important;
            }
            .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
                background-color: #f1f5f9 !important;
                border: 1px solid #cbd5e1 !important;
                border-radius: 4px !important;
                padding: 2px 8px !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                color: #334155 !important;
                margin-right: 6px !important;
                margin-top: 4px !important;
                margin-bottom: 4px !important;
                display: inline-flex !important;
                align-items: center !important;
                position: relative !important;
                flex-direction: row-reverse !important;
            }
            .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
                position: static !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: auto !important;
                height: auto !important;
                margin-left: 6px !important;
                margin-right: 0 !important;
                padding: 0 4px !important;
                border: none !important;
                background: transparent !important;
                color: var(--bs-primary) !important;
                font-size: 14px !important;
                font-weight: bold !important;
                text-indent: 0 !important;
                overflow: visible !important;
                cursor: pointer !important;
            }
            .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove span {
                display: inline !important;
            }
            .select2-container--bootstrap-5 .select2-selection--multiple .select2-search {
                display: inline-flex !important;
                align-items: center !important;
                height: auto !important;
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                flex-grow: 1 !important;
            }
            .select2-container--bootstrap-5 .select2-selection--multiple .select2-search .select2-search__field {
                height: 20px !important;
                margin: 0 !important;
                padding: 0 4px !important;
                font-size: 12px !important;
                border: none !important;
                background: transparent !important;
                width: 100% !important;
            }

            /* Error and invalid overrides */
            input.odoo-form-control.is-invalid {
                border: none !important;
                border-bottom: 1px solid #dc3545 !important;
            }
            textarea.odoo-form-control.is-invalid {
                border: 1px solid #dc3545 !important;
            }
            .is-invalid + .select2-container--bootstrap-5 .select2-selection,
            .is-invalid + .select2-container--bootstrap-5.select2-container--focus .select2-selection {
                border-bottom: 1px solid #dc3545 !important;
            }
            .odoo-editor-wrapper.is-invalid {
                border-color: #dc3545 !important;
            }
            .file-upload-label:has(.is-invalid) {
                border-color: #dc3545 !important;
                color: #dc3545 !important;
            }
        </style>
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/vendors/js/quill.min.js') }}"></script>
        <script>
            function initOdooComponents() {
                // Auto initialize any odoo-select2 dropdowns
                if ($('.odoo-select2').length && $.fn.select2) {
                    $('.odoo-select2').each(function() {
                        var select = $(this);
                        if (!select.hasClass('select2-hidden-accessible')) {
                            select.select2({
                                theme: "bootstrap-5",
                                width: "100%"
                            });
                        }
                    });
                }

                // Initialize Quill editors
                if ($('.odoo-editor').length && typeof Quill !== 'undefined') {
                    $('.odoo-editor').each(function() {
                        var container = $(this);
                        if (container.hasClass('quill-initialized')) return;
                        
                        var editorId = container.attr('id');
                        var inputId = editorId + '_input';
                        
                        var quill = new Quill('#' + editorId, {
                            theme: 'snow',
                            modules: {
                                toolbar: [
                                    [{ 'header': [1, 2, 3, false] }],
                                    ['bold', 'italic', 'underline', 'strike'],
                                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                    ['link', 'clean']
                                ]
                            }
                        });
                        
                        container.addClass('quill-initialized');
                        
                        quill.on('text-change', function() {
                            var html = quill.root.innerHTML;
                            if (html === '<p><br></p>') {
                                html = '';
                            }
                            $('#' + inputId).val(html);
                        });
                    });
                }
            }

            $(document).ready(function() {
                initOdooComponents();

                // File input text changer
                $(document).on('change', '.erp-file-input', function() {
                    var fileName = this.files[0] ? this.files[0].name : 'Click to upload...';
                    $(this).siblings('.file-text').text(fileName);
                });
            });

            // Re-initialize for modals/drawers when shown
            $(document).on('show.bs.modal show.bs.offcanvas', function () {
                setTimeout(initOdooComponents, 150);
            });
        </script>
    @endpush
@endonce

@if ($type === 'sheet')
    <div {{ $attributes->class(['odoo-sheet bg-white mb-2']) }}>
        {{ $slot }}
    </div>

@elseif ($type === 'input')
    @php
        $fieldId = $id ?? ($name ? str_replace('[]', '', $name) . '_' . uniqid() : 'input_' . uniqid());
    @endphp
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" for="{{ $fieldId }}" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <input type="{{ $inputType }}" 
                       name="{{ $name }}" 
                       id="{{ $fieldId }}"
                       value="{{ $value }}" 
                       placeholder="{{ $placeholder }}" 
                       {{ $readonly ? 'readonly' : '' }}
                       {{ $disabled ? 'disabled' : '' }}
                       {{ $attributes->class([
                           $label ? 'odoo-form-control' : 'odoo-table-input',
                           $errorText ? 'is-invalid' : ''
                       ]) }}>
                @if($errorText)
                    <div class="invalid-feedback d-block fs-11 mt-1">{{ $errorText }}</div>
                @elseif($helperText)
                    <div class="text-muted fs-11 mt-1">{{ $helperText }}</div>
                @endif
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'select')
    @php
        $fieldId = $id ?? ($name ? str_replace('[]', '', $name) . '_' . uniqid() : 'select_' . uniqid());
    @endphp
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" for="{{ $fieldId }}" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <select name="{{ $name }}" 
                        id="{{ $fieldId }}"
                         
                        {{ $multiple ? 'multiple' : '' }}
                        {{ $disabled ? 'disabled' : '' }}
                        @if($select2Selector) data-select2-selector="{{ $select2Selector }}" @endif
                        {{ $attributes->class([
                            $label ? 'odoo-form-control form-select-sm' : 'odoo-table-select', 
                            ($searchable && !$select2Selector) ? 'odoo-select2' : '',
                            $select2Selector ? 'max-select' : '',
                            $errorText ? 'is-invalid' : ''
                        ]) }}
                        style="border-radius:0;">
                    {{ $slot }}
                </select>
                @if($errorText)
                    <div class="invalid-feedback d-block fs-11 mt-1">{{ $errorText }}</div>
                @elseif($helperText)
                    <div class="text-muted fs-11 mt-1">{{ $helperText }}</div>
                @endif
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'textarea')
    @php
        $fieldId = $id ?? ($name ? str_replace('[]', '', $name) . '_' . uniqid() : 'textarea_' . uniqid());
    @endphp
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" for="{{ $fieldId }}" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <textarea name="{{ $name }}" 
                          id="{{ $fieldId }}"
                          rows="{{ $rows }}" 
                          
                          {{ $readonly ? 'readonly' : '' }}
                          {{ $disabled ? 'disabled' : '' }}
                          {{ $attributes->class(['odoo-form-control', $errorText ? 'is-invalid' : '']) }} 
                          placeholder="{{ $placeholder }}">{{ $value ?? $slot }}</textarea>
                @if($errorText)
                    <div class="invalid-feedback d-block fs-11 mt-1">{{ $errorText }}</div>
                @elseif($helperText)
                    <div class="text-muted fs-11 mt-1">{{ $helperText }}</div>
                @endif
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'checkbox')
    @php
        $fieldId = $id ?? ($name ? str_replace('[]', '', $name) . '_' . uniqid() : 'checkbox_' . uniqid());
    @endphp
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" for="{{ $fieldId }}" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <div class="form-check">
                    <input type="checkbox" 
                           name="{{ $name }}" 
                           id="{{ $fieldId }}"
                           value="{{ $value ?? '1' }}" 
                            
                           {{ $disabled ? 'disabled' : '' }}
                           {{ $attributes->class(['form-check-input', $errorText ? 'is-invalid' : '']) }}>
                    @if(isset($slot) && $slot->isNotEmpty())
                        <label class="form-check-label fs-13 text-dark ms-1" for="{{ $fieldId }}">{{ $slot }}</label>
                    @endif
                </div>
                @if($errorText)
                    <div class="invalid-feedback d-block fs-11 mt-1">{{ $errorText }}</div>
                @elseif($helperText)
                    <div class="text-muted fs-11 mt-1">{{ $helperText }}</div>
                @endif
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'radio')
    @if($label)
        <div class="odoo-form-group align-items-start">
            <label class="odoo-form-label pt-1" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
                <div class="d-flex gap-3 align-items-center">
    @endif
                    {{ $slot }}
    @if($label)
                </div>
                @if($errorText)
                    <div class="invalid-feedback d-block fs-11 mt-1">{{ $errorText }}</div>
                @elseif($helperText)
                    <div class="text-muted fs-11 mt-1">{{ $helperText }}</div>
                @endif
            </div>
        </div>
    @endif

@elseif ($type === 'file')
    @php
        $fieldId = $id ?? ($name ? str_replace('[]', '', $name) . '_' . uniqid() : 'file_' . uniqid());
    @endphp
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" for="{{ $fieldId }}" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <div class="erp-custom-file-upload">
                    <label class="file-upload-label w-100 {{ $disabled ? 'opacity-50 cursor-not-allowed' : '' }}" for="{{ $fieldId }}">
                        <i class="feather-upload-cloud me-2 text-primary fs-16"></i>
                        <span class="file-text text-muted">{{ $placeholder ?? 'Click to upload...' }}</span>
                        <input type="file" 
                               name="{{ $name }}" 
                               id="{{ $fieldId }}"
                               
                               {{ $disabled ? 'disabled' : '' }}
                               {{ $attributes->class(['erp-file-input d-none', $errorText ? 'is-invalid' : '']) }}>
                    </label>
                </div>
                @if($errorText)
                    <div class="invalid-feedback d-block fs-11 mt-1">{{ $errorText }}</div>
                @elseif($helperText)
                    <div class="text-muted fs-11 mt-1">{{ $helperText }}</div>
                @endif
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'editor' || $type === 'text-editor')
    @php
        $fieldId = $id ?? ($name ? str_replace('[]', '', $name) . '_' . uniqid() : 'editor_' . uniqid());
    @endphp
    @if($label)
        <div class="odoo-form-group align-items-start">
            <label class="odoo-form-label pt-2" for="{{ $fieldId }}" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <div class="odoo-editor-wrapper {{ $errorText ? 'is-invalid' : '' }}">
                    <div id="{{ $fieldId }}" class="odoo-editor {{ $editorHeight }}">
                        {!! $value ?? $slot !!}
                    </div>
                </div>
                <input type="hidden" name="{{ $name }}" id="{{ $fieldId }}_input" value="{{ $value ?? $slot }}">
                @if($errorText)
                    <div class="invalid-feedback d-block fs-11 mt-1">{{ $errorText }}</div>
                @elseif($helperText)
                    <div class="text-muted fs-11 mt-1">{{ $helperText }}</div>
                @endif
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'table')
    <table {{ $attributes->class(['odoo-table']) }}>
        {{ $slot }}
    </table>
@endif
