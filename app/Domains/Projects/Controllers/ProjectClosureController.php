<?php

namespace App\Domains\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Requests\CloseProjectRequest;
use App\Domains\Projects\Services\ProjectClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectClosureController extends Controller
{
    public function __construct(
        private readonly ProjectClosureService $closureService
    ) {}

    /**
     * Check project closure condition gates and return diagnostics.
     */
    public function checkGates(Project $project): JsonResponse
    {
        $this->authorize('close', $project);

        $evaluation = $this->closureService->evaluateGates($project);

        return response()->json([
            'success'   => true,
            'can_close' => $evaluation['can_close'],
            'gates'     => $evaluation['gates'],
            'blockers'  => $evaluation['blockers'],
        ]);
    }

    /**
     * Execute controlled project closure.
     */
    public function close(CloseProjectRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->authorize('close', $project);

        try {
            $updatedProject = $this->closureService->close(
                $project,
                $request->validated(),
                $request->user()
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('projects.closure_success_message'),
                    'project' => $updatedProject,
                ]);
            }

            return redirect()
                ->route('projects.show', $project)
                ->with('success', __('projects.closure_success_message'));
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }

            return redirect()
                ->route('projects.show', $project)
                ->withErrors($e->errors())
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            return redirect()
                ->route('projects.show', $project)
                ->with('error', $e->getMessage());
        }
    }
}
