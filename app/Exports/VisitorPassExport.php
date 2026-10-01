<?php

namespace App\Exports;

use App\Domains\Visitor\Models\VisitorPass;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class VisitorPassExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = VisitorPass::where('tenant_id', $this->tenantId)
            ->with(['visitor', 'host', 'belongings']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Purpose Filter
        if (!empty($this->filters['purpose'])) {
            $query->where('purpose', $this->filters['purpose']);
        }

        // 3. Host Filter
        if (!empty($this->filters['host_user_id'])) {
            $query->where('host_user_id', $this->filters['host_user_id']);
        }

        // 4. Date Range Filters
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        // 5. Search keyword
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('pass_number', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%")
                  ->orWhere('gate_number', 'like', "%{$search}%")
                  ->orWhereHas('visitor', function ($vQ) use ($search) {
                      $vQ->where('full_name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%")
                         ->orWhere('company_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        return $query->latest()->get();
    }

    public static function availableColumns(): array
    {
        return [
            'pass_number'         => 'Pass Number',
            'visitor_name'        => 'Visitor Name',
            'phone'               => 'Phone Number',
            'email'               => 'Email Address',
            'company_name'        => 'Company / Organization',
            'designation'         => 'Designation',
            'id_proof_type'       => 'ID Proof Type',
            'id_proof_number'     => 'ID Proof Number',
            'host_name'           => 'Host (Employee)',
            'purpose'             => 'Purpose of Visit',
            'gate_number'         => 'Gate / Entry Point',
            'entry_type'          => 'Entry Type',
            'status'              => 'Status',
            'fee_amount'          => 'Pass Fee (' . active_currency_symbol() . ')',
            'expected_arrival_at' => 'Expected Arrival',
            'check_in_at'         => 'Check-in Time',
            'check_out_at'        => 'Check-out Time',
            'notes'               => 'Security Notes',
            'created_at'          => 'Created Date',
        ];
    }

    public function getActiveColumns(): array
    {
        $all = static::availableColumns();
        if (!empty($this->filters['columns']) && is_array($this->filters['columns'])) {
            $selected = array_values(array_intersect(array_keys($all), $this->filters['columns']));
            if (!empty($selected)) {
                return $selected;
            }
        }
        return array_keys($all);
    }

    public function headings(): array
    {
        $all = static::availableColumns();
        $headers = [];
        foreach ($this->getActiveColumns() as $key) {
            $headers[] = $all[$key] ?? $key;
        }
        return $headers;
    }

    public function map($pass): array
    {
        $values = [
            'pass_number'         => $pass->pass_number,
            'visitor_name'        => $pass->visitor?->full_name ?? '—',
            'phone'               => $pass->visitor?->phone ?? '—',
            'email'               => $pass->visitor?->email ?? '—',
            'company_name'        => $pass->visitor?->company_name ?? '—',
            'designation'         => $pass->visitor?->designation ?? '—',
            'id_proof_type'       => $pass->visitor?->id_proof_type ?? '—',
            'id_proof_number'     => $pass->visitor?->id_proof_number ?? '—',
            'host_name'           => $pass->host?->name ?? 'Direct Reception',
            'purpose'             => $pass->purpose ?? 'Meeting',
            'gate_number'         => $pass->gate_number ?? 'Gate 1',
            'entry_type'          => $pass->entry_type ?? 'Walk-in',
            'status'              => $pass->status ?? 'Expected',
            'fee_amount'          => (float)($pass->fee_amount ?? 0),
            'expected_arrival_at' => $pass->expected_arrival_at ? $pass->expected_arrival_at->format('Y-m-d H:i') : '—',
            'check_in_at'         => $pass->check_in_at ? $pass->check_in_at->format('Y-m-d H:i') : '—',
            'check_out_at'        => $pass->check_out_at ? $pass->check_out_at->format('Y-m-d H:i') : '—',
            'notes'               => $pass->notes ?? '—',
            'created_at'          => $pass->created_at ? $pass->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
