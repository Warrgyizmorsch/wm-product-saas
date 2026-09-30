<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Requests\RescheduleTaskRequest;
use App\Domains\Projects\Services\ProjectScheduleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectScheduleController extends Controller
{
    public function __construct(
        private readonly ProjectScheduleService $scheduleService,
    ) {
    }

    public function index(Project $project, Request $request): View|JsonResponse
    {
        $this->authorize('view', $project);

        $milestoneId = $request->filled('milestone_id') ? (int) $request->input('milestone_id') : null;
        $schedule = $this->scheduleService->calculateSchedule($project, $milestoneId);

        if ($request->wantsJson()) {
            return response()->json($schedule);
        }

        $milestones = $project->milestones()->orderBy('milestone_order')->get();

        return view('modules.projects.timeline', [
            'project'    => $project,
            'schedule'   => $schedule,
            'milestones' => $milestones,
            'selectedMilestoneId' => $milestoneId,
        ]);
    }

    public function data(Project $project, Request $request): JsonResponse
    {
        $this->authorize('view', $project);

        $milestoneId = $request->filled('milestone_id') ? (int) $request->input('milestone_id') : null;
        $schedule = $this->scheduleService->calculateSchedule($project, $milestoneId);

        return response()->json($schedule);
    }

    public function reschedule(Project $project, Task $task, RescheduleTaskRequest $request): JsonResponse|RedirectResponse
    {
        if ((int) $task->project_id !== (int) $project->id) {
            abort(404);
        }

        $this->authorize('update', $task);

        $validated = $request->validated();

        try {
            $result = $this->scheduleService->rescheduleTask(
                $task,
                $validated['start_date'],
                $validated['due_date'],
                $validated['shift_mode']
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => __('projects.task_rescheduled', ['defaultValue' => 'Task rescheduled successfully.']),
                    'result'  => $result,
                ]);
            }

            return redirect()->back()->with('success', __('projects.task_rescheduled', ['defaultValue' => 'Task rescheduled successfully.']));
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }

            throw $e;
        }
    }
}
