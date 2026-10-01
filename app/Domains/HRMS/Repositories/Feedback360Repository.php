<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Feedback360Competency;
use App\Domains\HRMS\Models\Feedback360Cycle;
use App\Domains\HRMS\Models\Feedback360Nomination;
use App\Domains\HRMS\Models\Feedback360Participant;
use App\Domains\HRMS\Models\Feedback360Question;
use App\Domains\HRMS\Models\Feedback360Response;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Feedback360Repository implements Feedback360RepositoryInterface
{
    /**
     * Resolve the employee associated with a user.
     */
    protected function resolveEmployee(?User $user, int $tenantId): ?Employee
    {
        if (!$user) {
            return null;
        }

        // Try direct user_id relation or email match
        return Employee::where('tenant_id', $tenantId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('office_email', $user->email)
                  ->orWhere('personal_email', $user->email);
            })
            ->first();
    }

    /**
     * Ensure default standard competencies and questions exist for the tenant.
     */
    protected function ensureDefaultCompetenciesAndQuestions(int $tenantId, ?int $companyId = null): void
    {
        $existingCount = Feedback360Competency::where('tenant_id', $tenantId)->count();
        if ($existingCount > 0) {
            return;
        }

        $defaultCompetencies = [
            [
                'name' => 'Leadership & Strategic Vision',
                'code' => 'COMP-LEAD',
                'category' => 'Leadership',
                'description' => 'Inspires team members, sets clear direction, and aligns everyday work with organizational goals.',
                'questions' => [
                    [
                        'text' => 'Effectively communicates team vision, priorities, and roadmap.',
                        'type' => 'rating_scale',
                    ],
                    [
                        'text' => 'Empowers others, delegates appropriately, and provides coaching & constructive feedback.',
                        'type' => 'rating_scale',
                    ],
                ],
            ],
            [
                'name' => 'Teamwork & Collaboration',
                'code' => 'COMP-TEAM',
                'category' => 'Interpersonal',
                'description' => 'Builds productive cross-functional relationships, actively listens, and supports fellow teammates.',
                'questions' => [
                    [
                        'text' => 'Collaborates openly and constructively with cross-functional team members.',
                        'type' => 'rating_scale',
                    ],
                    [
                        'text' => 'Shares knowledge freely and helps teammates overcome obstacles.',
                        'type' => 'rating_scale',
                    ],
                ],
            ],
            [
                'name' => 'Execution & Technical Excellence',
                'code' => 'COMP-EXEC',
                'category' => 'Functional Excellence',
                'description' => 'Consistently delivers high-quality work on time with strong attention to detail and accountability.',
                'questions' => [
                    [
                        'text' => 'Consistently meets project deadlines with reliable, high-standard deliverables.',
                        'type' => 'rating_scale',
                    ],
                    [
                        'text' => 'Demonstrates deep domain knowledge and solves complex problems effectively.',
                        'type' => 'rating_scale',
                    ],
                ],
            ],
            [
                'name' => 'Communication & Transparency',
                'code' => 'COMP-COMM',
                'category' => 'Interpersonal',
                'description' => 'Communicates clearly, concisely, and with empathy across all channels and levels.',
                'questions' => [
                    [
                        'text' => 'Presents ideas and status updates with clarity, brevity, and empathy.',
                        'type' => 'rating_scale',
                    ],
                    [
                        'text' => 'Actively listens to diverse viewpoints and handles conflict constructively.',
                        'type' => 'rating_scale',
                    ],
                ],
            ],
            [
                'name' => 'Continuous Growth & Core Values',
                'code' => 'COMP-CULT',
                'category' => 'Core Values',
                'description' => 'Embodies company values, demonstrates integrity, and proactively pursues professional development.',
                'questions' => [
                    [
                        'text' => 'Exemplifies core company values, integrity, and ethical conduct in all situations.',
                        'type' => 'rating_scale',
                    ],
                    [
                        'text' => 'Proactively seeks feedback and adapts positively to organizational changes.',
                        'type' => 'rating_scale',
                    ],
                ],
            ],
        ];

        DB::transaction(function () use ($defaultCompetencies, $tenantId, $companyId) {
            $sort = 1;
            foreach ($defaultCompetencies as $cData) {
                $comp = Feedback360Competency::create([
                    'tenant_id'   => $tenantId,
                    'company_id'  => $companyId,
                    'name'        => $cData['name'],
                    'code'        => $cData['code'],
                    'category'    => $cData['category'],
                    'description' => $cData['description'],
                    'weightage'   => 100.00,
                    'is_active'   => true,
                ]);

                foreach ($cData['questions'] as $qData) {
                    Feedback360Question::create([
                        'tenant_id'            => $tenantId,
                        'competency_id'        => $comp->id,
                        'cycle_id'             => null, // global
                        'question_text'        => $qData['text'],
                        'question_type'        => $qData['type'],
                        'target_reviewer_type' => 'all',
                        'is_required'          => true,
                        'sort_order'           => $sort++,
                    ]);
                }
            }

            // Also add standard qualitative open-ended questions
            Feedback360Question::create([
                'tenant_id'            => $tenantId,
                'competency_id'        => null,
                'cycle_id'             => null,
                'question_text'        => 'What are this individual\'s greatest strengths and standout contributions?',
                'description'          => 'Highlight key achievements, qualities, and positive behaviors.',
                'question_type'        => 'text',
                'target_reviewer_type' => 'all',
                'is_required'          => false,
                'sort_order'           => $sort++,
            ]);

            Feedback360Question::create([
                'tenant_id'            => $tenantId,
                'competency_id'        => null,
                'cycle_id'             => null,
                'question_text'        => 'What are 1-2 actionable areas where this individual can grow or improve?',
                'description'          => 'Provide constructive, forward-looking advice for professional development.',
                'question_type'        => 'text',
                'target_reviewer_type' => 'all',
                'is_required'          => false,
                'sort_order'           => $sort++,
            ]);
        });
    }

    /**
     * Get all dataset required for the 360 Feedback Main Hub.
     */
    public function getIndexData(array $inputs, ?User $user, int $tenantId): array
    {
        $this->ensureDefaultCompetenciesAndQuestions($tenantId);

        $currentEmployee = $this->resolveEmployee($user, $tenantId);
        $isHrAdmin = true;
        $isManager = true;

        $activeTab = $inputs['active_tab'] ?? 'cycles';

        // 1. Cycles Query with search, filter, sort
        $cycleQuery = Feedback360Cycle::where('tenant_id', $tenantId)
            ->with(['company', 'creator'])
            ->withCount(['participants', 'nominations']);

        if (!empty($inputs['search'])) {
            $s = trim($inputs['search']);
            $cycleQuery->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if (!empty($inputs['status'])) {
            $cycleQuery->where('status', $inputs['status']);
        }

        if (!empty($inputs['company_id'])) {
            $cycleQuery->where('company_id', $inputs['company_id']);
        }

        $sortField = $inputs['sort_by'] ?? 'created_at';
        $sortDir = $inputs['sort_dir'] ?? 'desc';
        if (in_array($sortField, ['name', 'start_date', 'end_date', 'status', 'created_at'])) {
            $cycleQuery->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $cycleQuery->latest('id');
        }

        $cycles = $cycleQuery->paginate(12)->appends($inputs);

        // 2. Reviews dataset (only active cycles in 'in_progress' or 'review' stages are actionable for reviewers)
        if ($currentEmployee) {
            $myPendingReviews = Feedback360Nomination::where('tenant_id', $tenantId)
                ->where('reviewer_id', $currentEmployee->id)
                ->whereIn('status', ['approved', 'in_progress'])
                ->whereHas('cycle', function($q) {
                    $q->whereIn('status', ['in_progress', 'review']);
                })
                ->with(['cycle', 'employee.department', 'employee.designation'])
                ->latest('id')
                ->get();

            $mySubmittedReviews = Feedback360Nomination::where('tenant_id', $tenantId)
                ->where('reviewer_id', $currentEmployee->id)
                ->where('status', 'completed')
                ->whereHas('cycle')
                ->with(['cycle', 'employee.department', 'employee.designation'])
                ->latest('submitted_at')
                ->take(20)
                ->get();

            $myEvaluations = Feedback360Participant::where('tenant_id', $tenantId)
                ->where('employee_id', $currentEmployee->id)
                ->whereHas('cycle')
                ->with(['cycle', 'manager', 'nominations.reviewer'])
                ->latest('id')
                ->get();
        } else {
            $myPendingReviews = Feedback360Nomination::where('tenant_id', $tenantId)
                ->whereIn('status', ['approved', 'in_progress'])
                ->whereHas('cycle', function($q) {
                    $q->whereIn('status', ['in_progress', 'review']);
                })
                ->with(['cycle', 'employee.department', 'employee.designation'])
                ->latest('id')
                ->take(20)
                ->get();

            $mySubmittedReviews = Feedback360Nomination::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereHas('cycle')
                ->with(['cycle', 'employee.department', 'employee.designation'])
                ->latest('submitted_at')
                ->take(20)
                ->get();

            $myEvaluations = Feedback360Participant::where('tenant_id', $tenantId)
                ->whereHas('cycle')
                ->with(['cycle', 'manager', 'nominations.reviewer'])
                ->latest('id')
                ->take(20)
                ->get();
        }

        // All pending nominations needing approval
        $pendingApprovals = Feedback360Nomination::where('tenant_id', $tenantId)
            ->where('status', 'pending_approval')
            ->whereHas('cycle')
            ->with(['cycle', 'employee.department', 'reviewer.designation', 'nominator'])
            ->latest('id')
            ->get();

        // 3. Competencies & Questions for configuration tab
        $competencies = Feedback360Competency::where('tenant_id', $tenantId)
            ->withCount('questions')
            ->with(['cycle', 'questions'])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $questions = Feedback360Question::where('tenant_id', $tenantId)
            ->with('competency')
            ->orderBy('sort_order')
            ->get();

        // 4. Statistics Dashboard Metrics
        $statistics = [
            'total_cycles'        => Feedback360Cycle::where('tenant_id', $tenantId)->count(),
            'active_cycles'       => Feedback360Cycle::where('tenant_id', $tenantId)->whereIn('status', ['nomination', 'in_progress', 'review'])->count(),
            'total_participants'  => Feedback360Participant::where('tenant_id', $tenantId)->whereHas('cycle')->count(),
            'total_nominations'   => Feedback360Nomination::where('tenant_id', $tenantId)->whereHas('cycle')->count(),
            'completed_reviews'   => Feedback360Nomination::where('tenant_id', $tenantId)->whereHas('cycle')->where('status', 'completed')->count(),
            'pending_reviews'     => Feedback360Nomination::where('tenant_id', $tenantId)->whereHas('cycle')->whereIn('status', ['approved', 'in_progress'])->count(),
            'my_pending_count'    => $myPendingReviews->count(),
            'pending_approvals_count' => $pendingApprovals->count(),
        ];

        if ($statistics['total_nominations'] > 0) {
            $statistics['overall_completion_rate'] = round(($statistics['completed_reviews'] / $statistics['total_nominations']) * 100, 1);
        } else {
            $statistics['overall_completion_rate'] = 0.0;
        }

        // Dropdown references
        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', true)
            ->with(['department', 'designation'])
            ->orderBy('full_name')
            ->get();

        $companies = Company::where('tenant_id', $tenantId)->orderBy('company_name')->get();
        $departments = Department::where('tenant_id', $tenantId)->orderBy('name')->get();

        return compact(
            'cycles',
            'myPendingReviews',
            'mySubmittedReviews',
            'myEvaluations',
            'pendingApprovals',
            'competencies',
            'questions',
            'statistics',
            'employees',
            'companies',
            'departments',
            'currentEmployee',
            'isHrAdmin',
            'isManager',
            'activeTab'
        );
    }

    /**
     * Get details for a specific Feedback Cycle.
     */
    public function getCycleDetailData(int $id, ?User $user, int $tenantId): array
    {
        $cycle = Feedback360Cycle::where('tenant_id', $tenantId)
            ->with(['company', 'creator'])
            ->findOrFail($id);

        $currentEmployee = $this->resolveEmployee($user, $tenantId);
        $isHrAdmin = true;

        $participants = Feedback360Participant::where('tenant_id', $tenantId)
            ->where('cycle_id', $cycle->id)
            ->with([
                'employee.department',
                'employee.designation',
                'manager',
                'nominations.reviewer.designation',
            ])
            ->latest('id')
            ->get();

        $questions = Feedback360Question::where('tenant_id', $tenantId)
            ->where(function ($q) use ($cycle) {
                $q->whereNull('cycle_id')->orWhere('cycle_id', $cycle->id);
            })
            ->with('competency')
            ->orderBy('sort_order')
            ->get();

        $allNominations = Feedback360Nomination::where('tenant_id', $tenantId)
            ->where('cycle_id', $cycle->id)
            ->with(['employee', 'reviewer', 'participant'])
            ->get();

        $stats = [
            'total_participants' => $participants->count(),
            'total_raters'       => $allNominations->count(),
            'completed_raters'   => $allNominations->where('status', 'completed')->count(),
            'pending_raters'     => $allNominations->whereIn('status', ['approved', 'in_progress'])->count(),
            'pending_approvals'  => $allNominations->where('status', 'pending_approval')->count(),
            'published_reports'  => $participants->where('status', 'published')->count(),
        ];

        $stats['completion_rate'] = $stats['total_raters'] > 0
            ? round(($stats['completed_raters'] / $stats['total_raters']) * 100, 1)
            : 0.0;

        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', true)
            ->with(['department', 'designation'])
            ->orderBy('full_name')
            ->get();

        return compact(
            'cycle',
            'participants',
            'questions',
            'allNominations',
            'stats',
            'employees',
            'currentEmployee',
            'isHrAdmin'
        );
    }

    /**
     * Get data required for a reviewer to complete feedback workspace.
     */
    public function getReviewWorkspaceData(int $nominationId, ?User $user, int $tenantId): array
    {
        $nomination = Feedback360Nomination::where('tenant_id', $tenantId)
            ->with([
                'cycle',
                'employee.department',
                'employee.designation',
                'reviewer',
                'responses',
            ])
            ->findOrFail($nominationId);

        $currentEmployee = $this->resolveEmployee($user, $tenantId);

        // Fetch questions applicable for this cycle & reviewer type
        $questions = Feedback360Question::where('tenant_id', $tenantId)
            ->where(function ($q) use ($nomination) {
                $q->whereNull('cycle_id')->orWhere('cycle_id', $nomination->cycle_id);
            })
            ->where(function ($q) use ($nomination) {
                $q->where('target_reviewer_type', 'all')
                  ->orWhere('target_reviewer_type', $nomination->reviewer_type);
            })
            ->with('competency')
            ->orderBy('sort_order')
            ->get();

        // Group questions by competency category
        $competencyGroups = $questions->groupBy(function ($q) {
            return $q->competency ? $q->competency->name : 'General & Open-Ended Feedback';
        });

        // Key existing responses by question_id for easy form binding
        $existingResponses = $nomination->responses->keyBy('question_id');

        return compact(
            'nomination',
            'questions',
            'competencyGroups',
            'existingResponses',
            'currentEmployee'
        );
    }

    /**
     * Get the comprehensive 360-Degree Evaluation Report with Radar chart, gap analysis, and blind spots.
     */
    public function getParticipantReportData(int $participantId, ?User $user, int $tenantId): array
    {
        $participant = Feedback360Participant::where('tenant_id', $tenantId)
            ->with([
                'cycle',
                'employee.department',
                'employee.designation',
                'manager',
                'nominations' => function ($q) {
                    $q->with(['reviewer.designation', 'responses.question.competency']);
                },
            ])
            ->findOrFail($participantId);

        $cycle = $participant->cycle;
        $currentEmployee = $this->resolveEmployee($user, $tenantId);
        $isHrAdmin = true;

        // Fetch all competencies evaluated (Global + this Cycle)
        $cycleId = $participant->cycle_id;
        $competencies = Feedback360Competency::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) use ($cycleId) {
                $q->whereNull('cycle_id')->orWhere('cycle_id', $cycleId);
            })
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Completed nominations
        $completedNoms = $participant->nominations->where('status', 'completed');

        $raterCounts = [
            'self'          => $completedNoms->where('reviewer_type', 'self')->count(),
            'manager'       => $completedNoms->where('reviewer_type', 'manager')->count(),
            'peer'          => $completedNoms->where('reviewer_type', 'peer')->count(),
            'direct_report' => $completedNoms->where('reviewer_type', 'direct_report')->count(),
            'total'         => $completedNoms->count(),
        ];

        // Structure competency breakdown matrix
        $competencyScores = [];
        $radarLabels = [];
        $radarSelf = [];
        $radarManager = [];
        $radarPeers = [];
        $radarDirectReports = [];
        $radarOthersAvg = [];

        $blindSpots = [];
        $hiddenStrengths = [];

        foreach ($competencies as $comp) {
            $scoresByRole = [
                'self'          => [],
                'manager'       => [],
                'peer'          => [],
                'direct_report' => [],
            ];

            foreach ($completedNoms as $nom) {
                $role = $nom->reviewer_type;
                foreach ($nom->responses as $resp) {
                    if ($resp->competency_id === $comp->id && $resp->rating_value !== null) {
                        $scoresByRole[$role][] = (float) $resp->rating_value;
                    }
                }
            }

            $selfAvg = !empty($scoresByRole['self']) ? round(array_sum($scoresByRole['self']) / count($scoresByRole['self']), 2) : null;
            $mgrAvg  = !empty($scoresByRole['manager']) ? round(array_sum($scoresByRole['manager']) / count($scoresByRole['manager']), 2) : null;
            $peerAvg = !empty($scoresByRole['peer']) ? round(array_sum($scoresByRole['peer']) / count($scoresByRole['peer']), 2) : null;
            $drAvg   = !empty($scoresByRole['direct_report']) ? round(array_sum($scoresByRole['direct_report']) / count($scoresByRole['direct_report']), 2) : null;

            // Others Average (combining manager, peers, and direct reports)
            $allOthers = array_merge($scoresByRole['manager'], $scoresByRole['peer'], $scoresByRole['direct_report']);
            $othersAvg = !empty($allOthers) ? round(array_sum($allOthers) / count($allOthers), 2) : null;

            $gap = ($selfAvg !== null && $othersAvg !== null) ? round($selfAvg - $othersAvg, 2) : null;

            $entry = [
                'id'                 => $comp->id,
                'name'               => $comp->name,
                'category'           => $comp->category,
                'description'        => $comp->description,
                'self_score'         => $selfAvg,
                'manager_score'      => $mgrAvg,
                'peer_score'         => $peerAvg,
                'direct_report_score'=> $drAvg,
                'others_avg'         => $othersAvg,
                'gap'                => $gap,
            ];

            $competencyScores[] = $entry;

            // Radar data arrays
            $radarLabels[] = $comp->name;
            $radarSelf[] = $selfAvg ?? 0;
            $radarManager[] = $mgrAvg ?? 0;
            $radarPeers[] = $peerAvg ?? 0;
            $radarDirectReports[] = $drAvg ?? 0;
            $radarOthersAvg[] = $othersAvg ?? 0;

            // Blind Spot & Hidden Strength detection
            if ($gap !== null) {
                if ($gap >= 0.75) {
                    $blindSpots[] = [
                        'competency' => $comp->name,
                        'self_score' => $selfAvg,
                        'others_score' => $othersAvg,
                        'gap' => $gap,
                        'reason' => 'You rated yourself significantly higher than your team and colleagues perceived.',
                    ];
                } elseif ($gap <= -0.75) {
                    $hiddenStrengths[] = [
                        'competency' => $comp->name,
                        'self_score' => $selfAvg,
                        'others_score' => $othersAvg,
                        'gap' => abs($gap),
                        'reason' => 'Your team and colleagues rated you significantly higher than you evaluated yourself.',
                    ];
                }
            }
        }

        // Rank top strengths and growth areas by others' scores
        $sortedByOthers = collect($competencyScores)->filter(fn($c) => $c['others_avg'] !== null)->sortByDesc('others_avg')->values();
        $topStrengths = $sortedByOthers->take(3)->all();
        $growthAreas = $sortedByOthers->reverse()->take(3)->values()->all();

        // Anonymized Qualitative Feedback Responses
        $qualitativeFeedback = [];
        $textQuestions = Feedback360Question::where('tenant_id', $tenantId)
            ->where('question_type', 'text')
            ->where(function ($q) use ($cycle) {
                $q->whereNull('cycle_id')->orWhere('cycle_id', $cycle->id);
            })
            ->orderBy('sort_order')
            ->get();

        foreach ($textQuestions as $tq) {
            $responsesList = [];
            foreach ($completedNoms as $nom) {
                foreach ($nom->responses as $resp) {
                    if ($resp->question_id === $tq->id && !empty(trim($resp->text_response ?? ''))) {
                        $isAnon = ($nom->reviewer_type === 'peer' && $cycle->is_peer_anonymous)
                            || ($nom->reviewer_type === 'direct_report' && $cycle->is_direct_report_anonymous);

                        $responsesList[] = [
                            'reviewer_type' => $nom->reviewer_type,
                            'reviewer_name' => $isAnon ? 'Anonymous Reviewer' : ($nom->reviewer?->full_name ?? 'Reviewer'),
                            'is_anonymous'  => $isAnon,
                            'text'          => $resp->text_response,
                        ];
                    }
                }
            }

            if (!empty($responsesList)) {
                $qualitativeFeedback[] = [
                    'question'  => $tq->question_text,
                    'responses' => $responsesList,
                ];
            }
        }

        $radarData = [
            'labels'        => $radarLabels,
            'self'          => $radarSelf,
            'manager'       => $radarManager,
            'peers'         => $radarPeers,
            'directReports' => $radarDirectReports,
            'othersAvg'     => $radarOthersAvg,
        ];

        $isPublished = ($participant->status === 'published');
        $canPublish = in_array($cycle?->status, ['review', 'completed']);

        return compact(
            'participant',
            'cycle',
            'competencyScores',
            'radarData',
            'blindSpots',
            'hiddenStrengths',
            'topStrengths',
            'growthAreas',
            'qualitativeFeedback',
            'raterCounts',
            'isPublished',
            'canPublish',
            'currentEmployee',
            'isHrAdmin'
        );
    }

    /**
     * Store a new Feedback Cycle.
     */
    public function storeCycle(array $data, int $tenantId, ?User $user = null): Feedback360Cycle
    {
        return DB::transaction(function () use ($data, $tenantId, $user) {
            $code = !empty($data['code']) ? strtoupper(trim($data['code'])) : 'F360-' . date('Y') . '-' . strtoupper(Str::random(4));

            $cycle = Feedback360Cycle::create([
                'tenant_id'                  => $tenantId,
                'company_id'                 => $data['company_id'] ?? null,
                'name'                       => trim($data['name']),
                'code'                       => $code,
                'description'                => $data['description'] ?? null,
                'start_date'                 => $data['start_date'],
                'end_date'                   => $data['end_date'],
                'nomination_deadline'        => $data['nomination_deadline'] ?? null,
                'submission_deadline'        => $data['submission_deadline'] ?? null,
                'status'                     => $data['status'] ?? 'draft',
                'is_peer_anonymous'          => isset($data['is_peer_anonymous']) ? (bool) $data['is_peer_anonymous'] : true,
                'is_direct_report_anonymous' => isset($data['is_direct_report_anonymous']) ? (bool) $data['is_direct_report_anonymous'] : true,
                'min_peer_nominations'       => (int) ($data['min_peer_nominations'] ?? 2),
                'max_peer_nominations'       => (int) ($data['max_peer_nominations'] ?? 5),
                'allow_self_nomination'      => isset($data['allow_self_nomination']) ? (bool) $data['allow_self_nomination'] : true,
                'require_manager_approval'   => isset($data['require_manager_approval']) ? (bool) $data['require_manager_approval'] : true,
                'created_by'                 => $user?->id,
            ]);

            // Auto-enroll participants if employee_ids are provided
            if (!empty($data['employee_ids']) && is_array($data['employee_ids'])) {
                $this->addParticipantsToCycle($cycle->id, $data['employee_ids'], $tenantId, $user);
            }

            return $cycle;
        });
    }

    /**
     * Update an existing Feedback Cycle.
     */
    public function updateCycle(int $id, array $data, int $tenantId, ?User $user = null): Feedback360Cycle
    {
        $cycle = Feedback360Cycle::where('tenant_id', $tenantId)->findOrFail($id);

        $cycle->update([
            'name'                       => trim($data['name'] ?? $cycle->name),
            'company_id'                 => array_key_exists('company_id', $data) ? $data['company_id'] : $cycle->company_id,
            'description'                => $data['description'] ?? $cycle->description,
            'start_date'                 => $data['start_date'] ?? $cycle->start_date,
            'end_date'                   => $data['end_date'] ?? $cycle->end_date,
            'nomination_deadline'        => $data['nomination_deadline'] ?? $cycle->nomination_deadline,
            'submission_deadline'        => $data['submission_deadline'] ?? $cycle->submission_deadline,
            'status'                     => $data['status'] ?? $cycle->status,
            'is_peer_anonymous'          => isset($data['is_peer_anonymous']) ? (bool) $data['is_peer_anonymous'] : $cycle->is_peer_anonymous,
            'is_direct_report_anonymous' => isset($data['is_direct_report_anonymous']) ? (bool) $data['is_direct_report_anonymous'] : $cycle->is_direct_report_anonymous,
            'min_peer_nominations'       => (int) ($data['min_peer_nominations'] ?? $cycle->min_peer_nominations),
            'max_peer_nominations'       => (int) ($data['max_peer_nominations'] ?? $cycle->max_peer_nominations),
            'allow_self_nomination'      => isset($data['allow_self_nomination']) ? (bool) $data['allow_self_nomination'] : $cycle->allow_self_nomination,
            'require_manager_approval'   => isset($data['require_manager_approval']) ? (bool) $data['require_manager_approval'] : $cycle->require_manager_approval,
        ]);

        return $cycle;
    }

    /**
     * Delete a Feedback Cycle and cascade delete associated participants, nominations, and responses.
     */
    public function deleteCycle(int $id, int $tenantId): bool
    {
        return DB::transaction(function () use ($id, $tenantId) {
            $cycle = Feedback360Cycle::where('tenant_id', $tenantId)->findOrFail($id);

            // 1. Find all nomination IDs belonging to this cycle
            $nominationIds = Feedback360Nomination::where('tenant_id', $tenantId)
                ->where('cycle_id', $cycle->id)
                ->pluck('id')
                ->toArray();

            if (!empty($nominationIds)) {
                // Delete responses
                Feedback360Response::where('tenant_id', $tenantId)
                    ->whereIn('nomination_id', $nominationIds)
                    ->delete();

                // Delete nominations
                Feedback360Nomination::where('tenant_id', $tenantId)
                    ->whereIn('id', $nominationIds)
                    ->delete();
            }

            // 2. Delete all participants enrolled in this cycle
            Feedback360Participant::where('tenant_id', $tenantId)
                ->where('cycle_id', $cycle->id)
                ->delete();

            // 3. Delete cycle-specific custom questions if any
            Feedback360Question::where('tenant_id', $tenantId)
                ->where('cycle_id', $cycle->id)
                ->delete();

            // 4. Delete the cycle itself
            return (bool) $cycle->delete();
        });
    }

    /**
     * Launch or transition cycle status.
     */
    public function launchCycle(int $id, string $status, int $tenantId, ?User $user = null): Feedback360Cycle
    {
        $cycle = Feedback360Cycle::where('tenant_id', $tenantId)->findOrFail($id);
        $cycle->status = $status;
        $cycle->save();

        // If launching in_progress, activate pending nominations
        if ($status === 'in_progress') {
            Feedback360Nomination::where('tenant_id', $tenantId)
                ->where('cycle_id', $cycle->id)
                ->where('status', 'approved')
                ->update(['status' => 'approved']);
        }

        return $cycle;
    }

    /**
     * Add target employees (reviewees) to a cycle with automatic Manager and Direct Report resolution.
     */
    public function addParticipantsToCycle(int $cycleId, array $employeeIds, int $tenantId, ?User $user = null): int
    {
        $cycle = Feedback360Cycle::where('tenant_id', $tenantId)->findOrFail($cycleId);
        $count = 0;

        foreach ($employeeIds as $empId) {
            $employee = Employee::where('tenant_id', $tenantId)->find($empId);
            if (!$employee) {
                continue;
            }

            // Create or find participant
            $participant = Feedback360Participant::firstOrCreate([
                'tenant_id'   => $tenantId,
                'cycle_id'    => $cycle->id,
                'employee_id' => $employee->id,
            ], [
                'manager_id' => $employee->reporting_manager_id,
                'status'     => 'nomination_pending',
            ]);

            // 1. Auto-assign Self Review
            Feedback360Nomination::firstOrCreate([
                'tenant_id'      => $tenantId,
                'cycle_id'       => $cycle->id,
                'participant_id' => $participant->id,
                'employee_id'    => $employee->id,
                'reviewer_id'    => $employee->id,
                'reviewer_type'  => 'self',
            ], [
                'status'        => 'approved',
                'is_anonymous'  => false,
                'nominated_by'  => $employee->id,
            ]);

            // 2. Auto-assign Direct Manager Review
            if ($employee->reporting_manager_id) {
                Feedback360Nomination::firstOrCreate([
                    'tenant_id'      => $tenantId,
                    'cycle_id'       => $cycle->id,
                    'participant_id' => $participant->id,
                    'employee_id'    => $employee->id,
                    'reviewer_id'    => $employee->reporting_manager_id,
                    'reviewer_type'  => 'manager',
                ], [
                    'status'        => 'approved',
                    'is_anonymous'  => false,
                    'nominated_by'  => $employee->id,
                ]);
            }

            // 3. Auto-assign Direct Reports (Upward Feedback)
            $directReports = Employee::where('tenant_id', $tenantId)
                ->where('reporting_manager_id', $employee->id)
                ->where('status', true)
                ->get();

            foreach ($directReports as $dr) {
                Feedback360Nomination::firstOrCreate([
                    'tenant_id'      => $tenantId,
                    'cycle_id'       => $cycle->id,
                    'participant_id' => $participant->id,
                    'employee_id'    => $employee->id,
                    'reviewer_id'    => $dr->id,
                    'reviewer_type'  => 'direct_report',
                ], [
                    'status'        => 'approved',
                    'is_anonymous'  => $cycle->is_direct_report_anonymous,
                    'nominated_by'  => $dr->id,
                ]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Nominate peer reviewers for a participant.
     */
    public function nominatePeers(int $participantId, array $peerIds, int $tenantId, ?User $user = null): array
    {
        $participant = Feedback360Participant::where('tenant_id', $tenantId)->findOrFail($participantId);
        $cycle = $participant->cycle;

        if (!$cycle) {
            throw new Exception("Feedback cycle not found for this participant.");
        }

        if ($cycle->nomination_deadline && now()->startOfDay()->gt(Carbon::parse($cycle->nomination_deadline)->endOfDay())) {
            throw new Exception("The nomination deadline for this cycle passed on " . Carbon::parse($cycle->nomination_deadline)->format('d M Y') . ".");
        }

        if ($cycle->status === 'draft') {
            throw new Exception("Nominations cannot be submitted while the cycle is in Draft. Please wait until HR launches the Nomination phase.");
        }

        if (in_array($cycle->status, ['completed', 'closed'])) {
            throw new Exception("This feedback cycle is closed. Nominations cannot be submitted.");
        }

        $created = [];
        $initialStatus = $cycle->require_manager_approval ? 'pending_approval' : 'approved';

        foreach ($peerIds as $peerId) {
            if ($peerId == $participant->employee_id) {
                continue; // Can't peer review self
            }

            $nom = Feedback360Nomination::firstOrCreate([
                'tenant_id'      => $tenantId,
                'cycle_id'       => $cycle->id,
                'participant_id' => $participant->id,
                'employee_id'    => $participant->employee_id,
                'reviewer_id'    => $peerId,
                'reviewer_type'  => 'peer',
            ], [
                'status'        => $initialStatus,
                'is_anonymous'  => (bool) $cycle->is_peer_anonymous,
                'nominated_by'  => $participant->employee_id,
            ]);

            $created[] = $nom;
        }

        return $created;
    }

    /**
     * Approve or reject a peer nomination.
     */
    public function approveNomination(int $nominationId, bool $approved, ?string $reason, int $tenantId, ?User $user = null): Feedback360Nomination
    {
        $nomination = Feedback360Nomination::where('tenant_id', $tenantId)->findOrFail($nominationId);

        $nomination->status = $approved ? 'approved' : 'rejected';
        $nomination->approved_by = $user?->id;
        if (!$approved && $reason) {
            $nomination->rejection_reason = $reason;
        }
        $nomination->save();

        return $nomination;
    }

    /**
     * Batch approve or reject peer nominations.
     */
    public function batchApproveNominations(array $nominationIds, bool $approved, ?string $reason, int $tenantId, ?User $user = null): int
    {
        if (empty($nominationIds)) {
            return 0;
        }

        $updateData = [
            'status' => $approved ? 'approved' : 'rejected',
            'approved_by' => $user?->id,
            'updated_at' => Carbon::now(),
        ];

        if (!$approved && $reason) {
            $updateData['rejection_reason'] = $reason;
        }

        return Feedback360Nomination::where('tenant_id', $tenantId)
            ->whereIn('id', $nominationIds)
            ->where('status', 'pending_approval')
            ->update($updateData);
    }

    /**
     * Submit feedback answers and scores for a nomination.
     */
    public function submitReview(int $nominationId, array $data, int $tenantId, ?User $user = null): Feedback360Nomination
    {
        return DB::transaction(function () use ($nominationId, $data, $tenantId) {
            $nomination = Feedback360Nomination::where('tenant_id', $tenantId)->findOrFail($nominationId);
            $cycle = $nomination->cycle;

            if ($cycle && in_array($cycle->status, ['draft', 'nomination'])) {
                $phaseLabel = $cycle->status === 'draft' ? 'Draft' : 'Nomination';
                throw new Exception("This feedback cycle is currently in {$phaseLabel} phase. Review submissions will open when the cycle is launched In Progress.");
            }

            if ($cycle && $cycle->status === 'closed') {
                throw new Exception("This feedback cycle is closed and no longer accepting reviews.");
            }

            // Save rating & text responses
            if (!empty($data['responses']) && is_array($data['responses'])) {
                foreach ($data['responses'] as $qId => $resp) {
                    $question = Feedback360Question::find($qId);
                    if (!$question) {
                        continue;
                    }

                    $ratingVal = isset($resp['rating']) && is_numeric($resp['rating']) ? (float) $resp['rating'] : null;
                    $textVal   = isset($resp['text']) ? trim($resp['text']) : null;

                    Feedback360Response::updateOrCreate([
                        'tenant_id'     => $tenantId,
                        'nomination_id' => $nomination->id,
                        'question_id'   => $question->id,
                    ], [
                        'competency_id' => $question->competency_id,
                        'rating_value'  => $ratingVal,
                        'text_response' => $textVal,
                    ]);
                }
            }

            $isDraft = ($data['submit_action'] ?? 'submit') === 'draft';

            if ($isDraft) {
                $nomination->status = 'in_progress';
                $nomination->save();
            } else {
                $nomination->status = 'completed';
                $nomination->submitted_at = Carbon::now();
                $nomination->save();

                // Recalculate participant's overall scores
                $participant = $nomination->participant;
                if ($participant) {
                    if ($participant->status === 'nomination_pending') {
                        $participant->status = 'in_progress';
                    }
                    $participant->recalculateScores();
                }
            }

            return $nomination;
        });
    }

    /**
     * Calibrate and publish the 360 report for an employee.
     */
    public function publishReport(int $participantId, array $data, int $tenantId, ?User $user = null): Feedback360Participant
    {
        $participant = Feedback360Participant::where('tenant_id', $tenantId)->findOrFail($participantId);

        if (!in_array($participant->cycle?->status, ['review', 'completed'])) {
            throw new Exception("360 Assessment Reports can only be published once the cycle reaches the Manager Review stage.");
        }

        $participant->manager_summary  = $data['manager_summary'] ?? $participant->manager_summary;
        $participant->development_plan = $data['development_plan'] ?? $participant->development_plan;
        $participant->status           = 'published';
        $participant->published_at     = Carbon::now();
        $participant->published_by     = $user?->id;
        $participant->save();

        return $participant;
    }

    /**
     * Send bulk reminders to all reviewers with pending submissions in a cycle.
     */
    public function bulkRemind(int $cycleId, int $tenantId, ?User $user = null): int
    {
        $pending = Feedback360Nomination::where('tenant_id', $tenantId)
            ->where('cycle_id', $cycleId)
            ->whereIn('status', ['approved', 'in_progress'])
            ->get();

        // In production, triggers system notification / email dispatch
        return $pending->count();
    }

    /**
     * Store a Competency in the library.
     */
    public function storeCompetency(array $data, int $tenantId, ?User $user = null): Feedback360Competency
    {
        return Feedback360Competency::create([
            'tenant_id'   => $tenantId,
            'company_id'  => $data['company_id'] ?? null,
            'cycle_id'    => !empty($data['cycle_id']) ? (int) $data['cycle_id'] : null,
            'name'        => trim($data['name']),
            'code'        => !empty($data['code']) ? strtoupper(trim($data['code'])) : 'COMP-' . strtoupper(Str::random(4)),
            'category'    => trim($data['category'] ?? 'Core Competencies'),
            'description' => $data['description'] ?? null,
            'weightage'   => (float) ($data['weightage'] ?? 100.00),
            'is_active'   => true,
        ]);
    }

    /**
     * Delete a Competency.
     */
    public function deleteCompetency(int $id, int $tenantId): bool
    {
        $comp = Feedback360Competency::where('tenant_id', $tenantId)->findOrFail($id);
        return (bool) $comp->delete();
    }

    /**
     * Store a Question in the bank.
     */
    public function storeQuestion(array $data, int $tenantId, ?User $user = null): Feedback360Question
    {
        $maxOrder = Feedback360Question::where('tenant_id', $tenantId)->max('sort_order') ?? 0;

        return Feedback360Question::create([
            'tenant_id'            => $tenantId,
            'competency_id'        => $data['competency_id'] ?? null,
            'cycle_id'             => $data['cycle_id'] ?? null,
            'question_text'        => trim($data['question_text']),
            'description'          => $data['description'] ?? null,
            'question_type'        => $data['question_type'] ?? 'rating_scale',
            'target_reviewer_type' => $data['target_reviewer_type'] ?? 'all',
            'is_required'          => isset($data['is_required']) ? (bool) $data['is_required'] : true,
            'sort_order'           => (int) ($data['sort_order'] ?? ($maxOrder + 1)),
        ]);
    }

    /**
     * Delete a Question.
     */
    public function deleteQuestion(int $id, int $tenantId): bool
    {
        $question = Feedback360Question::where('tenant_id', $tenantId)->findOrFail($id);
        return (bool) $question->delete();
    }
}
