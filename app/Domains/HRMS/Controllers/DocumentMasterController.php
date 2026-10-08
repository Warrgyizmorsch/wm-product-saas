<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\DocumentCategory;
use App\Domains\HRMS\Models\DocumentMaster;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Repositories\DocumentMasterRepositoryInterface;
use App\Domains\HRMS\Services\DocumentTemplateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        $this->authorize('viewAny', DocumentMaster::class);

        $data = $this->documentMasterRepository->getIndexData($request->all());

        return view('modules.hrms.document-master.index', $data);
    }

    /**
     * Store a new document category.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('create', DocumentCategory::class);
        $validated = $request->validate([
            'company_id'  => 'required|exists:companies,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $this->documentMasterRepository->storeCategory($validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'categories'])
            ->with('success', __('hrms.document_master.category_created_success'));
    }

    /**
     * Update an existing document category.
     */
    public function updateCategory(Request $request, DocumentCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $validated = $request->validate([
            'company_id'  => 'required|exists:companies,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $this->documentMasterRepository->updateCategory($category, $validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'categories'])
            ->with('success', __('hrms.document_master.category_updated_success'));
    }

    /**
     * Delete an existing document category.
     */
    public function destroyCategory(DocumentCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);
        // Prevent deleting category if it has associated document masters
        if ($category->documentMasters()->exists()) {
            return redirect()->route('hrms.documents-master.index', ['active_tab' => 'categories'])
                ->with('error', __('hrms.document_master.cannot_delete_category_has_docs'));
        }

        $this->documentMasterRepository->deleteCategory($category);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'categories'])
            ->with('success', __('hrms.document_master.category_deleted_success'));
    }

    /**
     * Store a new document master.
     */
    public function storeDocument(Request $request): RedirectResponse
    {
        $this->authorize('create', DocumentMaster::class);
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;

        $validated = $request->validate([
            'document_category_id'  => 'required|exists:document_categories,id',
            'name'                  => 'required|string|max:255',
            'code'                  => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_masters', 'code')->where(fn($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q->whereNull('tenant_id')),
            ],
            'description'           => 'nullable|string|max:1000',
            'is_required'           => 'nullable|boolean',
            'upload_responsibility' => 'required|string|in:employee,hr,both',
            'approval_required'     => 'nullable|boolean',
            'requires_signature'    => 'nullable|boolean',
            'expiry_applicable'     => 'nullable|boolean',
            'reminder_days_before'  => 'nullable|required_if:expiry_applicable,1,true,on|integer|min:1',
            'employee_can_view'     => 'nullable|boolean',
            'employee_can_download' => 'nullable|boolean',
            'status'                => 'required|string|in:active,inactive',
        ]);

        // Normalize checkboxes
        $validated['is_required'] = $request->boolean('is_required');
        $validated['approval_required'] = $request->boolean('approval_required');
        $validated['requires_signature'] = $request->boolean('requires_signature');
        $validated['expiry_applicable'] = $request->boolean('expiry_applicable');
        if ($validated['upload_responsibility'] !== 'hr') {
            $validated['employee_can_view'] = true;
            $validated['employee_can_download'] = true;
        } else {
            $validated['employee_can_view'] = $request->boolean('employee_can_view');
            $validated['employee_can_download'] = $request->has('employee_can_download')
                ? $request->boolean('employee_can_download')
                : $validated['employee_can_view'];
        }

        if (!$validated['expiry_applicable']) {
            $validated['reminder_days_before'] = null;
        }

        $this->documentMasterRepository->storeDocument($validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'documents'])
            ->with('success', __('hrms.document_master.document_created_success'));
    }

    /**
     * Update an existing document master.
     */
    public function updateDocument(Request $request, DocumentMaster $document): RedirectResponse
    {
        $this->authorize('update', $document);
        $tenantId = $document->tenant_id ?? tenant_id() ?? auth()->user()?->tenant_id;

        $validated = $request->validate([
            'document_category_id'  => 'required|exists:document_categories,id',
            'name'                  => 'required|string|max:255',
            'code'                  => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_masters', 'code')->where(fn($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q->whereNull('tenant_id'))->ignore($document->id),
            ],
            'description'           => 'nullable|string|max:1000',
            'is_required'           => 'nullable|boolean',
            'upload_responsibility' => 'required|string|in:employee,hr,both',
            'approval_required'     => 'nullable|boolean',
            'requires_signature'    => 'nullable|boolean',
            'expiry_applicable'     => 'nullable|boolean',
            'reminder_days_before'  => 'nullable|required_if:expiry_applicable,1,true,on|integer|min:1',
            'employee_can_view'     => 'nullable|boolean',
            'employee_can_download' => 'nullable|boolean',
            'status'                => 'required|string|in:active,inactive',
        ]);

        // Normalize checkboxes
        $validated['is_required'] = $request->boolean('is_required');
        $validated['approval_required'] = $request->boolean('approval_required');
        $validated['requires_signature'] = $request->boolean('requires_signature');
        $validated['expiry_applicable'] = $request->boolean('expiry_applicable');
        if ($validated['upload_responsibility'] !== 'hr') {
            $validated['employee_can_view'] = true;
            $validated['employee_can_download'] = true;
        } else {
            $validated['employee_can_view'] = $request->boolean('employee_can_view');
            $validated['employee_can_download'] = $request->has('employee_can_download')
                ? $request->boolean('employee_can_download')
                : $validated['employee_can_view'];
        }

        if (!$validated['expiry_applicable']) {
            $validated['reminder_days_before'] = null;
        }

        $this->documentMasterRepository->updateDocument($document, $validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'documents'])
            ->with('success', __('hrms.document_master.document_updated_success'));
    }

    /**
     * Delete an existing document master.
     */
    public function destroyDocument(DocumentMaster $document): RedirectResponse
    {
        $this->authorize('delete', $document);
        $this->documentMasterRepository->deleteDocument($document);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'documents'])
            ->with('success', __('hrms.document_master.document_deleted_success'));
    }

    /**
     * Toggle the status (active/inactive) of an existing document master.
     */
    public function toggleStatus(Request $request, DocumentMaster $document): RedirectResponse
    {
        $this->authorize('update', $document);
        $newStatus = $request->has('status')
            ? ($request->input('status') === 'active' ? 'active' : 'inactive')
            : ($document->status === 'active' ? 'inactive' : 'active');
        
        $this->documentMasterRepository->updateDocument($document, [
            'status' => $newStatus
        ]);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'documents'])
            ->with('success', __('hrms.document_master.document_status_updated_success'));
    }

    /**
     * Store a new document template.
     */
    public function storeTemplate(Request $request): RedirectResponse
    {
        $this->authorize('create', DocumentTemplate::class);
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;

        $validated = $request->validate([
            'company_id'           => 'nullable|exists:companies,id',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'name'                 => 'required|string|max:255',
            'code'                 => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_templates', 'code')->where(fn($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q->whereNull('tenant_id')),
            ],
            'template_file'        => 'nullable|file|mimes:html,htm,txt,docx|max:10240',
            'header_content'       => 'nullable|string',
            'body_content'         => 'nullable|string',
            'footer_content'       => 'nullable|string',
            'css_styles'           => 'nullable|string',
            'requires_signature'   => 'nullable|boolean',
            'status'               => 'required|string|in:active,inactive',
        ]);

        $validated['requires_signature'] = $request->boolean('requires_signature');

        $templateService = app(DocumentTemplateService::class);

        // If file is uploaded, extract content to populate body_content
        if ($request->hasFile('template_file')) {
            $file = $request->file('template_file');
            $extractedContent = $templateService->importTemplateFromFile($file);
            if (!empty($extractedContent)) {
                $validated['body_content'] = $extractedContent;
            }
            $validated['template_file_path'] = $file->store('document_templates', 'public');
        }

        DocumentTemplate::create($validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'templates'])
            ->with('success', __('hrms.document_master.template_created_success'));
    }

    /**
     * Update an existing document template.
     */
    public function updateTemplate(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);
        $tenantId = $template->tenant_id ?? tenant_id() ?? auth()->user()?->tenant_id;

        $validated = $request->validate([
            'company_id'           => 'nullable|exists:companies,id',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'name'                 => 'required|string|max:255',
            'code'                 => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_templates', 'code')->where(fn($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q->whereNull('tenant_id'))->ignore($template->id),
            ],
            'template_file'        => 'nullable|file|mimes:html,htm,txt,docx|max:10240',
            'header_content'       => 'nullable|string',
            'body_content'         => 'nullable|string',
            'footer_content'       => 'nullable|string',
            'css_styles'           => 'nullable|string',
            'requires_signature'   => 'nullable|boolean',
            'status'               => 'required|string|in:active,inactive',
        ]);

        $validated['requires_signature'] = $request->boolean('requires_signature');

        $templateService = app(DocumentTemplateService::class);

        if ($request->hasFile('template_file')) {
            $file = $request->file('template_file');
            $extractedContent = $templateService->importTemplateFromFile($file);
            if (!empty($extractedContent)) {
                $validated['body_content'] = $extractedContent;
            }
            if ($template->template_file_path && Storage::disk('public')->exists($template->template_file_path)) {
                Storage::disk('public')->delete($template->template_file_path);
            }
            $validated['template_file_path'] = $file->store('document_templates', 'public');
        }

        $template->update($validated);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'templates'])
            ->with('success', __('hrms.document_master.template_updated_success'));
    }

    /**
     * Parse an uploaded template file (.html, .txt, .docx) and return extracted content for Quill editor.
     */
    public function parseTemplateFile(Request $request): JsonResponse
    {
        $this->authorize('create', DocumentTemplate::class);
        $request->validate([
            'template_file' => 'required|file|mimes:html,htm,txt,docx|max:10240',
        ]);

        try {
            $file = $request->file('template_file');
            $templateService = app(DocumentTemplateService::class);
            $extractedContent = $templateService->importTemplateFromFile($file);

            return response()->json([
                'success' => true,
                'content' => $extractedContent,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to parse template file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an existing document template.
     */
    public function destroyTemplate(DocumentTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);
        if ($template->template_file_path && Storage::disk('public')->exists($template->template_file_path)) {
            Storage::disk('public')->delete($template->template_file_path);
        }

        $template->delete();

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'templates'])
            ->with('success', __('hrms.document_master.template_deleted_success'));
    }

    /**
     * Toggle status of an existing document template.
     */
    public function toggleTemplateStatus(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);
        $newStatus = $request->has('status')
            ? ($request->input('status') === 'active' ? 'active' : 'inactive')
            : ($template->status === 'active' ? 'inactive' : 'active');

        $template->update([
            'status' => $newStatus
        ]);

        return redirect()->route('hrms.documents-master.index', ['active_tab' => 'templates'])
            ->with('success', __('hrms.document_master.template_status_updated_success'));
    }

    /**
     * Return live JSON preview of template rendered for an employee or template placeholders.
     */
    public function previewTemplate(Request $request, DocumentTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);
        try {
            $employeeId = $request->query('employee_id');
            $employee = $employeeId ? Employee::find($employeeId) : null;

            $templateService = app(DocumentTemplateService::class);
            $renderedHtml = $templateService->renderTemplate($template, $employee);

            return response()->json([
                'success'       => true,
                'template_name' => $template->name,
                'employee_name' => $employee?->full_name ?? 'Template Preview',
                'html'          => $renderedHtml,
            ]);
        } catch (\Throwable $e) {
            Log::error("Template Preview Exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => 'Error rendering document: ' . $e->getMessage(),
            ], 500);
        }
    }
}

