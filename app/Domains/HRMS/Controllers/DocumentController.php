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
}
