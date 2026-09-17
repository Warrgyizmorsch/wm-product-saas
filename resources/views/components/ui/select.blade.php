@props([
    'label'      => null,
    'name'       => null,
    'options'    => [],      // associative array value => label
    'selected'   => null,
    'disabled'   => false,
    'required'   => false,
    'helperText' => null,
    'master'     => null,    // When set, adds "Add New" option that opens the master modal
    'stacked'    => false,   // Label-above layout instead of the fixed 4/8 row — use inside narrow columns (modals, drawers) so the label doesn't wrap/crowd the field
])

<div class="mb-3">
    @if($label && $stacked)
        <label for="{{ $attributes->get('id') ?? $name }}" class="form-label fw-semibold fs-13 text-dark mb-2">
            {{ $label }} @if($required)<span class="text-danger">*</span>@endif
        </label>
        <select name="{{ $name }}"
                id="{{ $attributes->get('id') ?? $name }}"
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
    @elseif($label)
        <div class="row align-items-center">
            <div class="col-md-4">
                <label for="{{ $attributes->get('id') ?? $name }}" class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark" style="{{ $required ? 'color: #b91c1c !important;' : '' }}">
                    {{ $label }} @if($required)<span class="text-danger">*</span>@endif
                </label>
            </div>
            <div class="col-md-8">
                <select name="{{ $name }}"
                        id="{{ $attributes->get('id') ?? $name }}"
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
    @else
        <select name="{{ $name }}"
                id="{{ $attributes->get('id') ?? $name }}"
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
    @endif
</div>
