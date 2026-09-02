<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relieving Letter - {{ $document->content_data['employee_name'] ?? 'Employee' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 40px 20px;
        }
        .letter-container {
            max-width: 800px;
            margin: auto;
            background: #ffffff;
            padding: 60px 50px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .letter-header {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .letter-title {
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
            .letter-container {
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
        <button onclick="window.print()" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
            🖨️ Print / Save as PDF
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3 py-2 ms-2">
            Close
        </button>
    </div>

    <div class="letter-container">
        <!-- Header -->
        <div class="letter-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold text-primary mb-0">{{ $document->content_data['company_name'] ?? 'Warrgyizmorsch Pvt Ltd' }}</h3>
                <p class="text-muted fs-13 mb-0">{{ $document->content_data['company_legal_name'] ?? 'Corporate Headquarters' }}</p>
            </div>
            <div class="text-end text-muted fs-13">
                <div><strong>Ref:</strong> {{ $document->reference_number }}</div>
                <div><strong>Date:</strong> {{ $document->content_data['issue_date'] ?? date('d M, Y') }}</div>
            </div>
        </div>

        <h4 class="letter-title">RELIEVING LETTER</h4>

        <!-- Recipient -->
        <div class="mb-4">
            <div><strong>To,</strong></div>
            <div class="fw-bold text-dark fs-16">{{ $document->content_data['employee_name'] }}</div>
            <div class="text-muted fs-14">Employee Code: {{ $document->content_data['employee_id'] }}</div>
            <div class="text-muted fs-14">Designation: {{ $document->content_data['designation'] }}</div>
            <div class="text-muted fs-14">Department: {{ $document->content_data['department'] }}</div>
        </div>

        <!-- Body -->
        <div class="letter-body fs-15 lh-lg">
            <p>Dear <strong>{{ $document->content_data['employee_name'] }}</strong>,</p>

            <p>
                With reference to your resignation letter, we would like to confirm that your resignation from the services of 
                <strong>{{ $document->content_data['company_name'] ?? 'the Company' }}</strong> has been accepted.
            </p>

            <p>
                You are hereby officially relieved from your duties and responsibilities as 
                <strong>{{ $document->content_data['designation'] }}</strong> with effect from the close of business hours on 
                <strong>{{ $document->content_data['last_working_day'] }}</strong>.
            </p>

            <p>
                We confirm that you have served the organization from <strong>{{ $document->content_data['date_of_joining'] }}</strong> 
                to <strong>{{ $document->content_data['last_working_day'] }}</strong>, and all required multi-department company clearances, 
                asset handovers, and full & final accounts settlements have been concluded satisfactorily.
            </p>

            <p>
                We take this opportunity to thank you for your contributions during your tenure with us and wish you all the very best in your future career endeavors.
            </p>
        </div>

        <!-- Signature -->
        <div class="signature-section d-flex justify-content-between align-items-end pt-5">
            <div>
                <div class="text-muted fs-13 mb-4">Authorized Signatory,</div>
                <div class="fw-bold fs-15 text-dark">Human Resources Department</div>
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
