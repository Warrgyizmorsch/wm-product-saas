@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'disabled' => false,
    'required' => false,
    'helperText' => null,
    'stacked' => false,   // Label above the field instead of the label column
])

{{-- Layout (label column, spacing, 36px control) comes from .ax-form-row in public/assets/css/apex-ui.css. --}}
@php($fieldId = $attributes->get('id') ?? $name)

<div class="ax-field">
<div @class(['ax-form-row' => $label, 'ax-form-row-stacked' => $label && $stacked])>
    @if($label)
        <label for="{{ $fieldId }}" class="ax-form-label">
            {{ $label }}@if($required)<span class="ax-required">*</span>@endif
        </label>
    @endif
    <div class="ax-form-control">
        <input type="{{ $type }}"
               name="{{ $name }}"
               id="{{ $fieldId }}"
               value="{{ $value }}"
               placeholder="{{ $placeholder }}"
               {{ $disabled ? 'disabled' : '' }}
               {{ $required ? 'required' : '' }}
               {{ $attributes->class(['form-control erp-premium-input']) }}>
        @if($helperText)
            <small class="form-text text-muted fs-11 mt-1 d-block">{{ $helperText }}</small>
        @endif
    </div>
</div>
</div>
