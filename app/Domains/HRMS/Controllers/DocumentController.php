<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Repositories\DocumentRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Document::class);

        $data = $this->documentRepository->getIndexData($request->all(), auth()->user());

        return view('modules.hrms.documents.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $validated = $request->validate([
            'employee_id'         => 'required|exists:employees,id',
            'document_master_id'  => 'nullable|exists:document_masters,id',
            'name'                => 'nullable|string|max:255',
            'file'                => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv|max:10240',
        ]);

        $this->documentRepository->storeDocument($validated, $request, auth()->user());

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'document_master_id' => 'nullable|exists:document_masters,id',
            'name'               => 'nullable|string|max:255',
            'file'               => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv|max:10240',
        ]);

        $this->documentRepository->updateDocument($document, $validated, $request);

        return redirect()->back()->with('success', 'Document updated successfully.');
    }

    public function updateStatus(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'status'           => 'required|in:approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        $this->documentRepository->updateStatus($document, $validated['status'], $validated['rejection_reason'] ?? null, auth()->user());

        return redirect()->back()->with('success', "Document status updated to {$validated['status']}.");
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->documentRepository->deleteDocument($document);

        return redirect()->back()->with('success', 'Document deleted successfully.');
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $this->authorize('update', Document::class);

        $validated = $request->validate([
            'document_ids'   => 'required|array|min:1',
            'document_ids.*' => 'exists:documents,id',
        ]);

        $count = $this->documentRepository->bulkApprove($validated['document_ids'], auth()->user());

        return redirect()->back()->with('success', "Successfully approved {$count} documents.");
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $this->authorize('update', Document::class);

        $validated = $request->validate([
            'document_ids'     => 'required|array|min:1',
            'document_ids.*'   => 'exists:documents,id',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $count = $this->documentRepository->bulkReject($validated['document_ids'], $validated['rejection_reason'] ?? null, auth()->user());

        return redirect()->back()->with('success', "Successfully rejected {$count} documents.");
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $this->authorize('delete', Document::class);

        $validated = $request->validate([
            'document_ids'   => 'required|array|min:1',
            'document_ids.*' => 'exists:documents,id',
        ]);

        $count = $this->documentRepository->bulkDelete($validated['document_ids']);

        return redirect()->back()->with('success', "Successfully deleted {$count} documents.");
    }

    /**
     * Handle bulk upload and template document generation.
     */
    public function bulkUpload(Request $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $uploadMode = $request->input('upload_mode', 'file');

        if (in_array($uploadMode, ['generate', 'generate_template'], true)) {
            $request->validate([
                'employee_id'          => 'required|string',
                'document_template_id' => 'required|exists:document_templates,id',
            ]);
        } else {
            $request->validate([
                'employee_id'        => 'required|string',
                'document_master_id' => 'required|exists:document_masters,id',
                'file'               => 'required|file|max:10240',
                'expiry_date'        => 'nullable|date',
            ]);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        // Resolve target employee IDs
        $employeeId = $request->input('employee_id');
        if ($employeeId === 'all') {
            $targetEmployeeIds = Employee::pluck('id')->toArray();
        } else {
            $targetEmployeeIds = [$employeeId];
        }

        if (empty($targetEmployeeIds)) {
            return redirect()->back()->with('error', 'No employees found for this upload target.');
        }

        if (in_array($uploadMode, ['generate', 'generate_template'], true)) {
            $docTemplate = \App\Domains\HRMS\Models\DocumentTemplate::find($request->integer('document_template_id'));
            if (!$docTemplate) {
                return redirect()->back()->with('error', 'Document template not found.');
            }

            $templateService = app(\App\Domains\HRMS\Services\DocumentTemplateService::class);
            $hrName = $request->input('hr_name', auth()->user()?->name ?? 'Authorized Signatory');
            $hrDesignation = $request->input('hr_designation', 'HR Manager');
            $issueDate = $request->input('issue_date', date('Y-m-d'));

            // Resolve HR signature input
            $hrSigUrl = null;
            if ($request->hasFile('hr_signature_file') && $request->file('hr_signature_file')->isValid()) {
                $file = $request->file('hr_signature_file');
                $hrSigPath = $file->store("signatures/hr_tenant_{$tenantId}", 'public');
                $hrSigUrl = asset('storage/' . $hrSigPath);
            } elseif ($request->filled('hr_signature_data')) {
                $hrSigData = $request->input('hr_signature_data');
                if (str_starts_with($hrSigData, 'data:image')) {
                    $image = str_replace(' ', '+', preg_replace('/^data:image\/\w+;base64,/', '', $hrSigData));
                    $imageName = 'hr_sig_' . time() . '_' . \Illuminate\Support\Str::random(6) . '.png';
                    $hrSigPath = "signatures/hr_tenant_{$tenantId}/{$imageName}";
                    \Illuminate\Support\Facades\Storage::disk('public')->put($hrSigPath, base64_decode($image));
                    $hrSigUrl = asset('storage/' . $hrSigPath);
                } else {
                    $hrSigUrl = $hrSigData;
                }
            }

            $extraData = [
                'hr_name'          => $hrName,
                'hr_designation'   => $hrDesignation,
                'issue_date'       => $issueDate,
                'hr_signature_url' => $hrSigUrl,
            ];

            $count = 0;
            foreach ($targetEmployeeIds as $empId) {
                $employee = Employee::find($empId);
                if (!$employee) {
                    continue;
                }

                $customRef = $request->input('reference_number');
                $customTitle = $request->input('document_title');
                $refNo = $customRef ?: ('DOC/' . date('Y') . '/' . str_pad((string)$employee->id, 4, '0', STR_PAD_LEFT));
                $renderedContent = $templateService->renderTemplate($docTemplate, $employee, $refNo, $extraData);
                $title = $customTitle ?: ($docTemplate->name . ' - ' . $employee->full_name);

                $fileName = \Illuminate\Support\Str::slug($docTemplate->name . '-' . $employee->full_name) . '-' . time() . '.pdf';
                $path = "documents/tenant_{$tenantId}/employee_{$employee->id}/{$fileName}";

                try {
                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($templateService->toPrintableDocument($renderedContent, $title))
                        ->setPaper('a4', 'portrait')
                        ->setWarnings(false);
                    $pdfContent = $pdf->output();
                    \Illuminate\Support\Facades\Storage::disk('public')->put($path, $pdfContent);
                } catch (\Throwable $e) {
                    // Fallback to storing standalone HTML if DomPDF is unavailable
                    $htmlPath = "documents/tenant_{$tenantId}/employee_{$employee->id}/" . \Illuminate\Support\Str::slug($docTemplate->name . '-' . $employee->full_name) . '-' . time() . '.html';
                    \Illuminate\Support\Facades\Storage::disk('public')->put($htmlPath, $templateService->toPrintableDocument($renderedContent, $title));
                    $path = $htmlPath;
                }

                $masterId = null;
                if ($docTemplate->document_category_id) {
                    $master = \App\Domains\HRMS\Models\DocumentMaster::where('tenant_id', $tenantId)
                        ->where(function ($q) use ($docTemplate) {
                            $q->where('code', $docTemplate->code)
                              ->orWhere('name', $docTemplate->name);
                        })->first();

                    if (!$master) {
                        $master = \App\Domains\HRMS\Models\DocumentMaster::create([
                            'tenant_id'             => $tenantId,
                            'document_category_id'  => $docTemplate->document_category_id,
                            'name'                  => $docTemplate->name,
                            'code'                  => $docTemplate->code,
                            'upload_responsibility' => 'hr',
                            'requires_signature'    => $docTemplate->requires_signature,
                            'employee_can_view'     => true,
                            'employee_can_download' => true,
                            'status'                => 'active',
                        ]);
                    }
                    $masterId = $master?->id;
                }

                $docStatus = $docTemplate->requires_signature ? 'pending_signature' : 'approved';

                \App\Domains\HRMS\Models\GeneratedDocument::create([
                    'tenant_id'            => $tenantId,
                    'employee_id'          => $employee->id,
                    'document_template_id' => $docTemplate->id,
                    'document_master_id'   => $masterId,
                    'reference_number'     => $refNo,
                    'title'                => $title,
                    'rendered_content'     => $renderedContent,
                    'file_path'            => $path,
                    'issue_date'           => $issueDate,
                    'generated_by'         => auth()->id(),
                    'status'               => $docStatus === 'approved' ? 'issued' : 'generated',
                ]);

                Document::create([
                    'tenant_id'             => $tenantId,
                    'documentable_type'     => Employee::class,
                    'documentable_id'       => $employee->id,
                    'document_master_id'    => $masterId,
                    'name'                  => $title,
                    'file_path'             => $path,
                    'file_type'             => pathinfo($path, PATHINFO_EXTENSION),
                    'file_size'             => \Illuminate\Support\Facades\Storage::disk('public')->exists($path) ? \Illuminate\Support\Facades\Storage::disk('public')->size($path) : 0,
                    'status'                => $docStatus,
                    'requires_signature'    => (bool) $docTemplate->requires_signature,
                    'is_signed'             => false,
                    'requested_by_id'       => auth()->id(),
                ]);

                $count++;
            }

            return redirect()->back()->with('success', "Successfully generated {$count} document(s) from template.");
        }

        // Standard bulk upload mode
        $file = $request->file('file');
        $docMaster = \App\Domains\HRMS\Models\DocumentMaster::find($request->input('document_master_id'));
        $count = 0;

        foreach ($targetEmployeeIds as $empId) {
            $employee = Employee::find($empId);
            if (!$employee) {
                continue;
            }

            $storedPath = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            Document::create([
                'tenant_id'          => $tenantId,
                'documentable_type'  => Employee::class,
                'documentable_id'    => $employee->id,
                'document_master_id' => $docMaster?->id,
                'name'               => $docMaster?->name ?: $file->getClientOriginalName(),
                'file_path'          => $storedPath,
                'file_type'          => $file->getClientOriginalExtension(),
                'file_size'          => $file->getSize(),
                'expiry_date'        => $request->input('expiry_date'),
                'status'             => 'approved',
                'requires_signature' => (bool) ($docMaster?->requires_signature ?? false),
                'requested_by_id'    => auth()->id(),
            ]);

            $count++;
        }

        return redirect()->back()->with('success', "Successfully uploaded document for {$count} employee(s).");
    }
}
