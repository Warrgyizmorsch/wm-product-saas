@extends('layouts.duralux')

@section('title', __('purchase.enter_quotation_rates') . ' | SaaS ERP')
@section('page-title', __('purchase.enter_quotation_rates'))
@section('breadcrumb')
    <a href="{{ route('purchase.rfqs.index') }}">{{ __('purchase.rfqs') }}</a> &gt; <a href="{{ route('purchase.rfqs.show', $rfq->id) }}">{{ $rfq->rfq_number }}</a> &gt; {{ __('purchase.enter_quotes') }}
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
                            <h4 class="fw-bold text-dark mb-0">{{ __('purchase.record_vendor_rates_for') }} {{ $rfq->rfq_number }}</h4>
                            <small class="text-muted fs-12">{{ __('purchase.input_price_details_from') }} <strong>{{ $rfq->vendor?->name }}</strong>.</small>
                        </div>
                    </div>

                    <!-- Items Grid -->
                    <div class="mt-4">
                        <div class="table-responsive">
                            <table class="odoo-table" id="quotesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 45%">{{ __('purchase.product') }}</th>
                                        <th class="text-end" style="width: 20%">{{ __('purchase.inquired_qty') }}</th>
                                        <th class="text-end" style="width: 35%">{{ __('purchase.quoted_rate') }} ({{ active_currency_symbol() }}) <span class="text-danger">*</span></th>
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

                    <!-- Bottom Action Buttons (like Lead form) -->
                    <div class="d-flex align-items-center justify-content-end gap-2 mt-4 pt-3 border-top">
                        <x-ui.button href="{{ route('purchase.rfqs.show', $rfq->id) }}" variant="light" class="border px-4 py-2 fs-13">
                            {{ __('purchase.cancel') }}
                        </x-ui.button>
                        <x-ui.button type="submit" variant="primary" icon="feather-save" class="px-4 py-2 fs-13 fw-bold shadow-sm">
                            {{ __('purchase.save_rates') }}
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
