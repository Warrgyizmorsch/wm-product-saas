<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Models\PayrollRun;
use Illuminate\Http\Request;

interface PayrollRunRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeRun(array $validated): array;

    public function lockRun(PayrollRun $run): array;

    public function resolvePending(PayrollRun $run, string $resolution, Request $request): array;

    public function releasePayouts(PayrollRun $run): void;

    public function toggleHold(Employee $employee, string $month, ?string $targetMonth): string;

    public function getMySalaryData(Employee $employee): array;

    public function storeBulkAdhoc(array $validated): array;

    public function getPayslipData(PayrollRun $run, Employee $employee): array;
}
