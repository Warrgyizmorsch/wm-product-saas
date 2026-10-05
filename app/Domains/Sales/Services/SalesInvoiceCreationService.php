<?php

namespace App\Domains\Sales\Services;

use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Product;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesInvoiceCreationService
{
    /**
     * Create a standard Draft Sales Invoice with Sales-owned numbering, tax calculation, and persistence.
     *
     * @param array $invoiceData [
     *     'tenant_id'     => int,
     *     'company_id'    => int|null,
     *     'branch_id'     => int|null,
     *     'customer_id'   => int,
     *     'project_id'    => int|null,
     *     'sales_order_id'=> int|null,
     *     'invoice_date'  => string,
     *     'due_date'      => string,
     *     'payment_terms' => string|null,
     *     'notes'         => string|null,
     *     'tax_type'      => string|null,
     *     'discount_type' => string|null,
     *     'gst_type'      => string|null,
     *     'items'         => array,
     * ]
     * @return Invoice (Created in 'Draft' status)
     */
    public function createDraftInvoice(array $invoiceData): Invoice
    {
        $tenantId = (int) ($invoiceData['tenant_id'] ?? require_tenant_id());
        $customerId = (int) ($invoiceData['customer_id'] ?? 0);

        if ($customerId <= 0) {
            throw new InvalidArgumentException("Customer selection is required to create a Sales Invoice.");
        }

        $customer = Customer::where('tenant_id', $tenantId)->find($customerId);
        if (!$customer) {
            throw new InvalidArgumentException("Customer not found for the specified tenant.");
        }

        $items = $invoiceData['items'] ?? [];
        if (empty($items)) {
            throw new InvalidArgumentException("At least one line item is required to create a Sales Invoice.");
        }

        return DB::transaction(function () use ($invoiceData, $tenantId, $customerId, $customer, $items) {
            $companyId = $invoiceData['company_id'] ?? $customer->company_id ?? null;
            $branchId = $invoiceData['branch_id'] ?? $customer->branch_id ?? null;

            // 1. Resolve GST Type (Intra-state vs Inter-state)
            $gstType = $invoiceData['gst_type'] ?? $this->resolveGstType($customer, $companyId, $tenantId);
            $taxType = $invoiceData['tax_type'] ?? 'item_wise_tax';
            $discountType = $invoiceData['discount_type'] ?? 'without_discount';

            // 2. Sequential Invoice Numbering
            $invoiceNumber = $invoiceData['invoice_number'] ?? $this->generateNextInvoiceNumber($tenantId);

            // 3. Process line items and tax calculations
            $subtotal = 0.0;
            $totalTaxAmount = 0.0;
            $totalDiscountAmount = 0.0;
            $preparedLineItems = [];

            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_price'] ?? 0);
                $disc = ($discountType === 'item_wise') ? (float) ($item['discount'] ?? 0) : 0.0;

                $productId = $item['product_id'] ?? null;
                $product = $productId ? Product::where('tenant_id', $tenantId)->find($productId) : null;

                $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : (float) ($product?->gst_rate ?? 0);
                if ($taxType === 'without_tax') {
                    $taxRate = 0.0;
                }

                $lineSubtotal = round($qty * $price, 2);
                $lineTaxable = max(0, $lineSubtotal - $disc);
                $lineTax = round($lineTaxable * ($taxRate / 100), 2);

                $lineCgstPercent = 0.0;
                $lineSgstPercent = 0.0;
                $lineIgstPercent = 0.0;
                $lineCgstAmt = 0.0;
                $lineSgstAmt = 0.0;
                $lineIgstAmt = 0.0;

                if ($lineTax > 0 || $taxRate > 0) {
                    if ($gstType === 'igst') {
                        $lineIgstPercent = $taxRate;
                        $lineIgstAmt = $lineTax;
                    } else {
                        $lineCgstPercent = round($taxRate / 2, 2);
                        $lineSgstPercent = round($taxRate / 2, 2);
                        $lineCgstAmt = round($lineTax / 2, 2);
                        $lineSgstAmt = round($lineTax - $lineCgstAmt, 2);
                    }
                }

                $subtotal += $lineSubtotal;
                $totalDiscountAmount += $disc;
                $totalTaxAmount += $lineTax;

                $preparedLineItems[] = [
                    'product_id'                   => $productId,
                    'sales_order_item_id'          => $item['sales_order_item_id'] ?? null,
                    'material_requirement_item_id' => $item['material_requirement_item_id'] ?? null,
                    'warehouse_id'                 => $item['warehouse_id'] ?? null,
                    'item_name'                    => $item['item_name'] ?? ($product?->name ?? 'Service Item'),
                    'description'                  => $item['description'] ?? null,
                    'quantity'                     => $qty,
                    'unit_price'                   => $price,
                    'discount'                     => $disc,
                    'tax_rate'                     => $taxRate,
                    'tax_amount'                   => $lineTax,
                    'cgst_percent'                 => $lineCgstPercent,
                    'sgst_percent'                 => $lineSgstPercent,
                    'igst_percent'                 => $lineIgstPercent,
                    'cgst_amount'                  => $lineCgstAmt,
                    'sgst_amount'                  => $lineSgstAmt,
                    'igst_amount'                  => $lineIgstAmt,
                    'subtotal'                     => $lineSubtotal,
                    'total_amount'                 => $lineTaxable + $lineTax,
                ];
            }

            $cgstAmount = 0.0;
            $sgstAmount = 0.0;
            $igstAmount = 0.0;

            if ($totalTaxAmount > 0) {
                if ($gstType === 'igst') {
                    $igstAmount = $totalTaxAmount;
                } else {
                    $cgstAmount = round($totalTaxAmount / 2, 2);
                    $sgstAmount = round($totalTaxAmount - $cgstAmount, 2);
                }
            }

            $grandTotal = max(0, $subtotal - $totalDiscountAmount + $totalTaxAmount);

            // 4. Persist Draft Invoice
            $invoice = Invoice::create([
                'tenant_id'       => $tenantId,
                'company_id'      => $companyId,
                'branch_id'       => $branchId,
                'customer_id'     => $customerId,
                'project_id'      => $invoiceData['project_id'] ?? null,
                'sales_order_id'  => $invoiceData['sales_order_id'] ?? null,
                'invoice_number'  => $invoiceNumber,
                'invoice_date'    => $invoiceData['invoice_date'] ?? now()->toDateString(),
                'due_date'        => $invoiceData['due_date'] ?? now()->addDays(30)->toDateString(),
                'payment_terms'   => $invoiceData['payment_terms'] ?? null,
                'status'          => 'Draft',
                'discount_type'   => $discountType,
                'tax_type'        => $taxType,
                'order_tax_rate'  => 0,
                'adjustment'      => 0,
                'subtotal'        => $subtotal,
                'tax_amount'      => $totalTaxAmount,
                'gst_type'        => $gstType,
                'cgst_amount'     => $cgstAmount,
                'sgst_amount'     => $sgstAmount,
                'igst_amount'     => $igstAmount,
                'discount_amount' => $totalDiscountAmount,
                'freight_terms'   => 'To Pay',
                'freight_amount'  => 0,
                'total_amount'    => $grandTotal,
                'amount_paid'     => 0,
                'balance_due'     => $grandTotal,
                'notes'           => $invoiceData['notes'] ?? null,
            ]);

            // 5. Persist Line Items
            foreach ($preparedLineItems as $line) {
                $line['invoice_id'] = $invoice->id;
                InvoiceItem::create($line);
            }

            return $invoice->load(['items.product', 'customer', 'project']);
        });
    }

    /**
     * Generate the next tenant-sequential invoice number (e.g. INV-00001).
     */
    public function generateNextInvoiceNumber(int $tenantId): string
    {
        $latest = Invoice::where('tenant_id', $tenantId)->orderBy('id', 'desc')->first();
        $nextSeq = 1;

        if ($latest && preg_match('/INV-(\d+)/', (string) $latest->invoice_number, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        } elseif ($latest) {
            $nextSeq = (int) $latest->id + 1;
        }

        $candidate = 'INV-' . str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);

        // Ensure absolute uniqueness across tenant
        while (Invoice::where('tenant_id', $tenantId)->where('invoice_number', $candidate)->exists()) {
            $nextSeq++;
            $candidate = 'INV-' . str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }

    /**
     * Resolve GST type (cgst_sgst for Intra-state, igst for Inter-state) based on customer and company state.
     */
    public function resolveGstType(Customer $customer, ?int $companyId, int $tenantId): string
    {
        $company = $companyId
            ? DB::table('companies')->where('id', $companyId)->first()
            : DB::table('companies')->where('tenant_id', $tenantId)->first();
        $companyState = strtolower(trim((string) ($company?->state ?? '')));
        $customerState = strtolower(trim((string) ($customer->billing_state ?? $customer->state ?? '')));

        if (!empty($companyState) && !empty($customerState)) {
            return ($companyState === $customerState) ? 'cgst_sgst' : 'igst';
        }

        // Check GSTIN state code prefix (first 2 digits)
        $companyGstin = substr(trim((string) ($company?->tax_number ?? $company?->gstin ?? '')), 0, 2);
        $customerGstin = substr(trim((string) ($customer->gstin ?? $customer->tax_number ?? '')), 0, 2);

        if (!empty($companyGstin) && !empty($customerGstin) && ctype_digit($companyGstin) && ctype_digit($customerGstin)) {
            return ($companyGstin === $customerGstin) ? 'cgst_sgst' : 'igst';
        }

        return 'cgst_sgst';
    }
}
