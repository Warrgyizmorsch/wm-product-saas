<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Services\PayrollCalculationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Exports\PayrollBankExport;
use Maatwebsite\Excel\Facades\Excel;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollCalculationService $payrollCalculationService
    ) {}

    public function index(Request $request)
    {
        $runs = PayrollRun::orderBy('payroll_month', 'desc')->get();
        
        $selectedRunId = $request->get('run_id');
        $selectedRun = $selectedRunId ? PayrollRun::find($selectedRunId) : $runs->first();

        // Get all active pay groups for the initiation form dropdown
        $payGroups = \App\Domains\HRMS\Models\PayGroup::where('status', true)->get();

        $registerData = [];
        $pendingPriorHolds = [];
        
        if ($selectedRun) {
            // Get active employees mapped to pay groups & structures
            $employeesQuery = Employee::where('status', true)
                ->whereNotNull('pay_group_id')
                ->whereNotNull('salary_structure_id');

            // 1. If the current run is a specific pay group, filter by it
            if ($selectedRun->pay_group_id) {
                $employeesQuery->where('pay_group_id', $selectedRun->pay_group_id);
            } else {
                // 2. If current run is "All Pay Groups", exclude any pay group that already has its own run
                $otherProcessedPayGroupIds = PayrollRun::where('payroll_month', $selectedRun->payroll_month)
                    ->where('id', '!=', $selectedRun->id)
                    ->whereNotNull('pay_group_id')
                    ->pluck('pay_group_id')
                    ->toArray();

                if (!empty($otherProcessedPayGroupIds)) {
                    $employeesQuery->whereNotIn('pay_group_id', $otherProcessedPayGroupIds);
                }
            }

            $employees = $employeesQuery->get();

            foreach ($employees as $employee) {
                $calc = $this->payrollCalculationService->calculateSalary($employee, $selectedRun->payroll_month);
                
                $holdRecord = PayrollHold::where('employee_id', $employee->id)
                    ->where('payroll_month', $selectedRun->payroll_month)
                    ->first();
                
                $isHeld = $holdRecord && $holdRecord->status === 'on_hold';
                $holdStatus = $holdRecord ? $holdRecord->status : null;

                $registerData[] = [
                    'employee'    => $employee,
                    'is_held'     => $isHeld,
                    'hold_status' => $holdStatus,
                    'calc'        => $calc['summary'] ?? [
                        'employee_name'        => $employee->full_name,
                        'total_earnings'       => 0.00,
                        'total_deductions'     => 0.00,
                        'lop_days'             => 0,
                        'attendance_penalties'    => 0.00,
                        'attendance_penalty_days' => 0.00,
                        'net_payout'              => 0.00,
                    ],
                    'items'       => $calc['items'] ?? [],
                ];
            }

            // Fetch any unreleased holds from prior months to display warning alerts
            $priorHolds = PayrollHold::where('status', 'on_hold')
                ->where('payroll_month', '<', $selectedRun->payroll_month)
                ->get();

            foreach ($priorHolds as $hold) {
                if ($hold->employee) {
                    $calc = $this->payrollCalculationService->calculateSalary($hold->employee, $hold->payroll_month);
                    $pendingPriorHolds[] = [
                        'hold'       => $hold,
                        'employee'   => $hold->employee,
                        'net_payout' => $calc['summary']['net_payout'] ?? 0.00
                    ];
                }
            }
        }

        $pendingIssues = $selectedRun ? $this->getPendingIssues($selectedRun) : ['leaves' => 0, 'corrections' => 0, 'overtime' => 0, 'total' => 0];
        $salaryComponents = \App\Domains\HRMS\Models\SalaryComponent::where('status', true)
            ->where('is_adhoc', true)
            ->get();
        $allEmployees = Employee::where('status', true)->orderBy('full_name')->get();
        $departments = \App\Domains\HRMS\Models\Department::orderBy('name')->get();

        // Apply search, sort, and status/department filters to registerData
        $registerCollection = collect($registerData);
        
        $search = $request->get('search');
        if ($search) {
            $search = strtolower(trim($search));
            $registerCollection = $registerCollection->filter(function($row) use ($search) {
                $name = strtolower($row['employee']->full_name);
                $empId = strtolower($row['employee']->employee_id);
                return str_contains($name, $search) || str_contains($empId, $search);
            });
        }

        $status = $request->get('status');
        if ($status) {
            $registerCollection = $registerCollection->filter(function($row) use ($status, $selectedRun) {
                if ($status === 'held') {
                    return $selectedRun->status === 'paid' ? ($row['hold_status'] === 'on_hold') : $row['is_held'];
                } elseif ($status === 'approved') {
                    return $selectedRun->status === 'paid' ? ($row['hold_status'] !== 'on_hold') : !$row['is_held'];
                }
                return true;
            });
        }

        $deptId = $request->get('department_id');
        if ($deptId) {
            $registerCollection = $registerCollection->filter(function($row) use ($deptId) {
                return $row['employee']->department_id == $deptId;
            });
        }

        $sort = $request->get('sort', 'name_asc');
        if ($sort === 'name_asc') {
            $registerCollection = $registerCollection->sortBy(function($row) {
                return strtolower($row['employee']->full_name);
            });
        } elseif ($sort === 'name_desc') {
            $registerCollection = $registerCollection->sortByDesc(function($row) {
                return strtolower($row['employee']->full_name);
            });
        } elseif ($sort === 'net_desc') {
            $registerCollection = $registerCollection->sortByDesc(function($row) {
                return (float)($row['calc']['net_payout'] ?? 0);
            });
        } elseif ($sort === 'net_asc') {
            $registerCollection = $registerCollection->sortBy(function($row) {
                return (float)($row['calc']['net_payout'] ?? 0);
            });
        } elseif ($sort === 'lop_desc') {
            $registerCollection = $registerCollection->sortByDesc(function($row) {
                return (float)($row['calc']['lop_days'] ?? 0);
            });
        }

        $registerData = $registerCollection->values()->all();

        $filters = [
            'search'        => $request->get('search'),
            'sort'          => $request->get('sort', 'name_asc'),
            'status'        => $request->get('status'),
            'department_id' => $request->get('department_id'),
        ];

        return view('modules.hrms.payroll.index', compact('runs', 'selectedRun', 'registerData', 'payGroups', 'pendingPriorHolds', 'pendingIssues', 'salaryComponents', 'allEmployees', 'departments', 'filters'));
    }

    public function storeRun(Request $request)
    {
        $validated = $request->validate([
            'payroll_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'pay_group_id'  => 'nullable|exists:pay_groups,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
        ]);

        // 1. If trying to create a specific run, check if a general run already exists for this month
        if (!empty($validated['pay_group_id'])) {
            $generalExists = PayrollRun::where('payroll_month', $validated['payroll_month'])
                ->whereNull('pay_group_id')
                ->exists();
            if ($generalExists) {
                return redirect()->back()->with('error', "A general payroll run for all pay groups already exists for {$validated['payroll_month']}.");
            }
        }

        // 2. Check if a payroll run for this month and specific pay group already exists
        $existsQuery = PayrollRun::where('payroll_month', $validated['payroll_month']);
        if (!empty($validated['pay_group_id'])) {
            $existsQuery->where('pay_group_id', $validated['pay_group_id']);
        } else {
            $existsQuery->whereNull('pay_group_id');
        }

        if ($existsQuery->exists()) {
            $label = !empty($validated['pay_group_id']) ? 'this pay group' : 'all pay groups';
            return redirect()->back()->with('error', "A payroll run already exists for {$validated['payroll_month']} and {$label}.");
        }

        PayrollRun::create([
            'company_id'    => auth()->user()->company_id ?? 1,
            'pay_group_id'  => $validated['pay_group_id'] ?? null,
            'payroll_month' => $validated['payroll_month'],
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'],
            'status'        => 'draft',
            'processed_by'  => auth()->id(),
        ]);

        return redirect()->route('hrms.payroll.index')->with('success', 'Payroll run initiated successfully.');
    }

    public function lockRun(PayrollRun $run)
    {
        $pending = $this->getPendingIssues($run);
        if ($pending['total'] > 0) {
            return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])
                ->with('error', "Cannot lock payroll: There are {$pending['total']} pending requests (Leaves: {$pending['leaves']}, Corrections: {$pending['corrections']}, Overtime: {$pending['overtime']}). Please resolve them first.");
        }

        $run->update(['status' => 'locked']);
        return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])->with('success', 'Payroll run locked successfully.');
    }

    public function resolvePending(Request $request, PayrollRun $run)
    {
        $validated = $request->validate([
            'resolution_action' => 'required|string|in:approve_all,reject_all',
        ]);

        $resolution = $validated['resolution_action'];
        $startDate = $run->start_date;
        $endDate = $run->end_date;

        DB::beginTransaction();
        try {
            if ($resolution === 'approve_all') {
                // 1. Approve Leave Requests
                $leaves = LeaveRequest::where('status', 'pending')
                    ->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate)
                    ->get();
                
                $leaveRepo = app(\App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface::class);
                foreach ($leaves as $leave) {
                    $leaveRepo->updateStatus($leave, ['action' => 'approved'], $request);
                }

                // 2. Approve Attendance Corrections
                $corrections = AttendanceCorrection::where('status', 'pending')
                    ->whereBetween('date', [$startDate, $endDate])
                    ->get();

                $correctionController = app(\App\Domains\HRMS\Controllers\AttendanceCorrectionController::class);
                foreach ($corrections as $correction) {
                    $correctionController->approve($request, $correction);
                }

                // 3. Approve Overtime Requests
                $overtimes = OvertimeRequest::where('status', 'pending')
                    ->whereBetween('date', [$startDate, $endDate])
                    ->get();

                $otRepo = app(\App\Domains\HRMS\Repositories\OvertimeRequestRepositoryInterface::class);
                foreach ($overtimes as $ot) {
                    $otRepo->updateStatus($ot, [
                        'action' => 'approved',
                        'approved_duration_hours' => $ot->duration_hours
                    ], $request);
                }

                $message = "All pending requests approved and payroll calculations updated.";
            } else {
                // reject_all
                // 1. Reject Leave Requests
                $leaves = LeaveRequest::where('status', 'pending')
                    ->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate)
                    ->get();
                
                $leaveRepo = app(\App\Domains\HRMS\Repositories\LeaveRequestRepositoryInterface::class);
                foreach ($leaves as $leave) {
                    $leaveRepo->updateStatus($leave, [
                        'action' => 'rejected',
                        'rejection_reason' => 'Auto-rejected during payroll run execution.'
                    ], $request);
                }

                // 2. Reject Attendance Corrections
                $corrections = AttendanceCorrection::where('status', 'pending')
                    ->whereBetween('date', [$startDate, $endDate])
                    ->get();

                $correctionController = app(\App\Domains\HRMS\Controllers\AttendanceCorrectionController::class);
                foreach ($corrections as $correction) {
                    $fakeRequest = clone $request;
                    $fakeRequest->merge(['rejected_reason' => 'Auto-rejected during payroll run execution.']);
                    $correctionController->reject($fakeRequest, $correction);
                }

                // 3. Reject Overtime Requests
                $overtimes = OvertimeRequest::where('status', 'pending')
                    ->whereBetween('date', [$startDate, $endDate])
                    ->get();

                $otRepo = app(\App\Domains\HRMS\Repositories\OvertimeRequestRepositoryInterface::class);
                foreach ($overtimes as $ot) {
                    $otRepo->updateStatus($ot, [
                        'action' => 'rejected',
                        'rejection_reason' => 'Auto-rejected during payroll run execution.'
                    ], $request);
                }

                $message = "All pending requests rejected. Unresolved days calculated as LOP/zeros.";
            }

            DB::commit();
            return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to auto-resolve payroll issues", [
                'run_id' => $run->id,
                'error' => $e->getMessage()
            ]);
            return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])
                ->with('error', "Failed to resolve pending requests: " . $e->getMessage());
        }
    }

    private function getPendingIssues(PayrollRun $run): array
    {
        $startDate = $run->start_date;
        $endDate = $run->end_date;

        $pendingLeaves = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'pending')
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

        $pendingCorrections = AttendanceCorrection::with('employee')
            ->where('status', 'pending')
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $pendingOvertime = OvertimeRequest::with('employee')
            ->where('status', 'pending')
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        return [
            'leaves'            => $pendingLeaves->count(),
            'corrections'       => $pendingCorrections->count(),
            'overtime'          => $pendingOvertime->count(),
            'total'             => $pendingLeaves->count() + $pendingCorrections->count() + $pendingOvertime->count(),
            'leaves_list'       => $pendingLeaves,
            'corrections_list'  => $pendingCorrections,
            'overtime_list'     => $pendingOvertime,
        ];
    }

    public function releasePayouts(PayrollRun $run)
    {
        $run->update(['status' => 'paid']);
        
        // Also update any pending retro adjustments inside this month to 'processed'
        DB::table('payroll_retroactive_adjustments')
            ->where('target_payroll_month', $run->payroll_month)
            ->where('status', 'pending')
            ->update(['status' => 'processed']);

        // Also update any pending ad-hoc components inside this month to 'processed'
        DB::table('employee_adhoc_components')
            ->where('payroll_month', $run->payroll_month)
            ->where('status', 'pending')
            ->update(['status' => 'processed']);

        // Collect and update all approved overtime request records paid in this run
        $employeesQuery = Employee::where('status', true)
            ->whereNotNull('pay_group_id')
            ->whereNotNull('salary_structure_id');

        if ($run->pay_group_id) {
            $employeesQuery->where('pay_group_id', $run->pay_group_id);
        } else {
            $otherProcessedPayGroupIds = PayrollRun::where('payroll_month', $run->payroll_month)
                ->where('id', '!=', $run->id)
                ->whereNotNull('pay_group_id')
                ->pluck('pay_group_id')
                ->toArray();
            if (!empty($otherProcessedPayGroupIds)) {
                $employeesQuery->whereNotIn('pay_group_id', $otherProcessedPayGroupIds);
            }
        }
        $employees = $employeesQuery->get();

        $allOtIds = [];
        $allEncashIds = [];
        foreach ($employees as $employee) {
            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
            if (!empty($calc['summary']['processed_ot_ids'])) {
                $allOtIds = array_merge($allOtIds, $calc['summary']['processed_ot_ids']);
            }
            if (!empty($calc['summary']['processed_encash_ids'])) {
                $allEncashIds = array_merge($allEncashIds, $calc['summary']['processed_encash_ids']);
            }
        }

        if (!empty($allOtIds)) {
            DB::table('overtime_requests')
                ->whereIn('id', $allOtIds)
                ->update(['status' => 'processed']);
        }

        if (!empty($allEncashIds)) {
            DB::table('leave_encashments')
                ->whereIn('id', $allEncashIds)
                ->update(['status' => 'processed']);
        }

        return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])->with('success', 'Payroll payouts released successfully.');
    }

    public function toggleHold(Request $request, Employee $employee, string $month)
    {
        $hold = PayrollHold::where('employee_id', $employee->id)
            ->where('payroll_month', $month)
            ->first();

        if ($hold) {
            if ($hold->status === 'on_hold') {
                $hold->update(['status' => 'released']);
                $msg = 'Payout released for ' . $employee->full_name;

                // If the payroll run is already locked or paid, we cannot pay them directly in the current run.
                // Instead, we automatically convert this released payout to Arrears/Retroactive Adjustment for a target month (default: NEXT month).
                $run = PayrollRun::where('payroll_month', $month)->first();
                if ($run && in_array($run->status, ['locked', 'paid'])) {
                    $calc = $this->payrollCalculationService->calculateSalary($employee, $month);
                    $netPayout = $calc['summary']['net_payout'] ?? 0.00;

                    if ($netPayout > 0) {
                        // Use request's target_month if specified (e.g. from active cycle dashboard release), otherwise fallback to next month
                        $targetMonthStr = $request->get('target_month') ?: \Carbon\Carbon::parse($month . '-01')->addMonth()->format('Y-m');
                        
                        // Prevent duplicate adjustment entries
                        $adjustmentExists = \App\Domains\HRMS\Models\PayrollRetroactiveAdjustment::where('employee_id', $employee->id)
                            ->where('target_payroll_month', $targetMonthStr)
                            ->where('amount_reversal', $netPayout)
                            ->exists();

                        if (!$adjustmentExists) {
                            \App\Domains\HRMS\Models\PayrollRetroactiveAdjustment::create([
                                'tenant_id'            => $employee->tenant_id,
                                'employee_id'          => $employee->id,
                                'target_payroll_month' => $targetMonthStr,
                                'reversal_days'        => 0,
                                'amount_reversal'      => $netPayout,
                                'status'               => 'pending',
                            ]);
                            $msg .= '. It has been added as Arrears for ' . $targetMonthStr . '.';
                        }
                    }
                }
            } else {
                $hold->update(['status' => 'on_hold']);
                $msg = 'Payout put on hold for ' . $employee->full_name;

                // Clean up any pending retroactive adjustments that were created when this was released
                $calc = $this->payrollCalculationService->calculateSalary($employee, $month);
                $netPayout = $calc['summary']['net_payout'] ?? 0.00;

                \App\Domains\HRMS\Models\PayrollRetroactiveAdjustment::where('employee_id', $employee->id)
                    ->where('status', 'pending')
                    ->where('amount_reversal', $netPayout)
                    ->delete();
            }
        } else {
            PayrollHold::create([
                'employee_id'   => $employee->id,
                'payroll_month' => $month,
                'status'        => 'on_hold',
            ]);
            $msg = 'Payout put on hold for ' . $employee->full_name;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function mySalary()
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return redirect()->back()->with('error', 'No employee profile linked to your user account.');
        }

        // Get all paid runs (released payslips)
        $paidRuns = PayrollRun::where('status', 'paid')
            ->orderBy('payroll_month', 'desc')
            ->get();

        $salaryHistory = [];
        foreach ($paidRuns as $run) {
            // If the employee's payout is currently on hold, do not show the payslip in their employee portal
            $isHeld = PayrollHold::where('employee_id', $employee->id)
                ->where('payroll_month', $run->payroll_month)
                ->where('status', 'on_hold')
                ->exists();

            if ($isHeld) {
                continue;
            }

            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
            $salaryHistory[] = [
                'run' => $run,
                'calc' => $calc['summary'] ?? null,
                'details' => $calc['items'] ?? [],
            ];
        }

        return view('modules.hrms.payroll.my_salary', compact('employee', 'salaryHistory'));
    }

    public function storeBulkAdhoc(Request $request)
    {
        $validated = $request->validate([
            'salary_component_id' => 'required|exists:salary_components,id',
            'payroll_month'       => 'required|string',
            'employee_ids'        => 'required|array',
            'employee_ids.*'      => 'exists:employees,id',
            'amount'              => 'required|numeric|min:0',
            'remarks'             => 'nullable|string',
        ]);

        $employees = Employee::whereIn('id', $validated['employee_ids'])->get();

        if ($employees->isEmpty()) {
            return redirect()->back()->with('error', 'No selected employees found.');
        }

        DB::beginTransaction();
        try {
            $insertData = [];
            foreach ($employees as $employee) {
                $insertData[] = [
                    'tenant_id'           => auth()->user()->tenant_id ?? 1,
                    'employee_id'         => $employee->id,
                    'salary_component_id' => $validated['salary_component_id'],
                    'amount'              => $validated['amount'],
                    'payroll_month'       => $validated['payroll_month'],
                    'status'              => 'pending',
                    'remarks'             => $validated['remarks'],
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }

            DB::table('employee_adhoc_components')->insert($insertData);

            DB::commit();
            return redirect()->back()->with('success', 'Bulk ad-hoc adjustments created successfully for ' . $employees->count() . ' employees.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create bulk adjustments: ' . $e->getMessage());
        }
    }

    public function exportBankFile(PayrollRun $run)
    {
        $fileName = 'bank_transfer_' . $run->payroll_month . '.xlsx';
        return Excel::download(new PayrollBankExport($run), $fileName);
    }

    public function downloadPayslip(PayrollRun $run, Employee $employee)
    {
        // Security check: If the logged-in user is an employee, they can only access their own payslip
        $user = auth()->user();
        if ($user->employee && $user->employee->id !== $employee->id) {
            abort(403, 'Unauthorized access to this payslip.');
        }

        $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
        $company = $employee->company ?? \App\Domains\HRMS\Models\Company::first();

        // Convert Net Payout to Words (Rupees)
        $netPayout = $calc['summary']['net_payout'] ?? 0;
        $netPayoutInWords = $this->convertNumberToWords($netPayout) . ' Only';

        $data = [
            'run' => $run,
            'employee' => $employee,
            'company' => $company,
            'calc' => $calc['summary'] ?? null,
            'details' => $calc['items'] ?? [],
            'netPayoutInWords' => $netPayoutInWords,
            'payroll_month_formatted' => \Carbon\Carbon::parse($run->payroll_month . '-01')->format('F Y'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('modules.hrms.payroll.pdf_payslip', $data);
        $fileName = 'payslip_' . $employee->employee_id . '_' . $run->payroll_month . '.pdf';
        
        return $pdf->download($fileName);
    }

    private function convertNumberToWords($number)
    {
        $no = (int)floor($number);
        $point = (int)round(($number - $no) * 100);
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = [];
        $words = [
            0 => '', 1 => 'One', 2 => 'Two',
            3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight',
            9 => 'Nine', 10 => 'Ten', 11 => 'Eleven',
            12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
            18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
            30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
            60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty',
            90 => 'Ninety'
        ];
        $digits = ['', 'Hundred', 'Thousand', 'Lakh', 'Crore'];
        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred
                    : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else {
                $str[] = null;
            }
        }
        $Rupees = implode('', array_reverse($str));
        $paise = '';
        if ($point > 0) {
            $paise = ' and ' . ($point < 21 ? $words[$point] : $words[floor($point / 10) * 10] . ' ' . $words[$point % 10]) . ' Paise';
        }
        return ($Rupees ? $Rupees . 'Rupees' : '') . $paise;
    }
}
