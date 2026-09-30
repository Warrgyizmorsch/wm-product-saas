<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Requests\UploadProjectDocumentRequest;
use App\Domains\Projects\Services\ProjectDocumentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentController extends Controller
{
    public function __construct(
        private readonly ProjectDocumentService $documentService,
    ) {
    }

    public function index(Project $project, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', [ProjectDocument::class, $project]);

        if ($request->wantsJson()) {
            $documents = $project->documents()->with('uploader')->latest()->get();
            return response()->json(['documents' => $documents]);
        }

        return redirect()->to(route('projects.show', $project) . '?tab=documents');
    }

    public function store(UploadProjectDocumentRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('create', [ProjectDocument::class, $project]);

        $attachable = null;
        $attachableType = $request->input('attachable_type');
        $attachableId = $request->input('attachable_id');

        if ($attachableType && $attachableId) {
            if ($attachableType === 'task' || $attachableType === Task::class) {
                $attachable = $project->tasks()->findOrFail($attachableId);
            } elseif ($attachableType === 'issue' || $attachableType === Issue::class) {
                $attachable = $project->issues()->findOrFail($attachableId);
            } elseif ($attachableType === 'review' || $attachableType === \App\Domains\Projects\Models\ProjectReview::class) {
                $attachable = $project->reviews()->findOrFail($attachableId);
            }
        }

        $document = $this->documentService->upload(
            $project,
            $request->file('file'),
            $request->validated(),
            $request->user(),
            $attachable
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('projects.document_uploaded_success', ['default' => 'Document uploaded successfully.']),
                'document' => $document,
            ]);
        }

        return redirect()->back()
            ->with('success', __('projects.document_uploaded_success', ['default' => 'Document uploaded successfully.']));
    }

    public function preview(Request $request, Project $project, ProjectDocument $document): Response
    {
        $this->authorize('view', $document);

        return $this->documentService->preview($document, $request->user());
    }

    public function download(Request $request, Project $project, ProjectDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        return $this->documentService->download($document, $request->user());
    }

    public function destroy(Request $request, Project $project, ProjectDocument $document): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $document);

        $this->documentService->delete($document, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('projects.document_deleted_success', ['default' => 'Document deleted successfully.']),
            ]);
        }

        return redirect()->back()
            ->with('success', __('projects.document_deleted_success', ['default' => 'Document deleted successfully.']));
    }
}
