@extends('layouts.duralux')

@section('title', 'Register Vendor Payment | SaaS ERP')
@section('page-title', 'Register Vendor Payment')
@section('breadcrumb', 'Purchase / Vendor Payments / Register')

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <form action="{{ route('purchase.payments.store') }}" method="POST" class="odoo-sheet">
            @csrf

            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">Register Vendor Payment</h5>
                    <small class="text-muted fs-11">Posts double-entry Journal Entry (Dr: Accounts Payable 2000, Cr: Bank 1010) to General Ledger</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('purchase.payments.index') }}" class="btn btn-light border fs-12">Cancel</a>
                    <button type="submit" class="btn btn-success text-white fs-12 px-4 fw-semibold">
                        <i class="feather-check me-1.5"></i>Post Payment
                    </button>
                </div>
            </div>

            @if($selectedBill && $totalAdvancePaid > 0)
                <div class="alert alert-info border-info p-3 mb-4 rounded shadow-sm">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="feather-info text-info me-1.5"></i>PO Advance Payment Summary
                        </h6>
                        <span class="badge bg-success text-white px-2.5 py-1 fs-11 fw-bold">₹{{ number_format($totalAdvancePaid, 2) }} Advance Available</span>
                    </div>
                    <div class="row g-2 text-dark fs-13">
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold">Total Bill Due</span>
                            <strong class="font-monospace text-dark fs-14">₹{{ number_format($selectedBill->due_amount, 2) }}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold text-success">PO Advance Paid</span>
                            <strong class="font-monospace text-success fs-14">- ₹{{ number_format($totalAdvancePaid, 2) }}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold text-primary">Suggested Net Bank Out</span>
                            <strong class="font-monospace text-primary fs-15">₹{{ number_format($suggestedNetPayable, 2) }}</strong>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Vendor" name="vendor_id" required="true" :errorText="$errors->first('vendor_id')">
                        <option value="">Select Vendor...</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected(old('vendor_id', $selectedBill?->vendor_id) == $vendor->id)>
                                {{ $vendor->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Net Bank Outflow (₹)" name="amount" :value="old('amount', ($totalAdvancePaid > 0 ? $suggestedNetPayable : $selectedBill?->due_amount))" step="0.01" min="0.01" required="true" placeholder="Enter amount..." :errorText="$errors->first('amount')" />
                </div>

                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Payment Method" name="payment_method" required="true">
                        <option value="Bank Transfer" selected>Bank Transfer (NEFT/RTGS)</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Cash">Cash</option>
                        <option value="UPI">UPI</option>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Payment Date" name="payment_date" :value="old('payment_date', date('Y-m-d'))" required="true" />
                </div>
                <div class="col-md-8">
                    <x-ui.odoo-form-ui type="input" label="Reference / Transaction UTR No." name="reference_number" placeholder="e.g. UTR987654321" />
                </div>
            </div>

            @if($selectedBill)
                <h6 class="fw-bold text-dark mb-2">Payment Allocation to Bill</h6>
                <div class="table-responsive rounded border mb-4">
                    <table class="table table-bordered align-middle fs-13 text-dark mb-0">
                        <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                            <tr>
                                <th class="ps-3">Bill Number</th>
                                <th>Invoice Date</th>
                                <th class="text-end">Grand Total</th>
                                <th class="text-end">Outstanding Due</th>
                                <th class="text-end pe-3" style="width: 200px;">Allocated Amount (₹)</th>
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

            <x-ui.odoo-form-ui type="textarea" label="Payment Remarks" name="notes" placeholder="Enter remarks..." rows="2" />

        </form>
    </div>

@endsection
