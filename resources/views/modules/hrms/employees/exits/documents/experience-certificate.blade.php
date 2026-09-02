<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Experience Certificate - {{ $document->content_data['employee_name'] ?? 'Employee' }}</title>
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
            font-size: 60px;
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
            margin: 25px 0 20px 0;
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

        .doc-subtitle {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
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
        }

        .cert-body p {
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
            .info-table td {
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
        <div class="watermark">EXPERIENCE CERTIFICATE</div>

        <!-- Corporate Letterhead -->
        <div class="doc-header">
            <div class="d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-3">
                    <div class="company-logo-badge">{{ $companyInitials }}</div>
                    <div>
                        <div class="company-name">{{ $companyName }}</div>
                        <div class="company-meta">
                            {{ $document->content_data['company_legal_name'] ?? 'Corporate Headquarters' }}<br>
                            Department of Human Resources & Talent Management
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-dark fs-12 mb-1">REF: {{ $document->reference_number }}</div>
                    <div class="text-muted fs-11">Date: {{ $document->content_data['issue_date'] ?? date('d M, Y') }}</div>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fs-10 text-uppercase mt-1">
                        Certificate of Service
                    </span>
                </div>
            </div>
        </div>

        <!-- Document Title -->
        <div class="doc-title-bar">
            <h4 class="doc-title">TO WHOMSOEVER IT MAY CONCERN</h4>
            <div class="doc-subtitle">SERVICE & EXPERIENCE CERTIFICATE</div>
        </div>

        <!-- Employee & Service Details Grid -->
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
                    <div class="info-label">Department</div>
                    <div class="info-value">{{ $document->content_data['department'] }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="info-label">Date of Joining</div>
                    <div class="info-value">{{ $document->content_data['date_of_joining'] }}</div>
                </td>
                <td>
                    <div class="info-label">Last Working Day</div>
                    <div class="info-value">{{ $document->content_data['last_working_day'] }}</div>
                </td>
                <td colspan="2">
                    <div class="info-label">Total Completed Tenure</div>
                    <div class="info-value text-primary">{{ $document->content_data['tenure_string'] }}</div>
                </td>
            </tr>
        </table>

        <!-- Certificate Body -->
        <div class="cert-body">
            <p>
                This is to certify that <strong>{{ $document->content_data['employee_name'] }}</strong> (Employee Code: <strong>{{ $document->content_data['employee_id'] }}</strong>) 
                was gainfully employed with <strong>{{ $companyName }}</strong> from 
                <strong>{{ $document->content_data['date_of_joining'] }}</strong> to 
                <strong>{{ $document->content_data['last_working_day'] }}</strong>, completing a total tenure of 
                <strong>{{ $document->content_data['tenure_string'] }}</strong>.
            </p>

            <p>
                During their tenure with our organization, they served as a full-time 
                <strong>{{ $document->content_data['designation'] }}</strong> in the 
                <strong>{{ $document->content_data['department'] }}</strong> department. In this capacity, they were responsible for executing key responsibilities, demonstrating strong professional competence, technical aptitude, and collaboration with team members and cross-functional departments.
            </p>

            <p>
                {{ $document->content_data['conduct_statement'] ?? 'During their period of employment with us, we found them to be sincere, industrious, disciplined, and reliable. Their conduct and character throughout their tenure were exemplary.' }}
            </p>

            <p>
                They have completed all necessary handover procedures and exit formalities. We thank them for their valuable service and wish them continued growth, prosperity, and success in all their future professional endeavors.
            </p>
        </div>

        <!-- Signatures & Seal Section -->
        <div class="signature-section">
            <div class="row align-items-end">
                <div class="col-7">
                    <div class="text-muted fs-11 mb-4">For and on behalf of {{ $companyName }},</div>
                    <div class="signature-line"></div>
                    <div class="fw-bold text-dark fs-13">Authorized Signatory</div>
                    <div class="text-muted fs-11">Head of Human Resources & Corporate Affairs</div>
                    <div class="text-muted fs-11">{{ $companyName }}</div>
                </div>
                <div class="col-5 text-center">
                    <div class="seal-box mb-2">[ OFFICIAL SEAL ]</div>
                    <div class="text-muted fs-10">Corporate Seal & Attestation</div>
                </div>
            </div>
        </div>

        <!-- Document Footer -->
        <div class="doc-footer">
            This certificate is an official document issued under the authority of {{ $companyName }}.<br>
            Verification Code: {{ md5($document->reference_number . ($document->content_data['employee_id'] ?? '')) }} | Generated via SaaS ERP HRMS
        </div>
    </div>
</body>
</html>
