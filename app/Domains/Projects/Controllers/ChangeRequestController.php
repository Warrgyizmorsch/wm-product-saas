<?php

namespace App\Domains\Projects\Controllers;

use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Requests\RejectChangeRequestRequest;
use App\Domains\Projects\Requests\StoreChangeRequestRequest;
use App\Domains\Projects\Services\ChangeRequestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChangeRequestController extends Controller
{
    public function __construct(
        private readonly ChangeRequestService $changeRequestService,
    ) {
    }

    public function index(Project $project, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', [ChangeRequest::class, $project]);

        if ($request->wantsJson()) {
            $crs = $this->changeRequestService->list($project);
            return response()->json(['change_requests' => $crs]);
        }

        return redirect()->to(route('projects.show', $project) . '?tab=reviews');
    }

    public function store(StoreChangeRequestRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('create', [ChangeRequest::class, $project]);

        $cr = $this->changeRequestService->create(
            $project,
            $request->validated(),
            $request->user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success'        => true,
                'message'        => __('projects.cr_created_success', ['default' => 'Change request submitted successfully.']),
                'change_request' => $cr,
            ], 201);
        }

        return redirect()
            ->to(route('projects.show', $project) . '?tab=reviews')
            ->with('success', __('projects.cr_created_success', ['default' => 'Change request submitted successfully.']));
    }

    public function approve(Request $request, Project $project, ChangeRequest $changeRequest): RedirectResponse|JsonResponse
    {
        $this->authorize('approve', $changeRequest);

        $cr = $this->changeRequestService->approve($changeRequest, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success'        => true,
                'message'        => __('projects.cr_approved_success', ['default' => 'Change request approved and budget adjusted successfully.']),
                'change_request' => $cr,
            ]);
        }

        return redirect()
            ->to(route('projects.show', $project) . '?tab=reviews')
            ->with('success', __('projects.cr_approved_success', ['default' => 'Change request approved and budget adjusted successfully.']));
    }

    public function reject(RejectChangeRequestRequest $request, Project $project, ChangeRequest $changeRequest): RedirectResponse|JsonResponse
    {
        $this->authorize('approve', $changeRequest);

        $cr = $this->changeRequestService->reject(
            $changeRequest,
            $request->validated('rejection_remarks'),
            $request->user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success'        => true,
                'message'        => __('projects.cr_rejected_success', ['default' => 'Change request rejected successfully.']),
                'change_request' => $cr,
            ]);
        }

        return redirect()
            ->to(route('projects.show', $project) . '?tab=reviews')
            ->with('success', __('projects.cr_rejected_success', ['default' => 'Change request rejected successfully.']));
    }

    public function markImplemented(Request $request, Project $project, ChangeRequest $changeRequest): RedirectResponse|JsonResponse
    {
        $this->authorize('markImplemented', $changeRequest);

        $cr = $this->changeRequestService->markImplemented($changeRequest, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success'        => true,
                'message'        => __('projects.cr_implemented_success', ['default' => 'Change request marked as implemented.']),
                'change_request' => $cr,
            ]);
        }

        return redirect()
            ->to(route('projects.show', $project) . '?tab=reviews')
            ->with('success', __('projects.cr_implemented_success', ['default' => 'Change request marked as implemented.']));
    }

    public function destroy(Request $request, Project $project, ChangeRequest $changeRequest): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $changeRequest);

        $this->changeRequestService->delete($changeRequest, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('projects.cr_deleted_success', ['default' => 'Change request deleted successfully.']),
            ]);
        }

        return redirect()
            ->to(route('projects.show', $project) . '?tab=reviews')
            ->with('success', __('projects.cr_deleted_success', ['default' => 'Change request deleted successfully.']));
    }
}
