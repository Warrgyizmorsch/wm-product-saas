<?php

declare(strict_types=1);

namespace App\Domains\Purchase\Controllers;

use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\VendorDocument;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for managing Vendor Statutory & Compliance Documents and Expiry Alerts (FRS 2.3 / Task C-19).
 */
class VendorDocumentController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
    ) {
    }

    private function authorizeVendor(string $permission): void
    {
        $user = auth()->user();

        abort_unless(
            $user && $this->access->allows($user, $permission, ['tenant_id' => $user->tenant_id]),
            403
        );
    }

    /**
     * POST /purchase/vendors/{vendor}/documents
     * Upload and register a compliance document.
     */
    public function store(Request $request, Vendor $vendor): RedirectResponse|JsonResponse
    {
        $this->authorizeVendor('purchase.vendors.edit');
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'document_type' => 'required|string|in:msme,ldc_form13,iso_cert,gst_cert,agreement,nda,other',
            'document_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:100',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'notes' => 'nullable|string|max:500',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:10240', // 10MB max
        ]);

        $filePath = null;
        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file = $request->file('file');
            // Compliance papers go on the private disk (served only through download()) under a random name.
            $fileName = Str::random(16) . '.' . $file->getClientOriginalExtension();
            $destinationFolder = "uploads/tenants/{$tenantId}/vendors/{$vendor->id}/documents";
            $filePath = $file->storeAs($destinationFolder, $fileName, 'local');
        }

        $document = VendorDocument::create([
            'tenant_id' => $tenantId,
            'company_id' => $vendor->company_id,
            'branch_id' => $vendor->branch_id,
            'vendor_id' => $vendor->id,
            'document_type' => $validated['document_type'],
            'document_name' => $validated['document_name'],
            'document_number' => $validated['document_number'] ?? null,
            'file_path' => $filePath,
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'reminder_days' => VendorDocument::DEFAULT_REMINDER_DAYS,
            'status' => 'valid',
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // A document uploaded already expired or about to expire must not start out as "valid".
        $document->update(['status' => $document->computed_status]);

        // If this is Lower TDS Certificate (Form 13), synchronize with Vendor TDS columns for backward compatibility
        if ($validated['document_type'] === VendorDocument::TYPE_LDC) {
            $updates = [];
            if (!empty($validated['document_number'])) {
                $updates['ldc_number'] = $validated['document_number'];
            }
            if (!empty($validated['issue_date'])) {
                $updates['ldc_valid_from'] = $validated['issue_date'];
            }
            if (!empty($validated['expiry_date'])) {
                $updates['ldc_valid_to'] = $validated['expiry_date'];
            }
            if (!empty($updates)) {
                $vendor->update($updates);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'document' => $document]);
        }

        return back()->with('success', 'Vendor document uploaded successfully.');
    }

    /**
     * GET /purchase/vendors/{vendor}/documents/{document}/download
     */
    public function download(Request $request, Vendor $vendor, VendorDocument $document): StreamedResponse|RedirectResponse
    {
        $this->authorizeVendor('purchase.vendors.view');
        $tenantId = require_tenant_id();

        if ((int) $document->vendor_id !== (int) $vendor->id || (int) $document->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        $disk = null;
        if ($document->file_path) {
            if (Storage::disk('local')->exists($document->file_path)) {
                $disk = 'local';
            } elseif (Storage::disk('public')->exists($document->file_path)) {
                $disk = 'public';
            }
        }

        if (! $disk) {
            return back()->with('error', 'File not found on server.');
        }

        return Storage::disk($disk)->download($document->file_path, $document->document_name . '.' . pathinfo($document->file_path, PATHINFO_EXTENSION));
    }

    /**
     * DELETE /purchase/vendors/{vendor}/documents/{document}
     */
    public function destroy(Request $request, Vendor $vendor, VendorDocument $document): RedirectResponse|JsonResponse
    {
        $this->authorizeVendor('purchase.vendors.edit');
        $tenantId = require_tenant_id();

        if ((int) $document->vendor_id !== (int) $vendor->id || (int) $document->tenant_id !== (int) $tenantId) {
            abort(404);
        }

        $document->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Document deleted successfully.');
    }
}
