@props([
    'label' => null,
    'name' => null,
    'options' => [], // associative array value => label
    'selected' => null,
    'disabled' => false,
    'required' => false,
    'helperText' => null,
    'master' => null
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
        
        @if($master)
            <option value="">Select {{ ucwords(str_replace('_', ' ', $master)) }}</option>
            <option value="__ADD_NEW__" class="fw-bold text-primary">+ Add New {{ ucwords(str_replace('_', ' ', $master)) }}</option>
        @else
            @if(isset($slot) && $slot->isNotEmpty())
                {{ $slot }}
            @endif
        @endif
        
        @foreach($options as $val => $lbl)
            <option value="{{ $val }}" {{ $val == $selected ? 'selected' : '' }}>{{ $lbl }}</option>
        @endforeach
    </select>
    @if($helperText)
        <small class="form-text text-muted fs-11 mt-1 d-block">{{ $helperText }}</small>
    @endif

    @if($master && isset($slot) && $slot->isNotEmpty())
        <x-ui.modal id="quickCreateModal_{{ $master }}" title="Quick Create {{ ucwords(str_replace('_', ' ', $master)) }}" submit-text="Save {{ ucwords(str_replace('_', ' ', $master)) }}">
            <div data-action="{{ route(\Illuminate\Support\Str::plural($master) . '.quick-create') }}" class="quick-create-form animate-inputs" id="quickCreateForm_{{ $master }}">
                @csrf
                {{ $slot }}
            </div>
            <x-slot name="footer">
                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-save-master" data-form="quickCreateForm_{{ $master }}">Save {{ ucwords(str_replace('_', ' ', $master)) }}</button>
            </x-slot>
        </x-ui.modal>
    @endif
</div>
