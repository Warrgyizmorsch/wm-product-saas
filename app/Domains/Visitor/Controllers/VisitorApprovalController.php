<?php

namespace App\Domains\Visitor\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Visitor\Models\VisitorPass;
use App\Domains\Visitor\Services\VisitorService;
use Illuminate\Http\Request;

class VisitorApprovalController extends Controller
{
    public function __construct(
        protected readonly VisitorService $visitorService
    ) {}

    /**
     * Display a listing of visitor approval requests assigned to the logged-in user.
     */
    public function index(Request $request)
    {
        [$tenantId, $companyId, $branchId] = $this->visitorService->resolveTenantContext();
        $userId = auth()->id();

        $search = $request->input('search');
        $status = $request->input('status', 'Waiting Approval'); // default to Waiting Approval
        $purpose = $request->input('purpose');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $baseQuery = VisitorPass::where('tenant_id', $tenantId)
            ->where('host_user_id', $userId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        // Calculate approval stats for current user
        $stats = [
            'total'            => (clone $baseQuery)->count(),
            'waiting_approval' => (clone $baseQuery)->where('status', 'Waiting Approval')->count(),
            'approved'         => (clone $baseQuery)->where('status', 'Approved')->count(),
            'rejected'         => (clone $baseQuery)->where('status', 'Rejected')->count(),
        ];

        // Filtered query for list
        $query = (clone $baseQuery)
            ->when($status !== 'all' && !empty($status), fn($q) => $q->where('status', $status))
            ->when($purpose, fn($q) => $q->where('purpose', $purpose))
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
                        });
                });
            })
            ->with(['visitor', 'belongings'])
            ->latest();

        $passes = $query->paginate(15)->withQueryString();

        return view('modules.visitor.approvals.index', compact(
            'passes',
            'stats',
            'status',
            'search',
            'purpose',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Approve a visitor pass entry request
     */
    public function approve(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $userId = auth()->id();

        $pass = VisitorPass::where('tenant_id', $tenantId)
            ->where('host_user_id', $userId)
            ->findOrFail($id);

        $pass->update([
            'status'           => 'Approved',
            'rejection_reason' => null,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('visitor.approved_success'),
            ]);
        }

        return redirect()->back()->with('success', __('visitor.approved_success'));
    }

    /**
     * Reject a visitor pass entry request with a reason
     */
    public function reject(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $userId = auth()->id();

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $pass = VisitorPass::where('tenant_id', $tenantId)
            ->where('host_user_id', $userId)
            ->findOrFail($id);

        $pass->update([
            'status'           => 'Rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('visitor.rejected_success'),
            ]);
        }

        return redirect()->back()->with('success', __('visitor.rejected_success'));
    }
}
