<?php

namespace App\Exports;

use App\Domains\CRM\Models\Lead;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LeadExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;
        return Lead::where('tenant_id', $tenantId)->with(['owner'])->orderBy('id', 'desc')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Lead Number',
            'Lead Type',
            'Company Name',
            'GSTIN',
            'Company Email',
            'Company Phone',
            'Contact Person',
            'Designation',
            'Contact Email',
            'Contact Phone',
            'Lead Owner',
            'Product',
            'Expected Amount',
            'Expected Sale Date',
            'Requirement',
            'Industry Type',
            'Source',
            'Priority',
            'Segment',
            'Country',
            'State',
            'City',
            'Address',
            'Status',
            'Created At'
        ];
    }

    /**
     * @param Lead $lead
     * @return array
     */
    public function map($lead): array
    {
        return [
            $lead->id,
            $lead->lead_number ?: ('LD-' . str_pad($lead->id, 4, '0', STR_PAD_LEFT)),
            strtoupper($lead->lead_type ?: 'B2B'),
            $lead->company_name,
            $lead->gstin,
            $lead->company_email,
            $lead->company_phone,
            $lead->contact_person,
            $lead->designation,
            $lead->email,
            $lead->phone,
            $lead->owner?->name ?? 'N/A',
            $lead->product_names ?? 'N/A',
            $lead->expected_amount,
            $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : null,
            $lead->requirement,
            $lead->industry_type,
            $lead->source,
            $lead->priority,
            $lead->segment,
            $lead->country,
            $lead->state,
            $lead->city,
            $lead->address,
            $lead->status,
            $lead->created_at ? $lead->created_at->format('Y-m-d H:i') : null,
        ];
    }
}
