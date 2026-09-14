@extends('layouts.duralux')

@section('title', __('purchase.register_vendor_payment') . ' | SaaS ERP')
@section('page-title', __('purchase.register_vendor_payment'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.vendor_payments') . ' / ' . __('purchase.register'))

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <form action="{{ route('purchase.payments.store') }}" method="POST" class="odoo-sheet">
            @csrf

            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">{{ __('purchase.register_vendor_payment') }}</h5>
                    <small class="text-muted fs-11">{{ __('purchase.post_payment_ledger_help') }}</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('purchase.payments.index') }}" class="btn btn-light border fs-12">{{ __('purchase.cancel') }}</a>
                    <button type="submit" class="btn btn-success text-white fs-12 px-4 fw-semibold">
                        <i class="feather-check me-1.5"></i>{{ __('purchase.post_payment') }}
                    </button>
                </div>
            </div>

            @if($selectedBill && $totalAdvancePaid > 0)
                <div class="alert alert-info border-info p-3 mb-4 rounded shadow-sm">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="feather-info text-info me-1.5"></i>{{ __('purchase.po_advance_payment_summary') }}
                        </h6>
                        <span class="badge bg-success text-white px-2.5 py-1 fs-11 fw-bold">₹{{ number_format($totalAdvancePaid, 2) }} {{ __('purchase.advance_available') }}</span>
                    </div>
                    <div class="row g-2 text-dark fs-13">
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.total_bill_due') }}</span>
                            <strong class="font-monospace text-dark fs-14">₹{{ number_format($selectedBill->due_amount, 2) }}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold text-success">{{ __('purchase.po_advance_paid') }}</span>
                            <strong class="font-monospace text-success fs-14">- ₹{{ number_format($totalAdvancePaid, 2) }}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold text-primary">{{ __('purchase.suggested_net_bank_out') }}</span>
                            <strong class="font-monospace text-primary fs-15">₹{{ number_format($suggestedNetPayable, 2) }}</strong>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    @if($selectedBill)
                        <x-ui.odoo-form-ui type="select" label="{{ __('purchase.supplier_vendor') }}" name="vendor_id_disabled" required="true" disabled="disabled">
                            @foreach($vendors as $vendor)
                                @if($selectedBill->vendor_id == $vendor->id)
                                    <option value="{{ $vendor->id }}" selected>{{ $vendor->name }}</option>
                                @endif
                            @endforeach
                        </x-ui.odoo-form-ui>
                        <input type="hidden" name="vendor_id" value="{{ $selectedBill->vendor_id }}">
                    @else
                        <x-ui.odoo-form-ui type="select" label="{{ __('purchase.supplier_vendor') }}" name="vendor_id" required="true" :errorText="$errors->first('vendor_id')">
                            <option value="">{{ __('purchase.select_vendor_placeholder') }}</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>
                                    {{ $vendor->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    @endif
                </div>

                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('purchase.net_bank_outflow') }} (₹)" name="amount" :value="old('amount', ($totalAdvancePaid > 0 ? $suggestedNetPayable : $selectedBill?->due_amount))" step="0.01" min="0.01" required="true" placeholder="{{ __('purchase.amount_placeholder') }}" :errorText="$errors->first('amount')" />
                </div>

                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="{{ __('purchase.payment_method') }}" name="payment_method" required="true">
                        <option value="Bank Transfer" selected>{{ __('purchase.pay_method_bank_transfer_full') }}</option>
                        <option value="Cheque">{{ __('purchase.pay_method_cheque') }}</option>
                        <option value="Cash">{{ __('purchase.pay_method_cash') }}</option>
                        <option value="UPI">UPI</option>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.payment_date') }}" name="payment_date" :value="old('payment_date', date('Y-m-d'))" required="true" />
                </div>
                <div class="col-md-8">
                    <x-ui.odoo-form-ui type="input" label="{{ __('purchase.reference_transaction_utr_no') }}" name="reference_number" placeholder="e.g. UTR987654321" />
                </div>
            </div>

            @if($selectedBill)
                <h6 class="fw-bold text-dark mb-2">{{ __('purchase.payment_allocation_to_bill') }}</h6>
                <div class="table-responsive rounded border mb-4">
                    <table class="table table-bordered align-middle fs-13 text-dark mb-0">
                        <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                            <tr>
                                <th class="ps-3">{{ __('purchase.bill_number') }}</th>
                                <th>{{ __('purchase.invoice_date') }}</th>
                                <th class="text-end">{{ __('purchase.grand_total') }}</th>
                                <th class="text-end">{{ __('purchase.outstanding_due') }}</th>
                                <th class="text-end pe-3" style="width: 200px;">{{ __('purchase.allocated_amount') }} (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="ps-3 fw-bold text-primary">
                                    <input type="hidden" name="allocations[0][vendor_bill_id]" value="{{ $selectedBill->id }}">
                                    {{ $selectedBill->bill_number }}
                                </td>
                                <td>{{ $selectedBill->bill_date ? $selectedBill->bill_date->format('d-M-Y') : '—' }}</td>
                                <td class="text-end font-monospace">₹{{ number_format($selectedBill->grand_total, 2) }}</td>
                                <td class="text-end font-monospace text-danger fw-bold">₹{{ number_format($selectedBill->due_amount, 2) }}</td>
                                <td class="text-end pe-3">
                                    <input type="number" name="allocations[0][allocated_amount]" class="form-control form-control-sm text-end font-monospace fw-bold text-success" value="{{ ($totalAdvancePaid > 0 ? $suggestedNetPayable : $selectedBill->due_amount) }}" step="0.01" min="0.01" max="{{ $selectedBill->due_amount }}" required>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            <x-ui.odoo-form-ui type="textarea" label="{{ __('purchase.payment_remarks') }}" name="notes" placeholder="{{ __('purchase.enter_remarks') }}" rows="2" />

        </form>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const amountInput = document.querySelector('input[name="amount"]');
            const allocInput = document.querySelector('input[name="allocations[0][allocated_amount]"]');

            if (amountInput && allocInput) {
                allocInput.addEventListener('input', function () {
                    amountInput.value = this.value;
                });
                amountInput.addEventListener('input', function () {
                    allocInput.value = this.value;
                });
            }
        });
    </script>
@endpush

