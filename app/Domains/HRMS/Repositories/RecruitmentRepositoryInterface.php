<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Candidate;
use App\Domains\HRMS\Models\CandidateApplication;
use App\Domains\HRMS\Models\CandidateInterview;
use App\Domains\HRMS\Models\InterviewScorecard;
use App\Domains\HRMS\Models\JobOffer;
use App\Domains\HRMS\Models\JobRequisition;
use Illuminate\Http\Request;

interface RecruitmentRepositoryInterface
{
    public function getRequisitionsData(array $inputs, ?int $tenantId): array;

    public function storeRequisition(array $validated, ?int $tenantId, ?int $employeeId, ?int $userId): JobRequisition;

    public function updateRequisitionStatus(JobRequisition $requisition, string $status, ?int $userId): bool;

    public function getCandidatesData(array $inputs, ?int $tenantId): array;

    public function storeCandidate(array $validated, Request $request, ?int $tenantId): Candidate;

    public function getPipelineData(int $requisitionId, ?int $tenantId): array;

    public function updateStage(CandidateApplication $application, string $newStage, ?string $rejectionReason): array;

    public function scheduleInterview(CandidateApplication $application, array $validated, ?int $tenantId): CandidateInterview;

    public function submitScorecard(CandidateInterview $interview, array $validated, ?int $userId, ?int $tenantId): InterviewScorecard;

    public function createOffer(CandidateApplication $application, array $validated, Request $request, ?int $tenantId): JobOffer;

    public function sendOfferEmail(JobOffer $offer, array $validated, ?int $tenantId): array;

    public function convertToEmployee(JobOffer $offer, ?int $tenantId): array;
}
