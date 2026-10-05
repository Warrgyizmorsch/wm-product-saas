@props([
    'action' => null,
    'method' => 'GET',
    'resetUrl' => null,
    'searchLabel' => 'Search',
    'resetLabel' => 'Reset',
    'formId' => 'listFilterForm',
])

@once
    @push('styles')
        <style>
            /* Scoped to the filter toolbar only — a global .btn override would
               resize icon buttons, pagination, and every other button in the app.
               form-control-sm / form-select-sm render at 31px tall in this theme;
               match that exactly so Search/Reset sit flush with the inputs beside them
               instead of floating a few px off. */
            .ui-filter-toolbar .col-auto .btn {
                height: 40px;
                display: inline-flex;
                align-items: center;
                padding: 0 20px !important;
            }

            /* x-ui.input / x-ui.select wrap themselves in .mb-3 — inside the toolbar that
               margin lifts them above plain selects and the buttons. */
            .ui-filter-toolbar .mb-3 {
                margin-bottom: 0 !important;
            }
        </style>
    @endpush
@endonce

<form
    @if($action) action="{{ $action }}" @endif
    method="{{ $method }}"
    id="{{ $formId }}"
    {{ $attributes->class(['ui-filter-toolbar']) }}
>
    <div class="row g-3 align-items-end">
        {{ $slot }}
        <div class="col-auto d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary">{{ $searchLabel }}</button>
            @if($resetUrl)
                <a href="{{ $resetUrl }}" class="btn btn-sm btn-light border">{{ $resetLabel }}</a>
            @endif
        </div>
    </div>
</form>
