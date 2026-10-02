<?php

namespace App\Domains\Projects\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectReportExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle, WithStrictNullComparison
{
    public function __construct(
        private readonly string $reportType,
        private readonly Collection $rows,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function title(): string
    {
        return mb_substr(Str::studly($this->reportType) . ' Report', 0, 31);
    }

    public function headings(): array
    {
        return match ($this->reportType) {
            'summary' => [
                'Project Code', 'Project Name', 'Client', 'Owner', 'Status', 'Priority',
                'Start Date', 'End Date', 'Budget Amount', 'Budget Hours', 'Progress %',
            ],
            'task-status' => [
                'Task #', 'Title', 'Project', 'Milestone', 'Assignee', 'Priority',
                'Status', 'Due Date', 'Est. Hours', 'Actual Hours', 'Variance (Hrs)',
            ],
            'resource-utilization' => [
                'Resource Name', 'Email', 'Assigned Projects', 'Open Tasks', 'Completed Tasks',
                'Budget Hours', 'Total Hours', 'Billable Hours', 'Billable %', 'Budget Burn %',
            ],
            'timesheet-billability' => [
                'Date', 'Member', 'Project Code', 'Project Name', 'Task', 'Hours Logged',
                'Billable', 'Hourly Rate', 'Billable Amount', 'Approval Status', 'Invoiced',
            ],
            'issue-defect-density' => [
                'Issue #', 'Title', 'Project Code', 'Project Name', 'Task', 'Severity',
                'Priority', 'Status', 'Assignee', 'Reporter', 'Created At', 'Resolution Date', 'Days to Resolve',
            ],
            'milestone-variance' => [
                'Milestone Name', 'Project Code', 'Project Name', 'Status', 'Start Date',
                'Planned Due Date', 'Actual Completion Date', 'Schedule Slippage (Days)', 'Planned Cost', 'Completion %',
            ],
            'budget-cost' => [
                'Project Code', 'Project Name', 'Client', 'Status', 'Base Budget ($)',
                'Approved CRs ($)', 'Revised Budget ($)', 'Actual Cost ($)', 'Cost Variance ($)',
                'Base Hours', 'Approved CR Hours', 'Revised Hours', 'Actual Hours', 'Hours Variance',
            ],
            default => ['Column 1', 'Column 2'],
        };
    }

    public function map($row): array
    {
        if (is_array($row)) {
            return match ($this->reportType) {
                'resource-utilization' => [
                    $row['user_name'] ?? '—',
                    $row['user_email'] ?? '—',
                    $row['project_count'] ?? 0,
                    $row['open_tasks'] ?? 0,
                    $row['completed_tasks'] ?? 0,
                    number_format($row['budget_hours'] ?? 0, 2),
                    number_format($row['total_hours'] ?? 0, 2),
                    number_format($row['billable_hours'] ?? 0, 2),
                    ($row['billable_percent'] ?? 0) . '%',
                    isset($row['burn_percent']) ? $row['burn_percent'] . '%' : '—',
                ],
                'budget-cost' => [
                    $row['project_code'] ?? '—',
                    $row['name'] ?? '—',
                    $row['client_name'] ?? '—',
                    $row['status'] ?? '—',
                    number_format($row['base_budget_amount'] ?? 0, 2),
                    number_format($row['cr_budget_amount'] ?? 0, 2),
                    number_format($row['revised_budget_amount'] ?? 0, 2),
                    number_format($row['actual_cost'] ?? 0, 2),
                    number_format($row['cost_variance'] ?? 0, 2),
                    number_format($row['base_budget_hours'] ?? 0, 2),
                    number_format($row['cr_budget_hours'] ?? 0, 2),
                    number_format($row['revised_budget_hours'] ?? 0, 2),
                    number_format($row['actual_hours'] ?? 0, 2),
                    number_format($row['hours_variance'] ?? 0, 2),
                ],
                default => array_values($row),
            };
        }

        // Eloquent Model row
        return match ($this->reportType) {
            'summary' => [
                $row->project_code,
                $row->name,
                $row->customer?->name ?? '—',
                $row->owner?->name ?? '—',
                $row->status,
                $row->priority,
                $row->start_date?->format('d M Y') ?? '—',
                $row->end_date?->format('d M Y') ?? '—',
                number_format((float) ($row->budget_amount ?? 0), 2),
                number_format((float) ($row->budget_hours ?? 0), 2),
                $this->calculateProgress($row) . '%',
            ],
            'task-status' => [
                $row->task_number,
                $row->title,
                $row->project?->name ?? '—',
                $row->milestone?->name ?? '—',
                $row->assignee?->name ?? '—',
                $row->priority,
                $row->status,
                $row->due_date ? Carbon::parse($row->due_date)->format('d M Y') : '—',
                number_format((float) ($row->estimated_hours ?? 0), 2),
                number_format((float) ($row->actual_hours ?? 0), 2),
                number_format((float) (($row->actual_hours ?? 0) - ($row->estimated_hours ?? 0)), 2),
            ],
            'timesheet-billability' => [
                $row->date ? Carbon::parse($row->date)->format('d M Y') : '—',
                $row->user?->name ?? '—',
                $row->project?->project_code ?? '—',
                $row->project?->name ?? '—',
                $row->task?->title ?? '—',
                number_format((float) $row->hours, 2),
                $row->is_billable ? 'Yes' : 'No',
                number_format((float) ($row->hourly_rate ?? 0), 2),
                number_format((float) ($row->hours * ($row->hourly_rate ?? 0)), 2),
                $row->approval_status,
                $row->is_invoiced ? 'Invoiced' : 'Unbilled',
            ],
            'issue-defect-density' => [
                $row->issue_number,
                $row->title,
                $row->project?->project_code ?? '—',
                $row->project?->name ?? '—',
                $row->task?->title ?? '—',
                $row->severity,
                $row->priority,
                $row->status,
                $row->assignee?->name ?? '—',
                $row->reporter?->name ?? '—',
                $row->created_at?->format('d M Y') ?? '—',
                $row->resolution_date ? Carbon::parse($row->resolution_date)->format('d M Y') : '—',
                $row->resolution_date ? $row->created_at->diffInDays(Carbon::parse($row->resolution_date)) : $row->created_at->diffInDays(now()),
            ],
            'milestone-variance' => [
                $row->name,
                $row->project?->project_code ?? '—',
                $row->project?->name ?? '—',
                $row->status,
                $row->start_date ? Carbon::parse($row->start_date)->format('d M Y') : '—',
                $row->due_date ? Carbon::parse($row->due_date)->format('d M Y') : '—',
                $row->completed_at ? Carbon::parse($row->completed_at)->format('d M Y') : '—',
                $this->calculateSlippage($row),
                number_format((float) ($row->planned_cost ?? 0), 2),
                ($row->completion_percentage ?? 0) . '%',
            ],
            default => [json_encode($row)],
        };
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }

    public static function filename(string $reportType): string
    {
        $safeName = Str::studly($reportType);
        return "Project_{$safeName}_" . now()->format('Ymd_Hi');
    }

    private function calculateProgress($project): int
    {
        $tasks = $project->tasks ?? collect();
        $eligible = $tasks->where('status', '!=', 'Cancelled');
        $completed = $eligible->where('status', 'Completed');

        return $eligible->count() > 0 ? (int) round(($completed->count() / $eligible->count()) * 100) : 0;
    }

    private function calculateSlippage($milestone): int
    {
        if ($milestone->completed_at && $milestone->due_date) {
            $diff = (int) Carbon::parse($milestone->due_date)->diffInDays(Carbon::parse($milestone->completed_at), false);
            return max($diff, 0);
        }

        if (!$milestone->completed_at && $milestone->due_date && Carbon::today()->gt(Carbon::parse($milestone->due_date))) {
            return (int) Carbon::parse($milestone->due_date)->diffInDays(Carbon::today());
        }

        return 0;
    }
}
