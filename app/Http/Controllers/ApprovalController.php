<?php

namespace App\Http\Controllers;

use App\Services\Approval\ApprovalCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalCenterService $approvalService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $approvals = $this->approvalService->getPendingApprovals($request->user());

        return response()->json($approvals);
    }
}
