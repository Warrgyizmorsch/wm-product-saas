<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->name }} - Digitally Signed Document</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Feather Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.css">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        .viewer-header {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .doc-container-wrapper {
            max-width: 1100px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            overflow: hidden;
            border: 1px solid #cbd5e1;
            position: relative;
        }
        .doc-frame-box {
            position: relative;
            width: 100%;
            height: 780px;
            background: #f8fafc;
        }
        .sig-overlay-badge {
            position: absolute;
            z-index: 500;
            background: rgba(255, 255, 255, 0.96);
            border: 1.5px solid #0f172a;
            border-radius: 6px;
            padding: 8px 14px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            pointer-events: auto;
            min-width: 200px;
        }
        .sig-overlay-img {
            max-height: 55px;
            width: auto;
            display: block;
            margin: 0 auto 4px auto;
        }
        .audit-footer-bar {
            background: #f8fafc;
            border-top: 1px dashed #cbd5e1;
            padding: 15px 24px;
            font-size: 11px;
            color: #64748b;
        }
    </style>
</head>
<body>

    @php
        $metadata = $document->signature_metadata ?? [];
        $sigPath = $metadata['signature_path'] ?? null;
        $sigUrl = $sigPath ? asset('storage/' . $sigPath) : null;
        $signerName = $metadata['signed_by_name'] ?? ($document->signedBy?->name ?? 'Authorized Signer');
        $signedAtStr = $document->signed_at ? $document->signed_at->format('d M, Y H:i T') : 'N/A';

        $coords = $metadata['coordinates'] ?? [];
        $position = $coords['position'] ?? 'bottom_right';
        $posX = $coords['pos_x'] ?? null;
        $posY = $coords['pos_y'] ?? null;

        $sigPosCss = 'bottom: 60px; right: 40px;';
        if ($posX !== null && $posY !== null && is_numeric($posX) && is_numeric($posY)) {
            $sigPosCss = 'top: ' . intval($posY) . 'px; left: ' . intval($posX) . 'px;';
        } elseif ($position === 'bottom_left') {
            $sigPosCss = 'bottom: 60px; left: 40px;';
        } elseif ($position === 'bottom_center') {
            $sigPosCss = 'bottom: 60px; left: 50%; transform: translateX(-50%);';
        } elseif ($position === 'top_left') {
            $sigPosCss = 'top: 60px; left: 40px;';
        } elseif ($position === 'top_right') {
            $sigPosCss = 'top: 60px; right: 40px;';
        }

        $fileUrl = asset('storage/' . $document->file_path);
        $ext = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));
        $isImage = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif']);
    @endphp

    <!-- HEADER BAR -->
    <div class="viewer-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary-subtle text-primary p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i data-feather="file-text"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-0 text-dark fs-15">{{ $document->name }}</h5>
                <small class="text-muted fs-12">
                    File: {{ $document->file_name ?? basename($document->file_path) }} &bull; 
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill">
                        <i data-feather="check-circle" style="width: 11px; height: 11px;"></i> Digitally Signed
                    </span>
                </small>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ $fileUrl }}" download class="btn btn-outline-secondary btn-sm fw-semibold d-inline-flex align-items-center gap-1.5">
                <i data-feather="download" style="width: 14px; height: 14px;"></i> Download Original
            </a>
            @if($document->signed_file_path)
                <a href="{{ asset('storage/' . $document->signed_file_path) }}" download class="btn btn-primary btn-sm fw-bold d-inline-flex align-items-center gap-1.5">
                    <i data-feather="file-text" style="width: 14px; height: 14px;"></i> Download Signed PDF
                </a>
            @endif
            <button type="button" onclick="window.print()" class="btn btn-dark btn-sm fw-semibold d-inline-flex align-items-center gap-1.5">
                <i data-feather="printer" style="width: 14px; height: 14px;"></i> Print
            </button>
        </div>
    </div>

    <!-- MAIN DOCUMENT CONTAINER -->
    <div class="doc-container-wrapper">
        <div class="doc-frame-box">
            <!-- ORIGINAL DOCUMENT CONTENT (PDF or IMAGE) -->
            @if($isImage)
                <div class="d-flex justify-content-center align-items-center h-100 p-3 bg-secondary-subtle overflow-auto">
                    <img src="{{ $fileUrl }}" class="img-fluid rounded shadow-sm" style="max-height: 740px; object-fit: contain;" alt="Original Document">
                </div>
            @else
                <iframe src="{{ $fileUrl }}" style="width: 100%; height: 100%; border: none;"></iframe>
            @endif
        </div>

        <!-- DIGITAL SIGNATURE CARD (EXECUTIVE SECURITY CERTIFICATE DESIGN) -->
        @if($sigUrl)
            <div class="signature-section-block p-4 border-top" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-left: 4px solid #10b981 !important;">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-4">
                    <div class="pe-md-3" style="max-width: 650px;">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fs-11 fw-bold">
                                <i data-feather="shield-check" style="width: 12px; height: 12px;" class="me-1"></i> VERIFIED SIGNATURE
                            </span>
                            <span class="text-muted fs-11">&bull; Legal Authenticity Certificate</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1 fs-15">Employee Digital Signature Certificate</h6>
                        <p class="text-muted fs-12 mb-0" style="line-height: 1.5;">This document has been electronically executed and legally validated via biometric/digital signature capture by the authorized signatory.</p>
                    </div>

                    <!-- SECURITY STAMP BOX -->
                    <div class="employee-signature-card bg-white rounded-3 border p-3.5 text-center shadow-sm" style="min-width: 260px; max-width: 290px; border-color: #cbd5e1 !important; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06) !important;">
                        <div class="bg-light rounded p-2 mb-2 border border-light">
                            <img src="{{ $sigUrl }}" class="img-fluid" style="max-height: 65px; object-fit: contain; display: block; margin: 0 auto;" alt="Employee Signature">
                        </div>
                        <div class="fw-bold text-dark fs-14 mb-0.5" style="letter-spacing: -0.2px;">{{ $signerName }}</div>
                        <div class="text-success fw-semibold fs-11 mb-1 d-flex align-items-center justify-content-center gap-1">
                            <i data-feather="check-circle" style="width: 12px; height: 12px;"></i> Signed on {{ $signedAtStr }}
                        </div>
                        <div class="pt-2 border-top text-muted font-monospace fs-10 text-uppercase" style="font-size: 9.5px; letter-spacing: 0.5px;">
                            REF: SIG-{{ strtoupper(substr(md5($document->id . $signedAtStr), 0, 8)) }}
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- DIGITAL VERIFICATION AUDIT TRAIL FOOTER -->
        <div class="audit-footer-bar d-flex justify-content-between align-items-center flex-wrap gap-3" style="background-color: #0f172a; color: #94a3b8; padding: 16px 24px; border-top: none;">
            <div>
                <div class="text-white fw-bold fs-11 tracking-wide text-uppercase mb-0.5" style="letter-spacing: 0.5px;">DIGITAL VERIFICATION AUDIT TRAIL</div>
                <div class="fs-11 text-slate-300">
                    Signer: <strong class="text-white">{{ $signerName }}</strong> &bull; IP Address: <code class="text-info bg-dark px-1.5 py-0.5 rounded">{{ $document->signature_ip ?? '127.0.0.1' }}</code> &bull; Timestamp: <span class="text-white">{{ $signedAtStr }}</span>
                </div>
            </div>
            <div>
                <span class="badge bg-success text-white px-3 py-1.5 rounded-pill font-monospace fs-10 fw-bold shadow-sm" style="letter-spacing: 0.5px;">
                    <i data-feather="lock" style="width: 10px; height: 10px;" class="me-1"></i> CERTIFIED AUTHENTIC
                </span>
            </div>
        </div>
    </div>

    <script>
        feather.replace();
    </script>
</body>
</html>
