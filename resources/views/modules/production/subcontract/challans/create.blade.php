@extends('layouts.duralux')

@section('title', 'New Dispatch Slip | SaaS ERP')
@section('page-title', 'New Subcontract Dispatch Slip')
@section('breadcrumb', 'New Dispatch Slip')

{{--
    TF-04 (docs/subcontracting_module_audit_2026-11-26.md): a dispatch slip sends an outsourced operation's work to its
    vendor. It never moves stock again — the company material was requested and issued to the order (the store issue
    is the only stock-out); the slip sends what was issued and not sent yet, plus (WIP job work) the previous
    operation's good output. "Units of work" is what the vendor has to return.
--}}
@section('content')
<div class="erp-single-panel bg-white">

    @if ($errors->any())
        <x-ui.toast :auto="true" type="error" title="{{ $errors->first() }}" />
    @endif

    <form method="POST" action="{{ route('production.subcontract.delivery-challans.store') }}" id="challan-form">
        @csrf
        <input type="hidden" name="production_order_id" value="{{ $order?->id }}">
        <input type="hidden" name="production_order_operation_id" value="{{ $operation?->id }}">

        <x-ui.odoo-form-ui type="sheet">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <h4 class="fw-bold text-dark mb-1"><i class="feather-truck me-2 text-primary"></i>New Dispatch Slip (Gate Pass)</h4>
                    <span class="fs-12 text-muted">Sends issued material / previous-operation output to the vendor. No stock is deducted here — the store issue already did that.</span>
                </div>
                <a href="{{ route('production.subcontract.delivery-challans.index') }}" class="text-muted hover-danger fs-18"><i class="feather-x"></i></a>
            </div>

            @if(!$operation || !$order)
                <div class="alert alert-soft-warning border border-warning fs-12">
                    <i class="feather-alert-triangle me-1"></i>A dispatch slip is made for one outsourced operation: open it from the order's Subcontract tab or the MES floor ("Dispatch").
                </div>
            @else
                @php $isWipJobWork = $operation->isWipJobWork(); @endphp
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <h6 class="fw-bold text-dark mb-1">Order <span class="text-primary font-monospace">{{ $order->order_number }}</span> · Op #{{ $operation->operation_number }} — {{ $operation->name }}</h6>
                    <div class="fs-12 text-muted">
                        {{ $order->product?->name }} · operation target {{ rtrim(rtrim(number_format((float) ($operation->target_produced_qty ?: $order->quantity_ordered), 4, '.', ''), '0'), '.') }}
                        · still to send <strong>{{ rtrim(rtrim(number_format($unitsNotYetSent, 4, '.', ''), '0'), '.') ?: '0' }}</strong> unit(s)
                        · {{ $isWipJobWork ? 'WIP job work (previous operation output' . ($prevOp ? ': OP-' . $prevOp->operation_number : '') . ')' : 'company material' }}
                    </div>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Vendor" name="vendor_id" :required="true" :searchable="false" :error-text="$errors->first('vendor_id')">
                            @foreach($vendors as $v)
                                <option value="{{ $v->id }}" @selected((int) old('vendor_id', $vendor?->id) === (int) $v->id)>{{ $v->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        <x-ui.odoo-form-ui type="input" label="Units of work sent" name="units_sent" inputType="number" step="any" :required="true"
                            :value="old('units_sent', rtrim(rtrim(number_format($defaultUnits, 4, '.', ''), '0'), '.'))"
                            helper-text="What the vendor has to return (receipts are counted against this)." :error-text="$errors->first('units_sent')" />
                        <x-ui.odoo-form-ui type="input" label="Slip date" name="challan_date" inputType="date" :value="old('challan_date', date('Y-m-d'))" :required="true" />
                        <x-ui.odoo-form-ui type="input" label="Expected back" name="expected_return_date" inputType="date" :value="old('expected_return_date', $expectedReturn)"
                            helper-text="Scheduled start + dispatch buffer + vendor lead time + return buffer." />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Vehicle number" name="vehicle_number" :value="old('vehicle_number')" />
                        <x-ui.odoo-form-ui type="input" label="Transporter" name="transporter_name" :value="old('transporter_name')" />
                        <x-ui.odoo-form-ui type="input" label="LR number" name="lr_number" :value="old('lr_number')" />
                        <x-ui.odoo-form-ui type="input" label="Driver" name="driver_name" :value="old('driver_name')" />
                        <x-ui.odoo-form-ui type="textarea" label="Notes" name="notes" :rows="2" :value="old('notes')" />
                    </div>
                </div>

                <h5 class="fw-bold text-dark mb-2"><i class="feather-box me-1 text-warning"></i>What goes to the vendor</h5>
                <p class="fs-12 text-muted mb-3">Lines come from the BOM: this operation's company materials and, for WIP job work, the previous operation's output. Vendor-supplied materials are not sent.</p>
                <div class="table-responsive mb-4">
                    <x-ui.odoo-form-ui type="table">
                        <thead class="table-light fs-11 text-uppercase text-muted">
                            <tr>
                                <th>Line</th>
                                <th class="text-end">Needed for the units</th>
                                <th class="text-end">Can be sent</th>
                                <th class="text-end" style="width: 18%">Send now</th>
                                <th style="width: 10%">Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($prefilledItems as $idx => $item)
                                <tr>
                                    <td>
                                        <span class="badge {{ $item['item_type'] === 'wip' ? 'bg-soft-warning text-dark' : 'bg-soft-primary text-primary' }} me-1">{{ $item['item_type'] === 'wip' ? 'WIP' : 'Material' }}</span>
                                        <strong>{{ $item['product_name'] }}</strong> <span class="text-muted fs-11">{{ $item['sku'] }}</span>
                                        <input type="hidden" name="items[{{ $idx }}][item_type]" value="{{ $item['item_type'] }}">
                                        <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item['product_id'] }}">
                                        <input type="hidden" name="items[{{ $idx }}][required_qty]" value="{{ $item['required_qty'] }}">
                                        <input type="hidden" name="items[{{ $idx }}][production_wip_id]" value="{{ $item['production_wip_id'] ?? '' }}">
                                        <input type="hidden" name="items[{{ $idx }}][production_batch_id]" value="{{ $item['production_batch_id'] ?? '' }}">
                                        <input type="hidden" name="items[{{ $idx }}][unit_of_measure]" value="{{ $item['uom'] }}">
                                    </td>
                                    <td class="text-end font-monospace">{{ rtrim(rtrim(number_format($item['required_qty'], 4, '.', ''), '0'), '.') ?: '0' }}</td>
                                    <td class="text-end font-monospace {{ $item['available'] + 0.0001 < $item['required_qty'] ? 'text-danger' : 'text-success' }}">
                                        {{ rtrim(rtrim(number_format($item['available'], 4, '.', ''), '0'), '.') ?: '0' }}
                                        @if($item['item_type'] === 'material' && $item['available'] + 0.0001 < $item['required_qty'])
                                            <div class="fs-10 text-danger">request &amp; issue the rest from the store</div>
                                        @endif
                                    </td>
                                    <td><input type="number" step="any" min="0" class="form-control form-control-sm text-end" name="items[{{ $idx }}][quantity]" value="{{ old("items.$idx.quantity", $item['quantity']) }}"></td>
                                    <td class="text-uppercase fs-12">{{ $item['uom'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Nothing to send for this operation (vendor-supplied: the vendor brings everything).</td></tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="{{ route('production.subcontract.delivery-challans.index') }}" class="btn btn-sm btn-light border">{{ __('production.cancel') }}</a>
                    <button type="submit" name="status" value="draft" class="btn btn-sm btn-outline-secondary"><i class="feather-save me-1"></i>Save draft</button>
                    <button type="submit" name="status" value="dispatched" class="btn btn-sm btn-primary px-4 fw-bold shadow-sm"><i class="feather-send me-1"></i>Create &amp; dispatch</button>
                </div>
            @endif
        </x-ui.odoo-form-ui>
    </form>
</div>
@endsection
