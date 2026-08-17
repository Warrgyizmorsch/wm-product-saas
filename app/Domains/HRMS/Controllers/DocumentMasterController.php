<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\DocumentCategory;
use App\Domains\HRMS\Models\DocumentMaster;
use App\Domains\HRMS\Repositories\DocumentMasterRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentMasterController extends Controller
{
    public function __construct(
        private readonly DocumentMasterRepositoryInterface $documentMasterRepository
    ) {}

    /**
     * Display the document master dashboard with categories and documents tabs.
     */
    public function index(Request $request): View
    {
        $data = $this->documentMasterRepository->getIndexData($request->all());

        return view('modules.hrms.document-master.index', $data);
    }

    /**
     * Store a new document category.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        // tenant_id is automatically handled by BaseModel's creating event
        $this->documentMasterRepository->storeCategory($validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'categories'])
            ->with('success', 'Document category created successfully.');
    }

    /**
     * Update an existing document category.
     */
    public function updateCategory(Request $request, DocumentCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $this->documentMasterRepository->updateCategory($category, $validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'categories'])
            ->with('success', 'Document category updated successfully.');
    }

    /**
     * Delete an existing document category.
     */
    public function destroyCategory(DocumentCategory $category): RedirectResponse
    {
        // Prevent deleting category if it has associated document masters
        if ($category->documentMasters()->exists()) {
            return redirect()->route('hrms.documents-master.index', ['active_tab' => 'categories'])
                ->with('error', 'Cannot delete category because it has associated document masters.');
        }

        $this->documentMasterRepository->deleteCategory($category);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'categories'])
            ->with('success', 'Document category deleted successfully.');
    }

    /**
     * Store a new document master.
     */
    public function storeDocument(Request $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'document_category_id' => 'required|exists:document_categories,id',
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_masters', 'code')->where('tenant_id', $tenantId),
            ],
            'description' => 'nullable|string|max:1000',
            'is_required' => 'nullable|boolean',
            'upload_responsibility' => 'required|string|in:employee,hr,both',
            'approval_required' => 'nullable|boolean',
            'expiry_applicable' => 'nullable|boolean',
            'reminder_days_before' => 'nullable|required_if:expiry_applicable,1|integer|min:1',
            'employee_can_view' => 'nullable|boolean',
            'employee_can_download' => 'nullable|boolean',
            'status' => 'required|string|in:active,inactive',
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

        $this->documentMasterRepository->storeDocument($validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'documents'])
            ->with('success', 'Document master created successfully.');
    }

    /**
     * Update an existing document master.
     */
    public function updateDocument(Request $request, DocumentMaster $document): RedirectResponse
    {
        $tenantId = $document->tenant_id ?? auth()->user()->tenant_id;

        $validated = $request->validate([
            'document_category_id' => 'required|exists:document_categories,id',
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_masters', 'code')->where('tenant_id', $tenantId)->ignore($document->id),
            ],
            'description' => 'nullable|string|max:1000',
            'is_required' => 'nullable|boolean',
            'upload_responsibility' => 'required|string|in:employee,hr,both',
            'approval_required' => 'nullable|boolean',
            'expiry_applicable' => 'nullable|boolean',
            'reminder_days_before' => 'nullable|required_if:expiry_applicable,1|integer|min:1',
            'employee_can_view' => 'nullable|boolean',
            'employee_can_download' => 'nullable|boolean',
            'status' => 'required|string|in:active,inactive',
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

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'documents'])
            ->with('success', 'Document master updated successfully.');
    }

    /**
     * Delete an existing document master.
     */
    public function destroyDocument(DocumentMaster $document): RedirectResponse
    {
        $this->documentMasterRepository->deleteDocument($document);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'documents'])
            ->with('success', 'Document master deleted successfully.');
    }

    /**
     * Toggle the status (active/inactive) of an existing document master.
     */
    public function toggleStatus(DocumentMaster $document): RedirectResponse
    {
        $newStatus = $document->status === 'active' ? 'inactive' : 'active';
        
        $this->documentMasterRepository->updateDocument($document, [
            'status' => $newStatus
        ]);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'documents'])
            ->with('success', 'Document status updated successfully.');
    }
}
