<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\DispatchOrderItem;
use App\Domains\Sales\Repositories\DispatchOrderRepository;
use App\Domains\Inventory\Models\Warehouse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DispatchOrderController extends Controller
{
    public function __construct(
        private readonly DispatchOrderRepository $dispatchRepo
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', DispatchOrder::class);

        $dispatches = DispatchOrder::with('salesOrder.customer', 'materialRequirement')
            ->latest()
            ->get();

        $pendingDOs = MaterialRequirement::with('salesOrder.customer')
            ->whereNotIn('id', DispatchOrder::pluck('material_requirement_id'))
            ->whereNotIn('status', ['Cancelled', 'Delivered'])
            ->latest()
            ->take(5)
            ->get();

        return view('modules.sales.dispatches.index', compact('dispatches', 'pendingDOs'));
    }

    public function create(): View
    {
        $this->authorize('create', DispatchOrder::class);

        $warehouses = Warehouse::where('status', 'active')->orderBy('name')->get();

        $pendingDOs = MaterialRequirement::with('salesOrder.customer')
            ->whereNotIn('status', ['Cancelled'])
            ->latest()
            ->get();

        return view('modules.sales.dispatches.create', compact('warehouses', 'pendingDOs'));
    }

    public function pendingMaterialRequirements(Request $request): JsonResponse
    {
        $this->authorize('create', DispatchOrder::class);

        $materialRequirements = MaterialRequirement::with([
            'salesOrder.customer',
            'items.product',
            'items.warehouse',
        ])
        ->whereNotIn('status', ['Cancelled'])
        ->latest()
        ->get();

        $result = $materialRequirements->map(function ($requirement) {
            $itemsData = $requirement->items->map(function ($item) {
                $alreadyDispatched = DispatchOrderItem::whereHas('dispatchOrder', function ($q) {
                    $q->where('status', '!=', 'Cancelled');
                })
                ->where('material_requirement_item_id', $item->id)
                ->sum('quantity');

                $remainingQty = max(0, (float) $item->quantity - (float) $alreadyDispatched);

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'sku' => $item->product?->sku ?? '',
                    'warehouse_id' => $item->warehouse_id,
                    'warehouse_name' => $item->warehouse?->name ?? 'Main Warehouse',
                    'ordered_qty' => (float) $item->quantity,
                    'dispatched_qty' => (float) $alreadyDispatched,
                    'remaining_qty' => $remainingQty,
                ];
            })->filter(fn ($i) => $i['remaining_qty'] > 0)->values();

            return [
                'id' => $requirement->id,
                'requirement_number' => $requirement->requirement_number,
                'sales_order_number' => $requirement->salesOrder?->sales_order_number ?? 'N/A',
                'customer_name' => $requirement->salesOrder?->customer?->name ?? 'N/A',
                'items' => $itemsData,
            ];
        })->filter(fn ($do) => count($do['items']) > 0)->values();

        return response()->json(['data' => $result]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DispatchOrder::class);

        $validated = $request->validate([
            'material_requirement_id' => ['required', 'exists:material_requirements,id'],
            'vehicle_number'          => ['nullable', 'string', 'max:100'],
            'driver_name'             => ['nullable', 'string', 'max:100'],
            'driver_phone'            => ['nullable', 'string', 'max:50'],
            'shipping_agent'          => ['nullable', 'string', 'max:100'],
            'dispatch_date'           => ['required', 'date'],
            'notes'                   => ['nullable', 'string'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.material_requirement_item_id' => ['required', 'exists:material_requirement_items,id'],
            'items.*.product_id'      => ['required', 'exists:products,id'],
            'items.*.warehouse_id'    => ['required', 'exists:warehouses,id'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.0001'],
        ]);

        $req = MaterialRequirement::findOrFail($validated['material_requirement_id']);

        $count = DispatchOrder::whereYear('created_at', now()->year)->count() + 1;
        $dispatchNumber = 'DISP-' . now()->format('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($validated, $req, $dispatchNumber) {
            $dispatch = DispatchOrder::create([
                'tenant_id' => $req->tenant_id,
                'material_requirement_id' => $req->id,
                'sales_order_id' => $req->sales_order_id,
                'customer_id' => $req->salesOrder?->customer_id,
                'dispatch_number' => $dispatchNumber,
                'dispatch_date' => $validated['dispatch_date'],
                'status' => 'Pending',
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'driver_phone' => $validated['driver_phone'] ?? null,
                'shipping_agent' => $validated['shipping_agent'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                DispatchOrderItem::create([
                    'dispatch_order_id' => $dispatch->id,
                    'material_requirement_item_id' => $item['material_requirement_item_id'],
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'quantity' => $item['quantity'],
                    'status' => 'Pending',
                ]);
            }
        });

        return redirect()->route('sales.dispatches.index')->with('success', "Dispatch Order {$dispatchNumber} created successfully.");
    }

    public function show(int $id): View
    {
        $dispatch = $this->dispatchRepo->find($id);
        if (!$dispatch) abort(404);
        $this->authorize('view', $dispatch);

        return view('modules.sales.dispatches.show', compact('dispatch'));
    }
}
