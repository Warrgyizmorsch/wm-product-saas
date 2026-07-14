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
        return Lead::with(['owner', 'product'])->orderBy('id', 'desc')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Call Date',
            'Company Name',
            'Contact Person',
            'Email',
            'Phone',
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
            $lead->call_date ? $lead->call_date->format('Y-m-d H:i') : null,
            $lead->company_name,
            $lead->contact_person,
            $lead->email,
            $lead->phone,
            $lead->owner?->name ?? 'N/A',
            $lead->product?->name ?? 'N/A',
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
