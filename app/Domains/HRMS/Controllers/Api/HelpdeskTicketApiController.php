<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Domains\HRMS\Repositories\HelpdeskTicketRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpdeskTicketApiController extends Controller
{
    public function __construct(private readonly HelpdeskTicketRepository $repository)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->repository->getIndexData($request->all());
        return response()->json([
            'success' => true,
            'data'    => $data['tickets'],
            'stats'   => [
                'totalOpen'     => $data['totalOpen'],
                'totalOverdue'  => $data['totalOverdue'],
                'totalResolved' => $data['totalResolved'],
                'csatAvg'       => $data['csatAvg'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:helpdesk_categories,id',
            'subject'     => 'required|string|max:255',
            'description' => 'required|string',
            'priority'    => 'required|in:low,medium,high,urgent',
        ]);

        $ticket = $this->repository->createTicket($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'data'    => $ticket,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = tenant_id();
        $ticket = HelpdeskTicket::where('tenant_id', $tenantId)
            ->with(['employee', 'category', 'assignedAgent', 'replies.sender', 'replies.attachments', 'attachments', 'rating'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $ticket,
        ]);
    }
}
