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
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;
        $user = auth()->user();

        return [(int) $tenantId, $user];
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

        $request->validate([
            'name'                       => 'required|string|max:255',
            'code'                       => 'nullable|string|max:50',
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
            'employee_ids'               => 'nullable|array',
            'employee_ids.*'             => 'exists:employees,id',
        ]);

        try {
            $data = $request->all();
            $data['is_peer_anonymous']          = $request->boolean('is_peer_anonymous');
            $data['is_direct_report_anonymous'] = $request->boolean('is_direct_report_anonymous');
            $data['allow_self_nomination']      = $request->boolean('allow_self_nomination');
            $data['require_manager_approval']   = $request->boolean('require_manager_approval');

            $cycle = $this->feedbackRepository->storeCycle($data, $tenantId, $user);

            return redirect()->route('hrms.feedback360.cycles.show', $cycle->id)
                ->with('success', "Feedback cycle '{$cycle->name}' created successfully.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create cycle: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update an existing 360 Feedback Cycle.
     */
    public function updateCycle(Request $request, int $id): RedirectResponse
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

            $this->feedbackRepository->updateCycle($id, $data, $tenantId, $user);

            return redirect()->back()->with('success', 'Feedback cycle updated successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to update cycle: ' . $e->getMessage());
        }
    }

    /**
     * Delete a 360 Feedback Cycle.
     */
    public function destroyCycle(int $id): RedirectResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->feedbackRepository->deleteCycle($id, $tenantId);

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'cycles'])
                ->with('success', 'Feedback cycle deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete cycle: ' . $e->getMessage());
        }
    }

    /**
     * Transition cycle status (e.g. launch nominations or start review phase).
     */
    public function launchCycle(Request $request, int $id): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'status' => 'required|in:draft,nomination,in_progress,review,completed,closed',
        ]);

        try {
            $this->feedbackRepository->launchCycle($id, $request->input('status'), $tenantId, $user);

            return redirect()->back()->with('success', 'Cycle stage updated successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to change stage: ' . $e->getMessage());
        }
    }

    /**
     * Add participants (reviewees) to a cycle.
     */
    public function addParticipants(Request $request, int $id): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'employee_ids'   => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        try {
            $count = $this->feedbackRepository->addParticipantsToCycle($id, $request->input('employee_ids'), $tenantId, $user);

            return redirect()->back()->with('success', "{$count} participant(s) enrolled into the feedback cycle.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to enroll participants: ' . $e->getMessage());
        }
    }

    /**
     * Nominate peer reviewers for a participant.
     */
    public function nominatePeers(Request $request, int $participantId): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'peer_ids'   => 'required|array',
            'peer_ids.*' => 'exists:employees,id',
        ]);

        try {
            $this->feedbackRepository->nominatePeers($participantId, $request->input('peer_ids'), $tenantId, $user);

            return redirect()->back()->with('success', 'Peer reviewers nominated successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to nominate peers: ' . $e->getMessage());
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

            $msg = $isApproved ? 'Nomination approved.' : 'Nomination rejected.';
            return redirect()->back()->with('success', $msg);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to process nomination: ' . $e->getMessage());
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

            $actionWord = $isApproved ? 'approved' : 'rejected';
            return redirect()->back()->with('success', "{$count} peer nomination(s) {$actionWord} successfully.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to process batch nominations: ' . $e->getMessage());
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
                ? 'Feedback evaluation draft saved successfully. You can return and complete it anytime.'
                : 'Your 360-degree feedback evaluation was submitted successfully. Thank you!';

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'my_reviews'])
                ->with('success', $msg);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to save review: ' . $e->getMessage())->withInput();
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

            return redirect()->back()->with('success', '360° Evaluation Report has been published and made available to the employee.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to publish report: ' . $e->getMessage());
        }
    }

    /**
     * Bulk send reminders to reviewers.
     */
    public function bulkRemind(int $cycleId): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        try {
            $count = $this->feedbackRepository->bulkRemind($cycleId, $tenantId, $user);

            return redirect()->back()->with('success', "Reminders sent to {$count} reviewer(s) with pending feedback.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to send reminders: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // COMPETENCIES & QUESTIONS MASTER ACTIONS
    // =========================================================================

    public function storeCompetency(Request $request): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'name'        => 'required|string|max:150',
            'code'        => 'nullable|string|max:50',
            'category'    => 'required|string|max:100',
            'cycle_id'    => 'nullable|exists:feedback_360_cycles,id',
            'description' => 'nullable|string|max:1000',
            'weightage'   => 'nullable|numeric|min:1|max:100',
        ]);

        try {
            $this->feedbackRepository->storeCompetency($request->all(), $tenantId, $user);

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'competencies_questions'])
                ->with('success', 'Competency created successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create competency: ' . $e->getMessage());
        }
    }

    public function destroyCompetency(int $id): RedirectResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->feedbackRepository->deleteCompetency($id, $tenantId);

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'competencies_questions'])
                ->with('success', 'Competency deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete competency: ' . $e->getMessage());
        }
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'question_text'        => 'required|string|max:500',
            'competency_id'        => 'nullable|exists:feedback_360_competencies,id',
            'question_type'        => 'required|in:rating_scale,text',
            'target_reviewer_type' => 'required|in:all,self,manager,peer,direct_report',
        ]);

        try {
            $this->feedbackRepository->storeQuestion($request->all(), $tenantId, $user);

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'competencies_questions'])
                ->with('success', 'Question added to feedback library.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to add question: ' . $e->getMessage());
        }
    }

    public function destroyQuestion(int $id): RedirectResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->feedbackRepository->deleteQuestion($id, $tenantId);

            return redirect()->route('hrms.feedback360.index', ['active_tab' => 'competencies_questions'])
                ->with('success', 'Question deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete question: ' . $e->getMessage());
        }
    }
}
