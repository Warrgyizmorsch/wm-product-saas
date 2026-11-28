{{-- SF-1 (docs/production_ui_rules_review_2026-11-27.md): an outsourced operation has only Vendor + three times (days + hours,
     calendar time). What the vendor does goes in the operation's description / instructions; what is sent is decided in the BOM. --}}
<div class="col-md-3">
    <x-ui.odoo-form-ui type="select" label="Vendor *" x-bind:name="'operations['+index+'][vendor_id]'" class="form-select form-select-sm fs-11" x-model="operation.vendor_id" x-bind:required="operation.is_external" x-bind:disabled="!operation.is_external">
        <option value="">Select Vendor</option>
        @foreach($vendors as $v)
            <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->code }})</option>
        @endforeach
    </x-ui.odoo-form-ui>
    <div class="fs-10 text-muted mt-1"><i class="feather-info me-1" title="Who does this job work"></i>Who does this job work</div>
</div>
@foreach([
    'vendor_dispatch' => ['Dispatch time', 'Travel time from our work floor to the vendor'],
    'vendor_processing' => ['Processing time', 'Time the vendor takes to do the work'],
    'vendor_receipt' => ['Receipt time', 'Travel time from the vendor back to our work floor'],
] as $timeKey => [$timeLabel, $timeHint])
    <div class="col-md-3">
        <label class="odoo-form-label fs-11 text-muted mb-1 d-block">
            {{ $timeLabel }}
            <i class="feather-info ms-1 text-primary" style="cursor: help;" data-bs-toggle="tooltip" title="{{ $timeHint }}"></i>
        </label>
        <div class="input-group input-group-sm">
            <input type="number" min="0" step="1" class="form-control form-control-sm fs-11" placeholder="0"
                x-bind:name="'operations['+index+'][{{ $timeKey }}_days]'" x-model="operation.{{ $timeKey }}_days" x-bind:disabled="!operation.is_external">
            <span class="input-group-text fs-11">d</span>
            <input type="number" min="0" step="any" class="form-control form-control-sm fs-11" placeholder="0"
                x-bind:name="'operations['+index+'][{{ $timeKey }}_hours]'" x-model="operation.{{ $timeKey }}_hours" x-bind:disabled="!operation.is_external">
            <span class="input-group-text fs-11">h</span>
        </div>
        <div class="fs-10 text-muted mt-1">{{ $timeHint }}</div>
    </div>
@endforeach
