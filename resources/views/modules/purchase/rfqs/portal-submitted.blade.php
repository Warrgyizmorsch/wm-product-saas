<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('purchase.rfq_portal') }} — SaaS ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #334155;
        }
        .portal-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            background: #ffffff;
            overflow: hidden;
            max-width: 650px;
            margin: 60px auto;
        }
        .success-icon-box {
            width: 80px;
            height: 80px;
            background: #ecfdf5;
            color: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
    </style>
</head>
<body class="py-5">
    <div class="container">
        <div class="card portal-card text-center p-5">
            <div class="success-icon-box">
                <i data-feather="check-circle" style="width: 44px; height: 44px;"></i>
            </div>
            <h3 class="fw-bold text-dark mb-2">Thank You! Quotation Submitted Successfully</h3>
            <p class="text-muted fs-15 mb-4">
                Your quotation rates and terms have been recorded in our system. Our procurement team will review your quote and reach out shortly.
            </p>
            <div class="p-3 bg-light rounded text-start fs-13 mb-4">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Quotation Ref:</span>
                    <span class="fw-bold text-dark">{{ $rfqVendor->quotation_number ?: 'N/A' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">RFQ Number:</span>
                    <span class="fw-bold text-dark">{{ $rfqVendor->rfq?->rfq_number }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Submitted Date:</span>
                    <span class="fw-bold text-dark">{{ $rfqVendor->submitted_at ? $rfqVendor->submitted_at->format('d-M-Y h:i A') : now()->format('d-M-Y h:i A') }}</span>
                </div>
            </div>
            <p class="text-muted fs-12 mb-0">You can safely close this page.</p>
        </div>
    </div>
    <script>
        feather.replace();
    </script>
</body>
</html>
