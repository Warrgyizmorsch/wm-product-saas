<?php

namespace App\Exports;

use App\Domains\Inventory\Models\Warehouse;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class WarehouseExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = Warehouse::where('tenant_id', $this->tenantId)->with('vendor');

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Type Filter
        if (!empty($this->filters['type'])) {
            $query->where('type', $this->filters['type']);
        }

        // 3. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // 4. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'name';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'code', 'type', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'name'       => 'Warehouse / Location Name',
            'code'       => 'Warehouse Code',
            'type'       => 'Warehouse Type',
            'vendor_name'=> 'Subcontractor / Vendor',
            'address'    => 'Full Address',
            'is_default' => 'Is Primary Default',
            'status'     => 'Status',
            'created_at' => 'Created Date',
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

    public function map($wh): array
    {
        $values = [
            'name'       => $wh->name,
            'code'       => $wh->code ?? '—',
            'type'       => ucfirst((string)($wh->type ?? 'Standard')),
            'vendor_name'=> $wh->vendor?->name ?? '—',
            'address'    => $wh->address ?? '—',
            'is_default' => $wh->is_default ? 'Yes' : 'No',
            'status'     => ucfirst((string)$wh->status),
            'created_at' => $wh->created_at ? $wh->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
