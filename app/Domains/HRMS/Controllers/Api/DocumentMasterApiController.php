<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\DocumentCategory;
use App\Domains\HRMS\Models\DocumentMaster;
use App\Domains\HRMS\Repositories\DocumentMasterRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class DocumentMasterApiController extends Controller
{
    public function __construct(
        private readonly DocumentMasterRepositoryInterface $documentMasterRepository
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
     * GET /api/hrms/documents-master
     * Display the document master dashboard with categories and documents tabs.
     */
    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $data = $this->documentMasterRepository->getIndexData($request->all());

        return $this->sendSuccess($data, 'Document masters dashboard data loaded');
    }

    /**
     * POST /api/hrms/documents-master/categories
     * Store a new document category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_id'  => 'required|exists:companies,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $category = $this->documentMasterRepository->storeCategory($validated);

        return $this->sendSuccess($category, 'Document category created successfully.', 201);
    }

    /**
     * PUT /api/hrms/documents-master/categories/{category}
     * Update an existing document category.
     */
    public function updateCategory(Request $request, DocumentCategory $category): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_id'  => 'required|exists:companies,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $this->documentMasterRepository->updateCategory($category, $validated);

        return $this->sendSuccess($category->fresh(), 'Document category updated successfully.');
    }

    /**
     * DELETE /api/hrms/documents-master/categories/{category}
     * Delete an existing document category.
     */
    public function destroyCategory(DocumentCategory $category): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        // Prevent deleting category if it has associated document masters
        if ($category->documentMasters()->exists()) {
            return $this->sendError('Cannot delete category because it has associated document masters.', 422);
        }

        $this->documentMasterRepository->deleteCategory($category);

        return $this->sendSuccess(null, 'Document category deleted successfully.');
    }

    /**
     * POST /api/hrms/documents-master/documents
     * Store a new document master.
     */
    public function storeDocument(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'document_category_id'  => 'required|exists:document_categories,id',
            'name'                  => 'required|string|max:255',
            'code'                  => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_masters', 'code')->where('tenant_id', $tenantId),
            ],
            'description'           => 'nullable|string|max:1000',
            'is_required'           => 'nullable|boolean',
            'upload_responsibility' => 'required|string|in:employee,hr,both',
            'approval_required'     => 'nullable|boolean',
            'expiry_applicable'     => 'nullable|boolean',
            'reminder_days_before'  => 'nullable|required_if:expiry_applicable,1|integer|min:1',
            'employee_can_view'     => 'nullable|boolean',
            'employee_can_download' => 'nullable|boolean',
            'status'                => 'required|string|in:active,inactive',
        ]);

        // Normalize checkboxes
        $validated['is_required'] = $request->boolean('is_required');
        $validated['approval_required'] = $request->boolean('approval_required');
        $validated['expiry_applicable'] = $request->boolean('expiry_applicable');
        $validated['employee_can_view'] = $request->boolean('employee_can_view');
        $validated['employee_can_download'] = $request->boolean('employee_can_download');

        if (!$validated['expiry_applicable']) {
            $validated['reminder_days_before'] = null;
        }

        $document = $this->documentMasterRepository->storeDocument($validated);

        return $this->sendSuccess($document, 'Document master created successfully.', 201);
    }

    /**
     * PUT /api/hrms/documents-master/documents/{document}
     * Update an existing document master.
     */
    public function updateDocument(Request $request, DocumentMaster $document): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = $document->tenant_id ?? auth()->user()->tenant_id;

        $validated = $request->validate([
            'document_category_id'  => 'required|exists:document_categories,id',
            'name'                  => 'required|string|max:255',
            'code'                  => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_masters', 'code')->where('tenant_id', $tenantId)->ignore($document->id),
            ],
            'description'           => 'nullable|string|max:1000',
            'is_required'           => 'nullable|boolean',
            'upload_responsibility' => 'required|string|in:employee,hr,both',
            'approval_required'     => 'nullable|boolean',
            'expiry_applicable'     => 'nullable|boolean',
            'reminder_days_before'  => 'nullable|required_if:expiry_applicable,1|integer|min:1',
            'employee_can_view'     => 'nullable|boolean',
            'employee_can_download' => 'nullable|boolean',
            'status'                => 'required|string|in:active,inactive',
        ]);

        // Normalize checkboxes
        $validated['is_required'] = $request->boolean('is_required');
        $validated['approval_required'] = $request->boolean('approval_required');
        $validated['expiry_applicable'] = $request->boolean('expiry_applicable');
        $validated['employee_can_view'] = $request->boolean('employee_can_view');
        $validated['employee_can_download'] = $request->boolean('employee_can_download');

        if (!$validated['expiry_applicable']) {
            $validated['reminder_days_before'] = null;
        }

        $this->documentMasterRepository->updateDocument($document, $validated);

        return $this->sendSuccess($document->fresh(), 'Document master updated successfully.');
    }

    /**
     * DELETE /api/hrms/documents-master/documents/{document}
     * Delete an existing document master.
     */
    public function destroyDocument(DocumentMaster $document): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $this->documentMasterRepository->deleteDocument($document);

        return $this->sendSuccess(null, 'Document master deleted successfully.');
    }

    /**
     * PATCH /api/hrms/documents-master/documents/{document}/toggle
     * Toggle the status (active/inactive) of an existing document master.
     */
    public function toggleStatus(DocumentMaster $document): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $newStatus = $document->status === 'active' ? 'inactive' : 'active';
        
        $this->documentMasterRepository->updateDocument($document, [
            'status' => $newStatus
        ]);

        return $this->sendSuccess($document->fresh(), 'Document status toggled successfully.');
    }
}
