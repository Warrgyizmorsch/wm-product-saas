<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Projects\Requests\RejectTimeLogRequest;
use App\Domains\Projects\Services\TimeLogService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimesheetApprovalController extends Controller
{
    public function __construct(
        private readonly TimeLogService $timeLogs,
    ) {
    }

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('approveAny', TimeLog::class);

        $filters = $request->only(['project_id', 'user_id']);
        $pendingLogs = $this->timeLogs->getPendingApprovals($filters);

        if ($request->wantsJson()) {
            return response()->json([
                'count'     => $pendingLogs->count(),
                'time_logs' => $pendingLogs,
            ]);
        }

        $tenantId = require_tenant_id();
        $projects = Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get();
        $users = User::query()->where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('modules.projects.timelogs.approval', [
            'timeLogs' => $pendingLogs,
            'projects' => $projects,
            'users'    => $users,
            'filters'  => $filters,
        ]);
    }

    public function approve(Request $request, TimeLog $timeLog): RedirectResponse|JsonResponse
    {
        $this->authorize('approve', $timeLog);

        $approved = $this->timeLogs->approve($timeLog, auth()->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message'  => __('projects.timelog_approved_successfully', ['default' => 'Time log approved successfully.']),
                'time_log' => $approved->load(['user', 'approver']),
            ]);
        }

        return redirect()->back()
            ->with('success', __('projects.timelog_approved_successfully', ['default' => 'Time log approved successfully.']));
    }

    public function reject(RejectTimeLogRequest $request, TimeLog $timeLog): RedirectResponse|JsonResponse
    {
        $this->authorize('approve', $timeLog);

        $rejected = $this->timeLogs->reject($timeLog, auth()->user(), $request->input('rejection_remarks'));

        if ($request->wantsJson()) {
            return response()->json([
                'message'  => __('projects.timelog_rejected_successfully', ['default' => 'Time log rejected successfully.']),
                'time_log' => $rejected->load(['user', 'approver']),
            ]);
        }

        return redirect()->back()
            ->with('success', __('projects.timelog_rejected_successfully', ['default' => 'Time log rejected successfully.']));
    }
}
