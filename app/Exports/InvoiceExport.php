<?php

namespace App\Exports;

use App\Domains\Sales\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class InvoiceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = Invoice::where('tenant_id', $this->tenantId)
            ->with(['customer', 'salesOrder', 'allocations']);

        // 1. Status filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $status = $this->filters['status'];
            if ($status === 'paid') {
                $query->where('status', 'Paid');
            } elseif ($status === 'posted') {
                $query->whereIn('status', ['Posted', 'Sent']);
            } elseif ($status === 'draft') {
                $query->where('status', 'Draft');
            } elseif ($status === 'partially_paid') {
                $query->where('status', 'Partially Paid');
            } elseif ($status === 'cancelled') {
                $query->where('status', 'Cancelled');
            } else {
                $query->where('status', ucfirst($status));
            }
        }

        // 2. Customer filter
        if (!empty($this->filters['customer_id'])) {
            $query->where('customer_id', $this->filters['customer_id']);
        }

        // 3. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('invoice_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('invoice_date', '<=', $this->filters['date_to']);
        }

        // 4. Search keyword
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('irn', 'like', "%{$search}%")
                  ->orWhere('eway_bill_no', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('gstin', 'like', "%{$search}%");
                  });
            });
        }

        // 5. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'invoice_number'   => 'Invoice Number',
            'customer_name'    => 'Customer Name',
            'company_name'     => 'Company Name',
            'customer_gstin'   => 'Customer GSTIN',
            'invoice_date'     => 'Invoice Date',
            'due_date'         => 'Due Date',
            'payment_terms'    => 'Payment Terms',
            'gst_type'         => 'GST Treatment',
            'taxable_amount'   => 'Taxable Base (₹)',
            'tax_amount'       => 'GST Amount (₹)',
            'freight_amount'   => 'Freight Amount (₹)',
            'total_amount'     => 'Grand Total (₹)',
            'amount_paid'      => 'Amount Paid (₹)',
            'balance_due'      => 'Balance Due (₹)',
            'status'           => 'Invoice Status',
            'einvoice_status'  => 'E-Invoice Status',
            'ack_no'           => 'E-Invoice Ack No',
            'irn'              => 'IRN Hash',
            'eway_bill_no'     => 'E-Way Bill Number',
            'created_at'       => 'Created Date',
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

    public function map($invoice): array
    {
        $totalPaid = (float)($invoice->amount_paid ?: $invoice->allocations->sum('allocated_amount'));
        $balanceDue = max(0, (float)$invoice->total_amount - $totalPaid);

        $values = [
            'invoice_number'   => $invoice->invoice_number,
            'customer_name'    => $invoice->customer?->name ?? '—',
            'company_name'     => $invoice->customer?->company_name ?? '—',
            'customer_gstin'   => $invoice->customer?->gstin ?? '—',
            'invoice_date'     => $invoice->invoice_date ? date('Y-m-d', strtotime($invoice->invoice_date)) : '—',
            'due_date'         => $invoice->due_date ? date('Y-m-d', strtotime($invoice->due_date)) : '—',
            'payment_terms'    => \App\Domains\Platform\Models\PaymentTerm::getLabel($invoice->payment_terms ?: $invoice->salesOrder?->payment_terms),
            'gst_type'         => $invoice->gst_type === 'igst' ? 'IGST (Inter-State)' : 'CGST + SGST (Intra-State)',
            'taxable_amount'   => (float)($invoice->subtotal ?? 0),
            'tax_amount'       => (float)($invoice->tax_amount ?? 0),
            'freight_amount'   => (float)($invoice->freight_amount ?? 0),
            'total_amount'     => (float)($invoice->total_amount ?? 0),
            'amount_paid'      => $totalPaid,
            'balance_due'      => $balanceDue,
            'status'           => ucfirst((string)$invoice->status),
            'einvoice_status'  => $invoice->einvoice_status ?? 'Not Generated',
            'ack_no'           => $invoice->ack_no ?? '—',
            'irn'              => $invoice->irn ?? '—',
            'eway_bill_no'     => $invoice->eway_bill_no ?? '—',
            'created_at'       => $invoice->created_at ? $invoice->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
