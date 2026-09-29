<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Repositories\PayrollRunRepositoryInterface;
use App\Domains\HRMS\Services\DocumentTemplateService;
use App\Exports\PayrollBankExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollRunRepositoryInterface $payrollRunRepository
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PayrollRun::class);

        $data = $this->payrollRunRepository->getIndexData($request->all());

        return view('modules.hrms.payroll.index', $data);
    }

    public function storeRun(Request $request)
    {
        $this->authorize('create', PayrollRun::class);

        $validated = $request->validate([
            'payroll_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'pay_group_id'  => 'nullable|exists:pay_groups,id',
            'employee_ids'  => 'nullable|array',
            'employee_ids.*'=> 'exists:employees,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
        ]);

        $result = $this->payrollRunRepository->storeRun($validated);

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->route('hrms.payroll.index')->with('success', 'Payroll run initiated successfully.');
    }

    public function lockRun(PayrollRun $run)
    {
        $this->authorize('approve', $run);

        $result = $this->payrollRunRepository->lockRun($run);

        if (!$result['success']) {
            return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])
                ->with('error', $result['message']);
        }

        return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])->with('success', 'Payroll run locked successfully.');
    }

    public function resolvePending(Request $request, PayrollRun $run)
    {
        $this->authorize('approve', $run);

        $validated = $request->validate([
            'resolution_action' => 'required|string|in:approve_all,reject_all',
        ]);

        $result = $this->payrollRunRepository->resolvePending($run, $validated['resolution_action'], $request);

        if (!$result['success']) {
            return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])
                ->with('error', $result['message']);
        }

        return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])->with('success', $result['message']);
    }

    public function releasePayouts(PayrollRun $run)
    {
        $this->authorize('process', $run);

        $this->payrollRunRepository->releasePayouts($run);

        return redirect()->route('hrms.payroll.index', ['run_id' => $run->id])->with('success', 'Payroll payouts released successfully.');
    }

    public function toggleHold(Request $request, Employee $employee, string $month)
    {
        $this->authorize('approve', PayrollRun::class);

        $msg = $this->payrollRunRepository->toggleHold($employee, $month, $request->get('target_month'));

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
        $user = auth()->user();
        $employee = $user?->employee;
        if (!$employee && $user) {
            $employee = Employee::where('tenant_id', tenant_id())
                ->where(function ($q) use ($user) {
                    if ($user->email) {
                        $q->where('office_email', $user->email)
                          ->orWhere('personal_email', $user->email);
                    }
                })->first();
        }
        if (!$employee) {
            $employee = Employee::where('tenant_id', tenant_id())->first();
        }
        if (!$employee) {
            return redirect()->back()->with('error', 'No employee profile linked to your user account.');
        }

        $data = $this->payrollRunRepository->getMySalaryData($employee);

        return view('modules.hrms.payroll.my_salary', $data);
    }

    public function storeBulkAdhoc(Request $request)
    {
        $this->authorize('create', PayrollRun::class);

        $validated = $request->validate([
            'salary_component_id' => 'required|exists:salary_components,id',
            'payroll_month'       => 'required|string',
            'employee_ids'        => 'required|array',
            'employee_ids.*'      => 'exists:employees,id',
            'amount'              => 'required|numeric|min:0',
            'remarks'             => 'nullable|string',
        ]);

        $result = $this->payrollRunRepository->storeBulkAdhoc($validated);

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', 'Bulk ad-hoc adjustments created successfully for ' . $result['count'] . ' employees.');
    }

    public function exportBankFile(PayrollRun $run)
    {
        $this->authorize('viewAny', PayrollRun::class);

        $fileName = 'bank_transfer_' . $run->payroll_month . '.xlsx';
        return Excel::download(new PayrollBankExport($run), $fileName);
    }

    public function downloadPayslip(PayrollRun $run, Employee $employee)
    {
        $user = auth()->user();
        if ($user->employee && $user->employee->id !== $employee->id) {
            abort(403, 'Unauthorized access to this payslip.');
        }

        $data = $this->payrollRunRepository->getPayslipData($run, $employee);
        $fileName = 'payslip_' . $employee->employee_id . '_' . $run->payroll_month . '.pdf';

        try {
            $templateService = app(DocumentTemplateService::class);
            $template = \App\Domains\HRMS\Models\DocumentTemplate::query()
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereIn('code', ['PAYSLIP', 'SALARY_SLIP', 'SALARYSLIP'])
                      ->orWhereHas('category', function ($cq) {
                          $cq->where('name', 'like', '%payroll%')
                             ->orWhere('name', 'like', '%salary%');
                      });
                })
                ->orderBy('is_default', 'desc')
                ->first();

            if ($template && !empty($template->body_content)) {
                $renderedHtml = $templateService->renderPayslip($template, $data);
                $printableHtml = $templateService->toPrintableDocument($renderedHtml, 'Payslip - ' . $employee->full_name);
                $pdf = Pdf::loadHTML($printableHtml);
                return $pdf->download($fileName);
            }
        } catch (\Throwable $e) {
            Log::warning("Custom payslip template rendering failed, falling back to default: " . $e->getMessage());
        }

        $pdf = Pdf::loadView('modules.hrms.payroll.pdf_payslip', $data);
        return $pdf->download($fileName);
    }
}
