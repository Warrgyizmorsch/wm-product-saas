<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Models\ExpensePolicy;
use App\Domains\HRMS\Models\TravelRequest;
use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\ExpenseReport;
use App\Domains\HRMS\Models\ExpenseClaim;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TravelExpenseApiController extends Controller
{
    /**
     * Helper for standardized success JSON response.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Helper for standardized error JSON response.
     */
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

    /**
     * Null-safe authorization check.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();
            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access.', 401);
            }
        }
        return null;
    }

    /**
     * Resolves the authenticated user's employee model and admin status.
     *
     * @return array{0: ?\App\Domains\HRMS\Models\Employee, 1: bool}
     */
    private function resolveEmployeeContext(): array
    {
        $user = auth()->user();
        $employee = null;
        if ($user && $user->email) {
            $employee = Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
        }
        $isAdmin = ($user->role ?? '') === 'admin' || ($user && method_exists($user, 'hasRole') && $user->hasRole('admin'));
        return [$employee, $isAdmin];
    }

    /**
     * GET /api/hrms/travel-expense
     * Concise summary metrics for Travel & Expense dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        [$employee, $isAdmin] = $this->resolveEmployeeContext();

        $travelQuery = TravelRequest::where('tenant_id', $tenantId);
        $advanceQuery = CashAdvance::where('tenant_id', $tenantId);
        $reportQuery = ExpenseReport::where('tenant_id', $tenantId);

        if (!$isAdmin && $employee) {
            $travelQuery->where('employee_id', $employee->id);
            $advanceQuery->where('employee_id', $employee->id);
            $reportQuery->where('employee_id', $employee->id);
        }

        $company = \App\Domains\HRMS\Models\Company::first();
        $currencyCode = $company?->currency ?? 'USD';
        $currencySymbol = self::currencySymbol($currencyCode);

        $data = [
            'currency_code'   => $currencyCode,
            'currency_symbol' => $currencySymbol,
            'travel_requests' => [
                'total'    => (clone $travelQuery)->count(),
                'pending'  => (clone $travelQuery)->where('status', 'pending')->count(),
                'approved' => (clone $travelQuery)->where('status', 'approved')->count(),
                'rejected' => (clone $travelQuery)->where('status', 'rejected')->count(),
            ],
            'cash_advances' => [
                'total'     => (clone $advanceQuery)->count(),
                'pending'   => (clone $advanceQuery)->where('status', 'pending')->count(),
                'approved'  => (clone $advanceQuery)->where('status', 'approved')->count(),
                'disbursed' => (clone $advanceQuery)->where('status', 'disbursed')->count(),
                'settled'   => (clone $advanceQuery)->where('status', 'settled')->count(),
            ],
            'expense_reports' => [
                'total'     => (clone $reportQuery)->count(),
                'draft'     => (clone $reportQuery)->where('status', 'draft')->count(),
                'submitted' => (clone $reportQuery)->where('status', 'submitted')->count(),
                'approved'  => (clone $reportQuery)->where('status', 'approved')->count(),
                'paid'      => (clone $reportQuery)->where('status', 'paid')->count(),
                'rejected'  => (clone $reportQuery)->where('status', 'rejected')->count(),
                'total_claimed_amount' => (float)(clone $reportQuery)->whereIn('status', ['approved', 'paid'])->sum('total_amount'),
            ],
        ];

        return $this->sendSuccess($data, 'Travel & Expense summary loaded successfully.');
    }

    /**
     * Formats a LengthAwarePaginator into a clean, concise response structure.
     */
    private function formatPaginatedData(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator, callable $transformItem): array
    {
        return [
            'items' => collect($paginator->items())->map($transformItem)->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * Transforms a TravelRequest model into a clean array structure.
     */
    private function transformTravelRequest(TravelRequest $tr, bool $detailed = false): array
    {
        $data = [
            'id'               => $tr->id,
            'purpose'          => $tr->purpose,
            'destination'      => $tr->destination,
            'start_date'       => $tr->start_date ? $tr->start_date->format('Y-m-d') : null,
            'end_date'         => $tr->end_date ? $tr->end_date->format('Y-m-d') : null,
            'estimated_budget' => floatval($tr->estimated_budget),
            'approved_budget'  => $tr->approved_budget !== null ? floatval($tr->approved_budget) : null,
            'status'           => $tr->status,
            'created_at'       => $tr->created_at?->toDateTimeString(),
        ];

        if ($tr->relationLoaded('employee') && $tr->employee) {
            $data['employee'] = [
                'id'            => $tr->employee->id,
                'employee_code' => $tr->employee->employee_id,
                'full_name'     => $tr->employee->full_name,
                'photo'         => $tr->employee->photo,
            ];
        }

        if ($tr->relationLoaded('cashAdvances')) {
            $data['cash_advances'] = $tr->cashAdvances->map(fn($ca) => [
                'id'              => $ca->id,
                'amount'          => floatval($ca->amount),
                'approved_amount' => $ca->approved_amount !== null ? floatval($ca->approved_amount) : null,
                'status'          => $ca->status,
            ])->values();
        }

        if ($detailed && $tr->relationLoaded('expenseReports')) {
            $data['expense_reports'] = $tr->expenseReports->map(fn($er) => [
                'id'           => $er->id,
                'title'        => $er->title,
                'total_amount' => floatval($er->total_amount),
                'status'       => $er->status,
                'created_at'   => $er->created_at?->toDateTimeString(),
            ])->values();
        }

        return $data;
    }

    /**
     * Transforms a CashAdvance model into a clean array structure.
     */
    private function transformCashAdvance(CashAdvance $ca, bool $detailed = false): array
    {
        $data = [
            'id'              => $ca->id,
            'amount'          => floatval($ca->amount),
            'approved_amount' => $ca->approved_amount !== null ? floatval($ca->approved_amount) : null,
            'purpose'         => $ca->purpose,
            'status'          => $ca->status,
            'created_at'      => $ca->created_at?->toDateTimeString(),
        ];

        if ($ca->relationLoaded('employee') && $ca->employee) {
            $data['employee'] = [
                'id'            => $ca->employee->id,
                'employee_code' => $ca->employee->employee_id,
                'full_name'     => $ca->employee->full_name,
                'photo'         => $ca->employee->photo,
            ];
        }

        if ($ca->relationLoaded('travelRequest') && $ca->travelRequest) {
            $tr = $ca->travelRequest;
            $data['travel_request'] = [
                'id'               => $tr->id,
                'purpose'          => $tr->purpose,
                'destination'      => $tr->destination,
                'start_date'       => $tr->start_date ? $tr->start_date->format('Y-m-d') : null,
                'end_date'         => $tr->end_date ? $tr->end_date->format('Y-m-d') : null,
                'estimated_budget' => floatval($tr->estimated_budget),
                'status'           => $tr->status,
            ];
        }

        if ($detailed && $ca->relationLoaded('expenseReport') && $ca->expenseReport) {
            $er = $ca->expenseReport;
            $data['expense_report'] = [
                'id'           => $er->id,
                'title'        => $er->title,
                'total_amount' => floatval($er->total_amount),
                'status'       => $er->status,
            ];
        }

        return $data;
    }

    /**
     * Transforms an ExpenseReport model into a clean array structure.
     */
    private function transformExpenseReport(ExpenseReport $er, bool $detailed = false): array
    {
        $data = [
            'id'                         => $er->id,
            'title'                      => $er->title,
            'total_amount'               => floatval($er->total_amount),
            'advance_adjusted'           => floatval($er->advance_adjusted),
            'net_reimbursement'          => floatval($er->net_reimbursement),
            'approved_amount'            => $er->approved_amount !== null ? floatval($er->approved_amount) : null,
            'approved_net_reimbursement' => $er->approved_net_reimbursement !== null ? floatval($er->approved_net_reimbursement) : null,
            'status'                     => $er->status,
            'payout_channel'             => $er->payout_channel,
            'created_at'                 => $er->created_at?->toDateTimeString(),
        ];

        if ($er->claims_count !== null) {
            $data['claims_count'] = (int) $er->claims_count;
        }

        if ($er->relationLoaded('employee') && $er->employee) {
            $data['employee'] = [
                'id'            => $er->employee->id,
                'employee_code' => $er->employee->employee_id,
                'full_name'     => $er->employee->full_name,
                'photo'         => $er->employee->photo,
            ];
        }

        if ($er->relationLoaded('travelRequest') && $er->travelRequest) {
            $tr = $er->travelRequest;
            $data['travel_request'] = [
                'id'          => $tr->id,
                'purpose'     => $tr->purpose,
                'destination' => $tr->destination,
                'status'      => $tr->status,
            ];
        }

        if ($er->relationLoaded('cashAdvance') && $er->cashAdvance) {
            $ca = $er->cashAdvance;
            $data['cash_advance'] = [
                'id'              => $ca->id,
                'amount'          => floatval($ca->amount),
                'approved_amount' => $ca->approved_amount !== null ? floatval($ca->approved_amount) : null,
                'status'          => $ca->status,
            ];
        }

        if ($detailed && $er->relationLoaded('claims')) {
            $data['claims'] = $er->claims->map(fn($c) => [
                'id'                  => $c->id,
                'expense_category_id' => $c->expense_category_id,
                'category_name'       => $c->category?->name,
                'category_code'       => $c->category?->code,
                'expense_date'        => $c->expense_date ? $c->expense_date->format('Y-m-d') : null,
                'amount'              => floatval($c->amount),
                'tax_amount'          => floatval($c->tax_amount),
                'merchant'            => $c->merchant,
                'description'         => $c->description,
                'receipt_path'        => $c->receipt_path,
            ])->values();
        }

        return $data;
    }

    // ==========================================
    // 1. TRAVEL REQUEST INDIVIDUAL APIs
    // ==========================================

    /**
     * GET /api/hrms/travel-expense/travel
     * List paginated travel requests with filtering.
     */
    public function indexTravelRequests(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        [$employee, $isAdmin] = $this->resolveEmployeeContext();

        $query = TravelRequest::where('tenant_id', $tenantId)
            ->with([
                'employee:id,employee_id,full_name,photo',
                'cashAdvances:id,travel_request_id,amount,approved_amount,status',
            ]);

        if ($request->filled('employee_id')) {
            if ($isAdmin || ($employee && (int)$request->employee_id === (int)$employee->id)) {
                $query->where('employee_id', $request->employee_id);
            } else {
                $query->where('employee_id', $employee?->id ?? 0);
            }
        } elseif (!$isAdmin && $employee) {
            $query->where('employee_id', $employee->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('purpose', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($sq) use ($search) {
                      $sq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('start_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('end_date', '<=', $request->to_date);
        }

        $perPage = min($request->integer('per_page', 10), 100);
        $travelRequests = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formatted = $this->formatPaginatedData($travelRequests, fn($tr) => $this->transformTravelRequest($tr));

        return $this->sendSuccess($formatted, 'Travel requests retrieved successfully.');
    }

    /**
     * GET /api/hrms/travel-expense/travel/{id}
     * Get single travel request details.
     */
    public function showTravelRequest(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        [$employee, $isAdmin] = $this->resolveEmployeeContext();

        $travelRequest = TravelRequest::where('tenant_id', $tenantId)
            ->with([
                'employee:id,employee_id,full_name,office_email,personal_email,photo,job_title',
                'cashAdvances:id,travel_request_id,amount,approved_amount,purpose,status,created_at',
                'expenseReports:id,travel_request_id,title,total_amount,status,created_at',
            ])
            ->find($id);

        if (!$travelRequest) {
            return $this->sendError("Travel request with ID '{$id}' not found.", 404);
        }

        if (!$isAdmin && $employee && (int)$travelRequest->employee_id !== (int)$employee->id) {
            return $this->sendError('Unauthorized access to this travel request.', 403);
        }

        return $this->sendSuccess($this->transformTravelRequest($travelRequest, true), 'Travel request details loaded successfully.');
    }

    // ==========================================
    // 2. CASH ADVANCE INDIVIDUAL APIs
    // ==========================================

    /**
     * GET /api/hrms/travel-expense/advance
     * List paginated cash advances with filtering.
     */
    public function indexCashAdvances(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        [$employee, $isAdmin] = $this->resolveEmployeeContext();

        $query = CashAdvance::where('tenant_id', $tenantId)
            ->with([
                'employee:id,employee_id,full_name,photo',
                'travelRequest:id,purpose,destination,start_date,end_date,estimated_budget,status',
                'expenseReport:id,title,status',
            ]);

        if ($request->filled('employee_id')) {
            if ($isAdmin || ($employee && (int)$request->employee_id === (int)$employee->id)) {
                $query->where('employee_id', $request->employee_id);
            } else {
                $query->where('employee_id', $employee?->id ?? 0);
            }
        } elseif (!$isAdmin && $employee) {
            $query->where('employee_id', $employee->id);
        }

        if ($request->filled('travel_request_id')) {
            $query->where('travel_request_id', $request->travel_request_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('purpose', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($sq) use ($search) {
                      $sq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min($request->integer('per_page', 10), 100);
        $cashAdvances = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formatted = $this->formatPaginatedData($cashAdvances, fn($ca) => $this->transformCashAdvance($ca));

        return $this->sendSuccess($formatted, 'Cash advances retrieved successfully.');
    }

    /**
     * GET /api/hrms/travel-expense/advance/{id}
     * Get single cash advance details.
     */
    public function showCashAdvance(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        [$employee, $isAdmin] = $this->resolveEmployeeContext();

        $cashAdvance = CashAdvance::where('tenant_id', $tenantId)
            ->with([
                'employee:id,employee_id,full_name,office_email,personal_email,photo,job_title',
                'travelRequest:id,purpose,destination,start_date,end_date,estimated_budget,approved_budget,status',
                'expenseReport:id,title,total_amount,status',
            ])
            ->find($id);

        if (!$cashAdvance) {
            return $this->sendError("Cash advance with ID '{$id}' not found.", 404);
        }

        if (!$isAdmin && $employee && (int)$cashAdvance->employee_id !== (int)$employee->id) {
            return $this->sendError('Unauthorized access to this cash advance.', 403);
        }

        return $this->sendSuccess($this->transformCashAdvance($cashAdvance, true), 'Cash advance details loaded successfully.');
    }

    // ==========================================
    // 3. EXPENSE REPORT INDIVIDUAL APIs
    // ==========================================

    /**
     * GET /api/hrms/travel-expense/report
     * List paginated expense reports with filtering.
     */
    public function indexExpenseReports(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        [$employee, $isAdmin] = $this->resolveEmployeeContext();

        $query = ExpenseReport::where('tenant_id', $tenantId)
            ->with([
                'employee:id,employee_id,full_name,photo',
                'travelRequest:id,purpose,destination,status',
                'cashAdvance:id,expense_report_id,amount,approved_amount,status',
            ])
            ->withCount('claims');

        if ($request->filled('employee_id')) {
            if ($isAdmin || ($employee && (int)$request->employee_id === (int)$employee->id)) {
                $query->where('employee_id', $request->employee_id);
            } else {
                $query->where('employee_id', $employee?->id ?? 0);
            }
        } elseif (!$isAdmin && $employee) {
            $query->where('employee_id', $employee->id);
        }

        if ($request->filled('travel_request_id')) {
            $query->where('travel_request_id', $request->travel_request_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($sq) use ($search) {
                      $sq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min($request->integer('per_page', 10), 100);
        $expenseReports = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formatted = $this->formatPaginatedData($expenseReports, fn($er) => $this->transformExpenseReport($er));

        return $this->sendSuccess($formatted, 'Expense reports retrieved successfully.');
    }

    /**
     * GET /api/hrms/travel-expense/report/{id}
     * Get single expense report details with claims and category.
     */
    public function showExpenseReport(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        [$employee, $isAdmin] = $this->resolveEmployeeContext();

        $expenseReport = ExpenseReport::where('tenant_id', $tenantId)
            ->with([
                'employee:id,employee_id,full_name,office_email,personal_email,photo,job_title',
                'travelRequest:id,purpose,destination,start_date,end_date,status',
                'cashAdvance:id,expense_report_id,amount,approved_amount,purpose,status',
                'claims.category:id,name,code',
            ])
            ->find($id);

        if (!$expenseReport) {
            return $this->sendError("Expense report with ID '{$id}' not found.", 404);
        }

        if (!$isAdmin && $employee && (int)$expenseReport->employee_id !== (int)$employee->id) {
            return $this->sendError('Unauthorized access to this expense report.', 403);
        }

        return $this->sendSuccess($this->transformExpenseReport($expenseReport, true), 'Expense report details loaded successfully.');
    }

    /**
     * Returns the display symbol for a given ISO 4217 currency code.
     */
    public static function currencySymbol(string $code): string
    {
        $map = [
            'USD' => '$',   'EUR' => '€',   'GBP' => '£',   'INR' => '₹',
            'JPY' => '¥',   'CNY' => '¥',   'AUD' => 'A$',  'CAD' => 'CA$',
            'CHF' => 'Fr',  'SGD' => 'S$',  'AED' => 'د.إ', 'SAR' => '﷼',
            'MYR' => 'RM',  'IDR' => 'Rp',  'THB' => '฿',   'PHP' => '₱',
            'BDT' => '৳',   'PKR' => '₨',   'LKR' => '₨',   'NPR' => '₨',
            'BRL' => 'R$',  'MXN' => '$',   'ZAR' => 'R',   'NGN' => '₦',
            'KES' => 'KSh', 'GHS' => '₵',   'EGP' => '£',   'QAR' => '﷼',
            'KWD' => 'KD',  'BHD' => '.د.ب','OMR' => '﷼',   'HKD' => 'HK$',
            'NZD' => 'NZ$', 'SEK' => 'kr',  'NOK' => 'kr',  'DKK' => 'kr',
        ];

        return $map[strtoupper($code)] ?? strtoupper($code);
    }

    /**
     * POST /api/hrms/travel-expense/travel/store
     */
    public function storeTravelRequest(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'employee_id'      => 'required|exists:employees,id',
            'purpose'          => 'required|string|max:255',
            'destination'      => 'required|string|max:255',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'estimated_budget' => 'required|numeric|min:0',
            'request_advance'  => 'nullable|boolean',
            'advance_amount'   => 'nullable|required_if:request_advance,1|numeric|min:1',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'pending';

        $travelRequest = DB::transaction(function () use ($validated, $request) {
            $tr = TravelRequest::create([
                'tenant_id'        => $validated['tenant_id'],
                'employee_id'      => $validated['employee_id'],
                'purpose'          => $validated['purpose'],
                'destination'      => $validated['destination'],
                'start_date'       => $validated['start_date'],
                'end_date'         => $validated['end_date'],
                'estimated_budget' => $validated['estimated_budget'],
                'status'           => $validated['status'],
            ]);

            if ($request->boolean('request_advance')) {
                CashAdvance::create([
                    'tenant_id'         => $validated['tenant_id'],
                    'employee_id'       => $validated['employee_id'],
                    'travel_request_id' => $tr->id,
                    'amount'            => $validated['advance_amount'],
                    'purpose'           => $validated['purpose'],
                    'status'            => 'pending',
                ]);
            }

            return $tr;
        });

        return $this->sendSuccess($travelRequest->load('cashAdvances'), 'Travel request submitted successfully.', 201);
    }

    /**
     * POST /api/hrms/travel-expense/travel/{travelRequest}/approve
     */
    public function approveTravelRequest(Request $request, TravelRequest $travelRequest): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $approvedBudget = $request->input('approved_budget', $travelRequest->estimated_budget);
        
        $travelRequest->update([
            'status' => 'approved',
            'approved_budget' => $approvedBudget
        ]);

        return $this->sendSuccess($travelRequest, 'Travel request approved successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/travel/{travelRequest}/reject
     */
    public function rejectTravelRequest(TravelRequest $travelRequest): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $travelRequest->update(['status' => 'rejected']);
        return $this->sendSuccess($travelRequest, 'Travel request rejected successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/advance/store
     */
    public function storeCashAdvance(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'travel_request_id' => 'nullable|exists:travel_requests,id',
            'amount'            => 'required|numeric|min:1',
            'purpose'           => 'required|string|max:255',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'pending';

        $cashAdvance = CashAdvance::create($validated);

        return $this->sendSuccess($cashAdvance, 'Cash advance request submitted successfully.', 201);
    }

    /**
     * POST /api/hrms/travel-expense/advance/{cashAdvance}/approve
     */
    public function approveCashAdvance(Request $request, CashAdvance $cashAdvance): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $approvedAmount = $request->input('approved_amount', $cashAdvance->amount);
        
        $cashAdvance->update([
            'status' => 'approved',
            'approved_amount' => $approvedAmount
        ]);

        return $this->sendSuccess($cashAdvance, 'Cash advance request approved successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/advance/{cashAdvance}/disburse
     */
    public function disburseCashAdvance(CashAdvance $cashAdvance): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $cashAdvance->update(['status' => 'disbursed']);

        $amount = floatval($cashAdvance->approved_amount ?? $cashAdvance->amount);
        if ($amount > 0) {
            $advancesAccount = $this->getOrCreateAccount($tenantId, '1400', 'Loans & Advances', 'asset', 'debit', 'loans_advances');
            $bankAccount = $this->getOrCreateAccount($tenantId, '1020', 'Bank Account', 'asset', 'debit', 'current_asset');

            $lines = [
                [
                    'chart_of_account_id' => $advancesAccount->id,
                    'debit' => $amount,
                    'credit' => 0.00,
                    'description' => "Disbursement of Advance: " . $cashAdvance->purpose
                ],
                [
                    'chart_of_account_id' => $bankAccount->id,
                    'debit' => 0.00,
                    'credit' => $amount,
                    'description' => "Disbursement of Advance: " . $cashAdvance->purpose
                ]
            ];

            try {
                $journalService = app(\App\Domains\Accounting\Services\JournalService::class);
                $journalService->post($lines, [
                    'tenant_id' => $tenantId,
                    'journal_date' => now(),
                    'source' => 'expense',
                    'reference_type' => 'CashAdvance',
                    'reference_id' => $cashAdvance->id,
                    'memo' => "Disbursed Cash Advance: " . $cashAdvance->purpose,
                    'posted_by' => auth()->id(),
                ]);
            } catch (\Exception $e) {
                Log::error("Disbursement Journal Posting Failed: " . $e->getMessage());
            }
        }

        return $this->sendSuccess($cashAdvance, 'Cash advance disbursed successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/advance/{cashAdvance}/reject
     */
    public function rejectCashAdvance(CashAdvance $cashAdvance): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $cashAdvance->update(['status' => 'rejected']);
        return $this->sendSuccess($cashAdvance, 'Cash advance request rejected successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/report/store
     */
    public function storeExpenseReport(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'travel_request_id' => 'nullable|exists:travel_requests,id',
            'cash_advance_id'   => 'nullable|exists:cash_advances,id',
            'title'             => 'required|string|max:255',
            'claims'            => 'required|array|min:1',
            'claims.*.category_id' => 'required|exists:expense_categories,id',
            'claims.*.date'        => 'required|date',
            'claims.*.amount'      => 'required|numeric|min:0.01',
            'claims.*.tax'         => 'nullable|numeric|min:0',
            'claims.*.merchant'    => 'nullable|string|max:255',
            'claims.*.desc'        => 'nullable|string|max:1000',
            'claims.*.receipt'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $employee = Employee::find($validated['employee_id']);
        if ($employee) {
            $policy = ExpensePolicy::where('tenant_id', $tenantId)
                ->where('status', true)
                ->where(function($q) use ($employee) {
                    $q->where('designation_id', $employee->designation_id)
                      ->orWhere('department_id', $employee->department_id)
                      ->orWhere('company_id', $employee->company_id)
                      ->orWhere(function($sub) {
                          $sub->whereNull('designation_id')
                              ->whereNull('department_id')
                              ->whereNull('company_id');
                      });
                })
                ->orderByRaw('CASE 
                    WHEN designation_id IS NOT NULL THEN 1 
                    WHEN department_id IS NOT NULL THEN 2 
                    WHEN company_id IS NOT NULL THEN 3 
                    ELSE 4 
                END')
                ->first();

            if ($policy) {
                $errorMessages = [];
                foreach ($validated['claims'] as $index => $c) {
                    $rule = $policy->rules()->where('expense_category_id', $c['category_id'])->first();
                    if ($rule) {
                        if ($rule->max_limit_per_claim && floatval($c['amount']) > floatval($rule->max_limit_per_claim)) {
                            $errorMessages["claims.{$index}.amount"] = "This claim amount exceeds the policy limit of ₹" . number_format($rule->max_limit_per_claim, 2) . " for category " . $rule->category->name . ".";
                        }
                        
                        $needsReceipt = false;
                        if ($rule->receipt_required) {
                            $needsReceipt = true;
                        } elseif ($rule->receipt_required_threshold && floatval($c['amount']) > floatval($rule->receipt_required_threshold)) {
                            $needsReceipt = true;
                        }
                        
                        $fileKey = "claims.{$index}.receipt";
                        if ($needsReceipt && !$request->hasFile($fileKey)) {
                            $errorMessages[$fileKey] = "A receipt attachment is required for " . $rule->category->name . " claims above ₹" . number_format($rule->receipt_required_threshold ?: 0, 2) . ".";
                        }
                    }
                }
                
                if (!empty($errorMessages)) {
                    return $this->sendError('Expense policy validation failed.', 422, $errorMessages);
                }
            }
        }

        $report = DB::transaction(function () use ($validated, $request, $tenantId) {
            $totalAmount = 0.00;
            foreach ($validated['claims'] as $c) {
                $totalAmount += floatval($c['amount']);
            }

            $advanceAdjusted = 0.00;
            $advance = null;
            if (!empty($validated['cash_advance_id'])) {
                $advance = CashAdvance::find($validated['cash_advance_id']);
                if ($advance) {
                    $advanceAmountVal = floatval($advance->approved_amount ?? $advance->amount);
                    $advanceAdjusted = min($advanceAmountVal, $totalAmount);
                }
            }

            $netReimbursement = max($totalAmount - $advanceAdjusted, 0.00);

            $rep = ExpenseReport::create([
                'tenant_id'         => $tenantId,
                'employee_id'       => $validated['employee_id'],
                'travel_request_id' => $validated['travel_request_id'] ?: null,
                'title'             => $validated['title'],
                'total_amount'      => $totalAmount,
                'advance_adjusted'  => $advanceAdjusted,
                'net_reimbursement' => $netReimbursement,
                'status'            => 'draft',
            ]);

            if ($advance) {
                $advance->update(['expense_report_id' => $rep->id]);
            }

            foreach ($validated['claims'] as $index => $c) {
                $receiptPath = null;
                $fileKey = "claims.{$index}.receipt";
                if ($request->hasFile($fileKey)) {
                    $receiptPath = $request->file($fileKey)->store('expense_receipts', 'public');
                }

                ExpenseClaim::create([
                    'expense_report_id'   => $rep->id,
                    'expense_category_id' => $c['category_id'],
                    'expense_date'        => $c['date'],
                    'amount'              => $c['amount'],
                    'tax_amount'          => $c['tax'] ?? 0.00,
                    'merchant'            => $c['merchant'] ?? null,
                    'description'         => $c['desc'] ?? null,
                    'receipt_path'        => $receiptPath,
                ]);
            }

            return $rep;
        });

        return $this->sendSuccess($report->load('claims.category'), 'Expense report saved to drafts.', 201);
    }

    /**
     * POST /api/hrms/travel-expense/report/{expenseReport}/update
     */
    public function updateExpenseReport(Request $request, ExpenseReport $expenseReport): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        if ($expenseReport->status !== 'draft') {
            return $this->sendError('Only draft expense reports can be edited.', 400);
        }

        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'travel_request_id' => 'nullable|exists:travel_requests,id',
            'cash_advance_id'   => 'nullable|exists:cash_advances,id',
            'title'             => 'required|string|max:255',
            'claims'            => 'required|array|min:1',
            'claims.*.category_id' => 'required|exists:expense_categories,id',
            'claims.*.date'        => 'required|date',
            'claims.*.amount'      => 'required|numeric|min:0.01',
            'claims.*.tax'         => 'nullable|numeric|min:0',
            'claims.*.merchant'    => 'nullable|string|max:255',
            'claims.*.desc'        => 'nullable|string|max:1000',
            'claims.*.receipt'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'claims.*.existing_receipt' => 'nullable|string',
        ]);

        $employee = Employee::find($validated['employee_id']);
        if ($employee) {
            $policy = ExpensePolicy::where('tenant_id', $tenantId)
                ->where('status', true)
                ->where(function($q) use ($employee) {
                    $q->where('designation_id', $employee->designation_id)
                      ->orWhere('department_id', $employee->department_id)
                      ->orWhere('company_id', $employee->company_id)
                      ->orWhere(function($sub) {
                          $sub->whereNull('designation_id')
                              ->whereNull('department_id')
                              ->whereNull('company_id');
                      });
                })
                ->orderByRaw('CASE 
                    WHEN designation_id IS NOT NULL THEN 1 
                    WHEN department_id IS NOT NULL THEN 2 
                    WHEN company_id IS NOT NULL THEN 3 
                    ELSE 4 
                END')
                ->first();

            if ($policy) {
                $errorMessages = [];
                foreach ($validated['claims'] as $index => $c) {
                    $rule = $policy->rules()->where('expense_category_id', $c['category_id'])->first();
                    if ($rule) {
                        if ($rule->max_limit_per_claim && floatval($c['amount']) > floatval($rule->max_limit_per_claim)) {
                            $errorMessages["claims.{$index}.amount"] = "This claim amount exceeds the policy limit of ₹" . number_format($rule->max_limit_per_claim, 2) . " for category " . $rule->category->name . ".";
                        }
                        
                        $needsReceipt = false;
                        if ($rule->receipt_required) {
                            $needsReceipt = true;
                        } elseif ($rule->receipt_required_threshold && floatval($c['amount']) > floatval($rule->receipt_required_threshold)) {
                            $needsReceipt = true;
                        }
                        
                        $fileKey = "claims.{$index}.receipt";
                        if ($needsReceipt && !$request->hasFile($fileKey) && empty($c['existing_receipt'])) {
                            $errorMessages[$fileKey] = "A receipt attachment is required for " . $rule->category->name . " claims.";
                        }
                    }
                }
                
                if (!empty($errorMessages)) {
                    return $this->sendError('Expense policy validation failed.', 422, $errorMessages);
                }
            }
        }

        DB::transaction(function () use ($validated, $request, $tenantId, $expenseReport) {
            $totalAmount = 0.00;
            foreach ($validated['claims'] as $c) {
                $totalAmount += floatval($c['amount']);
            }

            $advanceAdjusted = 0.00;
            $advance = null;
            if (!empty($validated['cash_advance_id'])) {
                $advance = CashAdvance::find($validated['cash_advance_id']);
                if ($advance) {
                    $advanceAmountVal = floatval($advance->approved_amount ?? $advance->amount);
                    $advanceAdjusted = min($advanceAmountVal, $totalAmount);
                }
            }

            $netReimbursement = max($totalAmount - $advanceAdjusted, 0.00);

            $expenseReport->update([
                'travel_request_id' => $validated['travel_request_id'] ?: null,
                'title'             => $validated['title'],
                'total_amount'      => $totalAmount,
                'advance_adjusted'  => $advanceAdjusted,
                'net_reimbursement' => $netReimbursement,
            ]);

            CashAdvance::where('expense_report_id', $expenseReport->id)->update(['expense_report_id' => null]);

            if ($advance) {
                $advance->update(['expense_report_id' => $expenseReport->id]);
            }

            $expenseReport->claims()->delete();

            foreach ($validated['claims'] as $index => $c) {
                $receiptPath = $c['existing_receipt'] ?? null;
                $fileKey = "claims.{$index}.receipt";
                if ($request->hasFile($fileKey)) {
                    $receiptPath = $request->file($fileKey)->store('expense_receipts', 'public');
                }

                ExpenseClaim::create([
                    'expense_report_id'   => $expenseReport->id,
                    'expense_category_id' => $c['category_id'],
                    'expense_date'        => $c['date'],
                    'amount'              => $c['amount'],
                    'tax_amount'          => $c['tax'] ?? 0.00,
                    'merchant'            => $c['merchant'] ?? null,
                    'description'         => $c['desc'] ?? null,
                    'receipt_path'        => $receiptPath,
                ]);
            }
        });

        return $this->sendSuccess($expenseReport->fresh()->load('claims.category'), 'Expense report updated successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/report/{expenseReport}/submit
     */
    public function submitExpenseReport(ExpenseReport $expenseReport): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $expenseReport->update(['status' => 'submitted']);
        return $this->sendSuccess($expenseReport, 'Expense report submitted for approval.');
    }

    /**
     * POST /api/hrms/travel-expense/report/{expenseReport}/approve
     */
    public function approveExpenseReport(Request $request, ExpenseReport $expenseReport): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $approvedAmount = floatval($request->input('approved_amount', $expenseReport->total_amount));

        $advance = $expenseReport->cashAdvance;
        $totalAdvance = $advance ? floatval($advance->approved_amount ?? $advance->amount) : 0.00;

        $adjusted = min($totalAdvance, $approvedAmount);
        $approvedNet = max($approvedAmount - $totalAdvance, 0.00);

        $payoutChannel = $request->input('payout_channel', 'accounting');

        DB::transaction(function () use ($expenseReport, $tenantId, $approvedAmount, $approvedNet, $adjusted, $payoutChannel, $advance) {
            $expenseReport->update([
                'status' => 'approved',
                'approved_amount' => $approvedAmount,
                'approved_net_reimbursement' => $approvedNet,
                'advance_adjusted' => $adjusted,
                'payout_channel' => $payoutChannel,
            ]);

            if ($payoutChannel === 'accounting') {
                // Do not post to accounting on approval. Accounting entry is posted only on actual payout.
            } else {
                $employee = $expenseReport->employee;
                if ($employee) {
                    $currentMonth = Carbon::now()->format('Y-m');

                    if ($adjusted < $totalAdvance) {
                        $surplus = $totalAdvance - $adjusted;
                        if ($surplus > 0) {
                            $comp = $this->getOrCreateRecoveryComponent($employee->company_id, $employee->pay_group_id);
                            if ($comp && $employee->salary_structure_id) {
                                \App\Domains\HRMS\Models\SalaryStructureItem::firstOrCreate([
                                    'salary_structure_id' => $employee->salary_structure_id,
                                    'salary_component_id' => $comp->id,
                                ], [
                                    'calculation_type' => 'flat',
                                    'value' => 0.00,
                                    'sort_order' => 99,
                                ]);
                            }

                            \App\Domains\HRMS\Models\EmployeeAdhocComponent::create([
                                'employee_id' => $employee->id,
                                'salary_component_id' => $comp->id,
                                'amount' => $surplus,
                                'payroll_month' => $currentMonth,
                                'status' => 'pending',
                                'remarks' => "Surplus Recovery for Travel Advance: " . $expenseReport->title
                            ]);
                        }
                    }

                    $reimbursement = $approvedNet;
                    if ($reimbursement > 0) {
                        $comp = $this->getOrCreateReimbursementComponent($employee->company_id, $employee->pay_group_id);
                        if ($comp && $employee->salary_structure_id) {
                            \App\Domains\HRMS\Models\SalaryStructureItem::firstOrCreate([
                                'salary_structure_id' => $employee->salary_structure_id,
                                'salary_component_id' => $comp->id,
                            ], [
                                'calculation_type' => 'flat',
                                'value' => 0.00,
                                'sort_order' => 99,
                            ]);
                        }

                        \App\Domains\HRMS\Models\EmployeeAdhocComponent::create([
                            'employee_id' => $employee->id,
                            'salary_component_id' => $comp->id,
                            'amount' => $reimbursement,
                            'payroll_month' => $currentMonth,
                            'status' => 'pending',
                            'remarks' => "Reimbursement for Travel Expense Claim: " . $expenseReport->title
                        ]);
                    }
                }
            }
        });

        return $this->sendSuccess($expenseReport->fresh(), 'Expense report approved successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/report/{expenseReport}/reject
     */
    public function rejectExpenseReport(ExpenseReport $expenseReport): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $expenseReport->update(['status' => 'rejected']);
        return $this->sendSuccess($expenseReport, 'Expense report rejected successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/report/{expenseReport}/pay
     */
    public function payExpenseReport(ExpenseReport $expenseReport): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        DB::transaction(function () use ($expenseReport, $tenantId) {
            $expenseReport->update(['status' => 'paid']);

            $advance = $expenseReport->cashAdvance;
            $isAccountingChannel = ($expenseReport->payout_channel ?? 'accounting') === 'accounting';

            if ($isAccountingChannel) {
                $totalAdvance = $advance ? floatval($advance->approved_amount ?? $advance->amount) : 0.00;
                $approvedAmount = floatval($expenseReport->approved_amount ?? $expenseReport->total_amount);
                $surplus = max($totalAdvance - $approvedAmount, 0.00);
                $approvedNet = max($approvedAmount - $totalAdvance, 0.00);

                $lines = [];

                // 1. Debit Other Expense (Code 5900)
                if ($approvedAmount > 0) {
                    $expenseAccount = $this->getOrCreateAccount($tenantId, '5900', 'Other Expense', 'expense', 'debit', 'operating_expense');
                    $lines[] = [
                        'chart_of_account_id' => $expenseAccount->id,
                        'debit' => $approvedAmount,
                        'credit' => 0.00,
                        'description' => "Expense Claim: " . $expenseReport->title
                    ];
                }

                // 2. Credit Advances (Code 1400)
                if ($totalAdvance > 0) {
                    $advancesAccount = $this->getOrCreateAccount($tenantId, '1400', 'Loans & Advances', 'asset', 'debit', 'loans_advances');
                    $lines[] = [
                        'chart_of_account_id' => $advancesAccount->id,
                        'debit' => 0.00,
                        'credit' => $totalAdvance,
                        'description' => "Clear Advance for Claim: " . $expenseReport->title
                    ];
                }

                // 3. Bank Account transaction (Code 1020)
                if ($surplus > 0 || $approvedNet > 0) {
                    $bankAccount = $this->getOrCreateAccount($tenantId, '1020', 'Bank Account', 'asset', 'debit', 'current_asset');
                    if ($surplus > 0) {
                        // Recovery (Debit Bank)
                        $lines[] = [
                            'chart_of_account_id' => $bankAccount->id,
                            'debit' => $surplus,
                            'credit' => 0.00,
                            'description' => "Advance surplus recovered for Claim: " . $expenseReport->title
                        ];
                    } elseif ($approvedNet > 0) {
                        // Payout (Credit Bank)
                        $lines[] = [
                            'chart_of_account_id' => $bankAccount->id,
                            'debit' => 0.00,
                            'credit' => $approvedNet,
                            'description' => "Payout for Claim: " . $expenseReport->title
                        ];
                    }
                }

                if (count($lines) > 0) {
                    try {
                        $journalService = app(\App\Domains\Accounting\Services\JournalService::class);
                        $journalService->post($lines, [
                            'tenant_id' => $tenantId,
                            'journal_date' => now(),
                            'source' => 'expense',
                            'reference_type' => 'ExpenseReport',
                            'reference_id' => $expenseReport->id,
                            'memo' => "Paid Expense Claim: " . $expenseReport->title,
                            'posted_by' => auth()->id(),
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Payout Journal Posting Failed: " . $e->getMessage());
                    }
                }
            }

            // If an advance was associated, mark it as settled
            if ($advance) {
                $advance->update(['status' => 'settled']);
            }
        });

        return $this->sendSuccess($expenseReport->fresh(), 'Expense report marked as paid and advance settled successfully.');
    }

    /**
     * GET /api/hrms/travel-expense/employee-policy/{employee}
     */
    public function getEmployeePolicy(Employee $employee): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        
        $policy = ExpensePolicy::where('tenant_id', $tenantId)
            ->where('status', true)
            ->where(function($q) use ($employee) {
                $q->where('designation_id', $employee->designation_id)
                  ->orWhere('department_id', $employee->department_id)
                  ->orWhere('company_id', $employee->company_id)
                  ->orWhere(function($sub) {
                      $sub->whereNull('designation_id')
                          ->whereNull('department_id')
                          ->whereNull('company_id');
                  });
            })
            ->orderByRaw('CASE 
                WHEN designation_id IS NOT NULL THEN 1 
                WHEN department_id IS NOT NULL THEN 2 
                WHEN company_id IS NOT NULL THEN 3 
                ELSE 4 
            END')
            ->first();

        $rules = [];
        if ($policy) {
            foreach ($policy->rules as $rule) {
                $rules[$rule->expense_category_id] = [
                    'category_name'              => $rule->category->name,
                    'max_limit_per_claim'        => $rule->max_limit_per_claim ? floatval($rule->max_limit_per_claim) : null,
                    'receipt_required'           => (bool)$rule->receipt_required,
                    'receipt_required_threshold' => $rule->receipt_required_threshold ? floatval($rule->receipt_required_threshold) : null,
                ];
            }
        }

        return $this->sendSuccess([
            'policy_name' => $policy ? $policy->name : null,
            'rules'       => $rules
        ], 'Employee active expense policy retrieved.');
    }

    /**
     * Get or create a chart of account record for integration.
     */
    private function getOrCreateAccount(int $tenantId, string $code, string $name, string $type, string $normalBalance, ?string $subtype = null)
    {
        $account = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();

        if ($account) {
            return $account;
        }

        $groupAccountIds = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique()
            ->toArray();

        $account = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->whereNotIn('id', $groupAccountIds)
            ->first();

        if ($account) {
            return $account;
        }

        return \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->whereNotIn('id', $groupAccountIds)
            ->first();
    }

    /**
     * Get or create a travel advance recovery component for Payroll.
     */
    private function getOrCreateRecoveryComponent(int $companyId, ?int $payGroupId)
    {
        return \App\Domains\HRMS\Models\SalaryComponent::updateOrCreate(
            ['code' => 'TE_RECOVERY', 'company_id' => $companyId, 'pay_group_id' => $payGroupId],
            [
                'name' => 'T&E Advance Recovery',
                'type' => 'deduction',
                'calculation_type' => 'flat',
                'default_value' => 0.00,
                'is_adhoc' => true,
                'status' => true,
                'description' => 'Recovery of surplus cash advance from Travel & Expense module'
            ]
        );
    }

    /**
     * Get or create a travel reimbursement component for Payroll.
     */
    private function getOrCreateReimbursementComponent(int $companyId, ?int $payGroupId)
    {
        return \App\Domains\HRMS\Models\SalaryComponent::updateOrCreate(
            ['code' => 'TE_REIMB', 'company_id' => $companyId, 'pay_group_id' => $payGroupId],
            [
                'name' => 'T&E Reimbursement',
                'type' => 'earning',
                'calculation_type' => 'flat',
                'default_value' => 0.00,
                'is_adhoc' => true,
                'status' => true,
                'description' => 'Reimbursement of travel & expense claims'
            ]
        );
    }
}
