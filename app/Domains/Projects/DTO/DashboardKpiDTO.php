<?php

namespace App\Domains\Projects\DTO;

class DashboardKpiDTO
{
    public function __construct(
        public readonly int $total_projects,
        public readonly int $active_projects,
        public readonly int $completed_projects,
        public readonly int $on_hold_projects,
        public readonly float $portfolio_health_score,
        public readonly float $total_budget_amount,
        public readonly float $total_incurred_cost,
        public readonly ?float $cost_consumption_percent,
        public readonly float $total_budget_hours,
        public readonly float $total_tracked_hours,
        public readonly ?float $hours_consumption_percent,
        public readonly int $overdue_tasks_count,
        public readonly int $overdue_milestones_count,
        public readonly int $open_issues_count,
        public readonly int $critical_issues_count,
        public readonly float $unbilled_approved_hours,
        public readonly float $unbilled_amount,
    ) {}

    public function toArray(): array
    {
        return [
            'total_projects'             => $this->total_projects,
            'active_projects'            => $this->active_projects,
            'completed_projects'         => $this->completed_projects,
            'on_hold_projects'           => $this->on_hold_projects,
            'portfolio_health_score'     => $this->portfolio_health_score,
            'total_budget_amount'        => $this->total_budget_amount,
            'total_incurred_cost'        => $this->total_incurred_cost,
            'cost_consumption_percent'   => $this->cost_consumption_percent,
            'total_budget_hours'         => $this->total_budget_hours,
            'total_tracked_hours'        => $this->total_tracked_hours,
            'hours_consumption_percent'  => $this->hours_consumption_percent,
            'overdue_tasks_count'        => $this->overdue_tasks_count,
            'overdue_milestones_count'   => $this->overdue_milestones_count,
            'open_issues_count'          => $this->open_issues_count,
            'critical_issues_count'      => $this->critical_issues_count,
            'unbilled_approved_hours'    => $this->unbilled_approved_hours,
            'unbilled_amount'            => $this->unbilled_amount,
        ];
    }
}
