<?php

namespace App\Imports;

use App\Domains\CRM\Models\Lead;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Carbon;

class LeadImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Infer or format lead_type (b2b or b2c)
        $rawType = strtolower(trim((string)($row['lead_type'] ?? '')));
        if (in_array($rawType, ['b2b', 'b2c'], true)) {
            $leadType = $rawType;
        } else {
            if (!empty($row['company_name'])) {
                $leadType = 'b2b';
            } elseif (!empty($row['contact_person'])) {
                $leadType = 'b2c';
            } else {
                $leadType = 'b2b';
            }
        }

        // Parse date robustly (handles serialized excel date format or string date format)
        $expectedSaleDate = null;
        if (!empty($row['expected_sale_date'])) {
            $val = $row['expected_sale_date'];
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

        $ownerId = !empty($row['lead_owner_id'])
            ? $row['lead_owner_id']
            : (!empty($row['lead_owner'])
                ? $row['lead_owner']
                : (!empty($row['owner_id'])
                    ? $row['owner_id']
                    : (auth()->id() ?? 1)));

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        return new Lead([
            'tenant_id' => $tenantId,
            'company_id' => company_id() ?? 1,
            'branch_id' => branch_id() ?? null,
            'lead_type' => $leadType,
            'company_name' => $row['company_name'] ?? null,
            'gstin' => $row['gstin'] ?? null,
            'company_email' => $row['company_email'] ?? null,
            'company_phone' => $row['company_phone'] ?? null,
            'contact_person' => $row['contact_person'] ?? null,
            'designation' => $row['designation'] ?? null,
            'email' => $row['contact_email'] ?? ($row['email'] ?? null),
            'phone' => $row['contact_phone'] ?? ($row['phone'] ?? null),
            'expected_amount' => (isset($row['expected_amount']) && is_numeric($row['expected_amount'])) ? (float)$row['expected_amount'] : null,
            'expected_sale_date' => $expectedSaleDate,
            'requirement' => $row['requirement'] ?? ($row['requirements'] ?? null),
            'industry_type' => $row['industry_type'] ?? null,
            'source' => $row['source'] ?? null,
            'priority' => $row['priority'] ?? null,
            'segment' => $row['segment'] ?? null,
            'country' => $row['country'] ?? null,
            'state' => $row['state'] ?? null,
            'city' => $row['city'] ?? null,
            'address' => $row['address'] ?? null,
            'status' => !empty($row['status']) ? $row['status'] : 'New',
            'lead_owner_id' => $ownerId,
            'call_date' => now(),
        ]);
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'lead_type' => 'nullable|string',
            'company_name' => 'nullable|max:255',
            'gstin' => 'nullable|max:100',
            'company_email' => 'nullable|email|max:255',
            'company_phone' => 'nullable|max:50',
            'contact_person' => 'nullable|max:255',
            'designation' => 'nullable|max:255',
            'email' => 'nullable|email|max:255',
            'contact_email' => 'nullable|email|max:255',
            'phone' => 'nullable|max:50',
            'contact_phone' => 'nullable|max:50',
            'expected_amount' => 'nullable|numeric|min:0',
            'expected_sale_date' => 'nullable',
            'requirement' => 'nullable',
            'requirements' => 'nullable',
            'industry_type' => 'nullable|max:255',
            'source' => 'nullable|max:255',
            'priority' => 'nullable|max:255',
            'segment' => 'nullable|max:255',
            'country' => 'nullable|max:255',
            'state' => 'nullable|max:255',
            'city' => 'nullable|max:255',
            'address' => 'nullable',
            'status' => 'nullable|max:255',
        ];
    }
}
