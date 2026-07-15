@props(['field', 'value', 'url', 'type' => 'text', 'options' => []])
@php
    $dateValue = $type === 'date' && $value ? \Illuminate\Support\Carbon::parse($value) : null;
    $isEmpty = $type === 'date' ? $dateValue === null : ($value === null || $value === '');
    $displayValue = match (true) {
        $type === 'date' => $dateValue?->format('d/m/Y'),
        $type === 'select', $type === 'select2' => $options[$value] ?? $value,
        default => $value,
    };
    $controlValue = $type === 'date' ? $dateValue?->format('Y-m-d') : $value;
    $placeholder = __('ui.inline_edit_add_value');
@endphp

<span class="inline-edit" data-field="{{ $field }}" data-url="{{ $url }}" data-type="{{ $type }}" data-empty-placeholder="{{ $placeholder }}">
    <span class="inline-edit__view @if ($isEmpty) inline-edit__view--empty @endif" tabindex="0" role="button" aria-label="Edit {{ $field }}">{{ $isEmpty ? $placeholder : $displayValue }}</span>
    <span class="inline-edit__edit d-none">
        @if ($type === 'number')
            <input type="number" step="0.01" class="form-control form-control-sm inline-edit__control" value="{{ $value }}">
        @elseif ($type === 'date')
            <input type="date" class="form-control form-control-sm inline-edit__control" value="{{ $controlValue }}">
        @elseif ($type === 'select')
            <select class="form-select form-select-sm inline-edit__control">
                @foreach ($options as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected($optionValue === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif ($type === 'select2')
            <select class="form-select form-select-sm inline-edit__control">
                @foreach ($options as $optionValue => $optionLabel)
                    {{-- Loose comparison: option keys include a '' placeholder for nullable FKs, and null == '' in PHP. --}}
                    <option value="{{ $optionValue }}" @selected($optionValue == $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif ($type === 'textarea')
            <textarea class="form-control form-control-sm inline-edit__control" rows="3">{{ $value }}</textarea>
        @else
            <input type="text" class="form-control form-control-sm inline-edit__control" value="{{ $value }}">
        @endif
        <div class="inline-edit__error invalid-feedback d-block d-none"></div>
    </span>
</span>

@once
    <style>
        .inline-edit__view {
            display: inline-block;
            cursor: pointer;
            border-radius: 4px;
            padding: 2px 6px;
            margin: -2px -6px;
        }

        .inline-edit__view:hover,
        .inline-edit__view:focus-visible {
            background-color: rgba(0, 0, 0, 0.05);
            outline: none;
        }

        .inline-edit__view--empty {
            color: #adb5bd;
            font-style: italic;
        }

        .inline-edit__edit {
            display: inline-block;
            min-width: 220px;
        }

        .inline-edit[data-type="textarea"] {
            display: block;
        }

        .inline-edit[data-type="textarea"] .inline-edit__view {
            display: block;
            white-space: pre-wrap;
        }

        .inline-edit[data-type="textarea"] .inline-edit__edit {
            display: block;
            width: 100%;
        }

        .inline-edit.is-saving .inline-edit__control {
            opacity: 0.6;
        }
    </style>
@endonce
