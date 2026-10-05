<?php

namespace App\Domains\Projects\DTO;

use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportFilterDTO
{
    public function __construct(
        public readonly int $tenant_id,
        public readonly ?int $company_id = null,
        public readonly ?int $branch_id = null,
        public readonly ?int $project_id = null,
        public readonly ?int $customer_id = null,
        public readonly ?int $user_id = null,
        public readonly ?string $status = null,
        public readonly ?string $priority = null,
        public readonly string $preset = 'this_month',
        public readonly ?Carbon $start_date = null,
        public readonly ?Carbon $end_date = null,
        public readonly ?string $search = null,
        public readonly bool $overdue_only = false,
        public readonly bool $cost_overrun_only = false,
        public readonly bool $slippage_only = false,
        public readonly ?bool $billable = null,
        public readonly ?string $approval_status = null,
        public readonly ?string $invoiced_status = null,
        public readonly ?string $severity = null,
    ) {}

    public static function fromRequest(Request $request, int $tenantId): self
    {
        $preset = $request->query('preset', 'this_month');
        $rawStart = $request->query('start_date') ?: $request->query('from');
        $rawEnd = $request->query('end_date') ?: $request->query('to');

        $startDate = null;
        $endDate = null;

        if ($rawStart || $rawEnd || $preset === 'custom') {
            $startDate = $rawStart ? Carbon::parse($rawStart)->startOfDay() : null;
            $endDate = $rawEnd ? Carbon::parse($rawEnd)->endOfDay() : null;
            $preset = 'custom';
        } else {
            [$startDate, $endDate] = match ($preset) {
                'today'        => [now()->startOfDay(), now()->endOfDay()],
                'this_week'    => [now()->startOfWeek(), now()->endOfWeek()],
                'last_month'   => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                'this_quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
                'this_year'    => [now()->startOfYear(), now()->endOfYear()],
                'all_time'     => [null, null],
                default        => [now()->startOfMonth(), now()->endOfMonth()],
            };
        }

        $billable = null;
        if ($request->filled('billable')) {
            $val = $request->query('billable');
            $billable = in_array($val, ['1', 'true', true], true) ? true : (in_array($val, ['0', 'false', false], true) ? false : null);
        }

        return new self(
            tenant_id: $tenantId,
            company_id: $request->filled('company_id') ? (int) $request->query('company_id') : null,
            branch_id: $request->filled('branch_id') ? (int) $request->query('branch_id') : null,
            project_id: $request->filled('project_id') ? (int) $request->query('project_id') : null,
            customer_id: $request->filled('customer_id') ? (int) $request->query('customer_id') : null,
            user_id: $request->filled('user_id') ? (int) $request->query('user_id') : null,
            status: $request->query('status') ?: null,
            priority: $request->query('priority') ?: null,
            preset: $preset,
            start_date: $startDate,
            end_date: $endDate,
            search: $request->query('search') ?: $request->query('q') ?: null,
            overdue_only: $request->boolean('overdue_only'),
            cost_overrun_only: $request->boolean('cost_overrun_only'),
            slippage_only: $request->boolean('slippage_only'),
            billable: $billable,
            approval_status: $request->query('approval_status') ?: null,
            invoiced_status: $request->query('invoiced_status') ?: null,
            severity: $request->query('severity') ?: null,
        );
    }

    public function toArray(): array
    {
        return [
            'tenant_id'          => $this->tenant_id,
            'company_id'         => $this->company_id,
            'branch_id'          => $this->branch_id,
            'project_id'         => $this->project_id,
            'customer_id'        => $this->customer_id,
            'user_id'            => $this->user_id,
            'status'             => $this->status,
            'priority'           => $this->priority,
            'preset'             => $this->preset,
            'start_date'         => $this->start_date?->toDateString(),
            'end_date'           => $this->end_date?->toDateString(),
            'search'             => $this->search,
            'overdue_only'       => $this->overdue_only,
            'cost_overrun_only'  => $this->cost_overrun_only,
            'slippage_only'      => $this->slippage_only,
            'billable'           => $this->billable,
            'approval_status'    => $this->approval_status,
            'invoiced_status'    => $this->invoiced_status,
            'severity'           => $this->severity,
        ];
    }
}
