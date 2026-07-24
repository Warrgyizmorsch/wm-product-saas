<?php

namespace App\Domains\Projects\Exports;

use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Renders the "current view" of the projects listing (whatever Builder the
 * caller passes in — already filtered/sorted by ProjectRepository) as a
 * human-readable .xlsx. Owns column order, headings, and row mapping only;
 * it never builds or modifies the query itself.
 */
class ProjectsExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private readonly Builder $query)
    {
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            __('projects.code'),
            __('projects.name'),
            __('projects.client'),
            __('projects.owner'),
            __('projects.priority'),
            __('projects.status'),
            __('projects.start_date'),
            __('projects.end_date'),
        ];
    }

    public function map($project): array
    {
        /** @var Project $project */
        return [
            $project->project_code,
            $project->name,
            $project->customer?->name ?? '—',
            $project->owner?->name ?? '—',
            $project->priority_label,
            $project->status_label,
            $project->start_date?->format('d M Y') ?? '—',
            $project->end_date?->format('d M Y') ?? '—',
        ];
    }

    public function title(): string
    {
        return 'Projects';
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

    /**
     * projects_YYYY-MM-DD_HH-mm.xlsx, timestamped in the current tenant's
     * timezone (falling back to the app default) rather than server time —
     * centralized here so every future export variant shares one convention.
     */
    public static function filename(): string
    {
        $timezone = tenant()?->timezone ?: config('app.timezone');

        return 'projects_' . Date::now($timezone)->format('Y-m-d_H-i') . '.xlsx';
    }
}
