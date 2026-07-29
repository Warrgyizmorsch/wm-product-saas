<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Repositories\DispatchOrderRepository;
use App\Domains\Sales\Services\DispatchOrderService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DispatchOrderController extends Controller
{
    public function __construct(
        private readonly DispatchOrderService $dispatchService,
        private readonly DispatchOrderRepository $dispatchRepo
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DispatchOrder::class);

        $dispatches = $this->dispatchRepo->getPaginated($request->all(), 15);

        return view('modules.sales.dispatches.index', compact('dispatches'));
    }

    public function create(): View
    {
        $this->authorize('create', DispatchOrder::class);

        $warehouses = Warehouse::where('status', 'active')->orderBy('name')->get();
        $pendingDOs = $this->dispatchRepo->getAllPendingMaterialRequirements();

        return view('modules.sales.dispatches.create', compact('warehouses', 'pendingDOs'));
    }

    public function pendingMaterialRequirements(Request $request): JsonResponse
    {
        $this->authorize('create', DispatchOrder::class);

        $result = $this->dispatchService->getPendingMaterialRequirementsFormatted();

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

        try {
            $dispatch = $this->dispatchService->createDispatchOrder($validated, Auth::id() ?? 1);
            return redirect()->route('sales.dispatches.index')->with('success', "Dispatch Order {$dispatch->dispatch_number} created successfully.");
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        $dispatch = $this->dispatchRepo->find($id);
        if (!$dispatch) abort(404);
        $this->authorize('view', $dispatch);

        return view('modules.sales.dispatches.show', compact('dispatch'));
    }

    public function confirm(int $id): RedirectResponse
    {
        $dispatch = $this->dispatchRepo->find($id);
        if (!$dispatch) abort(404);
        $this->authorize('update', $dispatch);

        try {
            $dispatch = $this->dispatchService->confirmDispatchOrder($dispatch);
            return redirect()->route('sales.dispatches.show', $dispatch->id)->with('success', "Dispatch Order {$dispatch->dispatch_number} confirmed and stock deducted successfully.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
