<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DocumentTemplateService
{
    /**
     * Import raw content from an uploaded template file (.html, .txt, .docx).
     */
    public function importTemplateFromFile($uploadedFile): string
    {
        if (!$uploadedFile || !$uploadedFile->isValid()) {
            return '';
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension());

        if (in_array($extension, ['html', 'htm', 'txt'])) {
            return file_get_contents($uploadedFile->getRealPath());
        }

        if ($extension === 'docx') {
            try {
                $zip = new \ZipArchive();
                if ($zip->open($uploadedFile->getRealPath()) === true) {
                    if (($index = $zip->locateName('word/document.xml')) !== false) {
                        $data = $zip->getFromIndex($index);
                        $zip->close();
                        $xml = strip_tags($data, '<w:p><w:r><w:t>');
                        $text = preg_replace('/<w:p[^>]*>/', "<p>", $xml);
                        $text = preg_replace('/<\/w:p>/', "</p>", $text);
                        $text = strip_tags($text, '<p>');
                        return $text;
                    }
                    $zip->close();
                }
            } catch (\Exception $e) {
                Log::warning("DOCX Template Import fallback: " . $e->getMessage());
            }
        }

        return file_get_contents($uploadedFile->getRealPath()) ?: '';
    }

    /**
     * Render complete template HTML for an employee by substituting dynamic tags.
     */
    public function renderTemplate(DocumentTemplate $template, Employee $employee, ?string $refNumber = null): string
    {
        $employee->loadMissing(['company', 'department', 'designation', 'branch', 'reportingManager']);
        
        $company = $employee->company ?: Company::first();
        $refNo = $refNumber ?: ('DOC/' . ($company?->code ?? 'ORG') . '/' . date('Y') . '/' . str_pad((string)$employee->id, 4, '0', STR_PAD_LEFT));

        // 1. Build Single Value Dictionary
        $dictionary = [
            '{{employee_name}}'      => e($employee->full_name ?? ''),
            '{{employee_id}}'        => e($employee->employee_id ?? ''),
            '{{email}}'              => e($employee->personal_email ?? $employee->office_email ?? 'N/A'),
            '{{phone}}'              => e($employee->personal_mobile_number ?? $employee->home_phone ?? 'N/A'),
            '{{dob}}'                => $employee->date_of_birth ? Carbon::parse($employee->date_of_birth)->format('d M, Y') : 'N/A',
            '{{gender}}'             => e(ucfirst($employee->gender ?? 'N/A')),
            '{{marital_status}}'     => e(ucfirst($employee->marital_status ?? 'N/A')),
            '{{designation}}'        => e($employee->designation?->name ?? 'N/A'),
            '{{department}}'         => e($employee->department?->name ?? 'N/A'),
            '{{branch}}'             => e($employee->branch?->name ?? 'N/A'),
            '{{reporting_manager}}'  => e($employee->reportingManager?->full_name ?? 'N/A'),
            '{{joining_date}}'       => $employee->date_of_joining ? Carbon::parse($employee->date_of_joining)->format('d M, Y') : 'N/A',
            '{{last_working_day}}'   => (isset($employee->relieving_date) && $employee->relieving_date) ? Carbon::parse($employee->relieving_date)->format('d M, Y') : Carbon::today()->format('d M, Y'),
            '{{employment_status}}'  => e(ucfirst(str_replace('_', ' ', $employee->employee_stage ?? $employee->employment_type ?? 'active'))),
            
            '{{company_name}}'       => e($company?->company_name ?? 'Company Name'),
            '{{company_logo}}'       => $company?->logo ? asset('storage/' . $company->logo) : '',
            '{{company_address}}'    => e($company?->address ?? 'Headquarters'),
            '{{company_email}}'      => e($company?->email ?? 'info@company.com'),
            '{{company_phone}}'      => e($company?->phone ?? 'N/A'),
            
            '{{current_date}}'       => Carbon::today()->format('d M, Y'),
            '{{issue_date}}'         => Carbon::today()->format('d M, Y'),
            '{{reference_number}}'   => e($refNo),
        ];

        // 2. Render Relational Tables
        $dictionary['{{education_table}}'] = $this->renderEducationTable($employee);
        $dictionary['{{experience_table}}'] = $this->renderExperienceTable($employee);
        $dictionary['{{skills_list}}'] = $this->renderSkillsList($employee);
        $dictionary['{{certifications_list}}'] = $this->renderCertificationsList($employee);

        // 3. Assemble Header, Body, and Footer
        $header = $template->header_content ?? '';
        $body = $template->body_content ?? '';
        $footer = $template->footer_content ?? '';

        $fullHtml = '';
        if ($header) {
            $fullHtml .= '<header class="doc-header mb-3">' . $header . '</header>';
        }
        $fullHtml .= '<main class="doc-body">' . $body . '</main>';
        if ($footer) {
            $fullHtml .= '<footer class="doc-footer mt-4 pt-3 border-top">' . $footer . '</footer>';
        }

        // Apply substitution
        foreach ($dictionary as $tag => $val) {
            $fullHtml = str_replace($tag, $val, $fullHtml);
        }

        // Wrap with CSS styling container
        $customCss = $template->css_styles ?? '';
        $wrapper = '<div class="generated-doc-container" style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; line-height: 1.6; padding: 30px; background: #ffffff;">';
        if ($customCss) {
            $wrapper .= '<style>' . $customCss . '</style>';
        }
        $wrapper .= $fullHtml . '</div>';

        return $wrapper;
    }

    private function renderEducationTable(Employee $employee): string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('employee_education_histories')) {
                return '<p class="text-muted fs-12">No educational qualification records on file.</p>';
            }

            $educations = \DB::table('employee_education_histories')
                ->where('employee_id', $employee->id)
                ->get();

            if ($educations->isEmpty()) {
                return '<p class="text-muted fs-12">No educational qualification records on file.</p>';
            }

            $html = '<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; margin: 10px 0; font-size: 13px;">';
            $html .= '<thead style="background-color: #f8fafc; font-weight: bold; text-align: left;">';
            $html .= '<tr><th>Degree / Qualification</th><th>Institution / University</th><th>Passing Year</th><th>Grade / Percentage</th></tr>';
            $html .= '</thead><tbody>';

            foreach ($educations as $edu) {
                $html .= '<tr>';
                $html .= '<td>' . e($edu->degree ?? $edu->qualification ?? 'Degree') . '</td>';
                $html .= '<td>' . e($edu->institution ?? $edu->university ?? 'University') . '</td>';
                $html .= '<td>' . e($edu->passing_year ?? 'N/A') . '</td>';
                $html .= '<td>' . e($edu->grade ?? $edu->percentage ?? 'N/A') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            return $html;
        } catch (\Throwable $e) {
            return '<p class="text-muted fs-12">No educational qualification records on file.</p>';
        }
    }

    private function renderExperienceTable(Employee $employee): string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('employee_employment_histories')) {
                return '<p class="text-muted fs-12">No past employment history records on file.</p>';
            }

            $experiences = \DB::table('employee_employment_histories')
                ->where('employee_id', $employee->id)
                ->get();

            if ($experiences->isEmpty()) {
                return '<p class="text-muted fs-12">No past employment history records on file.</p>';
            }

            $html = '<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; margin: 10px 0; font-size: 13px;">';
            $html .= '<thead style="background-color: #f8fafc; font-weight: bold; text-align: left;">';
            $html .= '<tr><th>Company / Organization</th><th>Designation / Role</th><th>From</th><th>To</th></tr>';
            $html .= '</thead><tbody>';

            foreach ($experiences as $exp) {
                $html .= '<tr>';
                $html .= '<td>' . e($exp->company_name ?? 'Company') . '</td>';
                $html .= '<td>' . e($exp->designation ?? 'Role') . '</td>';
                $html .= '<td>' . e(isset($exp->start_date) && $exp->start_date ? Carbon::parse($exp->start_date)->format('M Y') : 'N/A') . '</td>';
                $html .= '<td>' . e(isset($exp->end_date) && $exp->end_date ? Carbon::parse($exp->end_date)->format('M Y') : 'Present') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            return $html;
        } catch (\Throwable $e) {
            return '<p class="text-muted fs-12">No past employment history records on file.</p>';
        }
    }

    private function renderSkillsList(Employee $employee): string
    {
        $skills = $employee->skill_set ?? $employee->skills ?? [];
        if (is_string($skills)) {
            $skills = json_decode($skills, true) ?: array_filter(array_map('trim', explode(',', $skills)));
        }

        if (empty($skills)) {
            return '<p class="text-muted fs-12">N/A</p>';
        }

        $items = array_map(fn($s) => '<span style="display: inline-block; background: #e2e8f0; color: #334155; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-right: 6px; margin-bottom: 6px; font-weight: 600;">' . e(trim($s)) . '</span>', (array)$skills);
        return '<div>' . implode('', $items) . '</div>';
    }

    private function renderCertificationsList(Employee $employee): string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('employee_certifications')) {
                return '<p class="text-muted fs-12">N/A</p>';
            }

            $certs = \DB::table('employee_certifications')
                ->where('employee_id', $employee->id)
                ->get();

            if ($certs->isEmpty()) {
                return '<p class="text-muted fs-12">N/A</p>';
            }

            $html = '<ul style="padding-left: 20px; font-size: 13px;">';
            foreach ($certs as $c) {
                $html .= '<li><strong>' . e($c->name ?? 'Certification') . '</strong> - Issued by ' . e($c->issued_by ?? 'N/A') . ' (' . e($c->year ?? 'N/A') . ')</li>';
            }
            $html .= '</ul>';
            return $html;
        } catch (\Throwable $e) {
            return '<p class="text-muted fs-12">N/A</p>';
        }
    }
}
