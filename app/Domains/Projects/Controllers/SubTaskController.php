<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\SubTask;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Requests\StoreSubTaskRequest;
use App\Domains\Projects\Requests\UpdateSubTaskRequest;
use App\Domains\Projects\Services\SubTaskService;
use App\Domains\Projects\Support\TaskDrawerPayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubTaskController extends Controller
{
    use BuildsBackUrl;

    public function __construct(
        private readonly SubTaskService $subTasks,
    ) {
    }

    public function store(StoreSubTaskRequest $request, Project $project, Task $task): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);

        $this->subTasks->create($task, $request->validated());

        return $this->respond($request, $project, $task, __('projects.subtask_added'));
    }

    public function update(UpdateSubTaskRequest $request, Project $project, Task $task, SubTask $subTask): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);

        $this->subTasks->update($subTask, $request->validated());

        return $this->respond($request, $project, $task, __('projects.subtask_updated'));
    }

    public function toggleComplete(Request $request, Project $project, Task $task, SubTask $subTask): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);

        $this->subTasks->toggleComplete($subTask);

        return $this->respond($request, $project, $task, __('projects.subtask_updated'));
    }

    public function destroy(Request $request, Project $project, Task $task, SubTask $subTask): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);

        $this->subTasks->delete($subTask);

        return $this->respond($request, $project, $task, __('projects.subtask_removed'));
    }

    private function respond(Request $request, Project $project, Task $task, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'subtasks' => TaskDrawerPayload::subtasks($project, $task),
            ]);
        }

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', $message);
    }
}
