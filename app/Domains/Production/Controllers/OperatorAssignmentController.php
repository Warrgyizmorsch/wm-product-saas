<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Domains\Production\Services\OperatorAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OperatorAssignmentController extends Controller
{
    public function __construct(
        private readonly OperatorAssignmentService $assignmentService
    ) {}

    public function assign(Request $request)
    {
        $this->authorize('manage', ProductionOperatorAssignment::class);

        $tenantId = require_tenant_id();
        $request->validate([
            'production_order_operation_id' => 'required|integer',
            'user_id'                       => 'required|integer',
            'remarks'                       => 'nullable|string|max:1000',
        ]);

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

    public function reassign(Request $request, int $assignment)
    {
        $this->authorize('manage', ProductionOperatorAssignment::class);

        $tenantId = require_tenant_id();
        $request->validate([
            'user_id' => 'required|integer',
            'remarks' => 'nullable|string|max:1000',
        ]);

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
