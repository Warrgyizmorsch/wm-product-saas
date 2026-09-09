<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Services\DocumentSignatureService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

        $activeTab = $request->query('tab', 'employee');

        $query = Document::with(['documentable', 'documentMaster', 'requestedBy'])
            ->where('documentable_type', Employee::class);

        // Separate by tab (Employee vs HR Uploads)
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
     * POST /api/hrms/documents/upload
     * Store newly created documents for employees (single employee or all employees).
     */
    public function upload(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'employee_id'        => 'required|string',
            'document_master_id' => 'required|exists:document_masters,id',
            'file'               => 'required|file|max:10240', // Max 10MB
            'expiry_date'        => 'nullable|date',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $file = $request->file('file');
        $documentMaster = DocumentMaster::find($request->integer('document_master_id'));
        if (!$documentMaster) {
            return $this->sendError("Document template with ID '{$request->input('document_master_id')}' not found.", 404);
        }

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

        $requiresSignature = (bool) $documentMaster->requires_signature;
        $approvalRequired = (bool) $documentMaster->approval_required;
        $status = $requiresSignature ? 'pending_signature' : ($approvalRequired ? 'uploaded' : 'approved');
        $uploadedDocs = [];

        foreach ($targetEmployeeIds as $empId) {
            $employee = Employee::find($empId);
            if (!$employee) {
                continue;
            }

            // Store file
            $path = $file->store("documents/tenant_{$tenantId}/employee_{$employee->id}", 'public');

            // Find or create document record for this employee and master
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
     * POST /api/hrms/documents/{document}/approve
     */
    public function approve(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $document = Document::find($id);
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

        $document = Document::find($id);
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

        $document = Document::find($id);
        if (!$document) {
            return $this->sendError("Document with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,uploaded,expired',
        ]);

        $document->update([
            'status' => $validated['status'],
        ]);

        return $this->sendSuccess($document, "Document status updated to '{$validated['status']}' successfully.");
    }

    /**
     * POST /api/hrms/documents/{document}/sign
     * Sign an existing uploaded document.
     */
    public function sign(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $document = Document::find($id);
        if (!$document) {
            return $this->sendError("Document with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'signature_image' => 'required|string',
            'coordinates'     => 'nullable|array',
        ]);

        $signedDoc = $this->documentSignatureService->signUploadedDocument(
            $document,
            $validated['signature_image'],
            $validated['coordinates'] ?? null
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

        $validated = $request->validate([
            'document_template_id' => 'required|exists:document_templates,id',
            'employee_id'          => 'required|exists:employees,id',
            'signature_image'      => 'nullable|string',
            'reference_number'     => 'nullable|string',
        ]);

        $template = DocumentTemplate::find($validated['document_template_id']);
        if (!$template) {
            return $this->sendError("Document template with ID '{$validated['document_template_id']}' not found.", 404);
        }

        $employee = Employee::find($validated['employee_id']);
        if (!$employee) {
            return $this->sendError("Employee with ID '{$validated['employee_id']}' not found.", 404);
        }

        $result = $this->documentSignatureService->generateSignedDocumentFromTemplate(
            $template,
            $employee,
            $validated['signature_image'] ?? null,
            $validated['reference_number'] ?? null
        );

        $doc = $result['document'];

        return $this->sendSuccess([
            'id'              => $doc->id,
            'name'            => $doc->name,
            'employee_id'     => $employee->id,
            'employee_name'   => $employee->full_name,
            'is_signed'       => $doc->is_signed,
            'status'          => $doc->status,
            'signed_at'       => $doc->signed_at?->toIso8601String(),
            'pdf_url'         => $result['pdf_url'],
            'signature_path'  => $result['signature_path'] ? asset('storage/' . $result['signature_path']) : null,
        ], 'Document generated and signed successfully from template.', 201);
    }

    /**
     * POST /api/hrms/user/signature
     * Upload or update logged-in user default digital signature.
     */
    public function updateUserSignature(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'signature_image' => 'required|string',
        ]);

        $user = auth()->user();
        if (!$user) {
            return $this->sendError('Authenticated user not found.', 404);
        }

        $savedPath = $this->documentSignatureService->saveSignatureImage($validated['signature_image'], $user, true);

        return $this->sendSuccess([
            'user_id'        => $user->id,
            'name'           => $user->name,
            'signature_path' => $savedPath,
            'signature_url'  => asset('storage/' . $savedPath),
        ], 'User digital signature saved successfully.');
    }
}
