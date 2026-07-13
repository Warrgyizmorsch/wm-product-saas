<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Concerns\BuildsBackUrl;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Requests\StoreTaskDependencyRequest;
use App\Domains\Projects\Services\TaskDependencyService;
use App\Domains\Projects\Services\TaskService;
use App\Domains\Projects\Support\TaskDrawerPayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaskDependencyController extends Controller
{
    use BuildsBackUrl;

    public function __construct(
        private readonly TaskDependencyService $dependencies,
        private readonly TaskService $tasks,
    ) {
    }

    public function store(StoreTaskDependencyRequest $request, Project $project, Task $task): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);

        $this->dependencies->create($task, (int) $request->validated()['depends_on_task_id']);

        return $this->respond($request, $project, $task, __('projects.dependency_added'));
    }

    public function destroy(Request $request, Project $project, Task $task, TaskDependency $dependency): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);

        $this->dependencies->delete($dependency);

        return $this->respond($request, $project, $task, __('projects.dependency_removed'));
    }

    private function respond(Request $request, Project $project, Task $task, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'dependencies' => TaskDrawerPayload::dependencies($project, $task),
                'otherTasks' => TaskDrawerPayload::otherTasks($task, $this->tasks->list($project)->keyBy('id')),
            ]);
        }

        return redirect()
            ->to($this->backUrlWithQuery(route('projects.show', $project), ['tab' => 'tasklists']))
            ->with('success', $message);
    }
}
