<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitDocument;
use Carbon\Carbon;

class ExitDocumentationService
{
    /**
     * Generate or fetch a Relieving Letter record.
     */
    public function generateRelievingLetter(EmployeeExit $exit): EmployeeExitDocument
    {
        $employee = $exit->employee;
        $company = $employee->company ?: \App\Domains\HRMS\Models\Company::first();
        $lwd = $exit->effective_lwd ?: Carbon::today();
        
        $refNumber = 'REL/' . ($company->code ?? 'ORG') . '/' . date('Y') . '/' . str_pad((string)$exit->id, 4, '0', STR_PAD_LEFT);

        $contentData = [
            'ref_number' => $refNumber,
            'issue_date' => Carbon::today()->format('d M, Y'),
            'company_name' => $company->company_name ?? 'The Company',
            'company_legal_name' => $company->legal_name ?? ($company->company_name ?? 'The Company'),
            'employee_name' => $employee->full_name,
            'employee_id' => $employee->employee_id,
            'designation' => $employee->designation->name ?? 'Employee',
            'department' => $employee->department->name ?? 'N/A',
            'date_of_joining' => $employee->date_of_joining ? Carbon::parse($employee->date_of_joining)->format('d M, Y') : 'N/A',
            'last_working_day' => Carbon::parse($lwd)->format('d M, Y'),
            'separation_type' => ucfirst(str_replace('_', ' ', $exit->separation_type)),
        ];

        $doc = EmployeeExitDocument::updateOrCreate(
            [
                'employee_exit_id' => $exit->id,
                'document_type' => 'relieving_letter',
            ],
            [
                'tenant_id' => $exit->tenant_id,
                'employee_id' => $exit->employee_id,
                'reference_number' => $refNumber,
                'issue_date' => Carbon::today()->format('Y-m-d'),
                'content_data' => $contentData,
            ]
        );

        $this->triggerExitNotification($exit, 'Relieving Letter', $lwd);

        return $doc;
    }

    /**
     * Generate or fetch an Experience Certificate record.
     */
    public function generateExperienceCertificate(EmployeeExit $exit): EmployeeExitDocument
    {
        $employee = $exit->employee;
        $company = $employee->company ?: \App\Domains\HRMS\Models\Company::first();
        $lwd = $exit->effective_lwd ?: Carbon::today();
        
        $refNumber = 'EXP/' . ($company->code ?? 'ORG') . '/' . date('Y') . '/' . str_pad((string)$exit->id, 4, '0', STR_PAD_LEFT);

        $doj = $employee->date_of_joining ? Carbon::parse($employee->date_of_joining) : Carbon::today();
        $lwdCarbon = Carbon::parse($lwd);
        $diff = $doj->diff($lwdCarbon);
        $tenureString = trim(($diff->y ? $diff->y . ' year' . ($diff->y > 1 ? 's ' : ' ') : '') . ($diff->m ? $diff->m . ' month' . ($diff->m > 1 ? 's' : '') : ''));
        if (empty($tenureString)) {
            $tenureString = $diff->days . ' days';
        }

        $contentData = [
            'ref_number' => $refNumber,
            'issue_date' => Carbon::today()->format('d M, Y'),
            'company_name' => $company->company_name ?? 'The Company',
            'company_legal_name' => $company->legal_name ?? ($company->company_name ?? 'The Company'),
            'employee_name' => $employee->full_name,
            'employee_id' => $employee->employee_id,
            'designation' => $employee->designation->name ?? 'Employee',
            'department' => $employee->department->name ?? 'N/A',
            'date_of_joining' => $doj->format('d M, Y'),
            'last_working_day' => $lwdCarbon->format('d M, Y'),
            'tenure_string' => $tenureString,
            'conduct_statement' => 'During their tenure, we found their character, dedication, and professional conduct to be exemplary.',
        ];

        $doc = EmployeeExitDocument::updateOrCreate(
            [
                'employee_exit_id' => $exit->id,
                'document_type' => 'experience_certificate',
            ],
            [
                'tenant_id' => $exit->tenant_id,
                'employee_id' => $exit->employee_id,
                'reference_number' => $refNumber,
                'issue_date' => Carbon::today()->format('Y-m-d'),
                'content_data' => $contentData,
            ]
        );

        $this->triggerExitNotification($exit, 'Experience Certificate', $lwd);

        return $doc;
    }

    /**
     * Generate or fetch a No Objection / No Dues Certificate.
     */
    public function generateNocCertificate(EmployeeExit $exit): EmployeeExitDocument
    {
        $employee = $exit->employee;
        $company = $employee->company ?: \App\Domains\HRMS\Models\Company::first();
        $lwd = $exit->effective_lwd ?: Carbon::today();
        
        $refNumber = 'NOC/' . ($company->code ?? 'ORG') . '/' . date('Y') . '/' . str_pad((string)$exit->id, 4, '0', STR_PAD_LEFT);

        $contentData = [
            'ref_number' => $refNumber,
            'issue_date' => Carbon::today()->format('d M, Y'),
            'company_name' => $company->company_name ?? 'The Company',
            'company_legal_name' => $company->legal_name ?? ($company->company_name ?? 'The Company'),
            'employee_name' => $employee->full_name,
            'employee_id' => $employee->employee_id,
            'designation' => $employee->designation->name ?? 'Employee',
            'last_working_day' => Carbon::parse($lwd)->format('d M, Y'),
            'clearance_status' => 'All company assets, dues, files, and accounts have been fully cleared across IT, Finance, HR, Admin, and Operations departments.',
        ];

        $doc = EmployeeExitDocument::updateOrCreate(
            [
                'employee_exit_id' => $exit->id,
                'document_type' => 'noc_certificate',
            ],
            [
                'tenant_id' => $exit->tenant_id,
                'employee_id' => $exit->employee_id,
                'reference_number' => $refNumber,
                'issue_date' => Carbon::today()->format('Y-m-d'),
                'content_data' => $contentData,
            ]
        );

        $this->triggerExitNotification($exit, 'NOC Certificate', $lwd);

        return $doc;
    }

    private function triggerExitNotification(EmployeeExit $exit, string $docType, mixed $lwd): void
    {
        try {
            $employee = $exit->employee;
            if ($employee) {
                \App\Domains\Platform\Services\NotificationRuleService::trigger('hrms.document.exit_cleared', [
                    'tenant_id'     => $exit->tenant_id,
                    'employee_name' => $employee->full_name,
                    'document_type' => $docType,
                    'exit_date'     => Carbon::parse($lwd)->format('d M Y'),
                    'employee_id'   => $employee->id,
                    'user_id'       => $employee->user_id,
                ], $exit);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("NotificationRule trigger failed for exit document: " . $e->getMessage());
        }
    }
}
