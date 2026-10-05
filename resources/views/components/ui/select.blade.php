@props([
    'label'      => null,
    'name'       => null,
    'options'    => [],      // associative array value => label
    'selected'   => null,
    'disabled'   => false,
    'required'   => false,
    'helperText' => null,
    'master'     => null,    // When set, adds "Add New" option that opens the master modal
    'stacked'    => false,   // Label above the field instead of the label column
])

{{-- Layout comes from .ax-form-row in public/assets/css/apex-ui.css. --}}
@php($fieldId = $attributes->get('id') ?? $name)

<div class="ax-field">
<div @class(['ax-form-row' => $label, 'ax-form-row-stacked' => $label && $stacked, 'mb-3' => ! $label])>
    @if($label)
        <label for="{{ $fieldId }}" class="ax-form-label">
            {{ $label }}@if($required)<span class="ax-required">*</span>@endif
        </label>
    @endif
    <div class="ax-form-control">
        <select name="{{ $name }}"
                id="{{ $fieldId }}"
                {{ $disabled ? 'disabled' : '' }}
                {{ $required ? 'required' : '' }}
                @if($master) data-master="{{ $master }}" @endif
                {{ $attributes->class(['form-select erp-premium-select']) }}>
            @if(isset($slot) && $slot->isNotEmpty())
                {{ $slot }}
            @endif
            @if($master)
                <option value="__ADD_NEW__" class="fw-bold text-primary">+ Add New {{ ucwords(str_replace('_', ' ', $master)) }}</option>
            @endif
            @foreach($options as $val => $lbl)
                <option value="{{ $val }}" {{ (string)$val === (string)$selected ? 'selected' : '' }}>{{ $lbl }}</option>
            @endforeach
        </select>
        @if($helperText)
            <small class="form-text text-muted fs-11 mt-1 d-block">{{ $helperText }}</small>
        @endif
    </div>
</div>
</div>
