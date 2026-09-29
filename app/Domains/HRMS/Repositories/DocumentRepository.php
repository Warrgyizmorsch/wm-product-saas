<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\DocumentCategory;
use App\Domains\HRMS\Models\DocumentMaster;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentRepository implements DocumentRepositoryInterface
{
    public function __construct(
        private readonly HrmsScopeService $scopeService
    ) {}

    public function getIndexData(array $inputs, ?User $user): array
    {
        $activeTab = $inputs['tab'] ?? 'employee';

        $query = Document::with(['documentable', 'documentMaster', 'requestedBy'])
            ->where('documentable_type', Employee::class);

        $this->scopeService->applyEmployeeScope($query, $user, 'documentable_id');

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

        if (!empty($inputs['search'])) {
            $search = $inputs['search'];
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

        if (!empty($inputs['status'])) {
            $query->where('status', $inputs['status']);
        }

        if (!empty($inputs['category_id'])) {
            $query->whereHas('documentMaster', function ($q) use ($inputs) {
                $q->where('document_category_id', $inputs['category_id']);
            });
        }

        $sort = $inputs['sort'] ?? 'newest';
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

        $documents = $query->paginate(10)->withQueryString();

        $employees = Employee::orderBy('full_name')->get();
        $categories = DocumentCategory::orderBy('name')->get();
        $templates = DocumentMaster::where('status', 'active')->orderBy('name')->get();
        $documentTemplates = DocumentTemplate::where('status', 'active')->orderBy('name')->get();

        $allQuery = Document::where('documentable_type', Employee::class);
        $this->scopeService->applyEmployeeScope($allQuery, $user, 'documentable_id');

        $empQuery = (clone $allQuery)->whereHasMorph('documentable', [Employee::class], function ($q) {
            $q->whereColumn('user_id', 'documents.requested_by_id');
        });
        $hrQuery = (clone $allQuery)->where(function ($q) {
            $q->whereDoesntHaveMorph('documentable', [Employee::class], function ($q2) {
                $q2->whereColumn('user_id', 'documents.requested_by_id');
            })->orWhereNull('requested_by_id');
        });

        $counts = [
            'emp_total'    => (clone $empQuery)->count(),
            'emp_pending'  => (clone $empQuery)->where('status', 'pending')->count(),
            'emp_approved' => (clone $empQuery)->where('status', 'approved')->count(),
            'emp_rejected' => (clone $empQuery)->where('status', 'rejected')->count(),
            'hr_total'     => (clone $hrQuery)->count(),
            'hr_pending'   => (clone $hrQuery)->where('status', 'pending')->count(),
            'hr_approved'  => (clone $hrQuery)->where('status', 'approved')->count(),
            'hr_rejected'  => (clone $hrQuery)->where('status', 'rejected')->count(),
        ];

        $templatesJson = $templates->map(fn($t) => [
            'id'         => $t->id,
            'text'       => $t->name,
            'categoryId' => $t->document_category_id,
            'expiry'     => $t->expiry_applicable ? 1 : 0,
        ])->values();

        return compact('documents', 'employees', 'categories', 'templates', 'documentTemplates', 'templatesJson', 'counts', 'activeTab');
    }

    public function storeDocument(array $validated, Request $request, ?User $user): Document
    {
        $file = $request->file('file');
        $path = $file->store('documents/employee', 'public');

        $docMaster = !empty($validated['document_master_id']) ? DocumentMaster::find($validated['document_master_id']) : null;
        $name = !empty($validated['name']) ? $validated['name'] : ($docMaster?->name ?? $file->getClientOriginalName());

        return Document::create([
            'documentable_type'   => Employee::class,
            'documentable_id'     => $validated['employee_id'],
            'document_master_id'  => $validated['document_master_id'] ?? null,
            'name'                => $name,
            'file_path'           => $path,
            'file_type'           => $file->getClientOriginalExtension(),
            'file_size'           => $file->getSize(),
            'status'              => 'approved',
            'requested_by_id'     => $user?->id,
            'approved_by_id'      => $user?->id,
            'approved_at'         => now(),
        ]);
    }

    public function updateDocument(Document $document, array $validated, Request $request): bool
    {
        $updateData = [
            'document_master_id' => $validated['document_master_id'] ?? $document->document_master_id,
            'name'               => $validated['name'] ?? $document->name,
        ];

        if ($request->hasFile('file')) {
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $file = $request->file('file');
            $updateData['file_path'] = $file->store('documents/employee', 'public');
            $updateData['file_type'] = $file->getClientOriginalExtension();
            $updateData['file_size'] = $file->getSize();
        }

        return $document->update($updateData);
    }

    public function updateStatus(Document $document, string $status, ?string $reason, ?User $user): bool
    {
        $updateData = ['status' => $status];

        if ($status === 'approved') {
            $updateData['approved_by_id'] = $user?->id;
            $updateData['approved_at']    = now();
            $updateData['rejection_reason'] = null;
        } elseif ($status === 'rejected') {
            $updateData['rejection_reason'] = $reason;
            $updateData['approved_by_id'] = null;
            $updateData['approved_at']    = null;
        }

        return $document->update($updateData);
    }

    public function deleteDocument(Document $document): bool
    {
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        return (bool) $document->delete();
    }

    public function bulkApprove(array $ids, ?User $user): int
    {
        return Document::whereIn('id', $ids)->update([
            'status'            => 'approved',
            'approved_by_id'    => $user?->id,
            'approved_at'       => now(),
            'rejection_reason'  => null,
        ]);
    }

    public function bulkReject(array $ids, ?string $reason, ?User $user): int
    {
        return Document::whereIn('id', $ids)->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason ?? 'Rejected in bulk review',
            'approved_by_id'   => null,
            'approved_at'      => null,
        ]);
    }

    public function bulkDelete(array $ids): int
    {
        $docs = Document::whereIn('id', $ids)->get();
        foreach ($docs as $doc) {
            if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                Storage::disk('public')->delete($doc->file_path);
            }
            $doc->delete();
        }

        return $docs->count();
    }
}
