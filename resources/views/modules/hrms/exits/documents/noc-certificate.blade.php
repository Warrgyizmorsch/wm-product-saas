<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No Objection Certificate - {{ $document->content_data['employee_name'] ?? 'Employee' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 40px 20px;
        }
        .cert-container {
            max-width: 800px;
            margin: auto;
            background: #ffffff;
            padding: 60px 50px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .cert-header {
            border-bottom: 2px solid #6366f1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .cert-title {
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 30px 0;
            color: #0f172a;
        }
        .signature-section {
            margin-top: 60px;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .cert-container {
                box-shadow: none;
                border: none;
                padding: 20px 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print text-center mb-4">
        <button onclick="window.print()" class="btn btn-indigo text-white px-4 py-2 fw-bold shadow-sm" style="background-color: #6366f1;">
            🖨️ Print / Save as PDF
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3 py-2 ms-2">
            Close
        </button>
    </div>

    <div class="cert-container">
        <!-- Header -->
        <div class="cert-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold mb-0" style="color: #6366f1;">{{ $document->content_data['company_name'] ?? 'Warrgyizmorsch Pvt Ltd' }}</h3>
                <p class="text-muted fs-13 mb-0">{{ $document->content_data['company_legal_name'] ?? 'Corporate Headquarters' }}</p>
            </div>
            <div class="text-end text-muted fs-13">
                <div><strong>Ref:</strong> {{ $document->reference_number }}</div>
                <div><strong>Date:</strong> {{ $document->content_data['issue_date'] ?? date('d M, Y') }}</div>
            </div>
        </div>

        <h4 class="cert-title">NO OBJECTION / NO DUES CERTIFICATE (NOC)</h4>

        <!-- Body -->
        <div class="cert-body fs-15 lh-lg">
            <p class="mb-4">
                This is to certify that <strong>{{ $document->content_data['employee_name'] }}</strong>, formerly designated as 
                <strong>{{ $document->content_data['designation'] }}</strong> (Employee Code: <strong>{{ $document->content_data['employee_id'] }}</strong>), 
                has served their tenure until <strong>{{ $document->content_data['last_working_day'] }}</strong>.
            </p>

            <p class="mb-4">
                {{ $document->content_data['clearance_status'] ?? 'We confirm that all company assets, intellectual property, ID cards, keys, travel expense claims, cash advances, and financial liabilities have been verified, settled, and cleared across all operational departments.' }}
            </p>

            <p class="mb-4">
                The management of <strong>{{ $document->content_data['company_name'] }}</strong> has <strong>No Objection</strong> to their joining any other organization or pursuing higher education.
            </p>
        </div>

        <!-- Signature -->
        <div class="signature-section d-flex justify-content-between align-items-end pt-5">
            <div>
                <div class="text-muted fs-13 mb-4">Issued By,</div>
                <div class="fw-bold fs-15 text-dark">HR & Administration Operations</div>
                <div class="text-muted fs-13">{{ $document->content_data['company_name'] ?? 'Warrgyizmorsch Pvt Ltd' }}</div>
            </div>
            <div class="text-center">
                <div class="p-3 border rounded-circle d-inline-block text-muted fs-11" style="width: 100px; height: 100px; line-height: 70px;">
                    [ OFFICIAL SEAL ]
                </div>
            </div>
        </div>
    </div>
</body>
</html>
