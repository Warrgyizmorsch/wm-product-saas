<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Projects\Repositories\ProjectDocumentRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentService
{
    public function __construct(
        private readonly ProjectDocumentRepositoryInterface $documents,
        private readonly ActivityLogService $activityLogs,
    ) {
    }

    public function upload(
        Project $project,
        UploadedFile $file,
        array $data,
        User $uploader,
        ?Model $attachable = null
    ): ProjectDocument {
        return DB::transaction(function () use ($project, $file, $data, $uploader, $attachable) {
            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $hashName = Str::random(40) . '.' . $extension;
            $directory = sprintf('tenants/%d/projects/%d/documents', $project->tenant_id, $project->id);

            $filePath = $file->storeAs($directory, $hashName, 'local');

            $document = $this->documents->create([
                'tenant_id'       => $project->tenant_id,
                'company_id'      => $project->company_id,
                'branch_id'       => $project->branch_id,
                'project_id'      => $project->id,
                'attachable_type' => $attachable ? $attachable->getMorphClass() : ($data['attachable_type'] ?? null),
                'attachable_id'   => $attachable ? $attachable->getKey() : ($data['attachable_id'] ?? null),
                'title'           => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_name'       => $file->getClientOriginalName(),
                'file_path'       => $filePath,
                'file_size'       => $file->getSize(),
                'mime_type'       => $file->getClientMimeType() ?: 'application/octet-stream',
                'category'        => $data['category'] ?? ProjectDocument::CATEGORY_ATTACHMENT,
                'uploaded_by'     => $uploader->id,
                'remarks'         => $data['remarks'] ?? null,
            ]);

            $this->activityLogs->record(
                $project,
                'project.document_uploaded',
                __('projects.document_uploaded_title', [
                    'title'   => $document->title,
                    'user'    => $uploader->name,
                    'default' => ":user uploaded document :title",
                ]),
                null,
                $document,
                [
                    'file_name' => $document->file_name,
                    'file_size' => $document->file_size,
                    'category'  => $document->category,
                ]
            );

            return $document;
        });
    }

    public function preview(ProjectDocument $document, ?User $user = null): Response
    {
        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404, __('projects.document_file_not_found', [
                'default' => 'Physical file not found on disk storage.',
            ]));
        }

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name,
            [
                'Content-Disposition' => 'inline; filename="' . addcslashes($document->file_name, '"\\') . '"',
            ]
        );
    }

    public function download(ProjectDocument $document, ?User $user = null): StreamedResponse
    {
        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404, __('projects.document_file_not_found', [
                'default' => 'Physical file not found on disk storage.',
            ]));
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function delete(ProjectDocument $document, User $actor): bool
    {
        $project = $document->project;
        $title = $document->title;
        $deleted = $this->documents->delete($document->id);

        if ($deleted && $project) {
            $this->activityLogs->record(
                $project,
                'project.document_deleted',
                __('projects.document_deleted_title', [
                    'title'   => $title,
                    'user'    => $actor->name,
                    'default' => ":user deleted document :title",
                ]),
                null,
                null,
                ['title' => $title]
            );
        }

        return $deleted;
    }
}
