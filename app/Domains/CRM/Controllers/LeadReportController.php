<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Lead;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * FRS 2.5 reports: response time, funnel conversion, ageing, win and loss reasons, forecast by salesperson.
 * Row visibility follows the normal lead scoping (a salesperson sees own leads, a manager the tenant's).
 */
class LeadReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);

        $days = max(7, min(365, (int) $request->query('days', 90)));
        $since = now()->subDays($days)->startOfDay();
        $closed = ['Won', 'Lost', 'Converted'];

        $leads = Lead::query()->where('created_at', '>=', $since)->get([
            'id', 'lead_owner_id', 'status', 'created_at', 'assigned_at', 'first_responded_at',
            'response_sla_minutes', 'response_sla_breached', 'expected_amount', 'expected_sale_date',
            'loss_reason_id', 'on_hold_until',
        ]);
        $open = Lead::query()->whereNotIn('status', $closed)->get([
            'id', 'lead_owner_id', 'status', 'created_at', 'expected_amount', 'expected_sale_date', 'on_hold_until',
        ]);

        $owners = User::whereIn('id', $leads->pluck('lead_owner_id')->merge($open->pluck('lead_owner_id'))->filter()->unique())
            ->pluck('name', 'id');
        $ownerName = fn ($id) => $id ? ($owners[$id] ?? ('User #' . $id)) : 'Unassigned';

        return view('modules.crm.reports.leads', [
            'days' => $days,
            'responseTime' => $this->responseTime($leads, $ownerName),
            'funnel' => $this->funnel($leads),
            'ageing' => $this->ageing($open, $ownerName),
            'winLoss' => $this->winLoss($leads),
            'forecast' => $this->forecast($open, $ownerName, $request->user()),
        ]);
    }

    /** @return array{overall: array, rows: array} */
    private function responseTime($leads, callable $ownerName): array
    {
        $assigned = $leads->filter(fn ($l) => !empty($l->assigned_at));
        $build = function ($set) {
            $responded = $set->filter(fn ($l) => !empty($l->first_responded_at));
            $mins = $responded->map(fn ($l) => Carbon::parse($l->assigned_at)->diffInMinutes(Carbon::parse($l->first_responded_at)));
            $within = $responded->filter(function ($l) {
                $m = Carbon::parse($l->assigned_at)->diffInMinutes(Carbon::parse($l->first_responded_at));
                return $m <= (int) ($l->response_sla_minutes ?: 60);
            })->count();

            return [
                'assigned' => $set->count(),
                'responded' => $responded->count(),
                'avg_minutes' => $mins->isEmpty() ? null : round($mins->avg(), 1),
                'median_minutes' => $mins->isEmpty() ? null : $mins->sort()->values()->get((int) floor(($mins->count() - 1) / 2)),
                'within_sla_pct' => $responded->isEmpty() ? null : round($within / $responded->count() * 100, 1),
                'breached' => $set->where('response_sla_breached', true)->count(),
                'awaiting' => $set->whereNull('first_responded_at')->count(),
            ];
        };

        $rows = [];
        foreach ($assigned->groupBy('lead_owner_id') as $ownerId => $set) {
            $rows[] = ['owner' => $ownerName($ownerId ?: null)] + $build($set);
        }
        usort($rows, fn ($a, $b) => strcmp($a['owner'], $b['owner']));

        return ['overall' => $build($assigned), 'rows' => $rows];
    }

    private function funnel($leads): array
    {
        $total = max(1, $leads->count());
        $stages = [];
        foreach ($leads->groupBy(fn ($l) => $l->status ?: 'New')->sortByDesc(fn ($g) => $g->count()) as $status => $set) {
            $stages[] = ['stage' => $status, 'count' => $set->count(), 'pct' => round($set->count() / $total * 100, 1)];
        }
        $won = $leads->filter(fn ($l) => in_array(strtolower((string) $l->status), ['won', 'converted'], true))->count();

        return ['stages' => $stages, 'total' => $leads->count(), 'won' => $won, 'conversion_pct' => round($won / $total * 100, 1)];
    }

    private function ageing($open, callable $ownerName): array
    {
        $buckets = ['0-7 days' => [0, 7], '8-30 days' => [8, 30], '31-60 days' => [31, 60], '61-90 days' => [61, 90], '90+ days' => [91, PHP_INT_MAX]];
        $overall = array_fill_keys(array_keys($buckets), 0);
        $byOwner = [];

        foreach ($open as $lead) {
            $age = (int) Carbon::parse($lead->created_at)->diffInDays(now());
            foreach ($buckets as $label => [$min, $max]) {
                if ($age >= $min && $age <= $max) {
                    $overall[$label]++;
                    $name = $ownerName($lead->lead_owner_id);
                    $byOwner[$name] ??= array_fill_keys(array_keys($buckets), 0);
                    $byOwner[$name][$label]++;
                    break;
                }
            }
        }
        ksort($byOwner);

        return ['overall' => $overall, 'by_owner' => $byOwner, 'open_total' => $open->count(), 'on_hold' => $open->filter(fn ($l) => !empty($l->on_hold_until) && Carbon::parse($l->on_hold_until)->endOfDay()->isFuture())->count()];
    }

    private function winLoss($leads): array
    {
        $lost = $leads->filter(fn ($l) => strtolower((string) $l->status) === 'lost');
        $reasons = \App\Domains\CRM\Models\CrmLossReason::withoutGlobalScopes()
            ->whereIn('id', $lost->pluck('loss_reason_id')->filter()->unique())->pluck('name', 'id');
        $byReason = [];
        foreach ($lost->groupBy('loss_reason_id') as $rid => $set) {
            $byReason[] = ['reason' => $rid ? ($reasons[$rid] ?? 'Reason #' . $rid) : 'Not recorded', 'count' => $set->count()];
        }
        usort($byReason, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'won' => $leads->filter(fn ($l) => in_array(strtolower((string) $l->status), ['won', 'converted'], true))->count(),
            'lost' => $lost->count(),
            'by_reason' => $byReason,
        ];
    }

    private function forecast($open, callable $ownerName, ?User $user = null): array
    {
        $rows = [];
        $months = [];
        foreach ($open as $lead) {
            $name = $ownerName($lead->lead_owner_id);
            $month = $lead->expected_sale_date ? Carbon::parse($lead->expected_sale_date)->format('Y-m') : 'Undated';
            $months[$month] = true;
            $rows[$name]['leads'][$month] = ($rows[$name]['leads'][$month] ?? 0) + (float) $lead->expected_amount;
            $rows[$name]['lead_total'] = ($rows[$name]['lead_total'] ?? 0) + (float) $lead->expected_amount;
        }

        // open deals, weighted by their probability
        $dealQuery = CrmDeal::query()->whereNotIn('stage', ['Won', 'Lost', 'Closed Won', 'Closed Lost']);
        // deals have no row scope of their own: only lead managers see everyone's deals, others only their own
        $seeAll = $user && \App\Domains\CRM\Services\LeadService::userMayManageLeadOwnership($user, new Lead(['tenant_id' => $user->tenant_id]), 'reassign');
        if (!$seeAll) {
            $dealQuery->where('owner_id', $user?->id);
        }
        $deals = $dealQuery->get(['owner_id', 'estimated_value', 'probability', 'closing_date']);
        $dealOwners = User::whereIn('id', $deals->pluck('owner_id')->filter()->unique())->pluck('name', 'id');
        foreach ($deals as $deal) {
            $name = $deal->owner_id ? ($dealOwners[$deal->owner_id] ?? 'User #' . $deal->owner_id) : 'Unassigned';
            $weighted = (float) $deal->estimated_value * ((float) $deal->probability / 100);
            $rows[$name]['deal_total'] = ($rows[$name]['deal_total'] ?? 0) + (float) $deal->estimated_value;
            $rows[$name]['deal_weighted'] = ($rows[$name]['deal_weighted'] ?? 0) + $weighted;
        }

        ksort($months);
        ksort($rows);

        return ['rows' => $rows, 'months' => array_keys($months)];
    }
}
