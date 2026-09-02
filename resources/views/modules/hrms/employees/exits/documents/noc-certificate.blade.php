<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No Objection Certificate (NOC) - {{ $document->content_data['employee_name'] ?? 'Employee' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            padding: 30px 15px;
            font-size: 13px;
            line-height: 1.6;
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
            margin: 25px 0;
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

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        .info-table td {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 12px;
        }

        .info-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .info-value {
            font-size: 12px;
            color: #0f172a;
            font-weight: 600;
        }

        .cert-body {
            position: relative;
            z-index: 1;
            text-align: justify;
            margin-bottom: 30px;
        }

        .clearance-matrix-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 11px;
            position: relative;
            z-index: 1;
        }

        .clearance-matrix-table th {
            background-color: #f8fafc;
            color: #334155;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 10px;
            border: 1px solid #cbd5e1;
        }

        .clearance-matrix-table td {
            padding: 7px 10px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }

        .signature-section {
            margin-top: 50px;
            position: relative;
            z-index: 1;
        }

        .signature-line {
            width: 160px;
            border-bottom: 1px solid #475569;
            margin: 0 auto 6px auto;
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
            .info-table td, .clearance-matrix-table td, .clearance-matrix-table th {
                border-color: #94a3b8 !important;
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
            🖨️ Print / Save Certificate (PDF)
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3 py-2 ms-2">
            Close
        </button>
    </div>

    <div class="document-wrapper">
        <div class="watermark">NO DUES CLEARED</div>

        <!-- Corporate Letterhead -->
        <div class="doc-header">
            <div class="d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-3">
                    <div class="company-logo-badge">{{ $companyInitials }}</div>
                    <div>
                        <div class="company-name">{{ $companyName }}</div>
                        <div class="company-meta">
                            {{ $document->content_data['company_legal_name'] ?? 'Corporate Headquarters' }}<br>
                            Human Resources & Personnel Department | Corporate Administration
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-dark fs-12 mb-1">REF: {{ $document->reference_number }}</div>
                    <div class="text-muted fs-11">Date: {{ $document->content_data['issue_date'] ?? date('d M, Y') }}</div>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fs-10 text-uppercase mt-1">
                        Verified & Cleared
                    </span>
                </div>
            </div>
        </div>

        <!-- Document Title -->
        <div class="doc-title-bar">
            <h4 class="doc-title">NO OBJECTION & NO DUES CERTIFICATE (NOC)</h4>
        </div>

        <!-- Employee Information Grid -->
        <table class="info-table">
            <tr>
                <td width="25%">
                    <div class="info-label">Employee Name</div>
                    <div class="info-value">{{ $document->content_data['employee_name'] }}</div>
                </td>
                <td width="25%">
                    <div class="info-label">Employee Code</div>
                    <div class="info-value">{{ $document->content_data['employee_id'] }}</div>
                </td>
                <td width="25%">
                    <div class="info-label">Designation</div>
                    <div class="info-value">{{ $document->content_data['designation'] }}</div>
                </td>
                <td width="25%">
                    <div class="info-label">Last Working Day</div>
                    <div class="info-value">{{ $document->content_data['last_working_day'] }}</div>
                </td>
            </tr>
        </table>

        <!-- Certificate Body -->
        <div class="cert-body">
            <p class="mb-3">
                This is to certify that <strong>{{ $document->content_data['employee_name'] }}</strong>, holding the position of 
                <strong>{{ $document->content_data['designation'] }}</strong> (Employee Code: <strong>{{ $document->content_data['employee_id'] }}</strong>), 
                has served their employment tenure with <strong>{{ $companyName }}</strong> up to the close of working hours on 
                <strong>{{ $document->content_data['last_working_day'] }}</strong>.
            </p>

            <p class="mb-3">
                We officially confirm that the employee has completed the multi-tier exit clearance process and has returned all company assets, intellectual property, technical equipment, access cards, library materials, and files in their possession. Furthermore, all accounts, travel imprests, and financial liabilities have been fully reconciled and settled.
            </p>

            <!-- Clearance Verification Summary Table -->
            <table class="clearance-matrix-table">
                <thead>
                    <tr>
                        <th width="25%">Department</th>
                        <th width="50%">Clearance Scope / Covered Assets</th>
                        <th width="25%" class="text-center">Verification Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-bold text-dark">IT & INFRASTRUCTURE</td>
                        <td>Laptop, Workstation Access, Email Account, Software Subscriptions</td>
                        <td class="text-center text-success fw-bold">✓ NO DUES PENDING</td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-dark">FINANCE & ACCOUNTS</td>
                        <td>Salary Advances, Expense Claims, Company Credit Cards, Final Settlement</td>
                        <td class="text-center text-success fw-bold">✓ NO DUES PENDING</td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-dark">ADMIN & FACILITIES</td>
                        <td>Physical Access Badges, Keys, ID Card, Parking Permits</td>
                        <td class="text-center text-success fw-bold">✓ NO DUES PENDING</td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-dark">HUMAN RESOURCES</td>
                        <td>Exit Interviews, Knowledge Handover Documents, Non-Disclosure Confirmation</td>
                        <td class="text-center text-success fw-bold">✓ NO DUES PENDING</td>
                    </tr>
                </tbody>
            </table>

            <p class="mb-3">
                The management of <strong>{{ $companyName }}</strong> has <strong>No Objection</strong> to <strong>{{ $document->content_data['employee_name'] }}</strong> securing employment with any other organization, pursuing higher education, or registering with professional institutions.
            </p>

            <p class="mb-0">
                We thank them for their services and wish them every success in their future career endeavors.
            </p>
        </div>

        <!-- Signatures Section -->
        <div class="signature-section">
            <div class="row text-center fs-11">
                <div class="col-4">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="fw-bold text-dark">{{ $document->content_data['employee_name'] }}</div>
                        <div class="text-muted fs-10">Employee Acknowledgment</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="fw-bold text-dark">Head of Human Resources</div>
                        <div class="text-muted fs-10">Department of Personnel</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="signature-box">
                        <div class="seal-box mb-2">[ OFFICIAL SEAL ]</div>
                        <div class="fw-bold text-dark">Authorized Signatory</div>
                        <div class="text-muted fs-10">{{ $companyName }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Footer -->
        <div class="doc-footer">
            This certificate is an official document issued under the seal and authority of {{ $companyName }}.<br>
            Verification Code: {{ md5($document->reference_number . ($document->content_data['employee_id'] ?? '')) }} | Generated via SaaS ERP HRMS
        </div>
    </div>
</body>
</html>
