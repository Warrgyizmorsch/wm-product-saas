<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Models\Employee;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentSignatureService
{
    public function __construct(
        private readonly DocumentTemplateService $documentTemplateService
    ) {}

    /**
     * Process signature input (base64 image or file path), save to storage, and return public relative path.
     */
    public function saveSignatureImage(string $signatureInput, ?User $user = null): string
    {
        $tenantId = $user?->tenant_id ?? (auth()->user()?->tenant_id ?? 1);
        $userId = $user?->id ?? (auth()->id() ?? 'guest');
        
        $folder = "signatures/tenant_{$tenantId}/user_{$userId}";
        
        if (str_starts_with($signatureInput, 'data:image')) {
            // Base64 Data URL string
            $imageParts = explode(';base64,', $signatureInput);
            $imageDecoded = base64_decode($imageParts[1] ?? '');
            
            $filename = "sig_" . Str::random(12) . "_" . time() . ".png";
            $relativePath = "{$folder}/{$filename}";
            
            Storage::disk('public')->put($relativePath, $imageDecoded);
        } else {
            $relativePath = $signatureInput;
        }

        return $relativePath;
    }

    /**
     * Render & generate a signed document from a DocumentTemplate with Option A on-the-fly signature.
     */
    public function generateSignedDocumentFromTemplate(
        DocumentTemplate $template,
        Employee $employee,
        ?string $signatureImageInput = null,
        ?string $refNumber = null
    ): array {
        /** @var User|null $user */
        $user = auth()->user();
        $tenantId = $user?->tenant_id ?? $employee->tenant_id ?? 1;

        // 1. Resolve Signature Path from on-the-fly input
        $signaturePath = null;
        if (!empty($signatureImageInput)) {
            $signaturePath = $this->saveSignatureImage($signatureImageInput, $user);
        }

        // 2. Render Template HTML with HR signature tags
        $renderedHtml = $this->documentTemplateService->renderTemplate($template, $employee, $refNumber);
        
        // Replace HR signature dynamic tags
        $dateStr = Carbon::today()->format('d M, Y');
        $hrName = e($user?->name ?? 'HR Department');
        $hrDesignation = e($user?->role ?? 'HR Manager');
        
        if ($signaturePath && Storage::disk('public')->exists($signaturePath)) {
            $sigFullUrl = asset('storage/' . $signaturePath);
            $sigHtml = '<div style="display:inline-block; text-align:center; margin-top:5px;">
                <img src="' . $sigFullUrl . '" style="max-height:60px; width:auto;" alt="Digital Signature" />
                <div style="font-size:10px; color:#64748b; margin-top:2px;">Digitally Signed by ' . $hrName . ' on ' . $dateStr . '</div>
            </div>';
        } else {
            $sigHtml = '<span style="color:#94a3b8; font-style:italic; font-size:12px;">[Signed Electronically by ' . $hrName . ']</span>';
        }

        $renderedHtml = str_replace([
            '{{hr_signature}}',
            '{{hr_name}}',
            '{{hr_designation}}',
            '{{signature_date}}'
        ], [
            $sigHtml,
            $hrName,
            $hrDesignation,
            $dateStr
        ], $renderedHtml);

        // 3. Append Certificate of Completion Audit Footer to HTML
        $ipAddress = request()->ip() ?? '127.0.0.1';
        $auditHtml = '<div style="margin-top:40px; padding-top:15px; border-top:1px dashed #cbd5e1; font-size:11px; color:#64748b;">
            <strong>Digital Signature Verification & Audit Trail</strong><br/>
            Signer: ' . $hrName . ' (' . e($user?->email ?? 'N/A') . ') | IP Address: ' . $ipAddress . ' | Date: ' . Carbon::now()->toIso8601String() . '<br/>
            Verification Status: Certified Authentic & Signed via ' . config('app.name', 'WM SaaS ERP') . ' HRMS Document Engine
        </div>';
        
        $fullContentWithAudit = str_replace('</div>', $auditHtml . '</div>', $renderedHtml);

        // 4. Generate PDF using DomPDF
        $pdfFileName = "signed_doc_" . Str::slug($template->name) . "_" . $employee->id . "_" . time() . ".pdf";
        $pdfStoragePath = "documents/tenant_{$tenantId}/employee_{$employee->id}/{$pdfFileName}";

        try {
            $pdf = Pdf::loadHTML($fullContentWithAudit)
                ->setPaper('a4', 'portrait')
                ->setWarnings(false);
            
            Storage::disk('public')->put($pdfStoragePath, $pdf->output());
        } catch (\Exception $e) {
            Log::error("Failed to render PDF in DocumentSignatureService: " . $e->getMessage());
        }

        // 5. Store / Update Document Record
        $document = Document::create([
            'tenant_id'          => $tenantId,
            'documentable_type'  => Employee::class,
            'documentable_id'    => $employee->id,
            'name'               => $template->name . ' - ' . $employee->full_name,
            'description'        => "Generated and signed from template: {$template->name}",
            'file_name'          => $pdfFileName,
            'file_path'          => $pdfStoragePath,
            'file_type'          => 'pdf',
            'status'             => 'approved',
            'is_signed'          => true,
            'requires_signature' => false,
            'signed_at'          => now(),
            'signed_by_id'       => $user?->id,
            'signature_ip'       => $ipAddress,
            'signed_file_path'   => $pdfStoragePath,
            'signature_metadata' => [
                'template_id'    => $template->id,
                'signed_by_name' => $hrName,
                'signed_by_email'=> $user?->email,
                'signature_path' => $signaturePath,
            ],
            'requested_by_id'    => $user?->id,
        ]);

        return [
            'document'       => $document,
            'pdf_path'       => $pdfStoragePath,
            'pdf_url'        => asset('storage/' . $pdfStoragePath),
            'rendered_html'  => $renderedHtml,
            'signature_path' => $signaturePath,
        ];
    }

    /**
     * Sign an uploaded PDF document by generating a signed version with the embedded signature image and audit trail.
     */
    public function signUploadedDocument(
        Document $document,
        string $signatureImageInput,
        ?array $coordinates = null
    ): Document {
        /** @var User|null $user */
        $user = auth()->user();
        $ipAddress = request()->ip() ?? '127.0.0.1';
        $signerName = e($user?->name ?? 'Employee');
        $signerEmail = e($user?->email ?? 'N/A');
        $signedAtStr = Carbon::now()->format('d M, Y H:i T');

        // 1. Save Signature PNG
        $signaturePath = $this->saveSignatureImage($signatureImageInput, $user);

        // 2. Prepare Base64 Data URI for DomPDF
        $sigDataUri = $signatureImageInput;
        if (!str_starts_with($signatureImageInput, 'data:image')) {
            if (Storage::disk('public')->exists($signaturePath)) {
                $fileBytes = Storage::disk('public')->get($signaturePath);
                $sigDataUri = 'data:image/png;base64,' . base64_encode($fileBytes);
            }
        }

        // 3. Resolve Original File Data URI
        $docTitle = e($document->name ?? 'Document');
        $docDesc = e($document->description ?? 'Employee Official Document');
        $tenantId = $document->tenant_id ?? 1;
        $empId = $document->documentable_id ?? ($user?->id ?? 1);

        $originalFilePath = $document->file_path;
        $originalDataUri = null;
        $isImage = false;

        if ($originalFilePath && Storage::disk('public')->exists($originalFilePath)) {
            $ext = strtolower(pathinfo($originalFilePath, PATHINFO_EXTENSION));
            $fileBytes = Storage::disk('public')->get($originalFilePath);
            $mimeType = Storage::disk('public')->mimeType($originalFilePath) ?? 'image/png';

            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                $isImage = true;
                $originalDataUri = 'data:' . $mimeType . ';base64,' . base64_encode($fileBytes);
            }
        }

        $position = $coordinates['position'] ?? 'bottom_right';
        $posX = $coordinates['pos_x'] ?? null;
        $posY = $coordinates['pos_y'] ?? null;

        if ($posX !== null && $posY !== null && is_numeric($posX) && is_numeric($posY)) {
            $sigBoxStyle = 'margin-top: ' . intval($posY) . 'px; margin-left: ' . intval($posX) . 'px; width: 240px; text-align: center; border: 1.5px solid #0f172a; padding: 8px; background: #ffffff; border-radius: 6px;';
        } elseif ($position === 'bottom_left') {
            $sigBoxStyle = 'margin-top: 20px; float: left; width: 260px; text-align: center; border: 1.5px solid #0f172a; padding: 10px; background: #ffffff; border-radius: 6px;';
        } elseif ($position === 'bottom_center') {
            $sigBoxStyle = 'margin-top: 20px; margin-left: auto; margin-right: auto; width: 260px; text-align: center; border: 1.5px solid #0f172a; padding: 10px; background: #ffffff; border-radius: 6px; clear: both;';
        } elseif ($position === 'top_left') {
            $sigBoxStyle = 'margin-bottom: 20px; float: left; width: 260px; text-align: center; border: 1.5px solid #0f172a; padding: 10px; background: #ffffff; border-radius: 6px;';
        } elseif ($position === 'top_right') {
            $sigBoxStyle = 'margin-bottom: 20px; float: right; width: 260px; text-align: center; border: 1.5px solid #0f172a; padding: 10px; background: #ffffff; border-radius: 6px;';
        } else {
            $sigBoxStyle = 'margin-top: 20px; float: right; width: 260px; text-align: center; border: 1.5px solid #0f172a; padding: 10px; background: #ffffff; border-radius: 6px;';
        }

        // Prepare main document body HTML
        if ($isImage && $originalDataUri) {
            $docBodyHtml = '
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="' . $originalDataUri . '" style="max-width: 100%; max-height: 650px; border: 1px solid #cbd5e1; border-radius: 4px;" alt="Original Document" />
            </div>';
        } else {
            $docBodyHtml = '
            <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 25px; margin-bottom: 25px;">
                <div style="font-size: 15px; font-weight: bold; color: #0f172a; margin-bottom: 10px;">Document: ' . $docTitle . '</div>
                <div style="font-size: 12px; color: #475569; line-height: 1.6;">
                    ' . $docDesc . '<br/>
                    <strong>Original File:</strong> ' . e($document->file_name ?? basename($originalFilePath)) . '
                </div>
            </div>';
        }

        $signedHtml = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . $docTitle . '</title>
            <style>
                @page { margin: 25px; }
                body { font-family: Arial, Helvetica, sans-serif; padding: 15px; color: #1e293b; line-height: 1.5; font-size: 12px; }
                .header { border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 20px; }
                .doc-title { font-size: 18px; font-weight: bold; color: #0f172a; margin: 0; }
                .doc-sub { font-size: 11px; color: #64748b; margin-top: 3px; }
                .sig-box { ' . $sigBoxStyle . ' }
                .sig-img { max-height: 65px; width: auto; margin-bottom: 4px; display: block; margin-left: auto; margin-right: auto; }
                .audit-footer { clear: both; margin-top: 60px; padding-top: 12px; border-top: 1px dashed #94a3b8; font-size: 9.5px; color: #64748b; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="doc-title">' . $docTitle . '</div>
                <div class="doc-sub">File: ' . e($document->file_name ?? basename($originalFilePath)) . ' | Status: Digitally Signed & Approved</div>
            </div>

            ' . $docBodyHtml . '

            <div class="sig-box">
                <img src="' . $sigDataUri . '" class="sig-img" alt="Digital Signature" />
                <div style="font-weight: bold; font-size: 12px; color: #0f172a;">' . $signerName . '</div>
                <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">Digitally Signed on ' . $signedAtStr . '</div>
            </div>

            <div class="audit-footer">
                <strong>DIGITAL VERIFICATION AUDIT TRAIL</strong><br/>
                Signer Name: ' . $signerName . ' (' . $signerEmail . ') | IP Address: ' . $ipAddress . ' | Timestamp: ' . Carbon::now()->toIso8601String() . '<br/>
                Verification Status: Certified Authentic & Signed via WM SaaS HRMS Engine
            </div>
        </body>
        </html>';

        // 4. Generate Signed PDF File using DomPDF
        $pdfFileName = "signed_doc_" . $document->id . "_" . time() . ".pdf";
        $pdfStoragePath = "documents/tenant_{$tenantId}/employee_{$empId}/{$pdfFileName}";

        try {
            $pdf = Pdf::loadHTML($signedHtml)->setPaper('a4', 'portrait')->setWarnings(false);
            Storage::disk('public')->put($pdfStoragePath, $pdf->output());
        } catch (\Exception $e) {
            Log::error("Failed to generate signed document PDF: " . $e->getMessage());
            $pdfStoragePath = $originalFilePath;
        }

        // 5. Update Document Record (Keep original file_path intact!)
        $document->update([
            'is_signed'          => true,
            'status'             => 'approved',
            'signed_at'          => now(),
            'signed_by_id'       => $user?->id,
            'signature_ip'       => $ipAddress,
            'signed_file_path'   => $pdfStoragePath,
            'signature_metadata' => array_merge($document->signature_metadata ?? [], [
                'signed_by_name' => $signerName,
                'signed_by_email'=> $signerEmail,
                'signature_path' => $signaturePath,
                'original_file'  => $originalFilePath,
                'coordinates'    => $coordinates ?? ['position' => 'bottom_right'],
                'signed_at_iso'  => now()->toIso8601String(),
                'ip_address'     => $ipAddress,
            ]),
        ]);

        return $document->fresh(['signedBy', 'documentable']);
    }
}
