@props([
    'label' => null,
    'name' => null,
    'icon' => 'feather-hash',
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'helperText' => null,
])

@once
    @push('styles')
        <style>
            .erp-icon-input .input-group-text {
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                border-right: none;
                border-radius: 8px 0 0 8px;
                color: #94a3b8;
                width: 42px;
                justify-content: center;
            }
            .erp-icon-input .form-control {
                border: 1px solid #e2e8f0;
                border-left: none;
                border-radius: 0 8px 8px 0 !important;
                font-size: 13px;
                padding: 10px 12px;
            }
            .erp-icon-input .form-control:focus {
                border-color: var(--bs-primary);
                box-shadow: none;
            }
            .erp-icon-input:focus-within .input-group-text {
                border-color: var(--bs-primary);
                color: var(--bs-primary);
            }
        </style>
    @endpush
@endonce

<div class="mb-3">
    @if($label)
        <label for="{{ $attributes->get('id') ?? $name }}" class="form-label fw-semibold fs-13 text-dark mb-2">
            {{ $label }} @if($required)<span class="text-danger">*</span>@endif
        </label>
    @endif
    <div class="input-group erp-icon-input">
        <span class="input-group-text"><i class="{{ $icon }}"></i></span>
        <input type="{{ $type }}"
               name="{{ $name }}"
               id="{{ $attributes->get('id') ?? $name }}"
               value="{{ $value }}"
               placeholder="{{ $placeholder }}"
               {{ $required ? 'required' : '' }}
               {{ $attributes->class(['form-control']) }}>
    </div>
    @if($helperText)
        <small class="form-text text-muted fs-11 mt-1 d-block">{{ $helperText }}</small>
    @endif
</div>
