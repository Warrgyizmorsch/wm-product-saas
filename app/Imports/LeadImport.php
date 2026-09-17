<?php

namespace App\Imports;

use App\Domains\CRM\Models\Lead;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class LeadImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $totalCount = 0;
    public int $successCount = 0;
    public int $failedCount = 0;
    public array $failedRows = [];

    public function collection(Collection $rows)
    {
        $tenantId  = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;
        $companyId = company_id() ?? 1;
        $branchId  = branch_id() ?? null;

        foreach ($rows as $index => $row) {
            $rowArray = $row->toArray();

            // Normalize key aliases from excel headers
            $companyName  = trim((string)($rowArray['company_name'] ?? ($rowArray['company'] ?? '')));
            $companyEmail = trim((string)($rowArray['company_email'] ?? ''));
            $companyPhone = trim((string)($rowArray['company_phone'] ?? ''));
            $contactPerson= trim((string)($rowArray['contact_person'] ?? ($rowArray['contact_name'] ?? ($rowArray['name'] ?? ''))));
            $designation  = trim((string)($rowArray['designation'] ?? ($rowArray['role'] ?? '')));
            $contactEmail = trim((string)($rowArray['email'] ?? ($rowArray['contact_email'] ?? ($rowArray['personal_email'] ?? ''))));
            $contactPhone = trim((string)($rowArray['phone'] ?? ($rowArray['contact_phone'] ?? ($rowArray['mobile'] ?? ''))));

            $hasCompanyName  = !empty($companyName);
            $hasCompanyEmail = !empty($companyEmail);
            $isB2B           = $hasCompanyName || $hasCompanyEmail;

            // Re-assign normalized keys for validator
            $dataToValidate = [
                'company_name'   => $companyName ?: null,
                'gstin'          => $rowArray['gstin'] ?? null,
                'company_email'  => $companyEmail ?: null,
                'company_phone'  => $companyPhone ?: null,
                'contact_person' => $contactPerson ?: null,
                'designation'    => $designation ?: null,
                'email'          => $contactEmail ?: null,
                'phone'          => $contactPhone ?: null,
                'industry_type'  => $rowArray['industry_type'] ?? null,
                'source'         => $rowArray['source'] ?? null,
                'priority'       => $rowArray['priority'] ?? null,
                'segment'        => $rowArray['segment'] ?? null,
                'country'        => $rowArray['country'] ?? null,
                'state'          => $rowArray['state'] ?? null,
                'city'           => $rowArray['city'] ?? null,
                'address'        => $rowArray['address'] ?? null,
            ];

            // Conditional validation rules (identical to API)
            $rules = [
                'company_name'   => $isB2B ? 'required|string|max:255' : 'nullable|string|max:255',
                'gstin'          => 'nullable|string|max:100',
                'company_email'  => $isB2B ? 'required|email|max:255' : 'nullable|email|max:255',
                'company_phone'  => 'nullable|string|max:50',
                'contact_person' => $isB2B ? 'nullable|string|max:255' : 'required|string|max:255',
                'designation'    => 'nullable|string|max:255',
                'email'          => $isB2B ? 'nullable|email|max:255' : 'required|email|max:255',
                'phone'          => 'nullable|string|max:50',
                'industry_type'  => 'nullable|string|max:255',
                'source'         => 'nullable|string|max:255',
                'priority'       => 'nullable|string|in:Low,Medium,High',
                'segment'        => 'nullable|string|max:255',
                'country'        => 'nullable|string|max:255',
                'state'          => 'nullable|string|max:255',
                'city'           => 'nullable|string|max:255',
                'address'        => 'nullable|string',
            ];

            $messages = [
                'company_name.required'  => 'Company name is required when Company email is provided.',
                'company_email.required' => 'Company email is required when Company name is provided.',
                'contact_person.required' => 'Contact person name is required for B2C leads (when company details are not provided).',
                'email.required'          => 'Contact email is required for B2C leads (when company details are not provided).',
            ];

            $validator = Validator::make($dataToValidate, $rules, $messages);

            $this->totalCount++;
            $rowNumber = $index + 2; // +1 for 1-based index, +1 for Excel header row

            if ($validator->fails()) {
                $this->failedCount++;
                $this->failedRows[] = [
                    'row'    => $rowNumber,
                    'data'   => $rowArray,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            // Auto-detect lead_type: b2b if company_name exists, b2c otherwise
            $leadType = $hasCompanyName ? 'b2b' : 'b2c';

            // Parse expected_sale_date safely
            $expectedSaleDate = null;
            if (!empty($rowArray['expected_sale_date'])) {
                $val = $rowArray['expected_sale_date'];
                if (is_numeric($val)) {
                    try {
                        $expectedSaleDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
                    } catch (\Exception $e) {
                        $expectedSaleDate = null;
                    }
                } else {
                    try {
                        $expectedSaleDate = Carbon::parse($val);
                    } catch (\Exception $e) {
                        $expectedSaleDate = null;
                    }
                }
            }

            $ownerId = !empty($rowArray['lead_owner_id'])
                ? $rowArray['lead_owner_id']
                : (!empty($rowArray['lead_owner'])
                    ? $rowArray['lead_owner']
                    : (!empty($rowArray['owner_id'])
                        ? $rowArray['owner_id']
                        : (auth()->id() ?? 1)));

            try {
                $lead = new Lead([
                    'tenant_id'          => $tenantId,
                    'company_id'         => $companyId,
                    'branch_id'          => $branchId,
                    'lead_type'          => $leadType,
                    'company_name'       => $companyName ?: null,
                    'gstin'              => $dataToValidate['gstin'],
                    'company_email'      => $dataToValidate['company_email'],
                    'company_phone'      => $dataToValidate['company_phone'],
                    'contact_person'     => $dataToValidate['contact_person'],
                    'designation'        => $dataToValidate['designation'],
                    'email'              => $dataToValidate['email'],
                    'phone'              => $dataToValidate['phone'],
                    'expected_amount'    => null,
                    'expected_sale_date' => $expectedSaleDate,
                    'requirement'        => $rowArray['requirement'] ?? ($rowArray['requirements'] ?? null),
                    'industry_type'      => $dataToValidate['industry_type'],
                    'source'             => $dataToValidate['source'],
                    'priority'           => $dataToValidate['priority'],
                    'segment'            => $dataToValidate['segment'],
                    'country'            => $dataToValidate['country'],
                    'state'              => $dataToValidate['state'],
                    'city'               => $dataToValidate['city'],
                    'address'            => $dataToValidate['address'],
                    'status'             => 'New',
                    'lead_owner_id'      => $ownerId,
                    'call_date'          => now(),
                ]);

                $lead->save();
                $this->successCount++;
            } catch (\Exception $e) {
                $this->failedCount++;
                $this->failedRows[] = [
                    'row'    => $rowNumber,
                    'data'   => $rowArray,
                    'errors' => [$e->getMessage()],
                ];
            }
        }
    }
}
