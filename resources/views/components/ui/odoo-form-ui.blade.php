@props([
    'type' => 'input',
    'label' => null,
    'name' => null,
    'inputType' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'readonly' => false,
    'rows' => 3,
    'searchable' => true
])

@once
    @push('styles')
        <style>
            .odoo-sheet {
                background: #ffffff;
            }
            .odoo-form-group {
                display: flex;
                align-items: center;
                margin-bottom: 10px;
            }
            .odoo-form-label {
                width: 130px;
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
        </style>
    @endpush
    @push('scripts')
        <script>
            $(document).ready(function() {
                // Auto initialize any odoo-select2 dropdowns
                if ($('.odoo-select2').length && $.fn.select2) {
                    $('.odoo-select2').select2({
                        theme: "bootstrap-5",
                        width: "100%"
                    });
                }

                // File input text changer
                $(document).on('change', '.erp-file-input', function() {
                    var fileName = this.files[0] ? this.files[0].name : 'Click to upload...';
                    $(this).siblings('.file-text').text(fileName);
                });
            });
        </script>
    @endpush
@endonce

@if ($type === 'sheet')
    <div {{ $attributes->class(['odoo-sheet bg-white mb-2']) }}>
        {{ $slot }}
    </div>

@elseif ($type === 'input')
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <input type="{{ $inputType }}" 
                       name="{{ $name }}" 
                       value="{{ $value }}" 
                       placeholder="{{ $placeholder }}" 
                       {{ $required ? 'required' : '' }} 
                       {{ $readonly ? 'readonly' : '' }}
                       {{ $attributes->class([$label ? 'odoo-form-control' : 'odoo-table-input']) }}>
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'select')
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <select name="{{ $name }}" 
                        {{ $required ? 'required' : '' }} 
                        {{ $attributes->class([$label ? 'odoo-form-control form-select-sm' : 'odoo-table-select', $searchable ? 'odoo-select2' : '']) }}
                        style="border-radius:0;">
                    {{ $slot }}
                </select>
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'textarea')
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <textarea name="{{ $name }}" 
                          rows="{{ $rows }}" 
                          {{ $required ? 'required' : '' }}
                          {{ $attributes->class(['odoo-form-control']) }} 
                          placeholder="{{ $placeholder }}">{{ $slot }}</textarea>
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'checkbox')
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <div class="form-check">
                    <input type="checkbox" 
                           name="{{ $name }}" 
                           value="{{ $value ?? '1' }}" 
                           {{ $required ? 'required' : '' }} 
                           {{ $attributes->class(['form-check-input']) }}>
                    @if(isset($slot) && $slot->isNotEmpty())
                        <label class="form-check-label fs-13 text-dark ms-1">{{ $slot }}</label>
                    @endif
                </div>
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'radio')
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1 d-flex gap-3">
    @endif
                {{ $slot }}
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'file')
    @if($label)
        <div class="odoo-form-group">
            <label class="odoo-form-label" style="{{ $required ? 'color: #dc3545 !important;' : '' }}">
                {{ $label }} @if($required)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1">
    @endif
                <div class="erp-custom-file-upload">
                    <label class="file-upload-label w-100">
                        <i class="feather-upload-cloud me-2 text-primary fs-16"></i>
                        <span class="file-text text-muted">{{ $placeholder ?? 'Click to upload...' }}</span>
                        <input type="file" 
                               name="{{ $name }}" 
                               {{ $required ? 'required' : '' }}
                               {{ $attributes->class(['erp-file-input d-none']) }}>
                    </label>
                </div>
    @if($label)
            </div>
        </div>
    @endif

@elseif ($type === 'table')
    <table {{ $attributes->class(['odoo-table']) }}>
        {{ $slot }}
    </table>
@endif
