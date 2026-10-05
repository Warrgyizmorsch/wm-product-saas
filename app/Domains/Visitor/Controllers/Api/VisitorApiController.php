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
        // Initial pre-register skeleton
        return response()->json([
            'success' => true,
            'message' => 'Visitor pre-registration endpoint ready.',
        ]);
    }

    /**
     * POST /api/visitor/passes/{id}/check-in
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->findOrFail($id);

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
        $pass = VisitorPass::where('tenant_id', $tenantId)->findOrFail($id);

        $pass->update([
            'status'       => 'Checked-Out',
            'check_out_at' => now(),
        ]);

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
        [$tenantId, , $branchId] = $this->visitorService->resolveTenantContext();
        $count = $this->visitorService->getActiveVisitorsCount($tenantId, $branchId);

        return response()->json([
            'success'            => true,
            'active_inside_count' => $count,
        ]);
    }
}
