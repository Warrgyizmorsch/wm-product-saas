<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Repositories\Feedback360RepositoryInterface;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Feedback360ApiController extends Controller
{
    public function __construct(
        private readonly Feedback360RepositoryInterface $feedbackRepository
    ) {}

    private function resolveContext(): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;
        $user = auth()->user();

        return [(int) $tenantId, $user];
    }

    /**
     * Get summary metrics and cycles list.
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        try {
            $data = $this->feedbackRepository->getIndexData($request->all(), $user, $tenantId);
            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single cycle details.
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        try {
            $data = $this->feedbackRepository->getCycleDetailData($id, $user, $tenantId);
            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Create a cycle via API.
     */
    public function store(Request $request): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'name'       => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $cycle = $this->feedbackRepository->storeCycle($request->all(), $tenantId, $user);
            return response()->json([
                'status'  => 'success',
                'message' => 'Feedback cycle created successfully.',
                'data'    => $cycle,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get review workspace for a nomination.
     */
    public function reviewWorkspace(int $nominationId): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        try {
            $data = $this->feedbackRepository->getReviewWorkspaceData($nominationId, $user, $tenantId);
            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Submit review ratings and comments.
     */
    public function submitReview(Request $request, int $nominationId): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'responses' => 'required|array',
        ]);

        try {
            $nom = $this->feedbackRepository->submitReview($nominationId, $request->all(), $tenantId, $user);
            return response()->json([
                'status'  => 'success',
                'message' => 'Feedback submitted successfully.',
                'data'    => $nom,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get 360 assessment report with radar and gap calculations.
     */
    public function report(int $participantId): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        try {
            $data = $this->feedbackRepository->getParticipantReportData($participantId, $user, $tenantId);
            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
