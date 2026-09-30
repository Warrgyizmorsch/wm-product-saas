<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Domains\HRMS\Models\HelpdeskTicketReply;

interface HelpdeskTicketRepositoryInterface
{
    public function ensureTablesExist(): void;

    public function getIndexData(array $inputs): array;

    public function createTicket(array $data, ?array $files = null): HelpdeskTicket;

    public function addReply(HelpdeskTicket $ticket, array $data, ?array $files = null): HelpdeskTicketReply;

    public function updateTicketStatus(HelpdeskTicket $ticket, string $status, ?int $assignedTo = null, ?string $priority = null): void;
}
