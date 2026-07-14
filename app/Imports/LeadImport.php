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

        return new Lead([
            'company_name' => $row['company_name'],
            'contact_person' => $row['contact_person'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'expected_amount' => $row['expected_amount'] ?? null,
            'expected_sale_date' => $expectedSaleDate,
            'requirement' => $row['requirement'] ?? ($row['requirements'] ?? null),
            'industry_type' => $row['industry_type'] ?? null,
            'source' => $row['source'] ?? null,
            'country' => $row['country'] ?? null,
            'state' => $row['state'] ?? null,
            'city' => $row['city'] ?? null,
            'address' => $row['address'] ?? null,
            'status' => !empty($row['status']) ? $row['status'] : 'New',
            'lead_owner_id' => auth()->id(),
            'call_date' => now(),
        ]);
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'company_name' => 'required|max:255',
            'contact_person' => 'nullable|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|max:50',
            'expected_amount' => 'nullable|numeric|min:0',
            'expected_sale_date' => 'nullable',
            'requirement' => 'nullable',
            'requirements' => 'nullable',
            'industry_type' => 'nullable|max:255',
            'source' => 'nullable|max:255',
            'country' => 'nullable|max:255',
            'state' => 'nullable|max:255',
            'city' => 'nullable|max:255',
            'address' => 'nullable',
            'status' => 'nullable|max:255',
        ];
    }
}
