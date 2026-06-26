@props([
    'label' => null,
    'name' => null,
    'value' => 1,
    'checked' => false,
    'disabled' => false,
    'id' => null
])

@php
    $elementId = $id ?? $name ?? 'checkbox_' . uniqid();
@endphp

<div class="form-check erp-premium-checkbox">
    <input class="form-check-input" 
           type="checkbox" 
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
