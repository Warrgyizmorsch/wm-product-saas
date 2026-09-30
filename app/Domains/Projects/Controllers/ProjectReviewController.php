<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectReview;
use App\Domains\Projects\Requests\SignOffProjectReviewRequest;
use App\Domains\Projects\Requests\StoreProjectReviewRequest;
use App\Domains\Projects\Services\ProjectReviewService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectReviewController extends Controller
{
    public function __construct(
        private readonly ProjectReviewService $reviewService,
    ) {
    }

    public function index(Project $project, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', [ProjectReview::class, $project]);

        if ($request->wantsJson()) {
            $reviews = $this->reviewService->list($project);
            return response()->json(['reviews' => $reviews]);
        }

        return redirect()->to(route('projects.show', $project) . '?tab=reviews');
    }

    public function store(StoreProjectReviewRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('create', [ProjectReview::class, $project]);

        $review = $this->reviewService->create(
            $project,
            $request->validated(),
            $request->user(),
            $request->file('evidence_file')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('projects.review_created_success', ['default' => 'Client UAT review initiated successfully.']),
                'review'  => $review,
            ], 201);
        }

        return redirect()
            ->to(route('projects.show', $project) . '?tab=reviews')
            ->with('success', __('projects.review_created_success', ['default' => 'Client UAT review initiated successfully.']));
    }

    public function signoff(SignOffProjectReviewRequest $request, Project $project, ProjectReview $review): RedirectResponse|JsonResponse
    {
        $this->authorize('signoff', $review);

        $updated = $this->reviewService->signoff(
            $review,
            $request->validated('outcome'),
            $request->validated('comments'),
            $request->validated('sign_off_ref'),
            $request->user(),
            $request->file('evidence_file')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('projects.review_signoff_success', ['default' => 'Client review sign-off recorded successfully.']),
                'review'  => $updated,
            ]);
        }

        return redirect()
            ->to(route('projects.show', $project) . '?tab=reviews')
            ->with('success', __('projects.review_signoff_success', ['default' => 'Client review sign-off recorded successfully.']));
    }

    public function destroy(Request $request, Project $project, ProjectReview $review): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $review);

        $this->reviewService->delete($review, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('projects.review_deleted_success', ['default' => 'UAT review deleted successfully.']),
            ]);
        }

        return redirect()
            ->to(route('projects.show', $project) . '?tab=reviews')
            ->with('success', __('projects.review_deleted_success', ['default' => 'UAT review deleted successfully.']));
    }
}
