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
                border: 1px solid #dee2e6;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                padding: 24px;
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
            }
            .odoo-form-control:focus {
                border-color: #714B67;
                outline: none;
                box-shadow: none;
            }
            .odoo-form-control[readonly] {
                border-bottom: none;
                background-color: transparent;
                font-weight: bold;
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
                border-bottom-color: #714B67 !important;
                box-shadow: none !important;
            }
            .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
                padding-left: 0 !important;
                font-size: 13px !important;
                color: #212529 !important;
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
            }
            .odoo-table-input:focus {
                border-bottom-color: #714B67;
                outline: none;
                box-shadow: none;
                background: transparent !important;
            }
            .odoo-table-select {
                border: none;
                background: transparent;
                padding: 4px 2px;
                width: 100%;
                font-size: 13px;
                cursor: pointer;
            }
            .odoo-table-select:focus {
                border-bottom: 1px solid #714B67;
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
            });
        </script>
    @endpush
@endonce

@if ($type === 'sheet')
    <div {{ $attributes->class(['odoo-sheet border rounded shadow-sm bg-white mb-4']) }}>
        {{ $slot }}
    </div>

@elseif ($type === 'input')
    <div class="odoo-form-group">
        <label class="odoo-form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
        <div class="flex-grow-1">
            <input type="{{ $inputType }}" 
                   name="{{ $name }}" 
                   value="{{ $value }}" 
                   placeholder="{{ $placeholder }}" 
                   {{ $required ? 'required' : '' }} 
                   {{ $readonly ? 'readonly' : '' }}
                   {{ $attributes->class(['odoo-form-control']) }}>
        </div>
    </div>

@elseif ($type === 'select')
    <div class="odoo-form-group">
        <label class="odoo-form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
        <div class="flex-grow-1">
            <select name="{{ $name }}" 
                    {{ $required ? 'required' : '' }} 
                    {{ $attributes->class(['odoo-form-control form-select-sm', $searchable ? 'odoo-select2' : '']) }}
                    style="border-radius:0;">
                {{ $slot }}
            </select>
        </div>
    </div>

@elseif ($type === 'textarea')
    <div class="odoo-form-group">
        <label class="odoo-form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
        <div class="flex-grow-1">
            <textarea name="{{ $name }}" 
                      rows="{{ $rows }}" 
                      {{ $required ? 'required' : '' }}
                      {{ $attributes->class(['odoo-form-control']) }} 
                      style="border: 1px solid #ced4da; padding: 4px; border-radius: 4px;" 
                      placeholder="{{ $placeholder }}">{{ $slot }}</textarea>
        </div>
    </div>

@elseif ($type === 'table')
    <table {{ $attributes->class(['odoo-table']) }}>
        {{ $slot }}
    </table>
@endif
