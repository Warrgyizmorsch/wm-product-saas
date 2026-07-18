@extends('layouts.duralux')

@section('title', "Enter Quotation Rates | SaaS ERP")
@section('page-title', "Enter Quotation Rates")
@section('breadcrumb')
    <a href="{{ route('purchase.rfqs.index') }}">RFQs</a> &gt; <a href="{{ route('purchase.rfqs.show', $rfq->id) }}">{{ $rfq->rfq_number }}</a> &gt; Enter Quotes
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <!-- Professional Flat Form Sheet -->
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="{{ route('purchase.rfqs.store-quotes', $rfq->id) }}" method="POST" id="quotesForm" class="odoo-sheet">
                    @csrf

                    <!-- Top buttons bar -->
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom flex-wrap gap-2">
                        <div>
                            <h4 class="fw-bold text-dark mb-0">Record Vendor Rates for {{ $rfq->rfq_number }}</h4>
                            <small class="text-muted fs-12">Input the price details received from <strong>{{ $rfq->vendor?->name }}</strong>.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <x-ui.button href="{{ route('purchase.rfqs.show', $rfq->id) }}" variant="light" size="sm">
                                Cancel
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-save" style="background-color: #714B67; border-color: #714B67;">
                                Save Rates
                            </x-ui.button>
                        </div>
                    </div>

                    <!-- Items Grid -->
                    <div class="mt-4">
                        <div class="table-responsive">
                            <table class="odoo-table" id="quotesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 45%">Product</th>
                                        <th class="text-end" style="width: 20%">Inquired Qty</th>
                                        <th class="text-end" style="width: 35%">Quoted Rate (₹) <span class="text-danger">*</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rfq->items as $index => $item)
                                        <tr class="item-row" data-index="{{ $index }}">
                                            <td>
                                                <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                                <div class="fw-bold text-dark">{{ $item->product?->name }}</div>
                                                <small class="text-muted">SKU: {{ $item->product?->sku ?: '—' }}</small>
                                            </td>
                                            <td class="text-end text-dark font-monospace">
                                                {{ (float)$item->quantity }}
                                            </td>
                                            <td>
                                                <x-ui.odoo-form-ui type="input" name="items[{{ $index }}][estimated_cost]" inputType="number" class="text-end quote-rate-input" step="0.01" min="0" required="true" :value="$item->estimated_cost" placeholder="0.00" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
