<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\ActivityLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activity,
    ) {
    }

    public function index(Project $project, Request $request): View|RedirectResponse
    {
        $this->authorize('view', $project);

        $activities = $this->activity->forProject($project, 200);

        if ($request->ajax()) {
            return view('modules.projects._activity-list', [
                'activities' => $activities,
            ]);
        }

        return redirect()->route('projects.show', $project);
    }
}
