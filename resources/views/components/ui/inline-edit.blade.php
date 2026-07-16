@props(['field', 'value', 'url', 'type' => 'text', 'options' => [], 'label' => null])
@php
    $dateValue = $type === 'date' && $value ? \Illuminate\Support\Carbon::parse($value) : null;
    $isEmpty = $type === 'date' ? $dateValue === null : ($value === null || $value === '');
    $displayValue = match (true) {
        $type === 'date' => $dateValue?->format('d/m/Y'),
        $type === 'select', $type === 'select2' => $options[$value] ?? $value,
        default => $value,
    };
    $controlValue = $type === 'date' ? $dateValue?->format('Y-m-d') : $value;
    $placeholder = $label ? __('ui.inline_edit_add_field', ['field' => $label]) : __('ui.inline_edit_add_value');
    $editTooltip = __('ui.inline_edit_edit_tooltip');
    $saveTooltip = __('ui.inline_edit_save_tooltip');
    $cancelTooltip = __('ui.inline_edit_cancel_tooltip');
@endphp

<span class="inline-edit" data-field="{{ $field }}" data-url="{{ $url }}" data-type="{{ $type }}" data-empty-placeholder="{{ $placeholder }}">
    <span
        class="inline-edit__view @if ($isEmpty) inline-edit__view--empty @endif"
        tabindex="0"
        role="button"
        aria-label="{{ $editTooltip }} {{ $label ?? $field }}"
        title="{{ $editTooltip }}"
        data-bs-toggle="tooltip"
    ><span class="inline-edit__text">{{ $isEmpty ? $placeholder : $displayValue }}</span><i class="feather-edit inline-edit__pencil" aria-hidden="true"></i></span>
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
        <span class="inline-edit__actions d-inline-flex align-items-center gap-1 ms-1">
            <button
                type="button"
                class="inline-edit__action inline-edit__action--save"
                title="{{ $saveTooltip }}"
                aria-label="{{ $saveTooltip }}"
                data-bs-toggle="tooltip"
            ><i class="feather-check" aria-hidden="true"></i></button>
            <button
                type="button"
                class="inline-edit__action inline-edit__action--cancel"
                title="{{ $cancelTooltip }}"
                aria-label="{{ $cancelTooltip }}"
                data-bs-toggle="tooltip"
            ><i class="feather-x" aria-hidden="true"></i></button>
        </span>
        <div class="inline-edit__error invalid-feedback d-block d-none"></div>
    </span>
</span>

@once
    <style>
        .inline-edit__view {
            display: inline-block;
            position: relative;
            cursor: pointer;
            border-radius: 4px;
            padding: 2px 22px 2px 6px;
            margin: -2px -22px -2px -6px;
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

        /* Reserved via absolute positioning so it never shifts the text; only opacity fades on hover/focus. */
        .inline-edit__pencil {
            position: absolute;
            top: 50%;
            right: 6px;
            transform: translateY(-50%);
            font-size: 12px;
            color: var(--bs-secondary-color, #6c757d);
            opacity: 0;
            transition: opacity 0.18s ease;
            pointer-events: none;
        }

        .inline-edit__view:hover .inline-edit__pencil,
        .inline-edit__view:focus-visible .inline-edit__pencil {
            opacity: 1;
        }

        .inline-edit__edit {
            display: inline-block;
            min-width: 220px;
        }

        .inline-edit__action {
            background: none;
            border: 0;
            padding: 0;
            line-height: 1;
            font-size: 13px;
            color: var(--bs-secondary-color, #6c757d);
            transition: color 0.15s ease;
        }

        .inline-edit__action:focus-visible {
            outline: 2px solid var(--bs-primary);
            outline-offset: 2px;
            border-radius: 2px;
        }

        /* Matches the success/danger text tones already used by .erp-icon-btn--success/--danger. */
        .inline-edit__action--save:hover,
        .inline-edit__action--save:focus-visible {
            color: #4a8f6f;
        }

        .inline-edit__action--cancel:hover,
        .inline-edit__action--cancel:focus-visible {
            color: #e76f51;
        }

        .inline-edit[data-type="textarea"] {
            display: block;
        }

        .inline-edit[data-type="textarea"] .inline-edit__view {
            display: block;
            white-space: pre-wrap;
        }

        /* Multi-line text: anchor to the top-right corner instead of vertically centering across the whole block. */
        .inline-edit[data-type="textarea"] .inline-edit__pencil {
            top: 2px;
            transform: none;
        }

        .inline-edit[data-type="textarea"] .inline-edit__edit {
            display: block;
            width: 100%;
        }

        .inline-edit[data-type="textarea"] .inline-edit__actions {
            margin-left: 0;
            margin-top: .375rem;
        }

        .inline-edit.is-saving .inline-edit__control {
            opacity: 0.6;
        }
    </style>
@endonce
