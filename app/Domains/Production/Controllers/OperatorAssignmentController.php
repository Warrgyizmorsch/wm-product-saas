<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Domains\Production\Services\OperatorAssignmentService;
use Illuminate\Http\Request;
use App\Domains\Production\Requests\OperatorAssignRequest;
use App\Domains\Production\Requests\OperatorReassignRequest;

class OperatorAssignmentController extends Controller
{
    public function __construct(
        private readonly OperatorAssignmentService $assignmentService
    ) {}

    public function assign(OperatorAssignRequest $request)
    {
        $this->authorize('manage', ProductionOperatorAssignment::class);

        $tenantId = require_tenant_id();
        $data = $request->validated();

        try {
            $this->assignmentService->assign(
                $tenantId,
                (int)$request->input('production_order_operation_id'),
                (int)$request->input('user_id'),
                auth()->id(),
                $request->input('remarks')
            );

            return redirect()->back()->with('success', 'Operator assigned successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reassign(OperatorReassignRequest $request, int $assignment)
    {
        $this->authorize('manage', ProductionOperatorAssignment::class);

        $tenantId = require_tenant_id();
        $data = $request->validated();

        try {
            $this->assignmentService->reassign(
                $tenantId,
                $assignment,
                (int)$request->input('user_id'),
                auth()->id(),
                $request->input('remarks')
            );

            return redirect()->back()->with('success', 'Operator reassigned successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function accept(Request $request, int $assignment)
    {
        $tenantId = require_tenant_id();

        $this->authorize('manageOwnAssignment', ProductionOperatorAssignment::findOrFail($assignment));

        try {
            $this->assignmentService->accept($tenantId, $assignment, auth()->id(), $request->input('remarks'));
            return redirect()->back()->with('success', 'Assignment accepted.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, int $assignment)
    {
        $tenantId = require_tenant_id();

        $this->authorize('manageOwnAssignment', ProductionOperatorAssignment::findOrFail($assignment));

        try {
            $this->assignmentService->reject($tenantId, $assignment, auth()->id(), $request->input('remarks'));
            return redirect()->back()->with('success', 'Assignment rejected.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
