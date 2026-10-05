<?php

namespace App\Domains\Visitor\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Visitor\Models\VisitorPass;
use App\Domains\Visitor\Services\VisitorService;
use Illuminate\Http\Request;

class VisitorPassController extends Controller
{
    public function __construct(
        protected readonly VisitorService $visitorService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', VisitorPass::class);
        [$tenantId, $companyId, $branchId] = $this->visitorService->resolveTenantContext();

        $search = $request->input('search');
        $status = $request->input('status');
        $purpose = $request->input('purpose');
        $hostUserId = $request->input('host_user_id');
        $date = $request->input('date');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = VisitorPass::where('tenant_id', $tenantId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($purpose, fn($q) => $q->where('purpose', $purpose))
            ->when($hostUserId, fn($q) => $q->where('host_user_id', $hostUserId))
            ->when($date === 'today', fn($q) => $q->whereDate('created_at', today()))
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('pass_number', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhere('gate_number', 'like', "%{$search}%")
                        ->orWhereHas('visitor', function ($vQ) use ($search) {
                            $vQ->where('full_name', 'like', "%{$search}%")
                               ->orWhere('phone', 'like', "%{$search}%")
                               ->orWhere('company_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('host', function ($hQ) use ($search) {
                            $hQ->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->with(['visitor', 'host', 'belongings'])
            ->latest();

        $passes = $query->paginate(15)->withQueryString();

        $hosts = \App\Models\User::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email']);

        $counts = [
            'all'         => VisitorPass::where('tenant_id', $tenantId)->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count(),
            'checked_in'  => VisitorPass::where('tenant_id', $tenantId)->when($branchId, fn($q) => $q->where('branch_id', $branchId))->where('status', 'Checked-In')->whereNull('check_out_at')->count(),
            'expected'    => VisitorPass::where('tenant_id', $tenantId)->when($branchId, fn($q) => $q->where('branch_id', $branchId))->where('status', 'Expected')->count(),
            'checked_out' => VisitorPass::where('tenant_id', $tenantId)->when($branchId, fn($q) => $q->where('branch_id', $branchId))->where('status', 'Checked-Out')->count(),
        ];

        return view('modules.visitor.passes.index', compact('passes', 'hosts', 'search', 'status', 'purpose', 'hostUserId', 'date', 'dateFrom', 'dateTo', 'counts'));
    }

    public function create()
    {
        $this->authorize('create', VisitorPass::class);
        return redirect()->route('visitor.index');
    }

    public function store(Request $request)
    {
        $this->authorize('create', VisitorPass::class);
        [$tenantId, $companyId, $branchId] = $this->visitorService->resolveTenantContext();

        $validated = $request->validate([
            'full_name'                 => 'required|string|max:255',
            'phone'                     => 'required|string|max:30',
            'email'                     => 'nullable|email|max:255',
            'company_name'              => 'nullable|string|max:255',
            'designation'               => 'nullable|string|max:100',
            'visitor_type'              => 'nullable|string|in:Client,Vendor,Candidate,Service,Guest',
            'id_proof_type'             => 'nullable|string|max:50',
            'id_proof_number'           => 'nullable|string|max:100',
            'id_verification_status'    => 'nullable|string|in:Pending,Verified,Exempted,Failed',
            'photo_url'                 => 'nullable|string',
            'host_user_id'              => 'nullable|exists:users,id',
            'department_id'             => 'nullable|integer',
            'purpose'                   => 'required|string|max:100',
            'entry_type'                => 'nullable|string|max:50',
            'gate_number'               => 'nullable|string|max:50',
            'fee_amount'                => 'nullable|numeric|min:0',
            'expected_duration_minutes' => 'nullable|integer|min:5|max:1440',
            'accompanying_count'        => 'nullable|integer|min:0|max:50',
            'accompanying_names'        => 'nullable|string|max:1000',
            'vehicle_type'              => 'nullable|string|max:50',
            'vehicle_number'            => 'nullable|string|max:50',
            'parking_slot'              => 'nullable|string|max:50',
            'badge_number'              => 'nullable|string|max:50',
            'badge_printed'             => 'nullable|boolean',
            'nda_safety_acknowledged'   => 'nullable|boolean',
            'restricted_area_access'    => 'nullable|boolean',
            'gate_pass_reference'       => 'nullable|string|max:100',
            'item_type'                 => 'nullable|string|max:100',
            'serial_number'             => 'nullable|string|max:100',
            'is_returnable'             => 'nullable|boolean',
            'belonging_gate_pass'       => 'nullable|string|max:100',
            'expected_arrival_at'       => 'nullable|date',
            'check_in_now'              => 'nullable|boolean',
            'status'                    => 'nullable|string|in:Expected,Arrived,Waiting Approval,Approved,Checked-In',
            'notes'                     => 'nullable|string|max:1000',
        ]);

        if (!empty($validated['item_type'])) {
            $validated['belongings'] = [
                [
                    'item_type'        => $validated['item_type'],
                    'serial_number'    => $validated['serial_number'] ?? null,
                    'is_returnable'    => isset($validated['is_returnable']) ? (bool)$validated['is_returnable'] : true,
                    'gate_pass_number' => $validated['belonging_gate_pass'] ?? null,
                ]
            ];
        }

        $pass = $this->visitorService->createPass($validated, $tenantId, $companyId, $branchId);

        return redirect()->route('visitor.passes.show', $pass->id)->with('success', __('visitor.pass_created_success'));
    }

    public function show(VisitorPass $pass)
    {
        $this->authorize('view', $pass);
        $pass->load(['visitor', 'host', 'belongings', 'linkedLead']);
        
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $hosts = \App\Models\User::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email']);
        $products = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)->with(['primaryImage', 'images'])->orderBy('name')->get();

        return view('modules.visitor.passes.show', compact('pass', 'hosts', 'products'));
    }

    public function convertToLead(Request $request, VisitorPass $pass)
    {
        $this->authorize('update', $pass);

        if ($pass->source_module === 'crm' && $pass->source_reference_id) {
            return redirect()->route('crm.leads.show', $pass->source_reference_id)
                ->with('info', "Pass #{$pass->pass_number} is already linked to CRM Lead #{$pass->source_reference_no}");
        }

        $validated = $request->validate([
            'lead_owner_id'               => 'nullable|exists:users,id',
            'lead_type'                   => 'nullable|string|in:hot,warm,cold',
            'priority'                    => 'nullable|string|in:Low,Medium,High,Urgent',
            'product_ids'                 => 'nullable|array',
            'product_ids.*'               => 'integer',
            'product_items'               => 'nullable|array',
            'product_items.*.product_id'  => 'nullable|integer',
            'product_items.*.quantity'    => 'nullable|numeric|min:0.01',
            'requirement'                 => 'nullable|string|max:2000',
            'next_followup_date'          => 'nullable|date',
        ]);

        $lead = $this->visitorService->convertToLead($pass, $validated);

        return redirect()->route('crm.leads.show', $lead->id)
            ->with('success', __('visitor.lead_converted_success') . " ({$lead->lead_number})");
    }

    public function checkIn(VisitorPass $pass)
    {
        $this->authorize('checkIn', $pass);

        if ($pass->status === 'Waiting Approval') {
            return redirect()->back()->with('error', 'Host approval is pending. You cannot check-in until the host approves this pass.');
        }

        if ($pass->status === 'Rejected') {
            return redirect()->back()->with('error', 'This visitor pass was rejected by the host and cannot be checked-in.');
        }

        $pass->update([
            'status'      => 'Checked-In',
            'check_in_at' => now(),
        ]);

        $this->visitorService->notifyHost($pass, 'checked_in');

        return redirect()->back()->with('success', 'Visitor checked in successfully.');
    }

    public function checkOut(VisitorPass $pass)
    {
        $this->authorize('checkOut', $pass);
        $pass->update([
            'status'       => 'Checked-Out',
            'check_out_at' => now(),
        ]);

        $this->visitorService->notifyHost($pass, 'checked_out');

        return redirect()->back()->with('success', 'Visitor checked out successfully.');
    }
}
