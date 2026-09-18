<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Services\PaymentGatewayManager;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Platform-wide settings screen — picks which registered PaymentGateway
 * implementation is active for every tenant's Subscription checkout.
 * Genuinely platform-admin-only (see UsageOverviewController for the same
 * pattern): omitting tenant_id context from the permission check means only
 * a platform-scope grant satisfies it, never a tenant_owner's blanket
 * tenant-scope grant.
 */
class PaymentGatewaySettingsController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly AccessService $access,
    ) {
    }

    public function index(): View
    {
        abort_unless($this->access->allows(auth()->user(), 'platform.payment_gateway.manage'), 403);

        return view('modules.platform.payment-gateway.index', [
            'gateways' => $this->gateways->all(),
            'activeIdentifier' => $this->gateways->activeIdentifier(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->access->allows(auth()->user(), 'platform.payment_gateway.manage'), 403);

        $validated = $request->validate([
            'gateway' => ['required', 'string'],
        ]);

        try {
            $this->gateways->setActive($validated['gateway']);
        } catch (RuntimeException $e) {
            return redirect()->route('platform.payment-gateway.index')->with('error', $e->getMessage());
        }

        return redirect()->route('platform.payment-gateway.index')
            ->with('success', 'Active payment gateway updated.');
    }
}
