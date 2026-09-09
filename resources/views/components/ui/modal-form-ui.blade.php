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
    'alpineError' => null
])

@once
    @push('styles')
        <style>
            .form-ui-group {
                margin-bottom: 0.85rem;
                text-align: left;
            }
            .form-ui-label {
                display: block;
                font-size: 12px;
                font-weight: 700;
                color: #334155;
                margin-bottom: 6px;
            }
            .form-ui-control {
                display: block;
                width: 100%;
                height: 42px;
                padding: 0.45rem 0.75rem;
                font-size: 13px;
                font-weight: 400;
                line-height: 1.5;
                color: #1e293b;
                background-color: #ffffff;
                background-clip: padding-box;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
            }
            textarea.form-ui-control {
                height: auto !important;
            }
            .form-ui-control:focus {
                border-color: #3b82f6;
                outline: 0;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
            }
            .form-ui-control[readonly], .form-ui-control[disabled] {
                background-color: #f8fafc;
                opacity: 0.8;
            }
            .form-ui-control.is-invalid {
                border-color: #ef4444 !important;
            }
            .form-ui-control.is-invalid:focus {
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
            }
            select.form-ui-control {
                appearance: auto;
                cursor: pointer;
                background-color: #ffffff;
            }
            
            /* Select2 Container Overrides for modal-form-ui */
            .form-ui-select2-container {
                width: 100%;
            }
            .form-ui-select2-container .select2-container--bootstrap-5 {
                display: block;
                width: 100% !important;
            }
            .form-ui-select2-container .select2-container--bootstrap-5 .select2-selection--single {
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                height: 42px !important;
                min-height: 42px !important;
                background-color: #ffffff !important;
                padding: 0.35rem 0.5rem !important;
                display: flex !important;
                align-items: center !important;
            }
            .form-ui-select2-container .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
                color: #1e293b !important;
                font-size: 13px !important;
                line-height: 40px !important;
                padding-left: 4px !important;
                margin-top: 0 !important;
            }
            .form-ui-select2-container .select2-container--bootstrap-5.select2-container--focus .select2-selection,
            .form-ui-select2-container .select2-container--bootstrap-5.select2-container--open .select2-selection {
                border-color: #3b82f6 !important;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12) !important;
            }
            .form-ui-select2-container .select2-container--bootstrap-5 .select2-selection--multiple {
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                min-height: 42px !important;
                background-color: #ffffff !important;
                padding: 0.25rem 0.5rem !important;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            function initFormUiComponents() {
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    $('.form-ui-select2').each(function() {
                        var select = $(this);
                        if (!select.hasClass('select2-hidden-accessible')) {
                            var parentModal = select.closest('.modal');
                            select.select2({
                                theme: "bootstrap-5",
                                width: "100%",
                                dropdownParent: parentModal.length ? parentModal : $(document.body)
                            });
                        }
                    });
                }
            }

            $(document).ready(function() {
                initFormUiComponents();
            });

            $(document).on('show.bs.modal show.bs.offcanvas', function () {
                setTimeout(initFormUiComponents, 150);
            });
        </script>
    @endpush
@endonce

@php
    $fieldId = $id ?? ($name ? str_replace(['[]', '[', ']'], ['_', '_', ''], $name) . '_' . uniqid() : 'modal_form_ui_' . uniqid());
@endphp

<div class="form-ui-group {{ $attributes->get('class') }}">
    @if($label && $type !== 'checkbox' && $type !== 'switch')
        <label class="form-ui-label" for="{{ $fieldId }}">
            {!! $label !!} @if($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    @if ($type === 'input')
        <input type="{{ $inputType }}" 
               name="{{ $name }}" 
               id="{{ $fieldId }}"
               value="{{ $value }}" 
               placeholder="{{ $placeholder }}" 
               {{ $required ? 'required' : '' }} 
               {{ $readonly ? 'readonly' : '' }}
               {{ $disabled ? 'disabled' : '' }}
               {{ $attributes->except(['class', 'type', 'label', 'inputType', 'value', 'placeholder', 'required', 'readonly', 'disabled', 'rows', 'searchable', 'multiple', 'select2Selector', 'helperText', 'errorText', 'alpineError']) }}
               class="form-ui-control {{ $errorText ? 'is-invalid' : '' }}"
               @if($alpineError) :class="{{ $alpineError }} ? 'is-invalid' : ''" @endif>

    @elseif ($type === 'select')
        <div class="{{ ($searchable || $select2Selector) ? 'form-ui-select2-container' : '' }}">
            <select name="{{ $name }}" 
                    id="{{ $fieldId }}"
                    {{ $required ? 'required' : '' }} 
                    {{ $multiple ? 'multiple' : '' }}
                    {{ $disabled ? 'disabled' : '' }}
                    @if($select2Selector) data-select2-selector="{{ $select2Selector }}" @endif
                    {{ $attributes->except(['class', 'type', 'label', 'name', 'id', 'required', 'multiple', 'disabled', 'searchable', 'select2Selector', 'helperText', 'errorText', 'alpineError']) }}
                    class="form-ui-control {{ ($searchable && !$select2Selector) ? 'form-ui-select2' : '' }} {{ $select2Selector ? $select2Selector : '' }} {{ $errorText ? 'is-invalid' : '' }}"
                    @if($alpineError) :class="{{ $alpineError }} ? 'is-invalid' : ''" @endif>
                {{ $slot }}
            </select>
        </div>

    @elseif ($type === 'textarea')
        <textarea name="{{ $name }}" 
                  id="{{ $fieldId }}"
                  rows="{{ $rows }}" 
                  {{ $required ? 'required' : '' }}
                  {{ $readonly ? 'readonly' : '' }}
                  {{ $disabled ? 'disabled' : '' }}
                  {{ $attributes->except(['class', 'type', 'label', 'name', 'id', 'rows', 'required', 'readonly', 'disabled', 'helperText', 'errorText', 'alpineError']) }}
                  class="form-ui-control {{ $errorText ? 'is-invalid' : '' }}" 
                  @if($alpineError) :class="{{ $alpineError }} ? 'is-invalid' : ''" @endif
                  placeholder="{{ $placeholder }}">{{ $value ?? $slot }}</textarea>

    @elseif ($type === 'switch' || $type === 'checkbox')
        <div class="form-check {{ $type === 'switch' ? 'form-switch' : '' }}">
            <input class="form-check-input" 
                   type="checkbox" 
                   name="{{ $name }}" 
                   id="{{ $fieldId }}"
                   value="{{ $value ?? '1' }}"
                   {{ $value == '1' || $attributes->get('checked') ? 'checked' : '' }}
                   {{ $required ? 'required' : '' }}
                   {{ $disabled ? 'disabled' : '' }}>
            @if($label)
                <label class="form-check-label fw-semibold fs-12 text-dark" for="{{ $fieldId }}">
                    {!! $label !!}
                </label>
            @endif
        </div>
    @endif

    @if($alpineError)
        <template x-if="{{ $alpineError }}">
            <div class="invalid-feedback d-block fs-11 mt-1" x-text="Array.isArray({{ $alpineError }}) ? {{ $alpineError }}[0] : {{ $alpineError }}"></div>
        </template>
    @endif
    @if($errorText)
        <div class="invalid-feedback d-block fs-11 mt-1" @if($alpineError) x-show="!{{ $alpineError }}" @endif>{{ $errorText }}</div>
    @elseif($helperText)
        <div class="text-muted fs-11 mt-1" @if($alpineError) x-show="!{{ $alpineError }}" @endif>{{ $helperText }}</div>
    @endif
</div>
