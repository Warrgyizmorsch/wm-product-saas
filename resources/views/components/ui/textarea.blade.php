@props([
    'label' => null,
    'name' => null,
    'value' => null,
    'placeholder' => null,
    'rows' => 3,
    'disabled' => false,
    'required' => false,
    'helperText' => null,
    'stacked' => false,   // Label above the field instead of the label column
])

{{-- Layout comes from .ax-form-row in public/assets/css/apex-ui.css. --}}
@php($fieldId = $attributes->get('id') ?? $name)

<div class="ax-field">
<div @class(['ax-form-row ax-form-row-top' => $label, 'ax-form-row-stacked' => $label && $stacked, 'mb-3' => ! $label])>
    @if($label)
        <label for="{{ $fieldId }}" class="ax-form-label">
            {{ $label }}@if($required)<span class="ax-required">*</span>@endif
        </label>
    @endif
    <div class="ax-form-control">
        <textarea name="{{ $name }}"
                  id="{{ $fieldId }}"
                  placeholder="{{ $placeholder }}"
                  rows="{{ $rows }}"
                  {{ $disabled ? 'disabled' : '' }}
                  {{ $required ? 'required' : '' }}
                  {{ $attributes->class(['form-control erp-premium-input']) }}>{{ $value }}</textarea>
        @if($helperText)
            <small class="form-text text-muted fs-11 mt-1 d-block">{{ $helperText }}</small>
        @endif
    </div>
</div>
</div>
