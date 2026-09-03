<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Models\Transporter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransporterController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Transporter::class);

        $tenantId = require_tenant_id();

        $transporters = Transporter::where('tenant_id', $tenantId)
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                return $q->where('name', 'like', "%{$search}%")
                    ->orWhere('transporter_id', 'like', "%{$search}%")
                    ->orWhere('gstin', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(15);

        return view('modules.platform.transporters.index', compact('transporters'));
    }

    public function create(): View
    {
        $this->authorize('create', Transporter::class);

        $tenantId = require_tenant_id();
        $count = Transporter::where('tenant_id', $tenantId)->count() + 1;
        $autoCode = 'TRP-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        return view('modules.platform.transporters.create', compact('autoCode'));
    }

    public function show(Transporter $transporter): View
    {
        $this->authorize('view', $transporter);

        $dispatches = $transporter->dispatchOrders()
            ->with(['materialRequirement.salesOrder.customer', 'warehouse'])
            ->latest('dispatch_date')
            ->paginate(15);

        $allDispatches = $transporter->dispatchOrders()->get();

        // Landed Cost Freight Expenses
        $landedCostExpenses = collect();
        if (class_exists(\App\Domains\Purchase\Models\LandedCostExpense::class)) {
            $landedCostExpenses = \App\Domains\Purchase\Models\LandedCostExpense::with(['landedCostVoucher', 'vendorBill'])
                ->where(function ($q) use ($transporter) {
                    $q->where('vendor_id', $transporter->id)
                      ->orWhere('description', 'like', "%{$transporter->name}%");
                })
                ->latest()
                ->get();
        }

        // Build Freight Bills Collection (Paid vs Pending Tracking)
        $freightBills = collect();

        foreach ($landedCostExpenses as $expense) {
            $bill = $expense->vendorBill;
            $amt = (float) ($expense->total_with_tax > 0 ? $expense->total_with_tax : $expense->amount);
            $paidAmt = $bill ? (float) $bill->paid_amount : 0.00;
            $balanceDue = $bill ? (float) $bill->balance_due : $amt;
            $status = $bill ? strtolower($bill->status) : ($balanceDue <= 0 ? 'paid' : 'unpaid');

            $billUrl = '#';
            if ($bill && \Illuminate\Support\Facades\Route::has('purchase.bills.show')) {
                $billUrl = route('purchase.bills.show', $bill->id);
            } elseif ($expense->landed_cost_voucher_id && \Illuminate\Support\Facades\Route::has('purchase.landed-costs.show')) {
                $billUrl = route('purchase.landed-costs.show', $expense->landed_cost_voucher_id);
            }

            $freightBills->push([
                'id'           => 'lc_' . $expense->id,
                'bill_number'  => $bill ? ($bill->bill_number ?? $bill->vendor_invoice_number) : ('BILL-' . date('Y', strtotime($expense->created_at)) . '-' . str_pad($expense->id, 6, '0', STR_PAD_LEFT)),
                'reference'    => 'Landed Cost Head: ' . ($expense->cost_head ?: 'Freight'),
                'type'         => 'Procurement Freight',
                'date'         => $expense->created_at,
                'due_date'     => $bill ? $bill->due_date : null,
                'total_amount' => $amt,
                'paid_amount'  => $paidAmt,
                'balance_due'  => $balanceDue,
                'status'       => $status, // 'paid', 'unpaid', 'partially_paid'
                'url'          => $billUrl,
            ]);
        }

        foreach ($allDispatches as $dispatch) {
            $freight = (float) $dispatch->freight_amount;
            if ($freight > 0) {
                $status = ($dispatch->status === 'delivered' && !empty($dispatch->is_freight_paid)) ? 'paid' : 'unpaid';
                $paidAmt = $status === 'paid' ? $freight : 0.00;
                $balanceDue = $freight - $paidAmt;

                $freightBills->push([
                    'id'           => 'do_' . $dispatch->id,
                    'bill_number'  => 'FRT-' . ($dispatch->dispatch_number ?? $dispatch->id),
                    'reference'    => 'Dispatch #' . ($dispatch->dispatch_number ?? $dispatch->id),
                    'type'         => 'Dispatch Freight',
                    'date'         => $dispatch->dispatch_date ?? $dispatch->created_at,
                    'due_date'     => null,
                    'total_amount' => $freight,
                    'paid_amount'  => $paidAmt,
                    'balance_due'  => $balanceDue,
                    'status'       => $status,
                    'url'          => \Illuminate\Support\Facades\Route::has('inventory.dispatches.show') ? route('inventory.dispatches.show', $dispatch->id) : '#',
                ]);
            }
        }

        $freightBills = $freightBills->sortByDesc('date')->values();

        $totalBillsCount   = $freightBills->count();
        $paidBillsCount    = $freightBills->where('status', 'paid')->count();
        $pendingBillsCount = $freightBills->whereIn('status', ['unpaid', 'partially_paid', 'pending', 'posted'])->count();

        // Build Ledger Entries Array for Transporter Freight Payable Running Balance
        $ledgerEntries = collect();

        foreach ($freightBills as $fb) {
            $ledgerEntries->push([
                'date'        => $fb['date'],
                'reference'   => $fb['bill_number'],
                'type'        => $fb['type'],
                'description' => $fb['reference'],
                'credit'      => $fb['total_amount'], // Credit increases Freight Payable
                'debit'       => $fb['paid_amount'],   // Debit reduces Freight Payable
                'status'      => $fb['status'],
                'url'         => $fb['url'],
            ]);
        }

        $ledgerEntries = $ledgerEntries->sortBy('date')->values();

        // Calculate Running Freight Payable Balance
        $runningBalance = 0.00;
        $ledgerWithBalance = $ledgerEntries->map(function ($entry) use (&$runningBalance) {
            $runningBalance += ($entry['credit'] - $entry['debit']);
            $entry['running_balance'] = $runningBalance;
            return $entry;
        });

        $totalFreightBilled = (float) $freightBills->sum('total_amount');
        $totalPaid          = (float) $freightBills->sum('paid_amount');
        $outstandingPayable = (float) $freightBills->sum('balance_due');

        $stats = [
            'total_dispatches'     => $allDispatches->count(),
            'in_transit'           => $allDispatches->where('status', 'in_transit')->count(),
            'delivered'            => $allDispatches->where('status', 'delivered')->count(),
            'pending'              => $allDispatches->whereIn('status', ['draft', 'pending', 'packed', 'ready_for_dispatch'])->count(),
            'total_freight_booked' => $totalFreightBilled,
            'total_paid'           => $totalPaid,
            'outstanding_payable'  => $outstandingPayable,
            'total_bills_count'    => $totalBillsCount,
            'paid_bills_count'     => $paidBillsCount,
            'pending_bills_count'  => $pendingBillsCount,
            'total_gross_weight'   => (float) $allDispatches->sum('gross_weight'),
            'total_net_weight'     => (float) $allDispatches->sum('net_weight'),
        ];

        return view('modules.platform.transporters.show', compact(
            'transporter',
            'dispatches',
            'landedCostExpenses',
            'freightBills',
            'ledgerWithBalance',
            'stats'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Transporter::class);

        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'code'                  => 'nullable|string|max:50',
            'transporter_id'        => 'nullable|string|max:50',
            'gstin'                 => 'nullable|string|max:20',
            'pan_number'            => 'nullable|string|max:20',
            'phone'                 => 'nullable|string|max:30',
            'email'                 => 'nullable|email|max:150',
            'address'               => 'nullable|string',
            'city'                  => 'nullable|string|max:100',
            'state'                 => 'nullable|string|max:100',
            'pincode'               => 'nullable|string|max:20',
            'status'                => 'nullable|string|in:active,inactive',
            'tds_section'           => 'nullable|string|max:50',
            'tds_rate'              => 'nullable|numeric',
            'has_194c_declaration'  => 'nullable|boolean',
            'declaration_reference' => 'nullable|string|max:100',
            'sac_code'              => 'nullable|string|max:20',
            'transport_mode'        => 'nullable|string|max:50',
            'fleet_type'            => 'nullable|string|max:100',
            'serviceable_zones'     => 'nullable|string',
            'bank_name'             => 'nullable|string|max:150',
            'branch_name'           => 'nullable|string|max:150',
            'account_name'          => 'nullable|string|max:150',
            'account_number'        => 'nullable|string|max:50',
            'ifsc_code'             => 'nullable|string|max:20',
            'payment_terms'         => 'nullable|string|max:100',
            'opening_balance'       => 'nullable|numeric',
            'contact_person_name'   => 'nullable|string|max:150',
            'contact_person_phone'  => 'nullable|string|max:30',
            'contact_person_email'  => 'nullable|email|max:150',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = $validated['status'] ?? 'active';

        Transporter::create($validated);

        return redirect()->route('platform.transporters.index')
            ->with('success', 'Transporter master created successfully.');
    }

    public function update(Request $request, Transporter $transporter): RedirectResponse
    {
        $this->authorize('update', $transporter);

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'code'                  => 'nullable|string|max:50',
            'transporter_id'        => 'nullable|string|max:50',
            'gstin'                 => 'nullable|string|max:20',
            'pan_number'            => 'nullable|string|max:20',
            'phone'                 => 'nullable|string|max:30',
            'email'                 => 'nullable|email|max:150',
            'address'               => 'nullable|string',
            'city'                  => 'nullable|string|max:100',
            'state'                 => 'nullable|string|max:100',
            'pincode'               => 'nullable|string|max:20',
            'status'                => 'required|string|in:active,inactive',
            'tds_section'           => 'nullable|string|max:50',
            'tds_rate'              => 'nullable|numeric',
            'has_194c_declaration'  => 'nullable|boolean',
            'declaration_reference' => 'nullable|string|max:100',
            'sac_code'              => 'nullable|string|max:20',
            'transport_mode'        => 'nullable|string|max:50',
            'fleet_type'            => 'nullable|string|max:100',
            'serviceable_zones'     => 'nullable|string',
            'bank_name'             => 'nullable|string|max:150',
            'branch_name'           => 'nullable|string|max:150',
            'account_name'          => 'nullable|string|max:150',
            'account_number'        => 'nullable|string|max:50',
            'ifsc_code'             => 'nullable|string|max:20',
            'payment_terms'         => 'nullable|string|max:100',
            'opening_balance'       => 'nullable|numeric',
            'contact_person_name'   => 'nullable|string|max:150',
            'contact_person_phone'  => 'nullable|string|max:30',
            'contact_person_email'  => 'nullable|email|max:150',
        ]);

        $transporter->update($validated);

        return redirect()->route('platform.transporters.index')
            ->with('success', 'Transporter master updated successfully.');
    }

    public function destroy(Transporter $transporter): RedirectResponse
    {
        $this->authorize('delete', $transporter);

        $transporter->delete();

        return redirect()->route('platform.transporters.index')
            ->with('success', 'Transporter deleted successfully.');
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $this->authorize('create', Transporter::class);

        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:50',
            'transporter_id' => 'nullable|string|max:50',
            'transport_mode' => 'nullable|string|max:50',
            'gstin'          => 'nullable|string|max:20',
            'pan_number'     => 'nullable|string|max:20',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:150',
            'city'           => 'nullable|string|max:100',
            'state'          => 'nullable|string|max:100',
        ]);

        if (empty($validated['code'])) {
            $count = Transporter::where('tenant_id', $tenantId)->count() + 1;
            $validated['code'] = 'TRP-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        }

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'active';

        $transporter = Transporter::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transporter added successfully.',
            'transporter' => [
                'id' => $transporter->id,
                'name' => $transporter->name,
                'code' => $transporter->code,
                'transporter_id' => $transporter->transporter_id,
                'gstin' => $transporter->gstin,
            ],
        ]);
    }
}
