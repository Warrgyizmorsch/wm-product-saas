<?php

namespace App\Domains\Visitor\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Visitor\Models\Visitor;
use App\Domains\Visitor\Models\VisitorPass;
use App\Domains\Visitor\Services\VisitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorApiController extends Controller
{
    public function __construct(
        protected readonly VisitorService $visitorService
    ) {}

    /**
     * GET /api/visitor/visitors
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Visitor::class);

        [$tenantId] = $this->visitorService->resolveTenantContext();
        $visitors = Visitor::where('tenant_id', $tenantId)->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $visitors,
        ]);
    }

    /**
     * POST /api/visitor/visitors
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Visitor::class);

        [$tenantId, $companyId, $branchId] = $this->visitorService->resolveTenantContext();

        $validated = $request->validate([
            'full_name'    => 'required|string|max:255',
            'phone'        => 'required|string|max:30',
            'email'        => 'nullable|email|max:255',
            'company_name' => 'nullable|string|max:255',
        ]);

        $visitor = Visitor::create(array_merge($validated, [
            'tenant_id'  => $tenantId,
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'status'     => 'Active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Visitor registered successfully.',
            'data'    => $visitor,
        ], 201);
    }

    /**
     * GET /api/visitor/passes
     */
    public function passes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VisitorPass::class);

        [$tenantId] = $this->visitorService->resolveTenantContext();
        $passes = VisitorPass::where('tenant_id', $tenantId)->with(['visitor', 'host'])->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $passes,
        ]);
    }

    /**
     * POST /api/visitor/passes/pre-register
     */
    public function preRegister(Request $request): JsonResponse
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
            'host_user_id'              => ['nullable', \Illuminate\Validation\Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'purpose'                   => 'required|string|max:100',
            'expected_arrival_at'       => 'required|date',
            'expected_duration_minutes' => 'nullable|integer|min:5|max:1440',
            'notes'                     => 'nullable|string|max:1000',
        ]);

        $pass = $this->visitorService->createPass(
            array_merge($validated, ['status' => 'Expected', 'entry_type' => 'Pre-registered']),
            $tenantId,
            $companyId,
            $branchId
        );

        return response()->json([
            'success' => true,
            'message' => 'Visitor pre-registered successfully.',
            'data'    => $pass,
        ], 201);
    }

    /**
     * POST /api/visitor/passes/{id}/check-in
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('checkIn', $pass);

        if ($pass->status === 'Checked-In') {
            return response()->json(['success' => false, 'message' => 'Visitor is already checked in.'], 422);
        }

        if ($pass->status === 'Checked-Out') {
            return response()->json(['success' => false, 'message' => 'Cannot re-admit a checked-out pass. A new pass must be issued.'], 422);
        }

        if ($pass->status === 'Waiting Approval') {
            return response()->json(['success' => false, 'message' => 'Host approval is pending. You cannot check-in until the host approves this pass.'], 422);
        }

        if (in_array($pass->status, ['Rejected', 'Denied'])) {
            return response()->json(['success' => false, 'message' => 'This visitor pass was denied/rejected and cannot be checked-in.'], 422);
        }

        $pass->update([
            'status'      => 'Checked-In',
            'check_in_at' => now(),
        ]);

        $this->visitorService->notifyHost($pass, 'checked_in');

        return response()->json([
            'success' => true,
            'message' => 'Visitor checked in successfully.',
            'data'    => $pass,
        ]);
    }

    /**
     * POST /api/visitor/passes/{id}/check-out
     */
    public function checkOut(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->with('belongings')->findOrFail($id);

        $this->authorize('checkOut', $pass);

        if ($pass->status === 'Checked-Out') {
            return response()->json(['success' => false, 'message' => 'Visitor pass has already been checked out.'], 422);
        }

        if (!in_array($pass->status, ['Checked-In', 'Meeting in Progress', 'Overstayed'])) {
            return response()->json(['success' => false, 'message' => "Cannot check out a pass that has not been checked in. Current status: {$pass->status}."], 422);
        }

        $pass->update([
            'status'       => 'Checked-Out',
            'check_out_at' => now(),
        ]);

        // Verify belongings on exit
        foreach ($pass->belongings as $belonging) {
            $belonging->update([
                'is_verified_on_exit' => true,
                'exit_verified_by'    => auth()->id(),
                'exit_verified_at'    => now(),
            ]);
        }

        $this->visitorService->notifyHost($pass, 'checked_out');

        return response()->json([
            'success' => true,
            'message' => 'Visitor checked out successfully.',
            'data'    => $pass,
        ]);
    }

    /**
     * GET /api/visitor/passes/live-headcount
     */
    public function liveHeadcount(): JsonResponse
    {
        $this->authorize('viewAny', VisitorPass::class);

        [$tenantId, , $branchId] = $this->visitorService->resolveTenantContext();
        $count = $this->visitorService->getActiveVisitorsCount($tenantId, $branchId);

        return response()->json([
            'success'            => true,
            'active_inside_count' => $count,
        ]);
    }
}
