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

    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $statusCode);
    }

    private function resolveContext(): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;
        $user = auth()->user();

        return [(int) $tenantId, $user];
    }

    /**
     * Get summary metrics and compact cycles list.
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        try {
            $data = $this->feedbackRepository->getIndexData($request->all(), $user, $tenantId);

            $paginated = $data['cycles'];
            $cyclesList = $paginated->getCollection()->map(function ($c) {
                return [
                    'id'                  => $c->id,
                    'code'                => $c->code,
                    'name'                => $c->name,
                    'status'              => $c->status,
                    'start_date'          => $c->start_date ? (is_string($c->start_date) ? substr($c->start_date, 0, 10) : $c->start_date->format('Y-m-d')) : null,
                    'end_date'            => $c->end_date ? (is_string($c->end_date) ? substr($c->end_date, 0, 10) : $c->end_date->format('Y-m-d')) : null,
                    'nomination_deadline' => $c->nomination_deadline ? (is_string($c->nomination_deadline) ? substr($c->nomination_deadline, 0, 10) : $c->nomination_deadline->format('Y-m-d')) : null,
                    'submission_deadline' => $c->submission_deadline ? (is_string($c->submission_deadline) ? substr($c->submission_deadline, 0, 10) : $c->submission_deadline->format('Y-m-d')) : null,
                    'participants_count'  => (int) ($c->participants_count ?? 0),
                    'nominations_count'   => (int) ($c->nominations_count ?? 0),
                    'capabilities'        => $c->capabilities ?? [
                        'can_view'             => true,
                        'can_edit'             => false,
                        'can_delete'           => false,
                        'can_launch'           => false,
                        'can_add_participants' => false,
                        'can_bulk_remind'      => false,
                    ],
                ];
            });

            $myPendingReviews = collect($data['myPendingReviews'] ?? [])->take(15)->map(function ($r) {
                return [
                    'id'                  => $r->id,
                    'cycle_id'            => $r->cycle_id,
                    'cycle_name'          => $r->cycle?->name,
                    'reviewee_name'       => $r->employee?->full_name,
                    'reviewee_department' => $r->employee?->department?->name,
                    'relationship'        => $r->reviewer_type,
                    'status'              => $r->status,
                    'capabilities'        => $r->capabilities ?? ['can_view' => true, 'can_submit_feedback' => true],
                ];
            })->values()->all();

            $summary = [
                'statistics' => [
                    'total_cycles'            => $data['statistics']['total_cycles'] ?? 0,
                    'active_cycles'           => $data['statistics']['active_cycles'] ?? 0,
                    'total_participants'      => $data['statistics']['total_participants'] ?? 0,
                    'total_nominations'       => $data['statistics']['total_nominations'] ?? 0,
                    'completed_reviews'       => $data['statistics']['completed_reviews'] ?? 0,
                    'pending_reviews'         => $data['statistics']['pending_reviews'] ?? 0,
                    'my_pending_count'        => $data['statistics']['my_pending_count'] ?? 0,
                    'pending_approvals_count' => $data['statistics']['pending_approvals_count'] ?? 0,
                    'completion_rate'         => $data['statistics']['overall_completion_rate'] ?? 0.0,
                ],
                'cycles' => [
                    'items'      => $cyclesList,
                    'pagination' => [
                        'current_page' => $paginated->currentPage(),
                        'per_page'     => $paginated->perPage(),
                        'total'        => $paginated->total(),
                        'last_page'    => $paginated->lastPage(),
                    ],
                ],
                'my_pending_reviews' => $myPendingReviews,
                'is_hr_admin'        => (bool) ($data['isHrAdmin'] ?? false),
            ];

            return $this->sendSuccess($summary, 'Feedback 360 overview loaded successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * Get single cycle details with concise participant roster.
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        try {
            $data = $this->feedbackRepository->getCycleDetailData($id, $user, $tenantId);
            $cycle = $data['cycle'];

            $participants = collect($data['participants'] ?? [])->map(function ($p) {
                $totalNoms = $p->nominations->count();
                $completedNoms = $p->nominations->where('status', 'completed')->count();

                return [
                    'id'                => $p->id,
                    'employee_id'       => $p->employee_id,
                    'employee_code'     => $p->employee?->employee_id ?? ('EMP-' . $p->employee_id),
                    'name'              => $p->employee?->full_name,
                    'department'        => $p->employee?->department?->name,
                    'designation'       => $p->employee?->designation?->name,
                    'status'            => $p->status,
                    'nominations_count' => $totalNoms,
                    'completed_count'   => $completedNoms,
                    'capabilities'      => $p->capabilities ?? ['can_view' => true],
                ];
            })->values()->all();

            $totalParticipants = count($participants);
            $totalNominations = collect($data['participants'] ?? [])->sum(fn($p) => $p->nominations->count());
            $completedNominations = collect($data['participants'] ?? [])->sum(fn($p) => $p->nominations->where('status', 'completed')->count());
            $completionRate = $totalNominations > 0 ? round(($completedNominations / $totalNominations) * 100, 1) : 0.0;

            $response = [
                'id'                         => $cycle->id,
                'code'                       => $cycle->code,
                'name'                       => $cycle->name,
                'description'                => $cycle->description,
                'status'                     => $cycle->status,
                'start_date'                 => $cycle->start_date ? (is_string($cycle->start_date) ? substr($cycle->start_date, 0, 10) : $cycle->start_date->format('Y-m-d')) : null,
                'end_date'                   => $cycle->end_date ? (is_string($cycle->end_date) ? substr($cycle->end_date, 0, 10) : $cycle->end_date->format('Y-m-d')) : null,
                'nomination_deadline'        => $cycle->nomination_deadline ? (is_string($cycle->nomination_deadline) ? substr($cycle->nomination_deadline, 0, 10) : $cycle->nomination_deadline->format('Y-m-d')) : null,
                'submission_deadline'        => $cycle->submission_deadline ? (is_string($cycle->submission_deadline) ? substr($cycle->submission_deadline, 0, 10) : $cycle->submission_deadline->format('Y-m-d')) : null,
                'is_peer_anonymous'          => (bool) $cycle->is_peer_anonymous,
                'is_direct_report_anonymous' => (bool) $cycle->is_direct_report_anonymous,
                'allow_self_nomination'      => (bool) $cycle->allow_self_nomination,
                'require_manager_approval'   => (bool) $cycle->require_manager_approval,
                'min_peer_nominations'       => (int) ($cycle->min_peer_nominations ?? 2),
                'max_peer_nominations'       => (int) ($cycle->max_peer_nominations ?? 5),
                'stats'                      => [
                    'total_participants'   => $totalParticipants,
                    'total_nominations'    => $totalNominations,
                    'completed_reviews'    => $completedNominations,
                    'completion_rate'      => $completionRate,
                ],
                'participants'               => $participants,
                'capabilities'               => $cycle->capabilities ?? ['can_view' => true],
            ];

            return $this->sendSuccess($response, 'Feedback cycle details retrieved.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 404);
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
            return $this->sendSuccess([
                'id'         => $cycle->id,
                'code'       => $cycle->code,
                'name'       => $cycle->name,
                'status'     => $cycle->status,
                'start_date' => $cycle->start_date ? (is_string($cycle->start_date) ? substr($cycle->start_date, 0, 10) : $cycle->start_date->format('Y-m-d')) : null,
                'end_date'   => $cycle->end_date ? (is_string($cycle->end_date) ? substr($cycle->end_date, 0, 10) : $cycle->end_date->format('Y-m-d')) : null,
            ], 'Feedback cycle created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
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

            return $this->sendSuccess([
                'id'         => $cycle->id,
                'code'       => $cycle->code,
                'name'       => $cycle->name,
                'status'     => $cycle->status,
                'start_date' => $cycle->start_date ? (is_string($cycle->start_date) ? substr($cycle->start_date, 0, 10) : $cycle->start_date->format('Y-m-d')) : null,
                'end_date'   => $cycle->end_date ? (is_string($cycle->end_date) ? substr($cycle->end_date, 0, 10) : $cycle->end_date->format('Y-m-d')) : null,
            ], 'Feedback cycle updated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
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

            return $this->sendSuccess(null, 'Feedback cycle deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 404);
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

            return $this->sendSuccess([
                'id'     => $cycle->id,
                'code'   => $cycle->code,
                'status' => $cycle->status,
            ], "Cycle stage updated to '{$request->input('status')}'.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
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

            return $this->sendSuccess([
                'enrolled_count' => $count,
            ], "{$count} participant(s) enrolled into the feedback cycle.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
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

            return $this->sendSuccess([
                'reminded_count' => $count,
            ], "Reminders sent to {$count} reviewer(s) with pending feedback.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
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

            return $this->sendSuccess([
                'nominated_count' => count($nominations),
            ], 'Peer reviewers nominated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
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

            return $this->sendSuccess([
                'id'     => $nomination->id,
                'status' => $nomination->status,
            ], $isApproved ? 'Nomination approved.' : 'Nomination rejected.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
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
            return $this->sendSuccess([
                'processed_count' => $count,
            ], "{$count} peer nomination(s) {$actionWord} successfully.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
        }
    }

    /**
     * Get review workspace questions and existing answers.
     */
    public function reviewWorkspace(int $nominationId): JsonResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        try {
            $data = $this->feedbackRepository->getReviewWorkspaceData($nominationId, $user, $tenantId);
            $nom = $data['nomination'];
            $existingResponses = $data['existingResponses'];

            $questions = collect($data['questions'])->map(function ($q) use ($existingResponses) {
                $saved = $existingResponses->get($q->id);
                return [
                    'id'                   => $q->id,
                    'competency'           => $q->competency?->name ?? 'General',
                    'question_text'        => $q->question_text,
                    'question_type'        => $q->question_type,
                    'target_reviewer_type' => $q->target_reviewer_type,
                    'current_rating'       => $saved?->rating_value,
                    'current_text'         => $saved?->text_response,
                ];
            });

            $response = [
                'nomination' => [
                    'id'            => $nom->id,
                    'cycle_name'    => $nom->cycle?->name,
                    'reviewee_name' => $nom->employee?->full_name,
                    'reviewer_type' => $nom->reviewer_type,
                    'status'        => $nom->status,
                ],
                'questions'  => $questions,
            ];

            return $this->sendSuccess($response, 'Review workspace loaded.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 403);
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
            return $this->sendSuccess([
                'id'           => $nom->id,
                'status'       => $nom->status,
                'submitted_at' => $nom->submitted_at?->format('Y-m-d H:i:s'),
            ], $isDraft
                ? 'Feedback evaluation draft saved successfully.'
                : 'Your 360-degree feedback evaluation was submitted successfully.'
            );
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
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
            $participant = $data['participant'];

            $response = [
                'participant' => [
                    'id'          => $participant->id,
                    'name'        => $participant->employee?->full_name,
                    'department'  => $participant->employee?->department?->name,
                    'designation' => $participant->employee?->designation?->name,
                    'cycle_name'  => $participant->cycle?->name,
                    'status'      => $participant->status,
                ],
                'rater_counts'         => $data['raterCounts'],
                'competency_scores'    => $data['competencyScores'],
                'radar_data'           => $data['radarData'],
                'blind_spots'          => $data['blindSpots'],
                'hidden_strengths'     => $data['hiddenStrengths'],
                'top_strengths'        => $data['topStrengths'],
                'growth_areas'         => $data['growthAreas'],
                'qualitative_feedback' => $data['qualitativeFeedback'],
                'capabilities'         => [
                    'can_publish'  => (bool) ($data['canPublish'] ?? false),
                    'is_published' => (bool) ($data['isPublished'] ?? false),
                ],
            ];

            return $this->sendSuccess($response, '360 evaluation report loaded.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 404);
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

            return $this->sendSuccess([
                'id'           => $participant->id,
                'status'       => $participant->status,
                'published_at' => $participant->published_at?->format('Y-m-d H:i:s'),
            ], '360° Evaluation Report has been published and made available to the employee.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
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

            return $this->sendSuccess([
                'id'       => $comp->id,
                'name'     => $comp->name,
                'category' => $comp->category,
            ], 'Competency created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
        }
    }

    public function destroyCompetency(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->feedbackRepository->deleteCompetency($id, $tenantId);

            return $this->sendSuccess(null, 'Competency deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 404);
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

            return $this->sendSuccess([
                'id'            => $q->id,
                'question_text' => $q->question_text,
                'question_type' => $q->question_type,
            ], 'Question added to feedback library.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 422);
        }
    }

    public function destroyQuestion(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->feedbackRepository->deleteQuestion($id, $tenantId);

            return $this->sendSuccess(null, 'Question deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 404);
        }
    }
}
