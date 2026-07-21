@extends('layouts.duralux')

@section('title', "Edit GRN {$grn->grn_number} | SaaS ERP")
@section('page-title', "Edit Draft Goods Receipt Note")
@section('breadcrumb')
    <a href="{{ route('purchase.orders.index') }}">Purchase</a> &gt; <a href="{{ route('purchase.grns.index') }}">Goods Receipt Notes</a> &gt; Edit {{ $grn->grn_number }}
@endsection

@section('page-actions')
    <a href="{{ route('purchase.grns.show', $grn->id) }}" class="btn btn-light border fs-12">
        <i class="feather-arrow-left me-2"></i>Back to View
    </a>
@endsection

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp
    <div class="row text-dark">
        <div class="col-12">
            @if (session('error'))
                <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
            @endif

            <form action="{{ route('purchase.grns.update', $grn->id) }}" method="POST" id="grnEditForm">
                @csrf
                @method('PUT')

                <div class="card border-0 shadow-sm bg-white mb-4 odoo-sheet">
                    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <h5 class="fw-bold text-dark mb-0 me-3"><i class="feather-edit text-warning me-2"></i>Edit Draft GRN</h5>
                            <span class="badge bg-soft-warning text-warning px-2.5 py-1 fw-bold fs-11 font-monospace">{{ $grn->grn_number }}</span>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary text-white fw-bold px-4 py-2">
                                <i class="feather-save me-1.5"></i>Update Draft GRN
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <div class="row g-3 fs-13 pb-4 border-bottom">
                            <div class="col-md-6 border-end">
                                <h6 class="fw-bold text-primary mb-3">PO & Supplier Details</h6>

                                <x-ui.odoo-form-ui type="input" label="Purchase Order" name="po_no" value="{{ $grn->purchaseOrder?->purchase_order_number ?? 'Direct Receipt' }}" readonly="true" />
                                <x-ui.odoo-form-ui type="input" label="Vendor Name" name="vendor_name" value="{{ $grn->vendor?->name }}" readonly="true" />
                                <x-ui.odoo-form-ui type="input" label="Warehouse" name="wh_name" value="{{ $grn->warehouse?->name ?? 'Main Warehouse' }}" readonly="true" />
                                <x-ui.odoo-form-ui type="input" inputType="date" label="Receipt Date" name="received_date" id="received_date" value="{{ old('received_date', $grn->received_date ? $grn->received_date->format('Y-m-d') : date('Y-m-d')) }}" :required="true" :error-text="$errors->first('received_date')" />
                            </div>

                            <div class="col-md-6">
                                <h6 class="fw-bold text-primary mb-3">Challan & Logistics Details</h6>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Challan / Invoice No" name="challan_number" id="challan_number" value="{{ old('challan_number', $grn->challan_number) }}" placeholder="Supplier Challan No" :error-text="$errors->first('challan_number')" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" inputType="date" label="Challan Date" name="challan_date" id="challan_date" value="{{ old('challan_date', $grn->challan_date ? $grn->challan_date->format('Y-m-d') : '') }}" :error-text="$errors->first('challan_date')" />
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Transporter Name" name="transporter_name" id="transporter_name" value="{{ old('transporter_name', $grn->transporter_name) }}" placeholder="Courier / Transporter" :error-text="$errors->first('transporter_name')" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Vehicle Number" name="vehicle_number" id="vehicle_number" value="{{ old('vehicle_number', $grn->vehicle_number) }}" placeholder="e.g. MH-12-AB-1234" :error-text="$errors->first('vehicle_number')" />
                                    </div>
                                </div>

                                <x-ui.odoo-form-ui type="input" label="L.R. Number" name="lr_number" id="lr_number" value="{{ old('lr_number', $grn->lr_number) }}" placeholder="Lorry Receipt / Docket No" :error-text="$errors->first('lr_number')" />
                            </div>
                        </div>

                        <div class="mt-3 mb-4">
                            <x-ui.odoo-form-ui type="textarea" label="Store Receipt Remarks / Notes" name="notes" placeholder="Enter any store verification remarks or package condition notes..." rows="2" :error-text="$errors->first('notes')">{{ old('notes', $grn->notes) }}</x-ui.odoo-form-ui>
                        </div>

                        <div class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="fw-bold text-primary mb-0"><i class="feather-layers text-primary me-2"></i>Received Products Matrix</h6>
                                <span class="badge bg-soft-info text-info fs-11 fw-semibold">{{ $grn->items->count() }} Items</span>
                            </div>

                            <div class="table-responsive border rounded bg-white">
                                <table class="table table-bordered align-middle mb-0 fs-12 text-dark">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 4%;">#</th>
                                            <th style="width: 25%;">Product Details</th>
                                            <th style="width: 10%;" class="text-center">Ordered</th>
                                            <th style="width: 10%;" class="text-center">Prev. Rec.</th>
                                            <th style="width: 11%;" class="text-center">Receive Qty <span class="text-danger">*</span></th>
                                            <th style="width: 10%;" class="text-center">Reject Qty</th>
                                            <th style="width: 10%;" class="text-center">Accepted</th>
                                            <th style="width: 10%;" class="text-end">Rate</th>
                                            <th style="width: 12%;" class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($grn->items as $idx => $item)
                                            <tr class="grn-item-row" data-idx="{{ $idx }}">
                                                <td class="text-center fw-semibold text-muted">{{ $idx + 1 }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div>
                                                            <div class="fw-bold text-dark">{{ $item->product?->name }}</div>
                                                            <div class="fs-11 text-muted">UOM: <strong>{{ $item->product?->uom?->name ?? 'Pcs' }}</strong></div>
                                                        </div>
                                                        <button type="button" class="btn btn-xs {{ $item->remarks ? 'bg-soft-danger text-danger' : 'bg-soft-primary text-primary' }} border-0 btn-toggle-remark ms-2 px-2 py-1 rounded-pill fw-semibold" data-target="#remark_row_{{ $idx }}">
                                                            <i class="{{ $item->remarks ? 'feather-minus' : 'feather-plus' }} me-1 fs-11"></i><span class="btn-lbl">{{ $item->remarks ? 'Hide' : 'Note' }}</span>
                                                        </button>
                                                    </div>
                                                    <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $item->id }}">
                                                </td>
                                                <td class="text-center font-monospace">{{ number_format($item->ordered_qty, 2) }}</td>
                                                <td class="text-center font-monospace text-muted">{{ number_format($item->previous_received_qty, 2) }}</td>
                                                <td>
                                                    <input type="number" step="0.0001" min="0" class="odoo-table-input text-center font-monospace fw-bold input-receive" name="items[{{ $idx }}][received_qty]" value="{{ (float)$item->received_qty }}" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.0001" min="0" class="odoo-table-input text-center font-monospace input-reject text-danger" name="items[{{ $idx }}][rejected_qty]" value="{{ (float)$item->rejected_qty }}">
                                                </td>
                                                <td class="text-center font-monospace fw-bold text-success cell-accepted">{{ number_format($item->accepted_qty, 2) }}</td>
                                                <td class="text-end font-monospace">{{ number_format($item->unit_rate, 2) }}</td>
                                                <td class="text-end font-monospace fw-bold text-dark cell-total">{{ number_format($item->total_amount, 2) }}</td>
                                            </tr>
                                            <tr id="remark_row_{{ $idx }}" class="remark-row bg-white" style="{{ $item->remarks ? '' : 'display: none;' }}">
                                                <td class="border-0"></td>
                                                <td colspan="8" class="py-2 px-3 border-top-0">
                                                    <div class="p-3 rounded-3 border bg-white shadow-xs">
                                                        <div class="d-flex align-items-center gap-2 mb-1.5">
                                                            <span class="badge bg-soft-primary text-primary fs-10 fw-bold text-uppercase">
                                                                <i class="feather-message-square me-1"></i>Item Remarks / Rejection Reason
                                                            </span>
                                                            <span class="fs-11 text-muted">For <strong>{{ $item->product?->name }}</strong></span>
                                                        </div>
                                                        <input type="text" class="odoo-table-input fs-12 text-dark px-2 py-1" name="items[{{ $idx }}][remarks]" value="{{ $item->remarks }}" placeholder="Enter rejection cause, damage details, or item notes for {{ $item->product?->name }}...">
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $(document).on('click', '.btn-toggle-remark', function() {
            var target = $($(this).data('target'));
            var icon = $(this).find('i');
            var lbl = $(this).find('.btn-lbl');

            target.slideToggle(150);
            if (icon.hasClass('feather-plus')) {
                icon.removeClass('feather-plus').addClass('feather-minus');
                $(this).removeClass('bg-soft-primary text-primary').addClass('bg-soft-danger text-danger');
                lbl.text('Hide');
            } else {
                icon.removeClass('feather-minus').addClass('feather-plus');
                $(this).removeClass('bg-soft-danger text-danger').addClass('bg-soft-primary text-primary');
                lbl.text('Note');
            }
        });
    });
</script>
@endpush
