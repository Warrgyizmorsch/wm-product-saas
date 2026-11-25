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

        $user = auth()->user();
        $currentEmployee = $user ? app(\App\Domains\HRMS\Services\HrmsScopeService::class)->resolveEmployee($user) : null;
        $canSelfManage = $this->canSelfManage($user, $currentEmployee);

        if ($currentEmployee && (int) $validated['employee_id'] === (int) $currentEmployee->id && !$canSelfManage) {
            return redirect()->back()->with('error', __('hrms.documents.error_hierarchy_upload'));
        }

        $this->documentRepository->storeDocument($validated, $request, auth()->user());

        return redirect()->back()->with('success', __('hrms.documents.upload_success'));
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

        return redirect()->back()->with('success', __('hrms.documents.update_success'));
    }

    public function updateStatus(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'status'           => 'required|in:approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        $user = auth()->user();
        $currentEmployee = $user ? app(\App\Domains\HRMS\Services\HrmsScopeService::class)->resolveEmployee($user) : null;
        $canSelfManage = $this->canSelfManage($user, $currentEmployee, $document);

        if ($currentEmployee && (int) $document->documentable_id === (int) $currentEmployee->id && !$canSelfManage) {
            return redirect()->back()->with('error', __('hrms.documents.error_hierarchy_approve'));
        }

        $this->documentRepository->updateStatus($document, $validated['status'], $validated['rejection_reason'] ?? null, auth()->user());

        return redirect()->back()->with('success', __('hrms.documents.status_updated', ['status' => $validated['status']]));
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->documentRepository->deleteDocument($document);

        return redirect()->back()->with('success', __('hrms.documents.delete_success'));
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $this->authorize('update', Document::class);

        $validated = $request->validate([
            'document_ids'   => 'required|array|min:1',
            'document_ids.*' => 'exists:documents,id',
        ]);

        $user = auth()->user();
        $currentEmployee = $user ? app(\App\Domains\HRMS\Services\HrmsScopeService::class)->resolveEmployee($user) : null;
        $canSelfManage = $this->canSelfManage($user, $currentEmployee);

        if ($currentEmployee && !$canSelfManage) {
            $hasOwn = Document::whereIn('id', $validated['document_ids'])
                ->where('documentable_type', Employee::class)
                ->where('documentable_id', $currentEmployee->id)
                ->exists();
            if ($hasOwn) {
                return redirect()->back()->with('error', __('hrms.documents.error_hierarchy_approve'));
            }
        }

        $count = $this->documentRepository->bulkApprove($validated['document_ids'], auth()->user());

        return redirect()->back()->with('success', __('hrms.documents.bulk_approve_success', ['count' => $count]));
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $this->authorize('update', Document::class);

        $validated = $request->validate([
            'document_ids'     => 'required|array|min:1',
            'document_ids.*'   => 'exists:documents,id',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $currentEmployee = $user ? app(\App\Domains\HRMS\Services\HrmsScopeService::class)->resolveEmployee($user) : null;
        $canSelfManage = $this->canSelfManage($user, $currentEmployee);

        if ($currentEmployee && !$canSelfManage) {
            $hasOwn = Document::whereIn('id', $validated['document_ids'])
                ->where('documentable_type', Employee::class)
                ->where('documentable_id', $currentEmployee->id)
                ->exists();
            if ($hasOwn) {
                return redirect()->back()->with('error', __('hrms.documents.error_hierarchy_approve'));
            }
        }

        $count = $this->documentRepository->bulkReject($validated['document_ids'], $validated['rejection_reason'] ?? null, auth()->user());

        return redirect()->back()->with('success', __('hrms.documents.bulk_reject_success', ['count' => $count]));
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $this->authorize('delete', Document::class);

        $validated = $request->validate([
            'document_ids'   => 'required|array|min:1',
            'document_ids.*' => 'exists:documents,id',
        ]);

        $count = $this->documentRepository->bulkDelete($validated['document_ids']);

        return redirect()->back()->with('success', __('hrms.documents.bulk_delete_success', ['count' => $count]));
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
                'file'               => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv|max:10240',
                'expiry_date'        => 'nullable|date',
            ]);
        }

        $user = auth()->user();
        $currentEmployee = $user ? app(\App\Domains\HRMS\Services\HrmsScopeService::class)->resolveEmployee($user) : null;
        $canSelfManage = $this->canSelfManage($user, $currentEmployee);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        // Resolve target employee IDs
        $employeeId = $request->input('employee_id');
        if ($employeeId === 'all') {
            $targetQuery = Employee::query();
            if ($currentEmployee && !$canSelfManage) {
                $targetQuery->where('id', '!=', $currentEmployee->id);
            }
            $targetEmployeeIds = $targetQuery->pluck('id')->toArray();
        } else {
            if ($currentEmployee && (int) $employeeId === (int) $currentEmployee->id && !$canSelfManage) {
                return redirect()->back()->with('error', __('hrms.documents.error_hierarchy_generate'));
            }
            $targetEmployeeIds = [$employeeId];
        }

        if (empty($targetEmployeeIds)) {
            return redirect()->back()->with('error', __('hrms.documents.error_no_employees_target'));
        }

        if (in_array($uploadMode, ['generate', 'generate_template'], true)) {
            $docTemplate = \App\Domains\HRMS\Models\DocumentTemplate::find($request->integer('document_template_id'));
            if (!$docTemplate) {
                return redirect()->back()->with('error', __('hrms.documents.error_template_not_found'));
            }

            $templateService = app(\App\Domains\HRMS\Services\DocumentTemplateService::class);
            $hrName = $request->input('hr_name', auth()->user()?->name ?? 'Authorized Signatory');
            $hrDesignation = $request->input('hr_designation', 'HR Manager');
            $issueDate = $request->input('issue_date', date('Y-m-d'));

            // Resolve HR signature input
            $hrSigUrl = null;
            if ($request->hasFile('hr_signature_file') && $request->file('hr_signature_file')->isValid()) {
                $file = $request->file('hr_signature_file');
                $hrSigPath = $file->storeTenantFile("signatures/hr_tenant_{$tenantId}", 'local');
                $hrSigUrl = 'data:' . ($file->getClientMimeType() ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($file->getRealPath()));
            } elseif ($request->filled('hr_signature_data')) {
                $hrSigData = $request->input('hr_signature_data');
                if (str_starts_with($hrSigData, 'data:image')) {
                    $image = str_replace(' ', '+', preg_replace('/^data:image\/\w+;base64,/', '', $hrSigData));
                    $imageName = 'hr_sig_' . time() . '_' . \Illuminate\Support\Str::random(6) . '.png';
                    $hrSigPath = "signatures/hr_tenant_{$tenantId}/{$imageName}";
                    tenant_storage()->put('local', $hrSigPath, base64_decode($image));
                    $hrSigUrl = $hrSigData;
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
                    tenant_storage()->put('local', $path, $pdfContent);
                } catch (\Throwable $e) {
                    // Fallback to storing standalone HTML if DomPDF is unavailable
                    $htmlPath = "documents/tenant_{$tenantId}/employee_{$employee->id}/" . \Illuminate\Support\Str::slug($docTemplate->name . '-' . $employee->full_name) . '-' . time() . '.html';
                    tenant_storage()->put('local', $htmlPath, $templateService->toPrintableDocument($renderedContent, $title));
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
                    'file_size'             => \Illuminate\Support\Facades\Storage::disk('local')->exists($path)
                        ? \Illuminate\Support\Facades\Storage::disk('local')->size($path)
                        : (\Illuminate\Support\Facades\Storage::disk('public')->exists($path) ? \Illuminate\Support\Facades\Storage::disk('public')->size($path) : 0),
                    'status'                => $docStatus,
                    'requires_signature'    => (bool) $docTemplate->requires_signature,
                    'is_signed'             => false,
                    'requested_by_id'       => auth()->id(),
                ]);

                if ($docTemplate->requires_signature) {
                    \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.document.signature_requested', [
                        'employee_name'  => $employee->full_name,
                        'document_title' => $title,
                        'due_date'       => now()->addDays(7)->format('d M Y'),
                    ]);
                }

                $count++;
            }

            return redirect()->back()->with('success', __('hrms.documents.bulk_generate_success', ['count' => $count]));
        }

        // Standard bulk upload mode
        $file = $request->file('file');
        $docMaster = \App\Domains\HRMS\Models\DocumentMaster::find($request->input('document_master_id'));

        if ($docMaster?->expiry_applicable && !$request->filled('expiry_date')) {
            return redirect()->back()->withErrors(['expiry_date' => __('hrms.documents.error_expiry_required')])->withInput();
        }

        $baseStoredPath = $file->storeTenantFile("documents/tenant_{$tenantId}/bulk_temp", 'local');
        $originalName = $file->getClientOriginalName();
        $originalExt = $file->getClientOriginalExtension();
        $fileSize = $file->getSize();
        $status = ($docMaster?->requires_signature) ? 'pending_signature' : 'approved';
        $hasExpiry = (bool) ($docMaster?->expiry_applicable ?? false);
        $expiryDate = $hasExpiry && $request->filled('expiry_date') ? $request->date('expiry_date') : null;

        $count = 0;

        foreach ($targetEmployeeIds as $empId) {
            $employee = Employee::find($empId);
            if (!$employee) {
                continue;
            }

            $empPath = "documents/tenant_{$tenantId}/employee_{$employee->id}/" . time() . '_' . \Illuminate\Support\Str::random(6) . '.' . $originalExt;
            \Illuminate\Support\Facades\Storage::disk('local')->copy($baseStoredPath, $empPath);

            Document::create([
                'tenant_id'          => $tenantId,
                'documentable_type'  => Employee::class,
                'documentable_id'    => $employee->id,
                'document_master_id' => $docMaster?->id,
                'name'               => $docMaster?->name ?: $originalName,
                'file_name'          => $originalName,
                'file_path'          => $empPath,
                'file_type'          => $originalExt,
                'file_size'          => $fileSize,
                'has_expiry'         => $hasExpiry,
                'expiry_date'        => $expiryDate,
                'status'             => $status,
                'requires_signature' => (bool) ($docMaster?->requires_signature ?? false),
                'requested_by_id'    => auth()->id(),
            ]);

            if ($status === 'pending_signature') {
                \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.document.signature_requested', [
                    'employee_name'  => $employee->full_name,
                    'document_title' => $docMaster?->name ?: $originalName,
                    'due_date'       => now()->addDays(7)->format('d M Y'),
                ]);
            }

            $count++;
        }

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($baseStoredPath)) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($baseStoredPath);
        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($baseStoredPath)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($baseStoredPath);
        }

        return redirect()->back()->with('success', __('hrms.documents.bulk_upload_success', ['count' => $count]));
    }

    /**
     * View document file securely through authorized stream response.
     */
    public function viewFile(Request $request, Document $document)
    {
        $this->authorizeDocumentAccess($document);

        $type = $request->query('type');
        $path = match ($type) {
            'signed' => $document->signed_file_path ?: $document->file_path,
            'signature' => $document->signature_metadata['signature_path'] ?? null,
            default => $document->file_path,
        };

        return $this->streamFile($path, $document->file_name ?? basename($path ?? 'document'), false);
    }

    /**
     * Download document file securely through authorized download response.
     */
    public function downloadFile(Request $request, Document $document)
    {
        $this->authorizeDocumentAccess($document);

        $type = $request->query('type');
        $path = match ($type) {
            'signed' => $document->signed_file_path ?: $document->file_path,
            'signature' => $document->signature_metadata['signature_path'] ?? null,
            default => $document->file_path,
        };

        return $this->streamFile($path, $document->file_name ?? basename($path ?? 'document'), true);
    }

    /**
     * Authorize access to document file.
     */
    private function authorizeDocumentAccess(Document $document): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        $tenantId = tenant_id() ?? $user->tenant_id;
        if ($document->tenant_id && $tenantId && (int) $document->tenant_id !== (int) $tenantId) {
            abort(403, 'Unauthorized tenant access.');
        }

        $isOwner = false;
        if ($document->documentable_type === Employee::class || !$document->documentable_type) {
            $currentEmployee = app(\App\Domains\HRMS\Services\HrmsScopeService::class)->resolveEmployee($user);
            if ($currentEmployee && (int) $document->documentable_id === (int) $currentEmployee->id) {
                $isOwner = true;
            } elseif ($user->employee?->id === (int) $document->documentable_id || $user->id === $document->documentable?->user_id) {
                $isOwner = true;
            }
        }

        if (!$isOwner) {
            $access = app(\App\Services\Access\AccessService::class);
            $canView = $access->allows($user, 'hrms.documents.view', ['tenant_id' => $tenantId])
                || $access->allows($user, 'hrms.documents.manage', ['tenant_id' => $tenantId])
                || $access->allows($user, 'hrms.employees.view', ['tenant_id' => $tenantId])
                || $access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId])
                || ($user->isCompanyAdmin() ?? false);

            if (!$canView) {
                abort(403, 'You do not have permission to view or download this document.');
            }
        }
    }

    /**
     * Stream or download a file from local or fallback public disk.
     */
    private function streamFile(?string $path, string $fileName, bool $isDownload)
    {
        if (!$path) {
            abort(404, 'File not specified.');
        }

        $disk = null;
        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            $disk = 'local';
        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            $disk = 'public';
        }

        if (!$disk) {
            abort(404, 'Document file not found.');
        }

        $storage = \Illuminate\Support\Facades\Storage::disk($disk);
        $mimeType = $storage->mimeType($path) ?: 'application/octet-stream';

        if ($isDownload) {
            return $storage->download($path, $fileName, [
                'Content-Type' => $mimeType,
            ]);
        }

        return $storage->response($path, $fileName, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
        ]);
    }

    /**
     * Check if the authenticated user has self-management / top-tier administrative privileges.
     */
    private function canSelfManage(?\App\Models\User $user, ?\App\Domains\HRMS\Models\Employee $currentEmployee, mixed $targetModel = null): bool
    {
        if (!$user) {
            return false;
        }

        return app(\App\Domains\HRMS\Services\ApprovalWorkflowService::class)->allowsSelfApproval($user, $targetModel, 'hrms.documents.manage');
    }
}
