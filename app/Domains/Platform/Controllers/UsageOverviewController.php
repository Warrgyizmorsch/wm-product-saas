<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Services\UsageLimitService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\View\View;

class UsageOverviewController extends Controller
{
    public function __construct(
        private readonly UsageLimitService $usageLimits,
        private readonly AccessService $access,
    ) {
    }

    public function index(): View
    {
        // Platform-wide, same as TenantPolicy::viewAny — omitting tenant_id
        // context means only a platform-scope grant can satisfy this, so a
        // tenant_owner cannot use this screen to see other tenants' seats.
        abort_unless($this->access->allows(auth()->user(), 'platform.usage.view'), 403);

        return view('modules.platform.usage.index', [
            'rows' => $this->usageLimits->overview(),
        ]);
    }
}
