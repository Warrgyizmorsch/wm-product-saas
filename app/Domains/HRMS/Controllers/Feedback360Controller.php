<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Repositories\Feedback360RepositoryInterface;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Feedback360Controller extends Controller
{
    public function __construct(
        private readonly Feedback360RepositoryInterface $feedbackRepository
    ) {}

    /**
     * Resolve current tenant ID and authenticated user context.
     */
    private function resolveContext(): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id;
        $user = auth()->user();

        if (!$tenantId) {
            abort(403, __('hrms.feedback_360.unauthorized_tenant'));
        }

        return [(int) $tenantId, $user];
    }

    /**
     * Guard administrative actions against unauthorized access.
     */
    private function authorizeAdmin(?\App\Models\User $user, int $tenantId): void
    {
        if (!$this->feedbackRepository->isHrAdminUser($user, $tenantId)) {
            abort(403, __('hrms.feedback_360.access_denied_admin'));
        }
    }

    /**
     * Display the Main 360-Degree Feedback Hub (Single Panel ERP Interface).
     */
    public function index(Request $request): View
    {
        [$tenantId, $user] = $this->resolveContext();

        $data = $this->feedbackRepository->getIndexData($request->all(), $user, $tenantId);

        return view('modules.hrms.feedback360.index', $data);
    }

    /**
     * Display Cycle Detail Dashboard (Participants, Roster, Stats).
     */
    public function showCycle(int $id): View
    {
        [$tenantId, $user] = $this->resolveContext();

        $data = $this->feedbackRepository->getCycleDetailData($id, $user, $tenantId);

        return view('modules.hrms.feedback360.cycle_show', $data);
    }

    /**
     * Display Feedback Review Workspace for a reviewer.
     */
    public function reviewWorkspace(int $nominationId): View
    {
        [$tenantId, $user] = $this->resolveContext();

        $data = $this->feedbackRepository->getReviewWorkspaceData($nominationId, $user, $tenantId);

        return view('modules.hrms.feedback360.review_workspace', $data);
    }

    /**
     * Display the 360 Assessment Report for a participant (Radar chart, blind spots, gap analysis).
     */
    public function report(int $participantId): View
    {
        [$tenantId, $user] = $this->resolveContext();

        $data = $this->feedbackRepository->getParticipantReportData($participantId, $user, $tenantId);

        return view('modules.hrms.feedback360.report', $data);
    }

    /**
     * Store a new 360 Feedback Cycle.
     */
    public function storeCycle(Request $request): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();
        $this->authorizeAdmin($user, $tenantId);

        $request->validate([
            'name'                       => 'required|string|max:255',
            'code'                       => 'nullable|string|max:50',
            'company_id'                 => 'nullable|exists:companies,id',
            'start_date'                 => 'required|date',
            'end_date'                   => 'required|date|after_or_equal:start_date',
            'nomination_deadline'        => 'nullable|date',
            'submission_deadline'        => 'nullable|date',
            'status'                     => 'nullable|in:draft,nomination,in_progress,review,completed,closed',
            'is_peer_anonymous'          => 'nullable|boolean',
            'is_direct_report_anonymous' => 'nullable|boolean',
            'allow_self_nomination'      => 'nullable|boolean',
            'require_manager_approval'   => 'nullable|boolean',
            'min_peer_nominations'       => 'nullable|integer|min:0|max:20',
            'max_peer_nominations'       => 'nullable|integer|min:1|max:30',
            'employee_ids'               => 'nullable|array',
            'employee_ids.*'             => 'exists:employees,id',
            'competency_ids'             => 'required|array|min:1',
            'competency_ids.*'           => 'exists:feedback_360_competencies,id',
        ], [
            'competency_ids.required' => __('hrms.feedback_360.val_competency_required'),
            'competency_ids.min'      => __('hrms.feedback_360.val_competency_required'),
        ]);

        try {
            $data = $request->all();
            $data['is_peer_anonymous']          = $request->boolean('is_peer_anonymous');
            $data['is_direct_report_anonymous'] = $request->boolean('is_direct_report_anonymous');
            $data['allow_self_nomination']      = $request->boolean('allow_self_nomination');
            $data['require_manager_approval']   = $request->boolean('require_manager_approval');

            $cycle = $this->feedbackRepository->storeCycle($data, $tenantId, $user);

            return redirect()->route('hrms.feedback360.cycles.show', $cycle->id)
                ->with('success', __('hrms.feedback_360.cycle_created_success', ['name' => $cycle->name]));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_create_cycle', ['message' => $e->getMessage()]))->withInput();
        }
    }

    /**
     * Update an existing 360 Feedback Cycle.
     */
    public function updateCycle(Request $request, int $id): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();
        $this->authorizeAdmin($user, $tenantId);

        $request->validate([
            'name'                       => 'required|string|max:255',
            'company_id'                 => 'nullable|exists:companies,id',
            'start_date'                 => 'required|date',
            'end_date'                   => 'required|date|after_or_equal:start_date',
            'nomination_deadline'        => 'nullable|date',
            'submission_deadline'        => 'nullable|date',
            'status'                     => 'nullable|in:draft,nomination,in_progress,review,completed,closed',
            'is_peer_anonymous'          => 'nullable|boolean',
            'is_direct_report_anonymous' => 'nullable|boolean',
            'allow_self_nomination'      => 'nullable|boolean',
            'require_manager_approval'   => 'nullable|boolean',
            'min_peer_nominations'       => 'nullable|integer|min:0|max:20',
            'max_peer_nominations'       => 'nullable|integer|min:1|max:30',
            'competency_ids'             => 'nullable|array',
            'competency_ids.*'           => 'exists:feedback_360_competencies,id',
        ]);

        try {
            $data = $request->all();
            if ($request->has('name')) {
                $data['is_peer_anonymous']          = $request->boolean('is_peer_anonymous');
                $data['is_direct_report_anonymous'] = $request->boolean('is_direct_report_anonymous');
                $data['allow_self_nomination']      = $request->boolean('allow_self_nomination');
                $data['require_manager_approval']   = $request->boolean('require_manager_approval');
            }

            $this->feedbackRepository->updateCycle($id, $data, $tenantId, $user);

            return redirect()->back()->with('success', __('hrms.feedback_360.cycle_updated_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_update_cycle', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Delete a 360 Feedback Cycle.
     */
    public function destroyCycle(int $id): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();
        $this->authorizeAdmin($user, $tenantId);

        try {
            $this->feedbackRepository->deleteCycle($id, $tenantId);

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'cycles'])
                ->with('success', __('hrms.feedback_360.cycle_deleted_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_delete_cycle', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Transition cycle status (e.g. launch nominations or start review phase).
     */
    public function launchCycle(Request $request, int $id): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();
        $this->authorizeAdmin($user, $tenantId);

        $request->validate([
            'status' => 'required|in:draft,nomination,in_progress,review,completed,closed',
        ]);

        try {
            $this->feedbackRepository->launchCycle($id, $request->input('status'), $tenantId, $user);

            return redirect()->back()->with('success', __('hrms.feedback_360.stage_updated_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_change_stage', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Add participants (reviewees) to a cycle.
     */
    public function addParticipants(Request $request, int $id): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();
        $this->authorizeAdmin($user, $tenantId);

        $request->validate([
            'employee_ids'   => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        try {
            $count = $this->feedbackRepository->addParticipantsToCycle($id, $request->input('employee_ids'), $tenantId, $user);

            return redirect()->back()->with('success', __('hrms.feedback_360.participants_enrolled_success', ['count' => $count]));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_enroll_participants', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Nominate peer and direct report reviewers for a participant.
     */
    public function nominatePeers(Request $request, int $participantId): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'peer_ids'            => 'nullable|array',
            'peer_ids.*'          => 'exists:employees,id',
            'direct_report_ids'   => 'nullable|array',
            'direct_report_ids.*' => 'exists:employees,id',
        ]);

        $peerIds = (array) $request->input('peer_ids', []);
        $directReportIds = (array) $request->input('direct_report_ids', []);

        if (empty($peerIds) && empty($directReportIds)) {
            return redirect()->back()->with('error', __('hrms.feedback_360.select_at_least_one_nominee'));
        }

        try {
            $this->feedbackRepository->nominatePeers($participantId, $peerIds, $tenantId, $user, $directReportIds);

            return redirect()->back()->with('success', __('hrms.feedback_360.reviewers_nominated_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_nominate_reviewers', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Delete an assigned rater from a participant's feedback roster.
     */
    public function destroyNomination(int $nominationId): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        try {
            $this->feedbackRepository->deleteNomination($nominationId, $tenantId, $user);

            return redirect()->back()->with('success', __('hrms.feedback_360.rater_removed_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_remove_rater', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Manually add raters (peers, direct reports, or managers) to a participant.
     */
    public function addRater(Request $request, int $participantId): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'reviewer_id'    => 'nullable|exists:employees,id',
            'reviewer_ids'   => 'nullable|array',
            'reviewer_ids.*' => 'exists:employees,id',
            'reviewer_type'  => 'required|in:peer,direct_report,manager',
        ]);

        $reviewerIds = (array) ($request->input('reviewer_ids') ?? []);
        if (empty($reviewerIds) && $request->filled('reviewer_id')) {
            $reviewerIds = [(int) $request->input('reviewer_id')];
        }
        $reviewerIds = array_values(array_filter(array_map('intval', $reviewerIds)));

        if (empty($reviewerIds)) {
            return redirect()->back()->with('error', __('hrms.feedback_360.select_at_least_one_nominee'));
        }

        try {
            foreach ($reviewerIds as $rId) {
                $this->feedbackRepository->addRaterToParticipant(
                    $participantId,
                    $rId,
                    $request->input('reviewer_type'),
                    $tenantId,
                    $user
                );
            }

            return redirect()->back()->with('success', __('hrms.feedback_360.reviewer_added_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_add_reviewer', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Approve or reject a peer nomination.
     */
    public function approveNomination(Request $request, int $nominationId): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $isApproved = ($request->input('action') === 'approve');
            $this->feedbackRepository->approveNomination($nominationId, $isApproved, $request->input('reason'), $tenantId, $user);

            $msg = $isApproved ? __('hrms.feedback_360.nomination_approved') : __('hrms.feedback_360.nomination_rejected');
            return redirect()->back()->with('success', $msg);
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_process_nomination', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Batch approve or reject peer nominations.
     */
    public function batchApproveNominations(Request $request): RedirectResponse
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

            $actionWord = $isApproved ? __('hrms.feedback_360.word_approved') : __('hrms.feedback_360.word_rejected');
            return redirect()->back()->with('success', __('hrms.feedback_360.batch_nominations_processed', ['count' => $count, 'action' => $actionWord]));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_batch_nominations', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Submit feedback answers from the review workspace.
     */
    public function submitReview(Request $request, int $nominationId): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $isDraft = ($request->input('submit_action') === 'draft');

        if (!$isDraft) {
            $request->validate([
                'responses' => 'required|array',
            ]);
        }

        try {
            $this->feedbackRepository->submitReview($nominationId, $request->all(), $tenantId, $user);

            $msg = $isDraft 
                ? __('hrms.feedback_360.review_draft_saved')
                : __('hrms.feedback_360.review_submitted_success');

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'my_reviews'])
                ->with('success', $msg);
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_save_review', ['message' => $e->getMessage()]))->withInput();
        }
    }

    /**
     * Publish the 360 Report for the reviewee.
     */
    public function publishReport(Request $request, int $participantId): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'manager_summary'  => 'nullable|string|max:2000',
            'development_plan' => 'nullable|string|max:2000',
        ]);

        try {
            $this->feedbackRepository->publishReport($participantId, $request->all(), $tenantId, $user);

            return redirect()->back()->with('success', __('hrms.feedback_360.report_published_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_publish_report', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Bulk send reminders to reviewers.
     */
    public function bulkRemind(int $cycleId): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();
        $this->authorizeAdmin($user, $tenantId);

        try {
            $count = $this->feedbackRepository->bulkRemind($cycleId, $tenantId, $user);

            return redirect()->back()->with('success', __('hrms.feedback_360.reminders_sent_success', ['count' => $count]));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_send_reminders', ['message' => $e->getMessage()]));
        }
    }

    // =========================================================================
    // COMPETENCIES & QUESTIONS MASTER ACTIONS
    // =========================================================================

    public function storeCompetency(Request $request): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();
        $this->authorizeAdmin($user, $tenantId);

        $request->validate([
            'name'                      => 'required|string|max:150',
            'description'               => 'nullable|string|max:1000',
            'questions'                 => 'nullable|array',
            'questions.*.question_text' => 'nullable|string|max:500',
        ]);

        try {
            $this->feedbackRepository->storeCompetency($request->all(), $tenantId, $user);

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'competencies_questions'])
                ->with('success', __('hrms.feedback_360.competency_saved_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_create_competency', ['message' => $e->getMessage()]));
        }
    }

    public function destroyCompetency(int $id): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();
        $this->authorizeAdmin($user, $tenantId);

        try {
            $this->feedbackRepository->deleteCompetency($id, $tenantId);

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'competencies_questions'])
                ->with('success', __('hrms.feedback_360.competency_deleted_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_delete_competency', ['message' => $e->getMessage()]));
        }
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();
        $this->authorizeAdmin($user, $tenantId);

        $request->validate([
            'question_text'        => 'required|string|max:500',
            'competency_id'        => 'nullable|exists:feedback_360_competencies,id',
            'question_type'        => 'required|in:rating_scale,text',
            'target_reviewer_type' => 'required|in:all,self,manager,peer,direct_report',
        ]);

        try {
            $this->feedbackRepository->storeQuestion($request->all(), $tenantId, $user);

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'competencies_questions'])
                ->with('success', __('hrms.feedback_360.question_added_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_add_question', ['message' => $e->getMessage()]));
        }
    }

    public function destroyQuestion(int $id): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();
        $this->authorizeAdmin($user, $tenantId);

        try {
            $this->feedbackRepository->deleteQuestion($id, $tenantId);

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'competencies_questions'])
                ->with('success', __('hrms.feedback_360.question_deleted_success'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', __('hrms.feedback_360.failed_delete_question', ['message' => $e->getMessage()]));
        }
    }
}
