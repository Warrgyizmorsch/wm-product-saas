<?php

namespace Tests\Feature;

use App\Core\Navigation\MenuRegistry;
use Tests\TestCase;

/**
 * Guardrail: every sidebar entry that links to a route must declare a
 * `permission` key (or a `when` closure), so MenuBuilder actually gates it
 * instead of showing it to every authenticated user regardless of role.
 *
 * Some routes are genuinely open server-side (no authorize()/AccessService
 * check at all) — those are named in ALLOWED_UNGATED_ROUTES below, one at a
 * time, each because it was individually verified against its controller.
 * Adding a route there without checking its controller defeats the point of
 * this test — don't do it just to make a failure go away.
 */
class MenuPermissionCoverageTest extends TestCase
{
    /**
     * Route names verified to have no server-side authorization check at all
     * (open to any authenticated user) as of the RBAC sidebar audit — see
     * memory `project-menu-permission-coverage-audit`. A route belongs here
     * only after checking its controller, not by default.
     *
     * @var list<string>
     */
    private const ALLOWED_UNGATED_ROUTES = [
        // Workspace home — every authenticated user has a personal dashboard.
        'dashboard',

        // Purchase — no controller-level check.
        'purchase.rfqs.savings',
        'purchase.requisitions.pending-items',
        'grns.pending',

        // CRM — no permission/policy exists for these entities yet.
        'crm.deals.index',
        'crm.accounts.index',
        'crm.masters.lead-statuses.index',
        'crm.masters.deal-statuses.index',
        'crm.settings.index',
        'crm.emailSettings.index',
        'crm.whatsappSettings.index',

        // Inventory — policy classes exist for some of these but are never invoked.
        'inventory.mrp-shortage.index',
        'inventory.material-requests.index',
        'inventory.serial-numbers.index',
        'inventory.batches.index',
        'inventory.transfers.index',
        'inventory.adjustments.index',
        'inventory.transactions.index',
        'inventory.reservations.index',
        'inventory.barcodes.index',
        'inventory.reports.low-stock',
        'inventory.reports.valuation',

        // Production — several Policy::viewAny() methods unconditionally return
        // true (ProductionOrderPolicy, ProductionBomPolicy, RoutingPolicy,
        // WorkCenterPolicy, MachinePolicy, ProductionPlanPolicy,
        // ProductionSchedulePolicy), the rest have no check at all.
        'production.orders.index',
        'production.subcontract.analytics',
        'production.boms.index',
        'production.routing.index',
        'production.ecos.index',
        'production.work-centers.index',
        'production.machines.index',
        'production.variances.index',
        'production.maintenance.dashboard',
        'production.maintenance.work-orders.index',
        'production.maintenance.schedules.index',
        'production.plans.index',
        'production.schedules.index',
        'production.schedules.calendar',
        'production.capacity.index',
        'production.schedules.scenarios.index',
        'production.planning-exceptions.index',

        // Platform — no controller-level check.
        'platform.payment-terms.index',

        // HRMS — self-service actions gated only by an Employee record, not a
        // permission (MesController-style "own record" routes).
        'hrms.assets-module.my-assets',
        'hrms.attendance.myAttendance',
        'hrms.payroll.mySalary',
    ];

    public function test_every_route_linked_menu_entry_is_gated(): void
    {
        $violations = [];

        foreach (app(MenuRegistry::class)->entries() as $entry) {
            $this->walk($entry, $violations);
        }

        $this->assertSame([], $violations, "These menu entries link to a route but have no `permission` or `when` gate, and aren't in ALLOWED_UNGATED_ROUTES:\n"
            . implode("\n", $violations)
            . "\n\nEither add a `permission` key matching what the controller actually enforces, or — only after checking the controller has genuinely no authorization check — add the route name to MenuPermissionCoverageTest::ALLOWED_UNGATED_ROUTES with a comment explaining why.");
    }

    /**
     * @param array<string, mixed> $entry
     * @param list<string> $violations
     */
    private function walk(array $entry, array &$violations): void
    {
        if (isset($entry['children'])) {
            foreach ($entry['children'] as $child) {
                $this->walk($child, $violations);
            }

            return;
        }

        if (! isset($entry['route'])) {
            return;
        }

        $gated = isset($entry['permission']) || isset($entry['when']);

        if (! $gated && ! in_array($entry['route'], self::ALLOWED_UNGATED_ROUTES, true)) {
            $violations[] = $entry['route'] . ' ("' . ($entry['label'] ?? '?') . '")';
        }
    }
}
