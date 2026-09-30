<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Projects\Requests\StoreTimeLogRequest;
use App\Domains\Projects\Requests\UpdateTimeLogRequest;
use App\Domains\Projects\Services\TimeLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimeLogController extends Controller
{
    use BuildsBackUrl;

    public function __construct(
        private readonly TimeLogService $timeLogs,
    ) {
    }

    public function index(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('viewAny', [TimeLog::class, $project]);

        $logs = $this->timeLogs->getForTask($task->id);

        return response()->json([
            'time_logs'    => $logs,
            'actual_hours' => (float) $task->fresh()->actual_hours,
        ]);
    }

    public function store(StoreTimeLogRequest $request, Project $project, Task $task): RedirectResponse|JsonResponse
    {
        $this->authorize('create', [TimeLog::class, $project]);

        $log = $this->timeLogs->logTime($project, $task, auth()->user(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'message'      => __('projects.timelog_logged_successfully', ['default' => 'Time logged successfully.']),
                'time_log'     => $log->load(['user', 'approver']),
                'actual_hours' => (float) $task->fresh()->actual_hours,
            ], 201);
        }

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.tasks.show', [$project, $task]), []))
            ->with('success', __('projects.timelog_logged_successfully', ['default' => 'Time logged successfully.']));
    }

    public function update(UpdateTimeLogRequest $request, Project $project, Task $task, TimeLog $timeLog): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $timeLog);

        $updated = $this->timeLogs->updateTimeLog($timeLog, $request->validated(), auth()->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message'      => __('projects.timelog_updated_successfully', ['default' => 'Time log updated successfully.']),
                'time_log'     => $updated->load(['user', 'approver']),
                'actual_hours' => (float) $task->fresh()->actual_hours,
            ]);
        }

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.tasks.show', [$project, $task]), []))
            ->with('success', __('projects.timelog_updated_successfully', ['default' => 'Time log updated successfully.']));
    }

    public function destroy(Request $request, Project $project, Task $task, TimeLog $timeLog): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $timeLog);

        $this->timeLogs->deleteTimeLog($timeLog, auth()->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message'      => __('projects.timelog_deleted_successfully', ['default' => 'Time log deleted successfully.']),
                'actual_hours' => (float) $task->fresh()->actual_hours,
            ]);
        }

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.tasks.show', [$project, $task]), []))
            ->with('success', __('projects.timelog_deleted_successfully', ['default' => 'Time log deleted successfully.']));
    }
}
