@props([
    'label' => null,
    'name' => null,
    'options' => [], // associative array value => label
    'selected' => null,
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
    <select name="{{ $name }}" 
            id="{{ $attributes->get('id') ?? $name }}" 
            {{ $disabled ? 'disabled' : '' }} 
            {{ $required ? 'required' : '' }} 
            {{ $attributes->class(['form-select erp-premium-select']) }}>
        {{ $slot }}
        @foreach($options as $val => $lbl)
            <option value="{{ $val }}" {{ $val == $selected ? 'selected' : '' }}>{{ $lbl }}</option>
        @endforeach
    </select>
    @if($helperText)
        <small class="form-text text-muted fs-11 mt-1 d-block">{{ $helperText }}</small>
    @endif
</div>
