@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'disabled' => false,
    'required' => false,
    'helperText' => null
])

<div class="mb-3">
    @if($label)
        <label for="{{ $attributes->get('id') ?? $name }}" class="form-label fw-semibold text-dark fs-12 text-uppercase mb-2">
            {{ $label }} @if($required)<span class="text-danger">*</span>@endif
        </label>
    @endif
    <input type="{{ $type }}" 
           name="{{ $name }}" 
           id="{{ $attributes->get('id') ?? $name }}" 
           value="{{ $value }}" 
           placeholder="{{ $placeholder }}" 
           {{ $disabled ? 'disabled' : '' }} 
           {{ $required ? 'required' : '' }} 
           {{ $attributes->class(['form-control erp-premium-input']) }}>
    @if($helperText)
        <small class="form-text text-muted fs-11 mt-1 d-block">{{ $helperText }}</small>
    @endif
</div>
