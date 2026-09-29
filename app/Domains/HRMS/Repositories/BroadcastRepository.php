<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Broadcast;
use App\Domains\HRMS\Models\BroadcastComment;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Services\BroadcastService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BroadcastRepository implements BroadcastRepositoryInterface
{
    public function __construct(
        private readonly BroadcastService $broadcastService
    ) {}

    private function applyFilters($query, ?string $search, ?string $priority, ?string $category, string $sort): void
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($category) {
            $query->where('category', $category);
        }

        match ($sort) {
            'oldest'   => $query->oldest('published_at')->oldest('created_at'),
            'priority' => $query->orderByRaw("FIELD(priority, 'urgent', 'important', 'general')"),
            default    => $query->latest('published_at')->latest('created_at'),
        };
    }

    public function getIndexData(array $inputs, bool $isHrAdmin, ?Employee $employee, int $tenantId): array
    {
        $this->broadcastService->processScheduledBroadcasts($tenantId);

        $activeTab = $inputs['active_tab'] ?? ($inputs['tab'] ?? 'published');
        $search = $inputs['search'] ?? null;
        $priority = $inputs['priority'] ?? null;
        $category = $inputs['category'] ?? null;
        $sort = $inputs['sort'] ?? 'newest';

        $baseQuery = Broadcast::query()->where('tenant_id', $tenantId);

        $statsBaseQuery = clone $baseQuery;
        if (!$isHrAdmin) {
            if ($employee) {
                $statsBaseQuery->where(function ($q) use ($employee) {
                    $q->where('target_type', 'all')
                      ->orWhereHas('receipts', fn($rq) => $rq->where('employee_id', $employee->id));
                });
            } else {
                $statsBaseQuery->where('target_type', 'all');
            }
        }

        $totalActive = (clone $statsBaseQuery)->where('status', 'published')->count();
        $publishedThisMonth = (clone $statsBaseQuery)->where('status', 'published')
            ->whereMonth('published_at', now()->month)
            ->whereYear('published_at', now()->year)
            ->count();

        if (!$isHrAdmin && $employee) {
            $pendingAckCount = (clone $statsBaseQuery)->where('status', 'published')
                ->where('is_acknowledgement_required', true)
                ->whereHas('receipts', fn($q) => $q->where('employee_id', $employee->id)->whereNull('acknowledged_at'))
                ->count();
        } else {
            $pendingAckCount = (clone $baseQuery)->where('status', 'published')
                ->where('is_acknowledgement_required', true)
                ->whereHas('receipts', fn($q) => $q->whereNull('acknowledged_at'))
                ->count();
        }

        $scheduledCount = $isHrAdmin ? (clone $baseQuery)->where('status', 'scheduled')->count() : 0;

        $publishedQuery = (clone $baseQuery)->where('status', 'published');
        if (!$isHrAdmin) {
            if ($employee) {
                $publishedQuery->where(function ($q) use ($employee) {
                    $q->where('target_type', 'all')
                      ->orWhereHas('receipts', fn($rq) => $rq->where('employee_id', $employee->id));
                });
            } else {
                $publishedQuery->where('target_type', 'all');
            }
        }
        $this->applyFilters($publishedQuery, $search, $priority, $category, $sort);
        $publishedBroadcasts = $publishedQuery->paginate(10, ['*'], 'published_page')->appends($inputs);

        if ($isHrAdmin) {
            $scheduledQuery = (clone $baseQuery)->whereIn('status', ['scheduled', 'draft']);
            $this->applyFilters($scheduledQuery, $search, $priority, $category, $sort);
            $scheduledBroadcasts = $scheduledQuery->paginate(10, ['*'], 'scheduled_page')->appends($inputs);
        } else {
            $scheduledBroadcasts = new LengthAwarePaginator([], 0, 10, 1);
        }

        $archivedQuery = (clone $baseQuery)->where(function ($q) {
            $q->where('status', 'archived')
              ->orWhere(function ($sub) {
                  $sub->where('status', 'published')
                      ->whereNotNull('expires_at')
                      ->where('expires_at', '<', now());
              });
        });

        if (!$isHrAdmin) {
            if ($employee) {
                $archivedQuery->where(function ($q) use ($employee) {
                    $q->where('target_type', 'all')
                      ->orWhereHas('receipts', fn($rq) => $rq->where('employee_id', $employee->id));
                });
            } else {
                $archivedQuery->where('target_type', 'all');
            }
        }
        $this->applyFilters($archivedQuery, $search, $priority, $category, $sort);
        $archivedBroadcasts = $archivedQuery->paginate(10, ['*'], 'archived_page')->appends($inputs);

        $departments = Department::where('tenant_id', $tenantId)->orderBy('name')->get();
        $branches = Branch::where('tenant_id', $tenantId)->orderBy('name')->get();
        $designations = Designation::where('tenant_id', $tenantId)->orderBy('name')->get();
        $employees = Employee::where('tenant_id', $tenantId)->where('status', true)->orderBy('full_name')->get();

        return compact(
            'publishedBroadcasts',
            'scheduledBroadcasts',
            'archivedBroadcasts',
            'totalActive',
            'publishedThisMonth',
            'pendingAckCount',
            'scheduledCount',
            'departments',
            'branches',
            'designations',
            'employees',
            'isHrAdmin',
            'employee',
            'activeTab',
            'search',
            'priority',
            'category',
            'sort'
        );
    }

    public function getShowData(int $id, bool $isHrAdmin, ?Employee $employee, int $tenantId): array
    {
        $broadcast = Broadcast::with([
            'sender',
            'receipts.employee.department',
            'comments.user',
            'comments.employee',
            'attachments'
        ])->where('tenant_id', $tenantId)->findOrFail($id);

        if ($employee) {
            $this->broadcastService->markAsViewed($broadcast, $employee);
        }

        $myReceipt = null;
        if ($employee) {
            $myReceipt = $broadcast->receipts()->where('employee_id', $employee->id)->first();
        }

        $totalRecipients = $broadcast->receipts()->count();
        $viewedCount = $broadcast->receipts()->whereNotNull('viewed_at')->count();
        $ackCount = $broadcast->receipts()->whereNotNull('acknowledged_at')->count();
        $viewPercentage = $totalRecipients > 0 ? round(($viewedCount / $totalRecipients) * 100) : 0;
        $ackPercentage = $totalRecipients > 0 ? round(($ackCount / $totalRecipients) * 100) : 0;

        return compact(
            'broadcast',
            'isHrAdmin',
            'employee',
            'myReceipt',
            'totalRecipients',
            'viewedCount',
            'ackCount',
            'viewPercentage',
            'ackPercentage'
        );
    }

    public function storeBroadcast(array $validated, Request $request, int $tenantId): Broadcast
    {
        $validated['tenant_id'] = $tenantId;
        $validated['created_by_id'] = auth()->id();

        $attachmentFiles = $request->file('attachments', []);

        $broadcast = $this->broadcastService->createBroadcast($validated, $attachmentFiles);

        if ($broadcast->status === 'published') {
            $this->broadcastService->publishBroadcast($broadcast);
        }

        return $broadcast;
    }

    public function updateBroadcast(int $id, array $validated, Request $request, int $tenantId): Broadcast
    {
        $broadcast = Broadcast::where('tenant_id', $tenantId)->findOrFail($id);

        $attachmentFiles = $request->file('attachments', []);

        $broadcast = $this->broadcastService->updateBroadcast($broadcast, $validated, $attachmentFiles);

        if ($broadcast->status === 'published' && !$broadcast->published_at) {
            $this->broadcastService->publishBroadcast($broadcast);
        }

        return $broadcast;
    }

    public function deleteBroadcast(int $id, int $tenantId): bool
    {
        $broadcast = Broadcast::where('tenant_id', $tenantId)->findOrFail($id);
        return $this->broadcastService->deleteBroadcast($broadcast);
    }

    public function acknowledge(int $id, ?Employee $employee, ?string $signatureData, int $tenantId): bool
    {
        $broadcast = Broadcast::where('tenant_id', $tenantId)->findOrFail($id);

        if ($employee) {
            $this->broadcastService->acknowledge($broadcast, $employee, $signatureData);
            return true;
        }

        return false;
    }

    public function storeComment(int $id, array $validated, ?User $user, ?Employee $employee, int $tenantId): BroadcastComment
    {
        $broadcast = Broadcast::where('tenant_id', $tenantId)->findOrFail($id);

        return BroadcastComment::create([
            'tenant_id'    => $tenantId,
            'broadcast_id' => $broadcast->id,
            'user_id'      => $user?->id,
            'employee_id'  => $employee?->id,
            'comment'      => $validated['comment'],
        ]);
    }

    public function deleteComment(int $commentId, ?User $user, bool $isHrAdmin, int $tenantId): bool
    {
        $comment = BroadcastComment::where('tenant_id', $tenantId)->findOrFail($commentId);

        if (!$isHrAdmin && $comment->user_id !== $user?->id) {
            return false;
        }

        return (bool) $comment->delete();
    }
}
