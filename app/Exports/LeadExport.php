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
        return $this->query()->orderBy('id', 'desc')->get();
    }

    /**
     * The leads this user may export, unordered (callers stream it in chunks).
     */
    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        $tenantId = require_tenant_id();
        $query = Lead::where('tenant_id', $tenantId)->with(['owner']);

        $currentUser = auth()->user();
        if ($currentUser && !app(\App\Services\Access\AccessService::class)->allows($currentUser, 'crm.leads.view', ['tenant_id' => $tenantId])) {
            $query->where(function ($sq) use ($currentUser) {
                $sq->where('lead_owner_id', $currentUser->id)
                   ->orWhereHas('crmAccount', fn($aq) => $aq->where('owner_id', $currentUser->id))
                   ->orWhereHas('crmDeal.account', fn($aq) => $aq->where('owner_id', $currentUser->id));
            });
        }

        return $query;
    }

    /**
     * Writes every row as CSV to $handle, 1,000 leads at a time, so memory stays
     * flat however many leads the tenant has (the old in-memory export failed
     * with HTTP 500 at ~50k leads).
     *
     * @param resource $handle
     */
    public function streamCsv($handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accents correctly
        fputcsv($handle, $this->headings());

        foreach ($this->query()->lazyByIdDesc(1000) as $lead) {
            fputcsv($handle, array_map([self::class, 'csvSafe'], $this->map($lead)));
        }
    }

    /** Stops spreadsheet apps from treating a cell as a formula (CSV injection). */
    private static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
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
