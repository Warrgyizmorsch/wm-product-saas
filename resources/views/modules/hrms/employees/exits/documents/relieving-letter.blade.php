<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relieving Letter - {{ $document->content_data['employee_name'] ?? 'Employee' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            padding: 30px 15px;
            font-size: 13px;
            line-height: 1.65;
        }

        .document-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: 50px 55px;
            border-radius: 8px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            position: relative;
            min-height: 1000px;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 64px;
            font-weight: 800;
            color: rgba(30, 41, 59, 0.03);
            text-transform: uppercase;
            letter-spacing: 6px;
            user-select: none;
            pointer-events: none;
            z-index: 0;
            white-space: nowrap;
        }

        .doc-header {
            border-bottom: 2px solid #1c3faa;
            padding-bottom: 16px;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        .company-logo-badge {
            width: 48px;
            height: 48px;
            background: #1c3faa;
            color: #ffffff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
        }

        .company-name {
            font-size: 18px;
            font-weight: 800;
            color: #1c3faa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .company-meta {
            font-size: 11px;
            color: #64748b;
            line-height: 1.4;
        }

        .doc-title-bar {
            text-align: center;
            margin: 22px 0 20px 0;
            position: relative;
            z-index: 1;
        }

        .doc-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #e2e8f0;
            display: inline-block;
            padding-bottom: 5px;
        }

        .recipient-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #1c3faa;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            font-size: 12px;
        }

        .subject-line {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 18px;
            font-size: 13px;
            position: relative;
            z-index: 1;
        }

        .letter-body {
            position: relative;
            z-index: 1;
            text-align: justify;
        }

        .letter-body p {
            margin-bottom: 14px;
        }

        .signature-section {
            margin-top: 50px;
            position: relative;
            z-index: 1;
        }

        .signature-line {
            width: 160px;
            border-bottom: 1px solid #475569;
            margin: 0 0 6px 0;
        }

        .seal-box {
            width: 85px;
            height: 85px;
            border: 1.5px dashed #94a3b8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
            margin: 0 auto;
            letter-spacing: 0.5px;
        }

        .doc-footer {
            margin-top: 40px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
            text-align: center;
            font-size: 9px;
            color: #64748b;
            position: relative;
            z-index: 1;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
                color: #000000;
            }
            .document-wrapper {
                box-shadow: none;
                border: none;
                padding: 10px 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    @php
        $companyName = $document->content_data['company_name'] ?? 'Warrgyizmorsch Pvt Ltd';
        $companyInitials = strtoupper(substr($companyName, 0, 2));
    @endphp

    <div class="no-print text-center mb-4">
        <button onclick="window.print()" class="btn btn-primary px-4 py-2 fw-bold shadow-sm" style="background-color: #1c3faa; border-color: #1c3faa;">
            🖨️ Print / Save Letter (PDF)
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3 py-2 ms-2">
            Close
        </button>
    </div>

    <div class="document-wrapper">
        <div class="watermark">RELIEVED</div>

        <!-- Corporate Letterhead -->
        <div class="doc-header">
            <div class="d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-3">
                    <div class="company-logo-badge">{{ $companyInitials }}</div>
                    <div>
                        <div class="company-name">{{ $companyName }}</div>
                        <div class="company-meta">
                            {{ $document->content_data['company_legal_name'] ?? 'Corporate Headquarters' }}<br>
                            Department of Human Resources & Corporate Administration
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-dark fs-12 mb-1">REF: {{ $document->reference_number }}</div>
                    <div class="text-muted fs-11">Date: {{ $document->content_data['issue_date'] ?? date('d M, Y') }}</div>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 fs-10 text-uppercase mt-1">
                        Official Relieving
                    </span>
                </div>
            </div>
        </div>

        <!-- Document Title -->
        <div class="doc-title-bar">
            <h4 class="doc-title">RELIEVING LETTER</h4>
        </div>

        <!-- Recipient Information Block -->
        <div class="recipient-card">
            <div class="text-muted fs-11">To,</div>
            <div class="fw-bold text-dark fs-13">{{ $document->content_data['employee_name'] }}</div>
            <div class="text-muted">Employee Code: <strong>{{ $document->content_data['employee_id'] }}</strong></div>
            <div class="text-muted">Designation: <strong>{{ $document->content_data['designation'] }}</strong> | Department: <strong>{{ $document->content_data['department'] }}</strong></div>
        </div>

        <!-- Subject Line -->
        <div class="subject-line">
            <strong>Subject:</strong> Formal Relieving Letter & Acceptance of Resignation
        </div>

        <!-- Letter Body Content -->
        <div class="letter-body">
            <p>Dear <strong>{{ $document->content_data['employee_name'] }}</strong>,</p>

            <p>
                With reference to your formal resignation letter, we hereby confirm that your resignation from the employment services of 
                <strong>{{ $companyName }}</strong> has been reviewed and accepted by the Management.
            </p>

            <p>
                You are hereby officially relieved from your duties and responsibilities as 
                <strong>{{ $document->content_data['designation'] }}</strong> with effect from the close of business hours on 
                <strong>{{ $document->content_data['last_working_day'] }}</strong>.
            </p>

            <p>
                We confirm that you served the organization from <strong>{{ $document->content_data['date_of_joining'] }}</strong> 
                to <strong>{{ $document->content_data['last_working_day'] }}</strong>. We also verify that you have successfully completed all necessary exit formalities, including the formal handover of charges, return of company equipment, hardware assets, security access cards, and resolution of full & final accounts.
            </p>

            <p>
                Please be reminded of your continuing contractual obligation regarding the confidentiality of all proprietary company information, trade secrets, client data, and software systems acquired during your tenure with us, as per the signed Employee Non-Disclosure Agreement.
            </p>

            <p>
                We take this opportunity to thank you for your contributions, effort, and dedication during your tenure with us and wish you continued success, growth, and prosperity in all your future professional endeavors.
            </p>
        </div>

        <!-- Signatures & Seal Section -->
        <div class="signature-section">
            <div class="row align-items-end">
                <div class="col-7">
                    <div class="text-muted fs-11 mb-4">Yours sincerely,</div>
                    <div class="signature-line"></div>
                    <div class="fw-bold text-dark fs-13">Authorized Signatory</div>
                    <div class="text-muted fs-11">Head of Human Resources</div>
                    <div class="text-muted fs-11">{{ $companyName }}</div>
                </div>
                <div class="col-5 text-center">
                    <div class="seal-box mb-2">[ OFFICIAL SEAL ]</div>
                    <div class="text-muted fs-10">Corporate HR Seal & Verification</div>
                </div>
            </div>
        </div>

        <!-- Document Footer -->
        <div class="doc-footer">
            This is an official document issued under the authority of {{ $companyName }}.<br>
            Verification Code: {{ md5($document->reference_number . ($document->content_data['employee_id'] ?? '')) }} | Generated via SaaS ERP HRMS
        </div>
    </div>
</body>
</html>
