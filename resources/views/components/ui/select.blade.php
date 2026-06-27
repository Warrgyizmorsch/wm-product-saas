@props([
    'label'      => null,
    'name'       => null,
    'options'    => [],      // associative array value => label
    'selected'   => null,
    'disabled'   => false,
    'required'   => false,
    'helperText' => null,
    'master'     => null     // When set, adds "Add New" option that opens the master modal
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
            @if($master) data-master="{{ $master }}" @endif
            {{ $attributes->class(['form-select erp-premium-select']) }}>

        {{-- Slot: custom options (e.g. dynamic Alpine rows) --}}
        @if(isset($slot) && $slot->isNotEmpty())
            {{ $slot }}
        @endif

        {{-- Quick-create "Add New" trigger --}}
        @if($master)
            <option value="__ADD_NEW__" class="fw-bold text-primary">+ Add New {{ ucwords(str_replace('_', ' ', $master)) }}</option>
        @endif

        {{-- Static options from prop --}}
        @foreach($options as $val => $lbl)
            <option value="{{ $val }}" {{ (string)$val === (string)$selected ? 'selected' : '' }}>{{ $lbl }}</option>
        @endforeach
    </select>

    @if($helperText)
        <small class="form-text text-muted fs-11 mt-1 d-block">{{ $helperText }}</small>
    @endif
</div>
