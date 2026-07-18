<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Quotation Portal | SaaS ERP</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
        }
        .portal-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            background-color: #ffffff;
        }
        .portal-header {
            background-color: #714B67; /* ERP Brand Color */
            color: #ffffff;
            border-radius: 12px 12px 0 0;
            padding: 30px;
        }
        .portal-body {
            padding: 40px;
        }
        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: #374151;
        }
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 13px;
            padding: 10px 12px;
            transition: all 0.2s ease-in-out;
        }
        .form-control:focus, .form-select:focus {
            border-color: #714B67;
            box-shadow: 0 0 0 0.2rem rgba(113, 75, 103, 0.15);
        }
        .table-quote th {
            font-weight: 600;
            color: #4b5563;
            background-color: #f9fafb;
            font-size: 13px;
            border-bottom: 2px solid #e5e7eb;
        }
        .table-quote td {
            font-size: 13px;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
        }
        .btn-submit {
            background-color: #714B67;
            border-color: #714B67;
            color: #ffffff;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 6px;
            transition: all 0.2s ease-in-out;
        }
        .btn-submit:hover {
            background-color: #5a3c52;
            border-color: #5a3c52;
            color: #ffffff;
        }
    </style>
</head>
<body class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                
                <!-- Success/Error Messages -->
                @if(session('success'))
                    <div class="alert alert-success border-0 shadow-sm mb-4 py-3" role="alert">
                        <div class="d-flex align-items-center">
                            <i data-feather="check-circle" class="me-2 text-success"></i>
                            <div class="fw-semibold">{{ session('success') }}</div>
                        </div>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger border-0 shadow-sm mb-4 py-3" role="alert">
                        <div class="d-flex align-items-center">
                            <i data-feather="alert-circle" class="me-2 text-danger"></i>
                            <div class="fw-semibold">{{ session('error') }}</div>
                        </div>
                    </div>
                @endif

                <!-- Portal Box -->
                <div class="card portal-card">
                    <div class="portal-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <span class="badge bg-white text-dark mb-2 px-2.5 py-1 fw-bold">RFQ Portal</span>
                                <h3 class="fw-bold mb-0">Quotation Submission Request</h3>
                                <p class="mb-0 mt-1 opacity-75">Submit your best prices and terms for inquiry number: <strong>{{ $rfq->rfq_number }}</strong></p>
                            </div>
                            <div class="text-md-end">
                                <small class="opacity-75">Inquiry Date</small>
                                <h5 class="fw-semibold mb-0">{{ $rfq->rfq_date ? $rfq->rfq_date->format('d-M-Y') : '—' }}</h5>
                            </div>
                        </div>
                    </div>

                    <div class="portal-body">
                        <form action="{{ route('purchase.rfqs.portal-submit', $rfqVendor->token) }}" method="POST" enctype="multipart/form-data">
                            @php
                                $tenant = \App\Models\Tenant::find($rfq->tenant_id);
                                $currency = $tenant?->settings['currency'] ?? 'INR';
                            @endphp
                            <!-- Vendor & Client Summary Row -->
                            <div class="row g-4 mb-5 pb-4 border-bottom bg-light p-3 rounded">
                                <div class="col-md-6">
                                    <small class="text-muted text-uppercase fw-semibold">Supplier details</small>
                                    <h5 class="fw-bold text-dark mb-1">{{ $vendor->name }}</h5>
                                    <p class="mb-0 text-secondary fs-13"><i data-feather="phone" class="me-1" style="width:13px;"></i>{{ $vendor->phone ?: '—' }}</p>
                                    <p class="mb-0 text-secondary fs-13"><i data-feather="mail" class="me-1" style="width:13px;"></i>{{ $vendor->email ?: '—' }}</p>
                                </div>
                                <div class="col-md-6 text-md-end border-start-md">
                                    <small class="text-muted text-uppercase fw-semibold">Requested By</small>
                                    <h5 class="fw-bold text-dark mb-1">Procurement Department</h5>
                                    <p class="mb-0 text-secondary fs-13">SaaS ERP Enterprise</p>
                                </div>
                            </div>

                            <!-- Quotation Details Form -->
                            <h5 class="fw-bold mb-3 text-dark"><i data-feather="file-text" class="me-2 text-primary"></i>Quotation Header</h5>
                            <div class="row g-4 mb-5">
                                <div class="col-md-4">
                                    <label class="form-label">Quotation Number / Ref <span class="text-danger">*</span></label>
                                    <input type="text" name="quotation_number" class="form-control" value="{{ old('quotation_number', $rfqVendor->quotation_number) }}" required placeholder="e.g. QU-XYZ-987">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Payment Terms</label>
                                    <select name="payment_type" class="form-select">
                                        <option value="">Select Payment Terms...</option>
                                        <option value="Cash" @selected(old('payment_type', $rfqVendor->payment_type) === 'Cash')>Cash</option>
                                        <option value="Net 30" @selected(old('payment_type', $rfqVendor->payment_type) === 'Net 30')>Net 30 Days</option>
                                        <option value="Net 60" @selected(old('payment_type', $rfqVendor->payment_type) === 'Net 60')>Net 60 Days</option>
                                        <option value="50% Advance, 50% Delivery" @selected(old('payment_type', $rfqVendor->payment_type) === '50% Advance, 50% Delivery')>50% Advance, 50% Delivery</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Quotation Attachment (PDF/Image)</label>
                                    <input type="file" name="attachment" class="form-control">
                                    @if($rfqVendor->attachment_path)
                                        <div class="mt-2 fs-12">
                                            <i data-feather="paperclip" class="me-1 text-success" style="width:13px;"></i>
                                            <a href="{{ asset('storage/' . $rfqVendor->attachment_path) }}" target="_blank" class="text-decoration-underline text-success fw-semibold">Current Attachment File</a>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Terms & Conditions / Remarks</label>
                                    <textarea name="terms_conditions" class="form-control" rows="3" placeholder="Mention any freight charges, waranty terms, payment specifics, etc.">{{ old('terms_conditions', $rfqVendor->terms_conditions) }}</textarea>
                                </div>
                            </div>

                            <!-- Quotation Items Rates Grid -->
                            <h5 class="fw-bold mb-3 text-dark"><i data-feather="list" class="me-2 text-primary"></i>Inquired Items Rates</h5>
                            <div class="table-responsive mb-5">
                                <table class="table table-quote border">
                                    <thead>
                                        <tr>
                                            <th style="width: 30%">Product</th>
                                            <th class="text-end" style="width: 12%">Inquired Qty</th>
                                            <th class="text-end" style="width: 18%">Your Qty <span class="text-danger">*</span></th>
                                            <th class="text-end" style="width: 20%">Quoted Rate / Unit <span class="text-danger">*</span></th>
                                            <th style="width: 10%">Delivery Date</th>
                                            <th style="width: 10%">Validity Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($rfq->items as $index => $item)
                                            @php
                                                $quoteRate = $existingRates->get($item->product_id);
                                                $uomName = $item->product?->uom?->code ?: ($item->product?->uom?->name ?? 'Pcs');
                                            @endphp
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="rates[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                                                    <div class="fw-bold text-dark">{{ $item->product?->name }}</div>
                                                    <div class="d-flex flex-column gap-0.5 mt-1">
                                                        <small class="text-muted">SKU: {{ $item->product?->sku ?: '—' }}</small>
                                                        <small class="text-primary fs-11 fw-semibold">
                                                            <i data-feather="calendar" class="me-1" style="width:12px; height:12px;"></i>Expected: {{ $rfq->requisition?->requisition_date ? $rfq->requisition->requisition_date->format('d-M-Y') : $rfq->rfq_date->format('d-M-Y') }}
                                                        </small>
                                                    </div>
                                                </td>
                                                <td class="text-end font-monospace fw-bold">
                                                    {{ (float)$item->quantity }} <span class="text-muted small">({{ $uomName }})</span>
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" name="rates[{{ $index }}][quantity]" class="form-control text-end font-monospace" style="font-size:13px;" value="{{ old("rates.{$index}.quantity", $quoteRate ? (float)$quoteRate->quantity : (float)$item->quantity) }}" step="0.0001" min="0.0001" required>
                                                        <span class="input-group-text bg-light text-muted" style="font-size: 10px; padding: 2px 6px; min-width: 40px; justify-content: center;">{{ $uomName }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light text-muted" style="font-size: 10px; padding: 2px 6px;">{{ $currency }}</span>
                                                        <input type="number" name="rates[{{ $index }}][rate]" class="form-control text-end font-monospace" style="font-size:13px;" step="0.01" min="0" value="{{ old("rates.{$index}.rate", $quoteRate ? $quoteRate->rate : '') }}" required placeholder="0.00">
                                                        <span class="input-group-text bg-light text-muted" style="font-size: 10px; padding: 2px 6px; min-width: 45px; justify-content: center;">/ {{ $uomName }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="date" name="rates[{{ $index }}][delivery_date]" class="form-control" value="{{ old("rates.{$index}.delivery_date", $quoteRate && $quoteRate->delivery_date ? $quoteRate->delivery_date->format('Y-m-d') : '') }}">
                                                </td>
                                                <td>
                                                    <input type="date" name="rates[{{ $index }}][validity_date]" class="form-control" value="{{ old("rates.{$index}.validity_date", $quoteRate && $quoteRate->validity_date ? $quoteRate->validity_date->format('Y-m-d') : '') }}">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end border-top pt-4">
                                <button type="submit" class="btn btn-submit">
                                    <i data-feather="check-circle" class="me-2"></i>Submit Quotation Rates
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-4 text-muted fs-12">
                    &copy; 2026 SaaS ERP. Powered by Google Deepmind team working on Advanced Agentic Coding.
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Init feather icons
        feather.replace();
    </script>
</body>
</html>
