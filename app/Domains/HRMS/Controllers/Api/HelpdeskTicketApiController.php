<?php

namespace App\Domains\HRMS\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @deprecated This controller is deprecated in favor of HelpdeskApiController.
 * All helpdesk API routes are routed to HelpdeskApiController (see app/Domains/HRMS/Routes/api.php).
 * This class inherits and delegates to HelpdeskApiController to eliminate duplicated logic,
 * enforce strict tenant-scoping on category and ticket access, and prevent routing drift.
 */
class HelpdeskTicketApiController extends HelpdeskApiController
{
    /**
     * Alias to storeTicket() with strict tenant-scoped category validation.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->storeTicket($request);
    }

    /**
     * Alias to showTicket() with strict tenant scoping and authorization.
     */
    public function show(mixed $id): JsonResponse
    {
        return $this->showTicket($id);
    }
}
