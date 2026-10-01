<?php

namespace App\Domains\Visitor\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Visitor\Models\Visitor;
use App\Domains\Visitor\Models\VisitorPass;
use App\Domains\Visitor\Services\VisitorService;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    public function __construct(
        protected readonly VisitorService $visitorService
    ) {}

    /**
     * Visitor Dashboard & Logs Overview
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Visitor::class);
        [$tenantId, $companyId, $branchId] = $this->visitorService->resolveTenantContext();

        $stats = $this->visitorService->getDashboardStats($tenantId, $branchId);

        $search = $request->input('search');
        $status = $request->input('status');
        $purpose = $request->input('purpose');
        $hostUserId = $request->input('host_user_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $passesQuery = VisitorPass::where('tenant_id', $tenantId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($purpose, fn($q) => $q->where('purpose', $purpose))
            ->when($hostUserId, fn($q) => $q->where('host_user_id', $hostUserId))
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

        $passes = $passesQuery->paginate(15)->withQueryString();
        $hosts = \App\Models\User::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email']);

        return view('modules.visitor.index', compact(
            'stats',
            'passes',
            'hosts',
            'search',
            'status',
            'purpose',
            'hostUserId',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Store a new visitor & generate pass
     */
    public function store(Request $request)
    {
        $this->authorize('create', Visitor::class);
        [$tenantId, $companyId, $branchId] = $this->visitorService->resolveTenantContext();

        $validated = $request->validate([
            'full_name'           => 'required|string|max:255',
            'phone'               => 'required|string|max:30',
            'email'               => 'nullable|email|max:255',
            'company_name'        => 'nullable|string|max:255',
            'designation'         => 'nullable|string|max:100',
            'id_proof_type'       => 'nullable|string|max:50',
            'id_proof_number'     => 'nullable|string|max:100',
            'photo_url'           => 'nullable|string',
            'host_user_id'        => 'nullable|exists:users,id',
            'purpose'             => 'required|string|max:100',
            'entry_type'          => 'nullable|string|max:50',
            'gate_number'         => 'nullable|string|max:50',
            'fee_amount'          => 'nullable|numeric|min:0',
            'item_type'           => 'nullable|string|max:100',
            'serial_number'       => 'nullable|string|max:100',
            'expected_arrival_at' => 'nullable|date',
            'check_in_now'        => 'nullable|boolean',
            'status'              => 'nullable|string|in:Expected,Waiting Approval,Approved,Checked-In',
            'notes'               => 'nullable|string|max:1000',
        ]);

        if (!empty($validated['item_type'])) {
            $validated['belongings'] = [
                [
                    'item_type'     => $validated['item_type'],
                    'serial_number' => $validated['serial_number'] ?? null,
                ]
            ];
        }

        $pass = $this->visitorService->createPass($validated, $tenantId, $companyId, $branchId);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('visitor.pass_created_success'),
                'data'    => $pass,
            ]);
        }

        return redirect()->route('visitor.index')->with('success', __('visitor.pass_created_success'));
    }

    /**
     * Check in visitor pass
     */
    public function checkIn(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->findOrFail($id);

        if ($pass->status === 'Waiting Approval') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => __('visitor.approval_required_checkin_blocked', [], null) ?? 'Host approval is pending. You cannot check-in until the host approves this pass.'], 422);
            }
            return redirect()->back()->with('error', 'Host approval is pending. You cannot check-in until the host approves this pass.');
        }

        if ($pass->status === 'Rejected') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'This visitor pass was rejected by the host and cannot be checked-in.'], 422);
            }
            return redirect()->back()->with('error', 'This visitor pass was rejected by the host and cannot be checked-in.');
        }

        $pass->update([
            'status'      => 'Checked-In',
            'check_in_at' => now(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => __('visitor.checked_in_success')]);
        }

        return redirect()->back()->with('success', __('visitor.checked_in_success'));
    }

    /**
     * Check out visitor pass
     */
    public function checkOut(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->findOrFail($id);

        $pass->update([
            'status'       => 'Checked-Out',
            'check_out_at' => now(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => __('visitor.checked_out_success')]);
        }

        return redirect()->back()->with('success', __('visitor.checked_out_success'));
    }

    /**
     * Look up existing visitor details by phone for fast auto-fill
     */
    public function lookup(Request $request)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $phone = trim($request->input('phone', ''));

        if (empty($phone) || strlen($phone) < 4) {
            return response()->json(['found' => false]);
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        $visitor = Visitor::where('tenant_id', $tenantId)
            ->where(function ($q) use ($phone, $cleanPhone) {
                $q->where('phone', $phone)
                  ->orWhere('phone', 'like', "{$phone}%");
                if (!empty($cleanPhone)) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["{$cleanPhone}%"]);
                }
            })
            ->withCount('passes')
            ->first();

        if (!$visitor) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'   => true,
            'visitor' => [
                'id'              => $visitor->id,
                'full_name'       => $visitor->full_name,
                'phone'           => $visitor->phone,
                'email'           => $visitor->email,
                'company_name'    => $visitor->company_name,
                'designation'     => $visitor->designation,
                'id_proof_type'   => $visitor->id_proof_type,
                'id_proof_number' => $visitor->id_proof_number,
                'photo_url'       => $visitor->photo_url,
                'total_visits'    => $visitor->passes_count,
                'is_blacklisted'  => (bool)$visitor->is_blacklisted,
                'blacklist_reason'=> $visitor->blacklist_reason,
            ],
        ]);
    }
}
