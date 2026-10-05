{{--
    Filter bar card (the reference's "Accounting Period / Cost Center / Voucher Status" panel).
    Put filter fields in the slot as Bootstrap columns; labels render as overlines.

    <x-ui.filter-panel :action="route('x.index')" :reset-url="route('x.index')" submit-label="Filter Ledger">
        <div class="col-md-3"><label class="form-label">Period</label><select class="form-select" name="period">...</select></div>
        <x-slot:meta><span class="ax-ref">LIVE AUDIT</span> Current snapshot: ...</x-slot:meta>
    </x-ui.filter-panel>
--}}
@props([
    'action' => null,
    'method' => 'GET',
    'resetUrl' => null,
    'submitLabel' => 'Apply',
    'submitIcon' => 'feather-filter',
    'resetLabel' => 'Reset',
    'formId' => null,
])

<form @if ($action) action="{{ $action }}" @endif method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}"
      @if ($formId) id="{{ $formId }}" @endif {{ $attributes->class(['ax-filter-panel']) }}>
    @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
        @method($method)
    @endif
    @if (strtoupper($method) !== 'GET')
        @csrf
    @endif
    <div class="row g-3 align-items-end">
        {{ $slot }}
        <div class="col-auto d-flex gap-2 ms-auto">
            <button type="submit" class="btn btn-dark"><i class="{{ $submitIcon }}"></i>{{ $submitLabel }}</button>
            @if ($resetUrl)
                <a href="{{ $resetUrl }}" class="btn btn-light">{{ $resetLabel }}</a>
            @endif
        </div>
    </div>
    @isset($meta)
        <div class="ax-filter-meta">{{ $meta }}</div>
    @endisset
</form>
