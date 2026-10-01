<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Feedback360Competency;
use App\Domains\HRMS\Models\Feedback360Cycle;
use App\Domains\HRMS\Models\Feedback360Nomination;
use App\Domains\HRMS\Models\Feedback360Participant;
use App\Domains\HRMS\Models\Feedback360Question;
use App\Models\User;

interface Feedback360RepositoryInterface
{
    /**
     * Get all dataset required for the 360 Feedback Main Hub (ERP Single Panel).
     */
    public function getIndexData(array $inputs, ?User $user, int $tenantId): array;

    /**
     * Get details for a specific Feedback Cycle (participants, roster, statistics).
     */
    public function getCycleDetailData(int $id, ?User $user, int $tenantId): array;

    /**
     * Get data required for a reviewer to complete feedback workspace.
     */
    public function getReviewWorkspaceData(int $nominationId, ?User $user, int $tenantId): array;

    /**
     * Get the comprehensive 360-Degree Evaluation Report with Radar chart, gap analysis, and blind spots.
     */
    public function getParticipantReportData(int $participantId, ?User $user, int $tenantId): array;

    /**
     * Store a new Feedback Cycle.
     */
    public function storeCycle(array $data, int $tenantId, ?User $user = null): Feedback360Cycle;

    /**
     * Update an existing Feedback Cycle.
     */
    public function updateCycle(int $id, array $data, int $tenantId, ?User $user = null): Feedback360Cycle;

    /**
     * Delete a Feedback Cycle.
     */
    public function deleteCycle(int $id, int $tenantId): bool;

    /**
     * Launch or transition cycle status.
     */
    public function launchCycle(int $id, string $status, int $tenantId, ?User $user = null): Feedback360Cycle;

    /**
     * Add target employees (reviewees) to a cycle with automatic Manager and Direct Report resolution.
     */
    public function addParticipantsToCycle(int $cycleId, array $employeeIds, int $tenantId, ?User $user = null): int;

    /**
     * Nominate peer reviewers for a participant.
     */
    public function nominatePeers(int $participantId, array $peerIds, int $tenantId, ?User $user = null): array;

    /**
     * Approve or reject a peer nomination.
     */
    public function approveNomination(int $nominationId, bool $approved, ?string $reason, int $tenantId, ?User $user = null): Feedback360Nomination;

    /**
     * Batch approve or reject peer nominations.
     */
    public function batchApproveNominations(array $nominationIds, bool $approved, ?string $reason, int $tenantId, ?User $user = null): int;

    /**
     * Submit feedback answers and scores for a nomination.
     */
    public function submitReview(int $nominationId, array $data, int $tenantId, ?User $user = null): Feedback360Nomination;

    /**
     * Calibrate and publish the 360 report for an employee.
     */
    public function publishReport(int $participantId, array $data, int $tenantId, ?User $user = null): Feedback360Participant;

    /**
     * Send bulk reminders to all reviewers with pending submissions in a cycle.
     */
    public function bulkRemind(int $cycleId, int $tenantId, ?User $user = null): int;

    /**
     * Store a Competency in the library.
     */
    public function storeCompetency(array $data, int $tenantId, ?User $user = null): Feedback360Competency;

    /**
     * Delete a Competency.
     */
    public function deleteCompetency(int $id, int $tenantId): bool;

    /**
     * Store a Question in the bank.
     */
    public function storeQuestion(array $data, int $tenantId, ?User $user = null): Feedback360Question;

    /**
     * Delete a Question.
     */
    public function deleteQuestion(int $id, int $tenantId): bool;
}
