@extends('layouts.duralux')

@section('title', __('purchase.landed_cost_voucher') . ' ' . $voucher->voucher_number . ' | SaaS ERP')
@section('page-title', __('purchase.landed_cost_voucher_details'))
@section('breadcrumb')
    <a href="{{ route('purchase.landed-costs.index') }}">{{ __('purchase.landed_cost_vouchers') }}</a> &gt; {{ __('purchase.details') }}
@endsection

@push('styles')
    <style>
        .odoo-sheet {
            background: #ffffff;
            border-radius: 4px;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark odoo-sheet">
        <!-- Title & Ribbon Bar Header -->
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
            <div>
                <small class="text-muted text-uppercase font-monospace fw-bold fs-11">{{ __('purchase.landed_cost_voucher') }}</small>
                <h3 class="fw-bold text-dark mb-0 font-monospace">{{ $voucher->voucher_number }}</h3>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <small class="text-muted text-uppercase font-monospace fw-bold fs-11 d-block">{{ __('purchase.total_additional_expenses') }}</small>
                    <h3 class="fw-bold text-primary font-monospace mb-0">{{ active_currency_symbol() }} {{ number_format($voucher->total_expenses, 2) }}</h3>
                </div>

                @if($voucher->isDraft())
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('purchase.landed-costs.post', $voucher->id) }}" class="d-inline">
                            @csrf
                            <x-ui.button type="submit" variant="primary" icon="feather-check-circle">
                                {{ __('purchase.post_voucher_update_valuation') }}
                            </x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('purchase.landed-costs.destroy', $voucher->id) }}" class="d-inline" onsubmit="return confirm('{{ __('purchase.confirm_cancel_voucher') }}');">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="light" class="text-danger border">
                                {{ __('purchase.cancel_voucher') }}
                            </x-ui.button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <!-- Primary 2-Column Fields Grid -->
        <div class="row g-4 mb-4 fs-13">
            <div class="col-md-6 border-end pe-md-4">
                <h6 class="fw-bold text-primary mb-3"><i class="feather-calendar me-2"></i>{{ __('purchase.voucher_details') }}</h6>

                <x-ui.odoo-form-ui type="input" :label="__('purchase.voucher_date')" name="voucher_date_dummy" value="{{ date('d-M-Y', strtotime($voucher->voucher_date)) }}" readonly="true" />

                <div class="odoo-form-group">
                    <label class="odoo-form-label">{{ __('purchase.linked_grns') }}</label>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap gap-1 pt-1">
                            @foreach($voucher->receipts as $receipt)
                                @if($receipt->goodsReceiptNote)
                                    <a href="{{ route('grns.show', $receipt->goods_receipt_note_id) }}" class="badge bg-light text-primary border px-2.5 py-1.5 font-monospace text-decoration-none">
                                        <i class="feather-truck me-1"></i>{{ $receipt->goodsReceiptNote->grn_number }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 ps-md-4">
                <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>{{ __('purchase.status_and_posting') }}</h6>

                @php
                    $statusLower = strtolower($voucher->status);
                    $statusLabel = $statusLower === 'posted' ? __('purchase.status_posted') : ($statusLower === 'draft' ? __('purchase.status_draft') : __('purchase.status_cancelled'));
                @endphp
                <x-ui.odoo-form-ui type="input" :label="__('purchase.status')" name="status_dummy" :value="$statusLabel" readonly="true" class="fw-bold" />

                <x-ui.odoo-form-ui type="input" :label="__('purchase.posting_date')" name="posting_date_dummy" value="{{ $voucher->posting_date ? date('d-M-Y', strtotime($voucher->posting_date)) : '—' }}" readonly="true" />
            </div>
        </div>

        <!-- Section 1: Expenses Breakdown Table -->
        <div class="mt-4 pt-3 border-top">
            <h6 class="fw-bold text-primary mb-3"><i class="feather-layers me-2"></i>1. {{ __('purchase.additional_expenses_transporter_bills') }}</h6>
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 18%">{{ __('purchase.expense_head') }}</th>
                            <th style="width: 22%">{{ __('purchase.transporter_vendor') }}</th>
                            <th style="width: 12%" class="text-end">{{ __('purchase.base_amount') }} ({{ active_currency_symbol() }})</th>
                            <th style="width: 13%">{{ __('purchase.gst_and_mechanism') }}</th>
                            <th style="width: 12%" class="text-end">{{ __('purchase.tax_amt') }} ({{ active_currency_symbol() }})</th>
                            <th style="width: 13%" class="text-end">{{ __('purchase.total_payable') }} ({{ active_currency_symbol() }})</th>
                            <th style="width: 10%">{{ __('purchase.vendor_bill') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($voucher->expenses as $exp)
                            <tr>
                                <td class="fw-bold text-dark">{{ $exp->cost_head }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $exp->vendor->name ?? '—' }}</div>
                                    <span class="badge bg-light text-dark border px-1.5 py-0.5 fs-11">
                                        {{ __('purchase.basis') }}: {{ $exp->allocation_basis === 'by_amount' ? __('purchase.by_value') : ($exp->allocation_basis === 'equal' ? __('purchase.equal') : __('purchase.by_qty')) }}
                                    </span>
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark">{{ active_currency_symbol() }} {{ number_format($exp->amount, 2) }}</td>
                                <td>
                                    @if($exp->tax_rate > 0)
                                        <span class="badge bg-soft-info text-info border border-info font-monospace px-2 py-1 fs-11">{{ (float)$exp->tax_rate }}% GST</span>
                                        <small class="d-block text-muted fs-11 mt-1 fw-semibold">
                                            @if($exp->is_rcm)
                                                <span class="text-danger fw-bold"><i class="feather-alert-circle me-1"></i>{{ __('purchase.rcm_reverse_charge') }}</span>
                                            @elseif($exp->gst_type === 'igst')
                                                {{ __('purchase.igst_interstate') }}
                                            @else
                                                {{ __('purchase.cgst_sgst_intrastate') }}
                                            @endif
                                        </small>
                                    @else
                                        <span class="badge bg-light text-muted border px-2 py-1 fs-11">{{ __('purchase.no_tax') }}</span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace text-primary">{{ active_currency_symbol() }} {{ number_format($exp->tax_amount, 2) }}</td>
                                <td class="text-end font-monospace fw-bold text-success">{{ active_currency_symbol() }} {{ number_format($exp->total_with_tax, 2) }}</td>
                                <td>
                                    @if($exp->vendorBill)
                                        <a href="{{ route('purchase.bills.show', $exp->vendor_bill_id) }}" class="badge bg-primary text-white text-decoration-none font-monospace">
                                            <i class="feather-file-text me-1"></i>{{ $exp->vendorBill->bill_number }}
                                        </a>
                                    @else
                                        <span class="text-muted fs-11">{{ __('purchase.pending_post') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <!-- Section 2: Item Landed Cost Valuation Breakdown -->
        <div class="mt-4 pt-3 border-top">
            <h6 class="fw-bold text-primary mb-3"><i class="feather-box me-2"></i>2. {{ __('purchase.item_landed_cost_valuation_breakdown') }}</h6>
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 15%">{{ __('purchase.grn_no') }}</th>
                            <th style="width: 25%">{{ __('purchase.product_details') }}</th>
                            <th style="width: 12%" class="text-center">{{ __('purchase.quantity') }}</th>
                            <th style="width: 13%" class="text-end">{{ __('purchase.base_unit_rate') }} ({{ active_currency_symbol() }})</th>
                            <th style="width: 15%" class="text-end">{{ __('purchase.allocated_extra_cost') }} ({{ active_currency_symbol() }})</th>
                            <th style="width: 20%" class="text-end">{{ __('purchase.new_landed_unit_cost') }} ({{ active_currency_symbol() }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($voucher->items as $item)
                            <tr>
                                <td>
                                    <span class="font-monospace fw-bold text-primary">
                                        {{ $item->goodsReceiptNote->grn_number ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $item->product->name ?? '—' }}</div>
                                    <small class="text-muted font-monospace">SKU: {{ $item->product->sku ?? '—' }}</small>
                                </td>
                                <td class="text-center fw-semibold">{{ (float)$item->quantity }} {{ $item->product?->uom?->code ?? 'PCS' }}</td>
                                <td class="text-end font-monospace">{{ active_currency_symbol() }} {{ number_format($item->base_unit_rate, 2) }}</td>
                                <td class="text-end font-monospace text-primary fw-bold">+ {{ active_currency_symbol() }} {{ number_format($item->allocated_cost, 2) }}</td>
                                <td class="text-end font-monospace text-success fw-bold">
                                    {{ active_currency_symbol() }} {{ number_format($item->new_landed_unit_cost, 2) }} / {{ $item->product?->uom?->code ?? 'PCS' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        @if($voucher->notes)
            <div class="mt-4 pt-3 border-top">
                <x-ui.odoo-form-ui type="textarea" :label="__('purchase.notes_remarks')" name="notes_dummy" value="{{ $voucher->notes }}" readonly="true" />
            </div>
        @endif

    </div>
@endsection
