<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\AccountingAuditLog;
use App\Domains\Accounting\Services\AccountingAuditLogService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingAuditLogController extends Controller
{
    public function __construct(
        private readonly AccountingAuditLogService $auditLogs,
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $filters = $request->only(['subject_type', 'event_type', 'from', 'to', 'search', 'sort', 'direction']);

        return view('modules.accounting.reports.audit-trail', [
            'logs' => $this->auditLogs->paginate($filters),
            'filters' => $filters,
            'eventTypes' => AccountingAuditLog::query()->distinct()->orderBy('event_type')->pluck('event_type'),
        ]);
    }
}
