<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\DocumentMaster;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Services\DocumentSignatureService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DocumentApiController extends Controller
{
    public function __construct(
        private readonly DocumentSignatureService $documentSignatureService
    ) {}
    /**
     * Helper for standardized success JSON response.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Helper for standardized error JSON response.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $statusCode);
    }

    /**
     * Helper to check if current user is HR Admin / Document Manager.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return (bool) (
            $user->is_admin ||
            in_array(strtolower($user->role ?? ''), ['admin', 'hr', 'hr_admin', 'manager'], true) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.documents.manage') ||
            $user->hasHrPermission('hrms.employees.manage')
        );
    }

    /**
     * Helper to resolve current authenticated user's employee record.
     */
    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)->first()
            ?? Employee::where(function ($q) use ($user) {
                $q->where('office_email', $user->email)
                  ->orWhere('personal_email', $user->email);
            })->first();
    }

    /**
     * Null-safe authorization check.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();
            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access.', 401);
            }
        }
        return null;
    }

    /**
     * GET /api/hrms/documents
     * Display a listing of the holiday/employee documents.
     */
    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();
        $activeTab = $request->query('tab', 'employee');

        $query = Document::with(['documentable', 'documentMaster', 'requestedBy'])
            ->where('documentable_type', Employee::class);

        if (!$isHrAdmin) {
            $query->where('documentable_id', $employee?->id ?? 0);
        } else {
            // Separate by tab (Employee vs HR Uploads) for HR Admins
            if ($activeTab === 'employee') {
                $query->whereHasMorph('documentable', [Employee::class], function ($q) {
                    $q->whereColumn('user_id', 'documents.requested_by_id');
                });
            } else {
                $query->where(function ($q) {
                    $q->whereDoesntHaveMorph('documentable', [Employee::class], function ($q2) {
                        $q2->whereColumn('user_id', 'documents.requested_by_id');
                    })->orWhereNull('requested_by_id');
                });
            }
        }

        // Search by employee name, ID or document name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('documentMaster', function ($q2) use ($search): void {
                      $q2->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHasMorph('documentable', [Employee::class], function ($q3) use ($search): void {
                      $q3->where('full_name', 'like', "%{$search}%")
                         ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by document category
        if ($request->filled('category_id')) {
            $query->whereHas('documentMaster', function ($q) use ($request) {
                $q->where('document_category_id', $request->input('category_id'));
            });
        }

        // Sort options
        $sort = $request->input('sort', 'newest');
        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sort === 'employee_asc') {
            $query->select('documents.*')
                ->leftJoin('employees', function ($join) {
                    $join->on('employees.id', '=', 'documents.documentable_id')
                         ->where('documents.documentable_type', '=', Employee::class);
                })
                ->orderBy('employees.full_name', 'asc');
        } elseif ($sort === 'employee_desc') {
            $query->select('documents.*')
                ->leftJoin('employees', function ($join) {
                    $join->on('employees.id', '=', 'documents.documentable_id')
                         ->where('documents.documentable_type', '=', Employee::class);
                })
                ->orderBy('employees.full_name', 'desc');
        } elseif ($sort === 'doc_name_asc') {
            $query->orderBy('name', 'asc');
        } elseif ($sort === 'doc_name_desc') {
            $query->orderBy('name', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $documents = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($documents, 'Documents retrieved successfully');
    }

    /**
     * GET /api/hrms/documents/masters
     * List active Document Masters (document categories / master definition templates).
     */
    public function listMasters(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $query = DocumentMaster::with('category')
            ->where('status', 'active');

        if ($request->filled('category_id')) {
            $query->where('document_category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $masters = $query->orderBy('name')->get();

        return $this->sendSuccess($masters, 'Document masters retrieved successfully.');
    }

    /**
     * GET /api/hrms/documents/templates
     * List active Document Templates for document generation.
     */
    public function listTemplates(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $query = DocumentTemplate::with('category')
            ->where('status', 'active');

        if ($request->filled('category_id')) {
            $query->where('document_category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $templates = $query->orderBy('name')->get();

        return $this->sendSuccess($templates, 'Document templates retrieved successfully.');
    }

    /**
     * GET /api/hrms/documents/templates/{template}
     * Get single Document Template detail.
     */
    public function showTemplate(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $template = $id instanceof DocumentTemplate ? $id : DocumentTemplate::with('category')->find($id);
        if (!$template) {
            return $this->sendError("Document template with ID '{$id}' not found.", 404);
        }

        return $this->sendSuccess($template, 'Document template retrieved successfully.');
    }

    /**
     * POST /api/hrms/documents/upload
     * Store newly uploaded or template-generated documents for employees (single or all employees).
     */
    public function upload(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can upload documents.', 403);
        }

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
                'file'               => 'required|file|max:10240', // Max 10MB
                'expiry_date'        => 'nullable|date',
            ]);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        // Resolve target employee IDs
        $employeeId = $request->input('employee_id');
        $targetEmployeeIds = [];
        if ($employeeId === 'all') {
            $targetEmployeeIds = Employee::pluck('id')->toArray();
        } else {
            $targetEmployeeIds = [$employeeId];
        }

        if (empty($targetEmployeeIds)) {
            return $this->sendError('No employees found for this upload target.', 422);
        }

        if (in_array($uploadMode, ['generate', 'generate_template'], true)) {
            $docTemplate = DocumentTemplate::find($request->integer('document_template_id'));
            if (!$docTemplate) {
                return $this->sendError("Document template with ID '{$request->input('document_template_id')}' not found.", 404);
            }

            $templateService = app(\App\Domains\HRMS\Services\DocumentTemplateService::class);
            $hrName = $request->input('hr_name', auth()->user()?->name ?? 'Authorized Signatory');
            $hrDesignation = $request->input('hr_designation', 'HR Manager');
            $issueDate = $request->input('issue_date', date('Y-m-d'));

            // Resolve HR signature input (file upload or drawn base64 string)
            $hrSigUrl = null;
            $hrSigImageInput = null;

            if ($request->hasFile('hr_signature_file') && $request->file('hr_signature_file')->isValid()) {
                $file = $request->file('hr_signature_file');
                $hrSigPath = $file->store("signatures/hr_tenant_{$tenantId}", 'public');
                $hrSigUrl = asset('storage/' . $hrSigPath);
                $hrSigImageInput = $hrSigPath;
            } elseif ($request->hasFile('signature_image') && $request->file('signature_image')->isValid()) {
                $file = $request->file('signature_image');
                $hrSigPath = $file->store("signatures/hr_tenant_{$tenantId}", 'public');
                $hrSigUrl = asset('storage/' . $hrSigPath);
                $hrSigImageInput = $hrSigPath;
            } elseif ($request->filled('hr_signature_data') || $request->filled('signature_image')) {
                $hrSigData = $request->input('hr_signature_data') ?? $request->input('signature_image');
                if (str_starts_with($hrSigData, 'data:image')) {
                    $image = str_replace(' ', '+', preg_replace('/^data:image\/\w+;base64,/', '', $hrSigData));
                    $imageName = 'hr_sig_' . time() . '_' . \Str::random(6) . '.png';
                    $hrSigPath = "signatures/hr_tenant_{$tenantId}/{$imageName}";
                    \Storage::disk('public')->put($hrSigPath, base64_decode($image));
                    $hrSigUrl = asset('storage/' . $hrSigPath);
                    $hrSigImageInput = $hrSigPath;
                } else {
                    $hrSigUrl = $hrSigData;
                    $hrSigImageInput = $hrSigData;
                }
            }

            $extraData = [
                'hr_name'          => $hrName,
                'hr_designation'   => $hrDesignation,
                'issue_date'       => $issueDate,
                'hr_signature_url' => $hrSigUrl,
            ];

            $createdDocs = [];

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

                // Render to a proper A4 PDF so "view"/"download" open a printable, correctly
                // paginated document instead of a raw, unstyled HTML fragment.
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($templateService->toPrintableDocument($renderedContent, $title))
                    ->setPaper('a4', 'portrait')
                    ->setWarnings(false);
                $pdfContent = $pdf->output();

                $fileName = \Str::slug($docTemplate->name . '-' . $employee->full_name) . '-' . time() . '.pdf';
                $path = "documents/tenant_{$tenantId}/employee_{$employee->id}/{$fileName}";
                \Storage::disk('public')->put($path, $pdfContent);

                $masterId = null;
                if ($docTemplate->document_category_id) {
                    $master = DocumentMaster::where('tenant_id', $tenantId)
                        ->where(function ($q) use ($docTemplate) {
                            $q->where('code', $docTemplate->code)
                              ->orWhere('name', $docTemplate->name);
                        })->first();

                    if (!$master) {
                        $master = DocumentMaster::create([
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
                    'status'               => 'issued',
                ]);

                $doc = Document::create([
                    'tenant_id'          => $tenantId,
                    'documentable_id'    => $employee->id,
                    'documentable_type'  => Employee::class,
                    'document_master_id' => $masterId,
                    'name'               => $title,
                    'description'        => "Generated from template: " . $docTemplate->name,
                    'file_name'          => $fileName,
                    'file_path'          => $path,
                    'file_type'          => 'pdf',
                    'file_size'          => strlen($pdfContent),
                    'requires_signature' => (bool)$docTemplate->requires_signature,
                    'status'             => $docStatus,
                    'requested_by_id'    => auth()->id(),
                ]);

                $createdDocs[] = $doc;
            }

            return $this->sendSuccess($createdDocs, 'Documents generated from template successfully.', 201);
        }

        // Standard file upload mode
        $file = $request->file('file');
        $documentMaster = DocumentMaster::find($request->integer('document_master_id'));
        if (!$documentMaster) {
            return $this->sendError("Document master with ID '{$request->input('document_master_id')}' not found.", 404);
        }

        $requiresSignature = (bool) $documentMaster->requires_signature;
        $approvalRequired = (bool) $documentMaster->approval_required;
        $status = $requiresSignature ? 'pending_signature' : ($approvalRequired ? 'uploaded' : 'approved');
        $uploadedDocs = [];

        foreach ($targetEmployeeIds as $empId) {
            $employee = Employee::find($empId);
            if (!$employee) {
                continue;
            }

            $path = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            $document = Document::where('documentable_type', Employee::class)
                ->where('documentable_id', $employee->id)
                ->where('document_master_id', $documentMaster->id)
                ->first();

            if ($document) {
                $document->update([
                    'file_name'          => $file->getClientOriginalName(),
                    'file_path'          => $path,
                    'file_type'          => $file->getClientMimeType(),
                    'file_size'          => $file->getSize(),
                    'expiry_date'        => $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                    'requires_signature' => $requiresSignature,
                    'status'             => $status,
                ]);
            } else {
                $document = Document::create([
                    'tenant_id'          => $tenantId,
                    'documentable_id'    => $employee->id,
                    'documentable_type'  => Employee::class,
                    'document_master_id' => $documentMaster->id,
                    'name'               => $documentMaster->name,
                    'description'        => $documentMaster->description,
                    'has_expiry'         => $documentMaster->expiry_applicable,
                    'file_name'          => $file->getClientOriginalName(),
                    'file_path'          => $path,
                    'file_type'          => $file->getClientMimeType(),
                    'file_size'          => $file->getSize(),
                    'expiry_date'        => $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                    'requires_signature' => $requiresSignature,
                    'status'             => $status,
                    'requested_by_id'    => auth()->id(),
                ]);
            }
            $uploadedDocs[] = $document;
        }

        return $this->sendSuccess($uploadedDocs, 'Documents uploaded successfully.', 201);
    }

    /**
     * POST /api/hrms/documents/employee/upload
     * Store document uploaded directly by logged-in employee for themselves.
     */
    public function employeeUpload(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return $this->sendError('Employee record not found for authenticated user.', 404);
        }

        $validated = $request->validate([
            'document_master_id' => 'required|exists:document_masters,id',
            'file'               => 'required|file|max:10240', // Max 10MB
            'expiry_date'        => 'nullable|date',
        ]);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? $employee->tenant_id ?? 1;
        $file = $request->file('file');
        $documentMaster = DocumentMaster::find($request->integer('document_master_id'));

        if (!$documentMaster) {
            return $this->sendError("Document master with ID '{$request->input('document_master_id')}' not found.", 404);
        }

        $requiresSignature = (bool) $documentMaster->requires_signature;
        $approvalRequired = (bool) $documentMaster->approval_required;
        $status = $requiresSignature ? 'pending_signature' : ($approvalRequired ? 'uploaded' : 'approved');

        $path = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

        $document = Document::where('documentable_type', Employee::class)
            ->where('documentable_id', $employee->id)
            ->where('document_master_id', $documentMaster->id)
            ->first();

        if ($document) {
            $document->update([
                'file_name'          => $file->getClientOriginalName(),
                'file_path'          => $path,
                'file_type'          => $file->getClientMimeType(),
                'file_size'          => $file->getSize(),
                'expiry_date'        => $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                'requires_signature' => $requiresSignature,
                'status'             => $status,
                'requested_by_id'    => auth()->id(),
            ]);
        } else {
            $document = Document::create([
                'tenant_id'          => $tenantId,
                'documentable_id'    => $employee->id,
                'documentable_type'  => Employee::class,
                'document_master_id' => $documentMaster->id,
                'name'               => $documentMaster->name,
                'description'        => $documentMaster->description,
                'has_expiry'         => $documentMaster->expiry_applicable,
                'file_name'          => $file->getClientOriginalName(),
                'file_path'          => $path,
                'file_type'          => $file->getClientMimeType(),
                'file_size'          => $file->getSize(),
                'expiry_date'        => $request->filled('expiry_date') ? $request->date('expiry_date') : null,
                'requires_signature' => $requiresSignature,
                'status'             => $status,
                'requested_by_id'    => auth()->id(),
            ]);
        }

        return $this->sendSuccess($document, 'Employee document uploaded successfully.', 201);
    }

    /**
     * POST /api/hrms/documents/{document}/approve
     */
    public function approve(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can approve documents.', 403);
        }

        $document = $id instanceof Document ? $id : Document::find($id);
        if (!$document) {
            return $this->sendError("Document with ID '{$id}' not found.", 404);
        }

        $document->update([
            'status' => 'approved',
        ]);

        return $this->sendSuccess($document, 'Document approved successfully.');
    }

    /**
     * POST /api/hrms/documents/{document}/reject
     */
    public function reject(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can reject documents.', 403);
        }

        $document = $id instanceof Document ? $id : Document::find($id);
        if (!$document) {
            return $this->sendError("Document with ID '{$id}' not found.", 404);
        }

        $document->update([
            'status' => 'rejected',
        ]);

        return $this->sendSuccess($document, 'Document rejected successfully.');
    }

    /**
     * PUT /api/hrms/documents/{document}/status
     */
    public function updateStatus(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can update document status.', 403);
        }

        $document = $id instanceof Document ? $id : Document::find($id);
        if (!$document) {
            return $this->sendError("Document with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,uploaded,expired,pending_signature',
        ]);

        $document->update([
            'status' => $validated['status'],
        ]);

        return $this->sendSuccess($document, "Document status updated to '{$validated['status']}' successfully.");
    }

    /**
     * POST /api/hrms/documents/{document}/sign
     * Sign an existing uploaded document. Accepts base64 string or file upload for signature.
     */
    public function sign(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $employee = $this->getAuthenticatedEmployee();
        $isHrAdmin = $this->isHrAdmin();

        $document = Document::find($id);
        if (!$document) {
            return $this->sendError("Document with ID '{$id}' not found.", 404);
        }

        if (!$isHrAdmin && $document->documentable_id !== $employee?->id) {
            return $this->sendError('Unauthorized action.', 403);
        }

        $signatureInput = null;

        if ($request->hasFile('signature_file') && $request->file('signature_file')->isValid()) {
            $file = $request->file('signature_file');
            $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
            $userId = auth()->id() ?? 'guest';
            $path = $file->store("signatures/tenant_{$tenantId}/user_{$userId}", 'public');
            $signatureInput = $path;
        } elseif ($request->hasFile('signature_image') && $request->file('signature_image')->isValid()) {
            $file = $request->file('signature_image');
            $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
            $userId = auth()->id() ?? 'guest';
            $path = $file->store("signatures/tenant_{$tenantId}/user_{$userId}", 'public');
            $signatureInput = $path;
        } else {
            $validator = Validator::make($request->all(), [
                'signature_image' => 'required|string',
                'coordinates'     => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed. Please provide a signature image (base64 string) or signature file.', 422, $validator->errors());
            }

            $signatureInput = $request->input('signature_image');
        }

        try {
            $coordinates = $request->input('coordinates');

            $signedDoc = $this->documentSignatureService->signUploadedDocument(
                $document,
                $signatureInput,
                is_array($coordinates) ? $coordinates : null
            );

            return $this->sendSuccess([
                'id'               => $signedDoc->id,
                'name'             => $signedDoc->name,
                'is_signed'        => $signedDoc->is_signed,
                'status'           => $signedDoc->status,
                'signed_at'        => $signedDoc->signed_at?->toIso8601String(),
                'signed_by'        => $signedDoc->signedBy?->name,
                'signature_ip'     => $signedDoc->signature_ip,
                'file_url'         => asset('storage/' . $signedDoc->file_path),
                'signed_file_url'  => $signedDoc->signed_file_path ? asset('storage/' . $signedDoc->signed_file_path) : null,
            ], 'Document digitally signed successfully.');
        } catch (\Throwable $e) {
            Log::error("Document signature failed: " . $e->getMessage(), [
                'exception'   => $e,
                'document_id' => $id,
            ]);
            return $this->sendError('Failed to digitally sign document: ' . $e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/documents/generate-signed-template
     * Generate document from template with Option A on-the-fly HR signature.
     */
    public function generateSignedTemplate(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Only HR Admins can generate template documents.', 403);
        }

        $validated = $request->validate([
            'document_template_id' => 'required|exists:document_templates,id',
            'employee_id'          => 'required|string',
            'signature_image'      => 'nullable|string',
            'hr_signature_file'    => 'nullable|file|max:5120',
            'reference_number'     => 'nullable|string',
        ]);

        $template = DocumentTemplate::find($validated['document_template_id']);
        if (!$template) {
            return $this->sendError("Document template with ID '{$validated['document_template_id']}' not found.", 404);
        }

        $sigInput = $validated['signature_image'] ?? null;
        if ($request->hasFile('hr_signature_file') && $request->file('hr_signature_file')->isValid()) {
            $file = $request->file('hr_signature_file');
            $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
            $userId = auth()->id() ?? 'guest';
            $sigInput = $file->store("signatures/tenant_{$tenantId}/user_{$userId}", 'public');
        }

        // Support single or all employees
        $empTarget = $validated['employee_id'];
        $employees = [];
        if ($empTarget === 'all') {
            $employees = Employee::all();
        } else {
            $singleEmp = Employee::find($empTarget);
            if ($singleEmp) {
                $employees[] = $singleEmp;
            }
        }

        if (empty($employees)) {
            return $this->sendError("No valid employees found for ID '{$empTarget}'.", 404);
        }

        $results = [];
        foreach ($employees as $emp) {
            $res = $this->documentSignatureService->generateSignedDocumentFromTemplate(
                $template,
                $emp,
                $sigInput,
                $validated['reference_number'] ?? null
            );
            $doc = $res['document'];
            $results[] = [
                'id'             => $doc->id,
                'name'           => $doc->name,
                'employee_id'    => $emp->id,
                'employee_name'  => $emp->full_name,
                'is_signed'      => $doc->is_signed,
                'status'         => $doc->status,
                'signed_at'      => $doc->signed_at?->toIso8601String(),
                'pdf_url'        => $res['pdf_url'],
                'signature_path' => $res['signature_path'] ? asset('storage/' . $res['signature_path']) : null,
            ];
        }

        return $this->sendSuccess(
            count($results) === 1 ? $results[0] : $results,
            'Document(s) generated and signed successfully from template.',
            201
        );
    }
}
