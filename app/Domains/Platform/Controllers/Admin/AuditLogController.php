<?php

namespace App\Domains\Platform\Controllers\Admin;

use App\Domains\Platform\Models\PlatformAuditLog;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request, AccessService $access): View
    {
        abort_unless($access->allows($request->user(), 'platform.audit.view'), 403);

        $logs = PlatformAuditLog::query()
            ->with(['user:id,name,email', 'tenant:id,name'])
            ->when($request->integer('tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when($request->string('action')->trim()->value(), fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->when($request->date('from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('modules.platform.admin.audit-logs', [
            'logs' => $logs,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
