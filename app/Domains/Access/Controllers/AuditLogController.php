<?php

namespace App\Domains\Access\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Access\AccessAuditLog;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'audit.logs.view', [
            'tenant_id' => tenant_id(),
        ]), 403);

        $logs = AccessAuditLog::query()
            ->where('tenant_id', tenant_id())
            ->with(['actor', 'targetUser'])
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('modules.access.audit-log.index', [
            'logs' => $logs,
            'actions' => AccessAuditLog::query()
                ->where('tenant_id', tenant_id())
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }
}
