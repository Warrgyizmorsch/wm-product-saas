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

<!-- <div class="mb-3"> -->
<div>
    @if($label)
        <div class="row align-items-center">
            <div class="col-md-4">
                <label for="{{ $attributes->get('id') ?? $name }}" class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark" style="{{ $required ? 'color: #b91c1c !important;' : '' }}">
                    {{ $label }} @if($required)<span class="text-danger">*</span>@endif
                </label>
            </div>
            <div class="col-md-8">
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
        </div>
    @else
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
    @endif
</div>
