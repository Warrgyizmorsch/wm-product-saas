<?php

namespace App\Exports;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Services\PayrollCalculationService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Carbon\Carbon;

class PayrollBankExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithColumnFormatting
{
    private $payrollCalculationService;

    public function __construct(
        private readonly PayrollRun $run
    ) {
        $this->payrollCalculationService = app(PayrollCalculationService::class);
    }

    public function array(): array
    {
        $employeesQuery = Employee::where('status', true)
            ->whereNotNull('pay_group_id')
            ->whereNotNull('salary_structure_id');

        if ($this->run->pay_group_id) {
            $employeesQuery->where('pay_group_id', $this->run->pay_group_id);
        } else {
            $otherProcessedPayGroupIds = PayrollRun::where('payroll_month', $this->run->payroll_month)
                ->where('id', '!=', $this->run->id)
                ->whereNotNull('pay_group_id')
                ->pluck('pay_group_id')
                ->toArray();

            if (!empty($otherProcessedPayGroupIds)) {
                $employeesQuery->whereNotIn('pay_group_id', $otherProcessedPayGroupIds);
            }
        }

        $employees = $employeesQuery->get();
        $rows = [];
        $sno = 1;

        foreach ($employees as $employee) {
            // Exclude employees with held payroll in this run
            $isHeld = \App\Domains\HRMS\Models\PayrollHold::where('employee_id', $employee->id)
                ->where('payroll_month', $this->run->payroll_month)
                ->where('status', 'on_hold')
                ->exists();

            if ($isHeld) {
                continue;
            }

            $calc = $this->payrollCalculationService->calculateSalary($employee, $this->run->payroll_month);
            $netPayout = $calc['summary']['net_payout'] ?? 0.00;

            // Skip zero payouts
            if ($netPayout <= 0) {
                continue;
            }

            $monthName = Carbon::parse($this->run->payroll_month . '-01')->format('F Y');

            $rows[] = [
                'sno'            => $sno++,
                'employee_id'    => $employee->employee_id,
                'name'           => $employee->full_name,
                'account_number' => $employee->account_number ? (string)$employee->account_number : 'N/A',
                'ifsc_code'      => $employee->ifsc_code ?: 'N/A',
                'bank_name'      => $employee->bank_name ?: 'N/A',
                'amount'         => (float)$netPayout,
                'remarks'        => 'Salary Payout for ' . $monthName,
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'S.No.',
            'Employee ID',
            'Beneficiary Name',
            'Account Number',
            'IFSC Code',
            'Bank Name',
            'Amount (₹)',
            'Remarks / Narration'
        ];
    }

    public function title(): string
    {
        $monthName = Carbon::parse($this->run->payroll_month . '-01')->format('F Y');
        return 'Bank Transfer - ' . $monthName;
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_TEXT, // Account Number column formatted as text to prevent dropping leading zeroes
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }
}
