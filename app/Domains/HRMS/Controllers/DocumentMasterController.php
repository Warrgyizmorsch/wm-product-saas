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

    /**
     * Store a new document template.
     */
    public function storeTemplate(Request $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'company_id'           => 'nullable|exists:companies,id',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'name'                 => 'required|string|max:255',
            'code'                 => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_templates', 'code')->where('tenant_id', $tenantId),
            ],
            'template_file'        => 'nullable|file|mimes:html,htm,txt,docx|max:10240',
            'header_content'       => 'nullable|string',
            'body_content'         => 'nullable|string',
            'footer_content'       => 'nullable|string',
            'css_styles'           => 'nullable|string',
            'status'               => 'required|string|in:active,inactive',
        ]);

        $templateService = app(\App\Domains\HRMS\Services\DocumentTemplateService::class);

        // If file is uploaded, extract content to populate body_content
        if ($request->hasFile('template_file')) {
            $file = $request->file('template_file');
            $extractedContent = $templateService->importTemplateFromFile($file);
            if (!empty($extractedContent)) {
                $validated['body_content'] = $extractedContent;
            }
            $validated['template_file_path'] = $file->store('document_templates', 'public');
        }

        \App\Domains\HRMS\Models\DocumentTemplate::create($validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'templates'])
            ->with('success', 'Document template created successfully.');
    }

    /**
     * Update an existing document template.
     */
    public function updateTemplate(Request $request, \App\Domains\HRMS\Models\DocumentTemplate $template): RedirectResponse
    {
        $tenantId = $template->tenant_id ?? auth()->user()->tenant_id;

        $validated = $request->validate([
            'company_id'           => 'nullable|exists:companies,id',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'name'                 => 'required|string|max:255',
            'code'                 => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_templates', 'code')->where('tenant_id', $tenantId)->ignore($template->id),
            ],
            'template_file'        => 'nullable|file|mimes:html,htm,txt,docx|max:10240',
            'header_content'       => 'nullable|string',
            'body_content'         => 'nullable|string',
            'footer_content'       => 'nullable|string',
            'css_styles'           => 'nullable|string',
            'status'               => 'required|string|in:active,inactive',
        ]);

        $templateService = app(\App\Domains\HRMS\Services\DocumentTemplateService::class);

        if ($request->hasFile('template_file')) {
            $file = $request->file('template_file');
            $extractedContent = $templateService->importTemplateFromFile($file);
            if (!empty($extractedContent)) {
                $validated['body_content'] = $extractedContent;
            }
            $validated['template_file_path'] = $file->store('document_templates', 'public');
        }

        $template->update($validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'templates'])
            ->with('success', 'Document template updated successfully.');
    }

    /**
     * Delete an existing document template.
     */
    public function destroyTemplate(\App\Domains\HRMS\Models\DocumentTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'templates'])
            ->with('success', 'Document template deleted successfully.');
    }

    /**
     * Toggle status of an existing document template.
     */
    public function toggleTemplateStatus(\App\Domains\HRMS\Models\DocumentTemplate $template): RedirectResponse
    {
        $template->update([
            'status' => $template->status === 'active' ? 'inactive' : 'active'
        ]);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'templates'])
            ->with('success', 'Template status updated successfully.');
    }

    /**
     * Return live JSON preview of template rendered for an employee.
     */
    public function previewTemplate(Request $request, \App\Domains\HRMS\Models\DocumentTemplate $template): \Illuminate\Http\JsonResponse
    {
        try {
            $employeeId = $request->query('employee_id');
            $employee = $employeeId ? \App\Domains\HRMS\Models\Employee::find($employeeId) : \App\Domains\HRMS\Models\Employee::first();

            if (!$employee) {
                return response()->json(['error' => 'No employee records found for preview.'], 404);
            }

            $templateService = app(\App\Domains\HRMS\Services\DocumentTemplateService::class);
            $renderedHtml = $templateService->renderTemplate($template, $employee);

            return response()->json([
                'success' => true,
                'template_name' => $template->name,
                'employee_name' => $employee->full_name,
                'html' => $renderedHtml,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Template Preview Exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error rendering document: ' . $e->getMessage(),
            ], 500);
        }
    }
}
