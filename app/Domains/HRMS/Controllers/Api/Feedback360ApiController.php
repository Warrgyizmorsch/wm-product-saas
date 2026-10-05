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
     * Update an existing Feedback Cycle.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'name'                       => 'required|string|max:255',
            'company_id'                 => 'nullable|exists:companies,id',
            'start_date'                 => 'required|date',
            'end_date'                   => 'required|date|after_or_equal:start_date',
            'nomination_deadline'        => 'nullable|date',
            'submission_deadline'        => 'nullable|date',
            'status'                     => 'nullable|in:draft,nomination,in_progress,review,completed,closed',
            'is_peer_anonymous'          => 'nullable',
            'is_direct_report_anonymous' => 'nullable',
            'allow_self_nomination'      => 'nullable',
            'require_manager_approval'   => 'nullable',
            'min_peer_nominations'       => 'nullable|integer|min:0|max:20',
            'max_peer_nominations'       => 'nullable|integer|min:1|max:30',
        ]);

        try {
            $data = $request->all();
            if ($request->has('name')) {
                $data['is_peer_anonymous']          = $request->boolean('is_peer_anonymous');
                $data['is_direct_report_anonymous'] = $request->boolean('is_direct_report_anonymous');
                $data['allow_self_nomination']      = $request->boolean('allow_self_nomination');
                $data['require_manager_approval']   = $request->boolean('require_manager_approval');
            }

            $cycle = $this->feedbackRepository->updateCycle($id, $data, $tenantId, $user);

            return response()->json([
                'status'  => 'success',
                'message' => 'Feedback cycle updated successfully.',
                'data'    => $cycle,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a Feedback Cycle.
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->feedbackRepository->deleteCycle($id, $tenantId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Feedback cycle deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Transition cycle status (launch nominations, start review, close).
     */
    public function launch(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'status' => 'required|in:draft,nomination,in_progress,review,completed,closed',
        ]);

        try {
            $cycle = $this->feedbackRepository->launchCycle($id, $request->input('status'), $tenantId, $user);

            return response()->json([
                'status'  => 'success',
                'message' => "Cycle stage updated to '{$request->input('status')}'.",
                'data'    => $cycle,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Add participants (reviewees) to a cycle.
     */
    public function addParticipants(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'employee_ids'   => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        try {
            $count = $this->feedbackRepository->addParticipantsToCycle($id, $request->input('employee_ids'), $tenantId, $user);

            return response()->json([
                'status'  => 'success',
                'message' => "{$count} participant(s) enrolled into the feedback cycle.",
                'data'    => ['enrolled_count' => $count],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk send reminders to reviewers with pending evaluations.
     */
    public function bulkRemind(int $id): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        try {
            $count = $this->feedbackRepository->bulkRemind($id, $tenantId, $user);

            return response()->json([
                'status'  => 'success',
                'message' => "Reminders sent to {$count} reviewer(s) with pending feedback.",
                'data'    => ['reminded_count' => $count],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Nominate peer reviewers for a participant.
     */
    public function nominatePeers(Request $request, int $participantId): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'peer_ids'   => 'required|array',
            'peer_ids.*' => 'exists:employees,id',
        ]);

        try {
            $nominations = $this->feedbackRepository->nominatePeers($participantId, $request->input('peer_ids'), $tenantId, $user);

            return response()->json([
                'status'  => 'success',
                'message' => 'Peer reviewers nominated successfully.',
                'data'    => $nominations,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Approve or reject a peer nomination.
     */
    public function approveNomination(Request $request, int $nominationId): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $isApproved = ($request->input('action') === 'approve');
            $nomination = $this->feedbackRepository->approveNomination(
                $nominationId,
                $isApproved,
                $request->input('reason'),
                $tenantId,
                $user
            );

            return response()->json([
                'status'  => 'success',
                'message' => $isApproved ? 'Nomination approved.' : 'Nomination rejected.',
                'data'    => $nomination,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Batch approve or reject peer nominations.
     */
    public function batchApproveNominations(Request $request): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'nomination_ids'   => 'required|array',
            'nomination_ids.*' => 'integer',
            'action'           => 'required|in:approve,reject',
            'reason'           => 'nullable|string|max:500',
        ]);

        try {
            $isApproved = ($request->input('action') === 'approve');
            $count = $this->feedbackRepository->batchApproveNominations(
                $request->input('nomination_ids'),
                $isApproved,
                $request->input('reason'),
                $tenantId,
                $user
            );

            $actionWord = $isApproved ? 'approved' : 'rejected';
            return response()->json([
                'status'  => 'success',
                'message' => "{$count} peer nomination(s) {$actionWord} successfully.",
                'data'    => ['processed_count' => $count],
            ]);
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

        $isDraft = ($request->input('submit_action') === 'draft');

        if (!$isDraft) {
            $request->validate([
                'responses' => 'required|array',
            ]);
        }

        try {
            $nom = $this->feedbackRepository->submitReview($nominationId, $request->all(), $tenantId, $user);
            return response()->json([
                'status'  => 'success',
                'message' => $isDraft
                    ? 'Feedback evaluation draft saved successfully.'
                    : 'Your 360-degree feedback evaluation was submitted successfully.',
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

    /**
     * Publish the 360 Report for the reviewee.
     */
    public function publishReport(Request $request, int $participantId): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'manager_summary'  => 'nullable|string|max:2000',
            'development_plan' => 'nullable|string|max:2000',
        ]);

        try {
            $participant = $this->feedbackRepository->publishReport($participantId, $request->all(), $tenantId, $user);

            return response()->json([
                'status'  => 'success',
                'message' => '360° Evaluation Report has been published and made available to the employee.',
                'data'    => $participant,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // =========================================================================
    // COMPETENCIES & QUESTIONS MASTER API ACTIONS
    // =========================================================================

    public function storeCompetency(Request $request): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'name'                      => 'required|string|max:150',
            'code'                      => 'nullable|string|max:50',
            'category'                  => 'required|string|max:100',
            'cycle_id'                  => 'nullable|exists:feedback_360_cycles,id',
            'description'               => 'nullable|string|max:1000',
            'weightage'                 => 'nullable|numeric|min:1|max:100',
            'questions'                 => 'nullable|array',
            'questions.*.question_text' => 'nullable|string|max:500',
        ]);

        try {
            $comp = $this->feedbackRepository->storeCompetency($request->all(), $tenantId, $user);

            return response()->json([
                'status'  => 'success',
                'message' => 'Competency created successfully.',
                'data'    => $comp,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroyCompetency(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->feedbackRepository->deleteCompetency($id, $tenantId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Competency deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function storeQuestion(Request $request): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'question_text'        => 'required|string|max:500',
            'competency_id'        => 'nullable|exists:feedback_360_competencies,id',
            'question_type'        => 'required|in:rating_scale,text',
            'target_reviewer_type' => 'required|in:all,self,manager,peer,direct_report',
        ]);

        try {
            $q = $this->feedbackRepository->storeQuestion($request->all(), $tenantId, $user);

            return response()->json([
                'status'  => 'success',
                'message' => 'Question added to feedback library.',
                'data'    => $q,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroyQuestion(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->feedbackRepository->deleteQuestion($id, $tenantId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Question deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
