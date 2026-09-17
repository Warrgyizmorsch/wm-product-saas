<?php

namespace Database\Seeders;

use App\Core\Branch\BranchContext;
use App\Core\Company\CompanyContext;
use App\Core\Tenant\TenantContext;
use App\Domains\CRM\Models\Customer;
use App\Domains\HRMS\Controllers\PayrollRunController;
use App\Domains\HRMS\Controllers\TravelExpenseController;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Purchase\Events\PurchaseReturnApproved;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\PurchaseReturnItem;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Services\VendorBillService;
use App\Domains\Sales\Events\InvoicePosted;
use App\Domains\Sales\Events\SalesReturnApproved;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\SalesReturn;
use App\Domains\Sales\Models\SalesReturnItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Phase 2 of the client-demo data build: Sales invoices/returns, Purchase
 * bills/returns, one Payroll run, and Expense Claims — all created through
 * the same Services/Controllers the UI uses, so the existing event-driven
 * Accounting auto-posting fires for real and GSTR-1/GSTR-3B/GST Summary/
 * AR & AP Aging show genuine, correctly-bucketed data.
 *
 * Idempotent: guarded by a `[DEMO2]` marker per section, so
 * `php artisan db:seed --class=AccountingSalesPurchasePayrollDemoSeeder`
 * is safe to re-run. Never mutates phase 1's data (AccountingVoucherDemoSeeder)
 * or any other pre-existing data.
 */
class AccountingSalesPurchasePayrollDemoSeeder extends Seeder
{
    private const MARK = '[DEMO2]';

    private int $tenantId;
    private int $companyId;
    private int $branchId;
    private User $adminUser;

    public function run(): void
    {
        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (!$tenant) {
            $this->command?->error('No tenant found — aborting.');
            return;
        }

        $this->tenantId = $tenant->id;

        $company = \App\Domains\HRMS\Models\Company::where('tenant_id', $this->tenantId)->first();
        $branch = \App\Domains\HRMS\Models\Branch::where('tenant_id', $this->tenantId)->first();
        $admin = User::where('tenant_id', $this->tenantId)->where('email', 'admin@example.com')->first()
            ?? User::where('tenant_id', $this->tenantId)->orderBy('id')->first();

        if (!$company || !$branch || !$admin) {
            $this->command?->error('Demo tenant is missing a company/branch/admin user — aborting.');
            return;
        }

        $this->companyId = $company->id;
        $this->branchId = $branch->id;
        $this->adminUser = $admin;

        app(TenantContext::class)->set($tenant);
        app(CompanyContext::class)->set($company);
        app(BranchContext::class)->set($branch);
        Auth::login($admin);

        $this->command?->info("Seeding Phase 2 demo data for tenant #{$this->tenantId} ({$tenant->slug})...");

        $customers = $this->ensureCustomers();
        $products = $this->salesProducts();

        if (Invoice::where('notes', 'like', self::MARK . '%')->exists()) {
            $this->command?->warn('Demo invoices already seeded — skipping Sales section.');
        } else {
            $this->seedSalesInvoicesAndReturn($customers, $products);
        }

        $vendors = Vendor::where('tenant_id', $this->tenantId)->orderBy('id')->get();
        $purchaseProducts = $this->purchaseProducts();

        if (VendorBill::where('notes', 'like', self::MARK . '%')->exists()) {
            $this->command?->warn('Demo vendor bills already seeded — skipping Purchase section.');
        } else {
            $this->seedPurchaseBillsAndReturn($vendors, $purchaseProducts);
        }

        if (PayrollRun::where('payroll_month', '2026-08')->exists()) {
            $this->command?->warn('Payroll run for 2026-08 already exists — skipping Payroll section.');
        } else {
            $this->seedPayroll();
        }

        $this->seedExpenseClaims();

        $this->command?->info('AccountingSalesPurchasePayrollDemoSeeder complete.');
    }

    // ------------------------------------------------------------------
    // Master data helpers
    // ------------------------------------------------------------------

    /** @return \Illuminate\Support\Collection<int, Customer> keyed by scenario */
    private function ensureCustomers(): array
    {
        $byName = fn (string $name) => Customer::where('tenant_id', $this->tenantId)->where('name', $name)->first();

        $bluewave = $byName('Bluewave Retail Pvt Ltd'); // gstin set (same-state B2B, code 27)
        $sunrise = $byName('Sunrise Traders'); // gstin set, treated as inter-state B2B here
        $greenfield = $byName('Greenfield Distributors'); // no gstin -> B2C

        $export = $byName('Colombo Overseas Trading (SEZ)');
        if (!$export) {
            Customer::$skipAccountAutoCreate = true;
            $export = Customer::create([
                'tenant_id' => $this->tenantId,
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'name' => 'Colombo Overseas Trading (SEZ)',
                'company_name' => 'Colombo Overseas Trading (SEZ)',
                'email' => 'accounts@colombo-overseas.example',
                'gstin' => '33SEZAB0001A1Z9',
                'status' => 'active',
                'opening_balance' => 0,
            ]);
            Customer::$skipAccountAutoCreate = false;
        }

        return compact('bluewave', 'sunrise', 'greenfield', 'export');
    }

    /** @return array<string, Product> */
    private function salesProducts(): array
    {
        $byName = fn (string $name) => Product::where('tenant_id', $this->tenantId)->where('name', $name)->first();

        return [
            'leg' => $byName('Fabricated Steel Table Leg (750mm)'),
            'beam' => $byName('Horizontal Support Beam'),
            'frame' => $byName('Table Frame Assembly'),
            'top' => $byName('Engineered Wood Table Top'),
            'table' => $byName('Industrial Dining Table (6-Seater 1800x900mm)'),
        ];
    }

    /** @return array<string, Product> */
    private function purchaseProducts(): array
    {
        $byName = fn (string $name) => Product::where('tenant_id', $this->tenantId)->where('name', $name)->first();

        return [
            'pipe' => $byName('Steel Square Pipe Heavy Stock (50x50mm x 2mm)'),
            'board' => $byName('Engineered Wood Table Top Board (1800x900mm)'),
            'hardware' => $byName('Dining Table Fastener & Hardware Set'),
        ];
    }

    private function warehouse(): Warehouse
    {
        return Warehouse::where('tenant_id', $this->tenantId)->orderBy('id')->first();
    }

    // ------------------------------------------------------------------
    // Sales: Invoices (all 5 GST scenarios) + one Sales Return / Credit Note
    // ------------------------------------------------------------------

    private function seedSalesInvoicesAndReturn(array $customers, array $products): void
    {
        $wh = $this->warehouse();
        $seq = 1;
        $nextInvNumber = function () use (&$seq) {
            $latest = Invoice::latest('id')->first();
            $base = $latest ? (int) str_replace('INV-', '', $latest->invoice_number) : 0;
            return 'INV-' . str_pad($base + $seq++, 4, '0', STR_PAD_LEFT);
        };

        $scenarios = [
            // [label, customer, gst_type, tax_rate, invoice_date, due_date, settlement]
            ['B2B Intra-State (CGST+SGST)', $customers['bluewave'], 'cgst_sgst', 18, '2026-09-05', '2026-09-30', 'open'],       // not_due
            ['B2B Intra-State (CGST+SGST)', $customers['bluewave'], 'cgst_sgst', 18, '2026-08-10', '2026-08-30', 'partial'],   // 0-30
            ['B2B Inter-State (IGST)', $customers['sunrise'], 'igst', 18, '2026-07-01', '2026-07-25', 'open'],                 // 31-60
            ['B2B Inter-State (IGST)', $customers['sunrise'], 'igst', 18, '2026-08-15', '2026-09-05', 'paid'],
            ['SEZ / Export (Zero-Rated)', $customers['export'], 'igst', 0, '2026-06-20', '2026-07-01', 'open'],                // 61-90
            ['SEZ / Export (Zero-Rated)', $customers['export'], 'igst', 0, '2026-08-20', '2026-09-10', 'paid'],
            ['B2C Unregistered', $customers['greenfield'], 'cgst_sgst', 18, '2026-06-01', '2026-06-10', 'open'],               // 90+
            ['B2C Unregistered', $customers['greenfield'], 'cgst_sgst', 12, '2026-08-12', '2026-08-30', 'partial'],            // 0-30
            ['Nil-Rated / Exempt Goods', $customers['bluewave'], 'cgst_sgst', 0, '2026-07-05', '2026-07-25', 'open'],          // 31-60
            ['Nil-Rated / Exempt Goods', $customers['greenfield'], 'cgst_sgst', 0, '2026-08-01', '2026-08-20', 'paid'],
        ];

        $created = [];
        foreach ($scenarios as [$label, $customer, $gstType, $taxRate, $invDate, $dueDate, $settlement]) {
            $qty = 2;
            $product = $products['leg'];
            $price = (float) $product->selling_price ?: 900.0;

            $items = [[
                'product' => $product,
                'warehouse_id' => $wh->id,
                'quantity' => $qty,
                'unit_price' => $price,
                'tax_rate' => $taxRate,
            ]];

            $invoice = $this->createInvoice($customer->id, $items, $gstType, $invDate, $dueDate, $nextInvNumber(), $label);
            $this->applySettlement($invoice, $settlement);
            $created[$label][] = $invoice;
        }

        $this->command?->info('Seeded ' . count($scenarios) . ' demo Sales Invoices across 5 GST scenarios.');

        // One Sales Return -> Credit Note against the first B2B intra-state invoice
        $baseInvoice = $created['B2B Intra-State (CGST+SGST)'][0];
        $this->seedSalesReturn($baseInvoice, $wh);
    }

    private function createInvoice(int $customerId, array $items, string $gstType, string $invDate, string $dueDate, string $invNumber, string $label): Invoice
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;
        $cgstAmount = 0.0;
        $sgstAmount = 0.0;
        $igstAmount = 0.0;
        $itemRows = [];

        foreach ($items as $row) {
            $qty = (float) $row['quantity'];
            $price = (float) $row['unit_price'];
            $taxR = (float) $row['tax_rate'];

            $lineSubtotal = $qty * $price;
            $lineTax = $lineSubtotal * ($taxR / 100);

            $lineCgstPercent = 0.0;
            $lineSgstPercent = 0.0;
            $lineIgstPercent = 0.0;
            $lineCgstAmt = 0.0;
            $lineSgstAmt = 0.0;
            $lineIgstAmt = 0.0;

            if ($lineTax > 0) {
                if ($gstType === 'igst') {
                    $lineIgstPercent = $taxR;
                    $lineIgstAmt = $lineTax;
                    $igstAmount += $lineTax;
                } else {
                    $lineCgstPercent = round($taxR / 2, 2);
                    $lineSgstPercent = round($taxR / 2, 2);
                    $lineCgstAmt = round($lineTax / 2, 2);
                    $lineSgstAmt = round($lineTax - $lineCgstAmt, 2);
                    $cgstAmount += $lineCgstAmt;
                    $sgstAmount += $lineSgstAmt;
                }
            }

            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;

            $itemRows[] = [
                'product' => $row['product'],
                'warehouse_id' => $row['warehouse_id'],
                'quantity' => $qty,
                'unit_price' => $price,
                'tax_rate' => $taxR,
                'line_subtotal' => $lineSubtotal,
                'line_tax' => $lineTax,
                'cgst_percent' => $lineCgstPercent,
                'sgst_percent' => $lineSgstPercent,
                'igst_percent' => $lineIgstPercent,
                'cgst_amount' => $lineCgstAmt,
                'sgst_amount' => $lineSgstAmt,
                'igst_amount' => $lineIgstAmt,
            ];
        }

        $totalAmount = round($subtotal + $taxAmount, 2);

        $invoice = DB::transaction(function () use (
            $customerId, $invNumber, $invDate, $dueDate, $gstType,
            $subtotal, $taxAmount, $cgstAmount, $sgstAmount, $igstAmount, $totalAmount, $itemRows, $label
        ) {
            $invoice = Invoice::create([
                'customer_id' => $customerId,
                'invoice_number' => $invNumber,
                'invoice_date' => $invDate,
                'due_date' => $dueDate,
                'status' => 'Sent',
                'discount_type' => 'without_discount',
                'tax_type' => 'item_wise_tax',
                'order_tax_rate' => 0,
                'gst_type' => $gstType,
                'cgst_amount' => round($cgstAmount, 2),
                'sgst_amount' => round($sgstAmount, 2),
                'igst_amount' => round($igstAmount, 2),
                'discount_amount' => 0,
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($taxAmount, 2),
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'balance_due' => $totalAmount,
                'freight_terms' => 'To Pay',
                'freight_amount' => 0,
                'adjustment' => 0,
                'notes' => self::MARK . ' ' . $label,
            ]);

            foreach ($itemRows as $row) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $row['product']->id,
                    'warehouse_id' => $row['warehouse_id'],
                    'item_name' => $row['product']->name,
                    'description' => null,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'discount' => 0,
                    'tax_rate' => $row['tax_rate'],
                    'tax_amount' => round($row['line_tax'], 2),
                    'cgst_percent' => $row['cgst_percent'],
                    'sgst_percent' => $row['sgst_percent'],
                    'igst_percent' => $row['igst_percent'],
                    'cgst_amount' => $row['cgst_amount'],
                    'sgst_amount' => $row['sgst_amount'],
                    'igst_amount' => $row['igst_amount'],
                    'subtotal' => round($row['line_subtotal'], 2),
                    'total_amount' => round($row['line_subtotal'] + $row['line_tax'], 2),
                ]);
            }

            return $invoice;
        });

        event(new InvoicePosted($invoice));

        return $invoice->refresh();
    }

    private function applySettlement(Invoice $invoice, string $mode): void
    {
        if ($mode === 'paid') {
            $invoice->update(['status' => 'Paid', 'amount_paid' => $invoice->total_amount, 'balance_due' => 0]);
        } elseif ($mode === 'partial') {
            $half = round((float) $invoice->total_amount / 2, 2);
            $invoice->update([
                'status' => 'Partially Paid',
                'amount_paid' => $half,
                'balance_due' => round((float) $invoice->total_amount - $half, 2),
            ]);
        }
        // 'open' -> leave as Sent with full balance_due (already set on create)
    }

    private function seedSalesReturn(Invoice $invoice, Warehouse $wh): void
    {
        $item = $invoice->items()->first();
        if (!$item) {
            return;
        }

        $returnQty = 1.0;
        $latest = SalesReturn::latest('id')->first();
        $nextSeq = $latest ? (int) str_replace('RET-', '', $latest->return_number) + 1 : 1;
        $returnNumber = 'RET-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        $salesReturn = DB::transaction(function () use ($invoice, $item, $returnQty, $returnNumber, $wh) {
            $totalAmount = $returnQty * (float) $item->unit_price;

            $salesReturn = SalesReturn::create([
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'return_number' => $returnNumber,
                'return_date' => Carbon::parse($invoice->invoice_date)->addDays(5)->toDateString(),
                'status' => 'Pending',
                'reason' => self::MARK . ' Damaged goods returned by customer',
                'total_amount' => $totalAmount,
                'total_refund_amount' => $totalAmount,
            ]);

            SalesReturnItem::create([
                'sales_return_id' => $salesReturn->id,
                'invoice_item_id' => $item->id,
                'product_id' => $item->product_id,
                'warehouse_id' => $wh->id,
                'quantity' => $returnQty,
                'unit_price' => $item->unit_price,
                'total_amount' => $totalAmount,
            ]);

            return $salesReturn;
        });

        // Mirror SalesReturnController::approve() — restock inventory, adjust
        // invoice balance, mark Completed, then fire the approval event that
        // drives the Credit Note + COGS-reversal journal posting.
        DB::transaction(function () use ($salesReturn, $invoice) {
            foreach ($salesReturn->items as $item) {
                $product = Product::find($item->product_id);
                $costPrice = (float) ($product->cost_price ?: $item->unit_price);

                StockService::recordInflow(
                    $this->tenantId,
                    (int) $item->product_id,
                    (int) $item->warehouse_id,
                    (float) $item->quantity,
                    $costPrice,
                    'SalesReturn',
                    (int) $salesReturn->id
                );
            }

            $apply = min((float) $salesReturn->total_refund_amount, (float) $invoice->balance_due);
            $invoice->balance_due = max(0, (float) $invoice->balance_due - $apply);
            $invoice->status = $invoice->balance_due <= 0 ? 'Paid' : 'Partially Paid';
            $invoice->save();

            $salesReturn->update(['status' => 'Completed']);
        });

        event(new SalesReturnApproved($salesReturn));

        $this->command?->info("Seeded Sales Return {$salesReturn->return_number} -> Credit Note against Invoice {$invoice->invoice_number}.");
    }

    // ------------------------------------------------------------------
    // Purchase: Bills (intra/inter/reverse-charge) + one Purchase Return / Debit Note
    // ------------------------------------------------------------------

    private function seedPurchaseBillsAndReturn($vendors, array $products): void
    {
        $wh = $this->warehouse();
        $billService = app(VendorBillService::class);

        $vendorA = $vendors->get(0); // Acme Supplies Ltd
        $vendorB = $vendors->get(1) ?? $vendorA; // Apex Trade Corp

        $scenarios = [
            // [label, vendor, gst_type, tax_rate, bill_date, due_date, settlement]
            ['Intra-State (CGST+SGST)', $vendorA, 'cgst_sgst', 18, '2026-09-03', '2026-09-28', 'open'],     // not_due
            ['Intra-State (CGST+SGST)', $vendorA, 'cgst_sgst', 18, '2026-08-10', '2026-08-30', 'partial'],  // 0-30
            ['Inter-State (IGST)', $vendorB, 'igst', 18, '2026-07-01', '2026-07-25', 'open'],                // 31-60
            ['Inter-State (IGST)', $vendorB, 'igst', 18, '2026-08-15', '2026-09-05', 'paid'],
            ['Reverse Charge (RCM)', $vendorA, 'rcm_cgst_sgst', 5, '2026-06-15', '2026-07-01', 'open'],      // 61-90
            ['Reverse Charge (RCM)', $vendorB, 'rcm_cgst_sgst', 5, '2026-06-01', '2026-06-15', 'open'],      // 90+
        ];

        $created = [];
        foreach ($scenarios as [$label, $vendor, $gstType, $taxRate, $billDate, $dueDate, $settlement]) {
            $product = $products['pipe'];
            $qty = 20;
            $price = (float) $product->cost_price ?: 300.0;

            $validated = [
                'vendor_id' => $vendor->id,
                'vendor_bill_number' => null,
                'vendor_invoice_number' => 'VINV-' . strtoupper(substr(md5($label . $billDate . $vendor->id), 0, 8)),
                'tax_type' => 'item_wise_tax',
                'gst_type' => $gstType,
                'bill_date' => $billDate,
                'due_date' => $dueDate,
                'notes' => self::MARK . ' ' . $label,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                ]],
            ];

            $bill = $billService->storeBill($validated, $this->tenantId);
            $this->applyBillSettlement($bill, $settlement);
            $created[$label][] = $bill;
        }

        $this->command?->info('Seeded ' . count($scenarios) . ' demo Vendor Bills across intra/inter-state/RCM GST scenarios.');

        $baseBill = $created['Intra-State (CGST+SGST)'][0];
        $this->seedPurchaseReturn($baseBill, $wh, $products['pipe']);
    }

    private function applyBillSettlement(VendorBill $bill, string $mode): void
    {
        $total = (float) $bill->grand_total;
        if ($mode === 'paid') {
            $bill->update(['status' => 'Paid', 'paid_amount' => $total, 'due_amount' => 0]);
        } elseif ($mode === 'partial') {
            $half = round($total / 2, 2);
            $bill->update(['status' => 'Partially Paid', 'paid_amount' => $half, 'due_amount' => round($total - $half, 2)]);
        }
        // 'open' -> leave as Unpaid with full due_amount
    }

    private function seedPurchaseReturn(VendorBill $bill, Warehouse $wh, Product $product): void
    {
        $item = $bill->items()->first();
        if (!$item) {
            return;
        }

        // Ensure warehouse stock exists to draw down from (mirrors what a real
        // GRN receipt against this bill would already have put into stock —
        // VendorBillService::storeBill itself only posts the GL inventory
        // value, it does not move physical ProductWarehouseStock).
        StockService::recordInflow(
            $this->tenantId,
            (int) $item->product_id,
            (int) $wh->id,
            (float) $item->quantity,
            (float) $item->unit_rate,
            'VendorBill',
            (int) $bill->id
        );

        $returnQty = 2.0;
        $latest = PurchaseReturn::latest('id')->first();
        $nextSeq = $latest ? (int) str_replace('PRET-', '', $latest->return_number) + 1 : 1;
        $returnNumber = 'PRET-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        $purchaseReturn = DB::transaction(function () use ($bill, $item, $returnQty, $returnNumber, $wh) {
            $totalAmount = $returnQty * (float) $item->unit_rate;

            $purchaseReturn = PurchaseReturn::create([
                'vendor_id' => $bill->vendor_id,
                'vendor_bill_id' => $bill->id,
                'return_number' => $returnNumber,
                'return_date' => Carbon::parse($bill->bill_date)->addDays(5)->toDateString(),
                'status' => 'Pending',
                'reason' => self::MARK . ' Defective material returned to vendor',
                'total_amount' => $totalAmount,
                'total_refund_amount' => $totalAmount,
            ]);

            PurchaseReturnItem::create([
                'purchase_return_id' => $purchaseReturn->id,
                'vendor_bill_item_id' => $item->id,
                'product_id' => $item->product_id,
                'warehouse_id' => $wh->id,
                'quantity' => $returnQty,
                'unit_price' => $item->unit_rate,
                'total_amount' => $totalAmount,
            ]);

            return $purchaseReturn;
        });

        // Mirror PurchaseReturnController::approve()
        DB::transaction(function () use ($purchaseReturn, $bill) {
            foreach ($purchaseReturn->items as $item) {
                StockService::recordOutflow(
                    $this->tenantId,
                    (int) $item->product_id,
                    (int) $item->warehouse_id,
                    (float) $item->quantity,
                    'PurchaseReturn',
                    (int) $purchaseReturn->id
                );
            }

            $apply = min((float) $purchaseReturn->total_refund_amount, (float) $bill->due_amount);
            $bill->due_amount = max(0, (float) $bill->due_amount - $apply);
            $bill->status = $bill->due_amount <= 0.001 ? 'Paid' : 'Partially Paid';
            $bill->save();

            $purchaseReturn->update(['status' => 'Completed']);
        });

        event(new PurchaseReturnApproved($purchaseReturn));

        $this->command?->info("Seeded Purchase Return {$purchaseReturn->return_number} -> Debit Note against Bill {$bill->bill_number}.");
    }

    // ------------------------------------------------------------------
    // HRMS: Payroll Run
    // ------------------------------------------------------------------

    private function seedPayroll(): void
    {
        $controller = app(PayrollRunController::class);

        $request = Request::create('/hrms/payroll/run', 'POST', [
            'payroll_month' => '2026-08',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]);
        $request->setUserResolver(fn () => $this->adminUser);

        try {
            $controller->storeRun($request);
        } catch (\Throwable $e) {
            $this->command?->error('Payroll storeRun failed: ' . $e->getMessage());
            return;
        }

        $run = PayrollRun::where('payroll_month', '2026-08')->latest('id')->first();
        if (!$run) {
            $this->command?->error('Payroll run was not created — skipping payout.');
            return;
        }

        try {
            $controller->releasePayouts($run);
            $run->refresh();
            $this->command?->info("Seeded Payroll Run #{$run->id} for 2026-08, status={$run->status}.");
        } catch (\Throwable $e) {
            $this->command?->error('Payroll releasePayouts failed: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // HRMS: Expense Claims / Reports
    // ------------------------------------------------------------------

    private function seedExpenseClaims(): void
    {
        if (\App\Domains\HRMS\Models\ExpenseReport::where('title', 'like', self::MARK . '%')->exists()) {
            $this->command?->warn('Demo expense reports already seeded — skipping Expense section.');
            return;
        }

        $travelCat = ExpenseCategory::where('name', 'Travel')->first();
        $mealsCat = ExpenseCategory::where('name', 'Meals')->first();
        $accomCat = ExpenseCategory::firstOrCreate(
            ['tenant_id' => $this->tenantId, 'name' => 'Accommodation'],
            ['status' => true, 'code' => 'ACCOM']
        );

        $employees = Employee::where('tenant_id', $this->tenantId)->where('status', true)->orderBy('id')->get();
        if ($employees->isEmpty()) {
            $this->command?->warn('No active employees found — skipping Expense section.');
            return;
        }

        $controller = app(TravelExpenseController::class);

        // Note: the "Travel" category (id from $travelCat) carries an
        // ExpensePolicyRule with receipt_required=true and no threshold, so
        // every Travel claim needs a receipt file attached or the report
        // submission silently redirects back with a validation error (no
        // exception is thrown). Since this seeder attaches no files, Travel
        // claims are intentionally left out — Meals/Accommodation carry no
        // such requirement.
        $reports = [
            ['employee' => $employees->get(1) ?? $employees->first(), 'title' => self::MARK . ' Client Dinner Expenses - Aug 2026', 'claims' => [
                ['category_id' => $mealsCat?->id, 'date' => '2026-08-05', 'amount' => 850, 'tax' => 0, 'merchant' => 'Taj Restaurant', 'desc' => 'Client dinner'],
                ['category_id' => $mealsCat?->id, 'date' => '2026-08-06', 'amount' => 620, 'tax' => 0, 'merchant' => 'Spice Route Cafe', 'desc' => 'Team breakfast'],
            ]],
            ['employee' => $employees->get(2) ?? $employees->first(), 'title' => self::MARK . ' Vendor Audit Stay - Aug 2026', 'claims' => [
                ['category_id' => $accomCat?->id, 'date' => '2026-08-12', 'amount' => 4200, 'tax' => 0, 'merchant' => 'Hotel Grand Plaza', 'desc' => '2-night stay for vendor audit'],
            ]],
            ['employee' => $employees->get(3) ?? $employees->first(), 'title' => self::MARK . ' Team Offsite Meals - Sep 2026', 'claims' => [
                ['category_id' => $mealsCat?->id, 'date' => '2026-09-02', 'amount' => 2100, 'tax' => 0, 'merchant' => 'Spice Route Cafe', 'desc' => 'Team lunch'],
            ]],
        ];

        $count = 0;
        foreach ($reports as $def) {
            $employee = $def['employee'];
            $claims = array_values(array_filter($def['claims'], fn ($c) => !empty($c['category_id'])));
            if (!$employee || empty($claims)) {
                continue;
            }

            $storeRequest = Request::create('/hrms/travel-expense/report', 'POST', [
                'employee_id' => $employee->id,
                'travel_request_id' => null,
                'cash_advance_id' => null,
                'title' => $def['title'],
                'claims' => $claims,
            ]);
            $storeRequest->setUserResolver(fn () => $this->adminUser);

            try {
                $controller->storeExpenseReport($storeRequest);
            } catch (\Throwable $e) {
                $this->command?->error("Expense report '{$def['title']}' failed to create: " . $e->getMessage());
                continue;
            }

            $report = \App\Domains\HRMS\Models\ExpenseReport::where('title', $def['title'])->latest('id')->first();
            if (!$report) {
                continue;
            }

            try {
                // Mirror TravelExpenseController::submitExpenseReport()'s state
                // transition directly rather than calling the controller method:
                // that method also calls HrmsNotificationService::sendToHrAdmins(),
                // which queries a `roleModel` relation that does not exist on
                // App\Models\User — a pre-existing bug unrelated to this seeder.
                $report->update(['status' => 'submitted']);
                $report->claims()->update(['status' => 'submitted']);

                $approveRequest = Request::create('/hrms/travel-expense/report/approve', 'POST', [
                    'payout_channel' => 'accounting',
                ]);
                $approveRequest->setUserResolver(fn () => $this->adminUser);

                $controller->approveExpenseReport($approveRequest, $report);
                $controller->payExpenseReport($report);
                $count++;
            } catch (\Throwable $e) {
                $this->command?->error("Expense report '{$def['title']}' failed to approve/pay: " . $e->getMessage());
            }
        }

        $this->command?->info("Seeded {$count} demo Expense Reports (submitted, approved, paid).");
    }
}
