@props([
    'label' => null,
    'name' => null,
    'value',
    'checked' => false,
    'disabled' => false,
    'id' => null
])

@php
    $elementId = $id ?? ($name ? $name . '_' . $value : 'radio_' . uniqid());
@endphp

<div class="form-check erp-premium-radio">
    <input class="form-check-input" 
           type="radio" 
           name="{{ $name }}" 
           id="{{ $elementId }}" 
           value="{{ $value }}" 
           {{ $checked ? 'checked' : '' }} 
           {{ $disabled ? 'disabled' : '' }} 
           {{ $attributes }}>
    @if($label)
        <label class="form-check-label c-pointer fw-medium text-dark fs-13 ms-1" for="{{ $elementId }}">
            {{ $label }}
        </label>
    @endif
</div>
