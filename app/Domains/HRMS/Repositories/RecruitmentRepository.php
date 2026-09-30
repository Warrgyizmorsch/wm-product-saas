<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Candidate;
use App\Domains\HRMS\Models\CandidateApplication;
use App\Domains\HRMS\Models\CandidateInterview;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\InterviewScorecard;
use App\Domains\HRMS\Models\JobOffer;
use App\Domains\HRMS\Models\JobRequisition;
use App\Models\User;
use App\Services\EmailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecruitmentRepository implements RecruitmentRepositoryInterface
{
    public function getRequisitionsData(array $inputs, ?int $tenantId): array
    {
        $filters = [
            'search'        => $inputs['search'] ?? '',
            'department_id' => $inputs['department_id'] ?? '',
            'status'        => $inputs['status'] ?? '',
            'sort'          => $inputs['sort'] ?? 'date_desc',
        ];

        $query = JobRequisition::with(['department', 'designation', 'requestedBy', 'applications'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('job_title', 'like', "%{$search}%")
                  ->orWhere('requisition_code', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        switch ($filters['sort']) {
            case 'title_asc':
                $query->orderBy('job_title', 'asc');
                break;
            case 'title_desc':
                $query->orderBy('job_title', 'desc');
                break;
            case 'date_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'vacancies_desc':
                $query->orderBy('vacancies', 'desc');
                break;
            case 'date_desc':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $requisitions = $query->paginate(10)->appends($filters);
        $departments = Department::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();
        $designations = Designation::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();
        $employees = Employee::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();

        $interviewsQuery = CandidateInterview::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'scheduled');
        $upcomingInterviews = $interviewsQuery->with(['application.candidate', 'application.requisition', 'interviewer'])
            ->orderBy('scheduled_at', 'asc')
            ->take(3)
            ->get();

        return compact('requisitions', 'departments', 'designations', 'employees', 'filters', 'upcomingInterviews');
    }

    public function storeRequisition(array $validated, ?int $tenantId, ?int $employeeId, ?int $userId): JobRequisition
    {
        $reqCount = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count() + 1;
        $code = 'REQ-' . date('Y') . '-' . str_pad($reqCount, 3, '0', STR_PAD_LEFT);

        return JobRequisition::create([
            'tenant_id'                 => $tenantId,
            'requisition_code'          => $code,
            'job_title'                 => $validated['job_title'],
            'department_id'             => $validated['department_id'],
            'designation_id'            => $validated['designation_id'],
            'vacancies'                 => $validated['vacancies'],
            'min_experience_years'      => $validated['min_experience_years'],
            'max_experience_years'      => $validated['max_experience_years'],
            'work_mode'                 => $validated['work_mode'],
            'employment_type'           => $validated['employment_type'],
            'priority'                  => $validated['priority'],
            'job_location'              => $validated['job_location'] ?? null,
            'skills_required'           => $validated['skills_required'] ?? null,
            'job_description'           => $validated['job_description'] ?? null,
            'target_joining_date'       => $validated['target_joining_date'] ?? null,
            'status'                    => 'approved',
            'requested_by_employee_id'  => $employeeId,
            'approved_by_user_id'       => $userId,
            'approved_at'               => now(),
        ]);
    }

    public function updateRequisitionStatus(JobRequisition $requisition, string $status, ?int $userId): bool
    {
        return $requisition->update([
            'status' => $status,
            'approved_by_user_id' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function getCandidatesData(array $inputs, ?int $tenantId): array
    {
        $filters = [
            'search' => $inputs['search'] ?? '',
            'status' => $inputs['status'] ?? '',
            'source' => $inputs['source'] ?? '',
            'sort'   => $inputs['sort'] ?? 'date_desc',
        ];

        $query = Candidate::with(['applications.requisition', 'applications.interviews'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('candidate_code', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        switch ($filters['sort']) {
            case 'name_asc':
                $query->orderBy('first_name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('first_name', 'desc');
                break;
            case 'exp_desc':
                $query->orderBy('total_experience_years', 'desc');
                break;
            case 'date_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'date_desc':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $candidates = $query->paginate(12)->appends($filters);
        $requisitions = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('status', ['approved', 'published'])
            ->get();

        return compact('candidates', 'requisitions', 'filters');
    }

    public function storeCandidate(array $validated, Request $request, ?int $tenantId): Candidate
    {
        $cndCount = Candidate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count() + 1;
        $code = 'CND-' . date('Y') . '-' . str_pad($cndCount, 4, '0', STR_PAD_LEFT);

        $resumePath = null;
        if ($request->hasFile('resume')) {
            $resumePath = $request->file('resume')->store('recruitment/resumes', 'local');
        }

        $candidate = Candidate::create([
            'tenant_id'             => $tenantId,
            'candidate_code'        => $code,
            'first_name'            => $validated['first_name'],
            'last_name'             => $validated['last_name'] ?? null,
            'email'                 => $validated['email'],
            'phone'                 => $validated['phone'] ?? null,
            'current_location'      => $validated['current_location'] ?? null,
            'current_company'       => $validated['current_company'] ?? null,
            'current_designation'   => $validated['current_designation'] ?? null,
            'total_experience_years'=> $validated['total_experience_years'],
            'notice_period_days'    => $validated['notice_period_days'] ?? 30,
            'resume_path'           => $resumePath,
            'source'                => $validated['source'],
            'status'                => 'active',
        ]);

        CandidateApplication::create([
            'tenant_id'             => $tenantId,
            'candidate_id'          => $candidate->id,
            'job_requisition_id'    => $validated['job_requisition_id'],
            'current_stage'         => 'applied',
            'stage_updated_at'      => now(),
        ]);

        return $candidate;
    }

    public function getPipelineData(int $requisitionId, ?int $tenantId): array
    {
        $requisition = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['department', 'designation'])
            ->findOrFail($requisitionId);

        $applications = CandidateApplication::where('job_requisition_id', $requisitionId)
            ->with(['candidate', 'interviews.scorecard', 'offer'])
            ->get();

        $employees = Employee::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();
        $departments = Department::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();
        $designations = Designation::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();
        $templates = DocumentTemplate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'active')
            ->get();

        $stages = [
            'applied'     => 'Applied',
            'screening'   => 'Screening',
            'interview'   => 'Interviewing',
            'offer_sent'  => 'Offer Sent',
            'hired'       => 'Hired',
            'rejected'    => 'Rejected',
        ];

        return compact('requisition', 'applications', 'stages', 'employees', 'departments', 'designations', 'templates');
    }

    public function updateStage(CandidateApplication $application, string $newStage, ?string $rejectionReason): array
    {
        $oldStage = $application->current_stage;

        $stageHierarchy = [
            'applied'           => 0,
            'screening'         => 1,
            'interview'         => 2,
            'interview_round_1' => 2,
            'interview_round_2' => 2,
            'interview_round_3' => 2,
            'final_hr'          => 2,
            'offer_sent'        => 3,
            'hired'             => 4,
        ];

        $oldStageKey = in_array($oldStage, ['interview_round_1', 'interview_round_2', 'interview_round_3', 'final_hr']) ? 'interview' : $oldStage;
        $newStageKey = in_array($newStage, ['interview_round_1', 'interview_round_2', 'interview_round_3', 'final_hr']) ? 'interview' : $newStage;

        $oldIndex = $stageHierarchy[$oldStageKey] ?? 0;
        $newIndex = $stageHierarchy[$newStageKey] ?? 0;

        if ($oldStage !== 'rejected' && $newStage !== 'rejected' && $newIndex < $oldIndex) {
            return ['success' => false, 'message' => 'Backtracking candidates to a previous recruitment stage is not allowed.'];
        }

        $application->update([
            'current_stage' => $newStage,
            'rejection_reason' => $newStage === 'rejected' ? $rejectionReason : null,
            'stage_updated_at' => now(),
        ]);

        $req = $application->requisition;
        if ($req) {
            if ($oldStage !== 'hired' && $newStage === 'hired') {
                if ($req->vacancies > 0) {
                    $req->decrement('vacancies');
                    if ($req->fresh()->vacancies === 0) {
                        $req->update(['status' => 'closed']);
                    }
                }
            } elseif ($oldStage === 'hired' && $newStage !== 'hired') {
                $req->increment('vacancies');
                if ($req->status === 'closed') {
                    $req->update(['status' => 'approved']);
                }
            }
        }

        return ['success' => true, 'message' => "Candidate stage updated to " . strtoupper(str_replace('_', ' ', $newStage))];
    }

    public function scheduleInterview(CandidateApplication $application, array $validated, ?int $tenantId): CandidateInterview
    {
        $interview = CandidateInterview::create([
            'tenant_id'               => $tenantId,
            'application_id'          => $application->id,
            'round_number'            => $validated['round_number'],
            'round_name'              => $validated['round_name'],
            'scheduled_at'            => $validated['scheduled_at'],
            'interviewer_employee_id' => $validated['interviewer_employee_id'] ?? null,
            'meeting_link'            => $validated['meeting_link'] ?? null,
            'venue_location'          => $validated['venue_location'] ?? null,
            'status'                  => 'scheduled',
            'round_notes'             => $validated['round_notes'] ?? null,
        ]);

        $stageMapping = [
            1 => 'interview_round_1',
            2 => 'interview_round_2',
            3 => 'interview_round_3',
        ];
        if (isset($stageMapping[$validated['round_number']])) {
            $application->update(['current_stage' => $stageMapping[$validated['round_number']], 'stage_updated_at' => now()]);
        }

        return $interview;
    }

    public function submitScorecard(CandidateInterview $interview, array $validated, ?int $userId, ?int $tenantId): InterviewScorecard
    {
        $scorecard = InterviewScorecard::updateOrCreate(
            ['interview_id' => $interview->id],
            [
                'tenant_id'             => $tenantId,
                'interviewer_user_id'   => $userId,
                'technical_rating'      => $validated['technical_rating'],
                'communication_rating'  => $validated['communication_rating'],
                'culture_fit_rating'    => $validated['culture_fit_rating'],
                'overall_rating'        => $validated['overall_rating'],
                'recommendation'        => $validated['recommendation'],
                'feedback_notes'        => $validated['feedback_notes'] ?? null,
            ]
        );

        $interview->update(['status' => 'completed']);

        if ($validated['recommendation'] === 'reject') {
            $interview->application->update([
                'current_stage' => 'rejected',
                'rejection_reason' => 'Failed Interview Round: ' . $interview->round_name,
                'stage_updated_at' => now(),
            ]);
        }

        return $scorecard;
    }

    public function createOffer(CandidateApplication $application, array $validated, Request $request, ?int $tenantId): JobOffer
    {
        $existingOffer = JobOffer::where('application_id', $application->id)->first();
        if ($existingOffer && $existingOffer->offer_code) {
            $code = $existingOffer->offer_code;
        } else {
            $i = JobOffer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count() + 1;
            do {
                $code = 'OFR-' . date('Y') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                $exists = JobOffer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('offer_code', $code)->exists();
                $i++;
            } while ($exists);
        }

        $offerLetterContent = null;
        if (!empty($validated['document_template_id'])) {
            $template = DocumentTemplate::find($validated['document_template_id']);
            if ($template) {
                $candidate = $application->candidate;
                $department = Department::find($validated['offered_department_id']);
                $designation = Designation::find($validated['offered_designation_id']);
                $company = $department?->company ?? \App\Domains\HRMS\Models\Company::first();

                $hrName = $validated['hr_name'] ?? auth()->user()?->name ?? 'HR Manager';
                $hrDesignation = $validated['hr_designation'] ?? 'HR Manager';
                $hrSigUrl = null;

                if ($request->hasFile('hr_signature_file') && $request->file('hr_signature_file')->isValid()) {
                    $file = $request->file('hr_signature_file');
                    $hrSigPath = $file->store("signatures/hr_tenant_{$tenantId}", 'public');
                    $hrSigUrl = asset('storage/' . $hrSigPath);
                } elseif (!empty($validated['hr_signature_data'])) {
                    $hrSigData = $validated['hr_signature_data'];
                    if (str_starts_with($hrSigData, 'data:image')) {
                        $image = str_replace(' ', '+', preg_replace('/^data:image\/\w+;base64,/', '', $hrSigData));
                        $imageName = 'hr_sig_' . time() . '_' . Str::random(6) . '.png';
                        $hrSigPath = "signatures/hr_tenant_{$tenantId}/{$imageName}";
                        Storage::disk('public')->put($hrSigPath, base64_decode($image));
                        $hrSigUrl = asset('storage/' . $hrSigPath);
                    } else {
                        $hrSigUrl = $hrSigData;
                    }
                }

                if ($hrSigUrl) {
                    $hrSigHtml = '<div style="display:inline-block; text-align:left; margin:10px 0;">' .
                                 '<img src="' . $hrSigUrl . '" style="max-height:60px; max-width:200px; object-fit:contain; display:block;" alt="HR Signature" />' .
                                 '<div style="font-weight:bold; font-size:13px; margin-top:4px;">' . e($hrName) . '</div>' .
                                 '<div style="font-size:11px; color:#64748b;">' . e($hrDesignation) . '</div>' .
                                 '</div>';
                } else {
                    $hrSigHtml = '<div style="display:inline-block; text-align:left; margin:10px 0;">' .
                                 '<div style="font-weight:bold; font-size:13px;">' . e($hrName) . '</div>' .
                                 '<div style="font-size:11px; color:#64748b;">' . e($hrDesignation) . '</div>' .
                                 '</div>';
                }

                $rawContent = trim(($template->header_content ?? '') . "\n" . ($template->body_content ?? '') . "\n" . ($template->footer_content ?? ''));
                if (empty($rawContent)) {
                    $rawContent = $template->body_content ?? '';
                }

                $replacements = [
                    'candidate_name'  => $candidate?->full_name ?? 'Candidate',
                    'employee_name'   => $candidate?->full_name ?? 'Candidate',
                    'candidate_email' => $candidate?->email ?? '',
                    'email'           => $candidate?->email ?? '',
                    'candidate_phone' => $candidate?->phone ?? '',
                    'phone'           => $candidate?->phone ?? '',
                    'employee_id'     => $candidate?->candidate_code ?? '',
                    'job_title'       => $designation?->name ?? '',
                    'designation'     => $designation?->name ?? '',
                    'department'      => $department?->name ?? '',
                    'annual_ctc'      => number_format((float)$validated['offered_annual_ctc'], 2),
                    'joining_date'    => date('d M, Y', strtotime($validated['joining_date'])),
                    'offer_code'      => $code,
                    'company_name'    => $company?->company_name ?? config('app.name'),
                    'current_date'    => date('d M, Y'),
                    'date'            => date('d M, Y'),
                    'offer_notes'     => $validated['offer_letter_notes'] ?? '',
                    'hr_signature'   => $hrSigHtml,
                    'hr_name'        => $hrName,
                    'hr_designation' => $hrDesignation,
                ];

                foreach ($replacements as $key => $val) {
                    $rawContent = str_replace(
                        ['{' . $key . '}', '{{' . $key . '}}', '{' . strtoupper($key) . '}', '{{' . strtoupper($key) . '}}'],
                        $val,
                        $rawContent
                    );
                }
                $rawContent = str_replace(['[HR Signature]', '[hr_signature]', '[HR SIGNATURE]'], $hrSigHtml, $rawContent);
                $rawContent = preg_replace('/\{([^{}\n]*)\}/', '$1', $rawContent);
                $offerLetterContent = $rawContent;
            }
        }

        $offer = JobOffer::updateOrCreate(
            ['application_id' => $application->id],
            [
                'tenant_id'              => $tenantId,
                'offer_code'             => $code,
                'offered_designation_id' => $validated['offered_designation_id'],
                'offered_department_id'  => $validated['offered_department_id'],
                'offered_annual_ctc'     => $validated['offered_annual_ctc'],
                'joining_date'           => $validated['joining_date'],
                'document_template_id'   => $validated['document_template_id'] ?? null,
                'offer_letter_content'   => $offerLetterContent,
                'offer_letter_notes'     => $validated['offer_letter_notes'] ?? null,
                'status'                 => 'accepted',
                'accepted_at'            => now(),
            ]
        );

        $application->update([
            'current_stage' => 'offer_sent',
            'stage_updated_at' => now(),
        ]);

        return $offer;
    }

    public function sendOfferEmail(JobOffer $offer, array $validated, ?int $tenantId): array
    {
        $application = $offer->application;
        $candidate = $application?->candidate;

        if (!$candidate) {
            return ['success' => false, 'message' => 'Candidate information not found.'];
        }

        $rawContent = $offer->offer_letter_content;
        if (empty($rawContent)) {
            $rawContent = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2 style='color: #1e293b; text-align: center;'>JOB OFFER LETTER</h2>
                    <p>Dear <strong>{$candidate->full_name}</strong>,</p>
                    <p>We are pleased to offer you employment for the position of <strong>" . ($offer->designation->name ?? 'Role') . "</strong>.</p>
                    <p><strong>Offer Details:</strong></p>
                    <ul>
                        <li><strong>Offer Reference:</strong> {$offer->offer_code}</li>
                        <li><strong>Annual Compensation (CTC):</strong> $" . number_format($offer->offered_annual_ctc, 2) . "</li>
                        <li><strong>Target Joining Date:</strong> " . (optional($offer->joining_date)->format('d M, Y') ?? 'TBD') . "</li>
                    </ul>
                    <p>Please review your offer details and contact HR for confirmation.</p>
                    <br><br>
                    <p>Sincerely,<br><strong>Human Resources Team</strong></p>
                </div>
            ";
        }

        $cleanLetterContent = preg_replace('/\{([^{}\n]*)\}/', '$1', $rawContent);

        $pdfFileName = "Job_Offer_{$offer->offer_code}.pdf";
        $pdfHtml = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <title>Job Offer - {$offer->offer_code}</title>
                <style>
                    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 30px; color: #1e293b; font-size: 13px; line-height: 1.6; }
                    .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 25px; }
                    .header h1 { margin: 0; color: #1e293b; font-size: 22px; font-weight: bold; }
                    .header p { margin: 5px 0 0 0; color: #64748b; font-size: 12px; }
                    .offer-body { margin-bottom: 30px; }
                    .footer { margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 15px; font-size: 11px; color: #94a3b8; text-align: center; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>OFFICIAL JOB OFFER LETTER</h1>
                    <p>Ref: {$offer->offer_code} | Date: " . date('d M, Y') . "</p>
                </div>
                <div class='offer-body'>
                    {$cleanLetterContent}
                </div>
                <div class='footer'>
                    This is an officially generated document. Confidential &copy; " . date('Y') . ".
                </div>
            </body>
            </html>
        ";

        $tempPath = storage_path("app/public/recruitment/offers/{$pdfFileName}");
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        try {
            $pdf = Pdf::loadHTML($pdfHtml)->setPaper('a4', 'portrait');
            file_put_contents($tempPath, $pdf->output());
        } catch (\Throwable $pe) {
            Log::error("Failed to generate offer PDF: " . $pe->getMessage());
        }

        $designationName = $offer->designation->name ?? 'Role';
        $joiningDateStr = optional($offer->joining_date)->format('d M, Y') ?? 'TBD';

        $coverEmailBody = "
            <div style='font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; padding: 15px; background-color: #ffffff; border-radius: 8px;'>
                <p>Dear <strong>{$candidate->full_name}</strong>,</p>
                <p>We are pleased to inform you that your official Job Offer for the position of <strong>{$designationName}</strong> has been generated.</p>
                <p>Please find attached your official <strong>Job Offer Letter PDF document</strong> (<code>{$pdfFileName}</code>) containing all employment details and terms.</p>
                <div style='background-color: #f8fafc; border-left: 4px solid #2563eb; padding: 14px 18px; margin: 20px 0; border-radius: 4px;'>
                    <strong style='color: #1e3a8a; font-size: 14px;'>Offer Summary:</strong>
                    <ul style='margin: 8px 0 0 0; padding-left: 20px; color: #334155;'>
                        <li><strong>Offer Reference:</strong> {$offer->offer_code}</li>
                        <li><strong>Position:</strong> {$designationName}</li>
                        <li><strong>Target Joining Date:</strong> {$joiningDateStr}</li>
                    </ul>
                </div>
                <p>Please review the attached PDF document and let us know if you have any questions or require further clarification.</p>
                <br>
                <p>Best regards,<br><strong>Human Resources Team</strong></p>
            </div>
        ";

        $attachments = [];
        if (file_exists($tempPath)) {
            $attachments[] = [
                'path' => $tempPath,
                'name' => $pdfFileName,
                'mime' => 'application/pdf',
            ];
        }

        try {
            $emailService = app(EmailService::class);
            $emailService->sendEmail([
                'to'         => $validated['to_email'],
                'subject'    => $validated['subject'],
                'body_html'  => $coverEmailBody,
                'account_id' => $validated['account_id'] ?? null,
            ], $attachments);

            $offer->update(['status' => 'sent', 'sent_at' => now()]);

            return ['success' => true, 'message' => "📧 Job Offer PDF document (#{$offer->offer_code}) sent successfully to {$validated['to_email']}!"];
        } catch (\Throwable $e) {
            try {
                Mail::send([], [], function ($msg) use ($validated, $coverEmailBody, $tempPath, $pdfFileName) {
                    $msg->to($validated['to_email'])
                        ->subject($validated['subject'])
                        ->html($coverEmailBody);

                    if (file_exists($tempPath)) {
                        $msg->attach($tempPath, [
                            'as'   => $pdfFileName,
                            'mime' => 'application/pdf',
                        ]);
                    }
                });

                $offer->update(['status' => 'sent', 'sent_at' => now()]);

                return ['success' => true, 'message' => "📧 Job Offer PDF document (#{$offer->offer_code}) sent successfully to {$validated['to_email']}!"];
            } catch (\Throwable $ex) {
                Log::error("Failed to send Job Offer Email: " . $ex->getMessage());
                return ['success' => false, 'message' => "Could not send offer email: " . $ex->getMessage()];
            }
        }
    }

    public function convertToEmployee(JobOffer $offer, ?int $tenantId): array
    {
        if ($offer->converted_employee_id) {
            return ['success' => false, 'message' => "Candidate has already been converted to an Employee!"];
        }

        $application = $offer->application;
        $candidate = $application?->candidate;

        if (!$candidate) {
            return ['success' => false, 'message' => "Candidate information not found."];
        }

        $user = User::where('email', $candidate->email)->first();

        if (!$user) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'name'      => $candidate->full_name,
                'email'     => $candidate->email,
                'password'  => Hash::make('12345678'),
                'role'      => 'employee',
                'is_active' => true,
            ]);
        }

        $candidate->update(['status' => 'hired']);
        if ($application && $application->current_stage !== 'hired') {
            $application->update([
                'current_stage'    => 'hired',
                'stage_updated_at' => now(),
            ]);

            $req = $application->requisition;
            if ($req && $req->vacancies > 0) {
                $req->decrement('vacancies');
                if ($req->fresh()->vacancies === 0) {
                    $req->update(['status' => 'closed']);
                }
            }
        }

        return [
            'success' => true,
            'user' => $user,
            'candidate' => $candidate,
        ];
    }
}
