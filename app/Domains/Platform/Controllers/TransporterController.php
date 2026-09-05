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
            ->with(['materialRequirement.salesOrder.customer', 'customer', 'items.warehouse'])
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

        $tenantId = require_tenant_id();

        // Fetch actual Vendor Bills linked to this transporter / vendor / dispatches
        $vendorId = $transporter->vendor_id ?? $transporter->id;
        $dispatchIds = $allDispatches->pluck('id')->toArray();

        $hasDispatchIdCol = \Illuminate\Support\Facades\Schema::hasColumn('vendor_bills', 'dispatch_order_id');

        $actualBills = \App\Domains\Purchase\Models\VendorBill::where('tenant_id', $tenantId)
            ->where(function ($q) use ($vendorId, $dispatchIds, $hasDispatchIdCol) {
                $q->where('vendor_id', $vendorId);
                if ($hasDispatchIdCol && !empty($dispatchIds)) {
                    $q->orWhereIn('dispatch_order_id', $dispatchIds);
                }
            })
            ->when($hasDispatchIdCol, function($q) {
                return $q->with(['dispatchOrder']);
            })
            ->latest('bill_date')
            ->get();

        // Build Freight Bills Collection from Actual Posted Bills
        $freightBills = collect();

        foreach ($actualBills as $bill) {
            $amt = (float) $bill->grand_total;
            $paidAmt = (float) $bill->paid_amount;
            $balanceDue = (float) $bill->balance_due;
            $status = strtolower($bill->status);

            $billUrl = \Illuminate\Support\Facades\Route::has('purchase.bills.show')
                ? route('purchase.bills.show', $bill->id)
                : '#';

            $freightBills->push([
                'id'           => 'bill_' . $bill->id,
                'bill_number'  => $bill->bill_number ?? $bill->vendor_invoice_number,
                'reference'    => $bill->dispatchOrder ? ('Dispatch #' . ($bill->dispatchOrder->dispatch_number ?? $bill->dispatchOrder->id)) : ($bill->notes ?: 'Transporter Service Bill'),
                'type'         => $bill->dispatch_order_id ? 'Dispatch Freight Bill' : 'Procurement/Service Bill',
                'date'         => $bill->bill_date,
                'due_date'     => $bill->due_date,
                'total_amount' => $amt,
                'paid_amount'  => $paidAmt,
                'balance_due'  => $balanceDue,
                'status'       => $status, // 'paid', 'unpaid', 'partially_paid'
                'url'          => $billUrl,
            ]);
        }

        // Unbilled Dispatches tracking (Dispatches with freight amount > 0 but NO VendorBill created yet)
        $billedDispatchIds = $actualBills->pluck('dispatch_order_id')->filter()->toArray();
        $unbilledDispatches = collect();

        foreach ($allDispatches as $dispatch) {
            $freight = (float) $dispatch->freight_amount;
            if ($freight > 0 && !in_array($dispatch->id, $billedDispatchIds)) {
                $createUrl = route('purchase.bills.create-service', [
                    'mode'              => 'outbound',
                    'dispatch_order_id' => $dispatch->id,
                ]);

                $unbilledDispatches->push([
                    'id'               => $dispatch->id,
                    'dispatch_number'  => $dispatch->dispatch_number ?? ('DISP-' . $dispatch->id),
                    'dispatch_date'    => $dispatch->dispatch_date ?? $dispatch->created_at,
                    'lr_number'        => $dispatch->lr_number ?: 'N/A',
                    'vehicle_number'   => $dispatch->vehicle_number ?: 'N/A',
                    'freight_amount'   => $freight,
                    'create_bill_url'  => $createUrl,
                ]);
            }
        }

        $freightBills = $freightBills->sortByDesc('date')->values();

        $totalBillsCount   = $freightBills->count();
        $paidBillsCount    = $freightBills->where('status', 'paid')->count();
        $pendingBillsCount = $freightBills->whereIn('status', ['unpaid', 'partially_paid', 'pending', 'posted'])->count();

        // Build Ledger Entries Array ONLY from Actual Posted Vendor Bills
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
        $unbilledFreightTotal = (float) $unbilledDispatches->sum('freight_amount');

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
            'unbilledDispatches',
            'unbilledFreightTotal',
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
