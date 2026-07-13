@php
    $milestonePage = (int) request('milestone_page', 1);
    $milestonesPerPage = 6;
    $totalMilestones = $milestones->count();
    $totalMilestonePages = (int) ceil($totalMilestones / $milestonesPerPage);
    $paginatedMilestones = $milestones->slice(($milestonePage - 1) * $milestonesPerPage, $milestonesPerPage);
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold text-dark mb-0">
        <i class="feather-flag me-2 text-primary"></i>{{ __('projects.milestones') }}
    </h5>
    @if ($canManageMilestones)
        <button type="button" class="btn btn-primary btn-sm" onclick="openMilestoneModal('add')">
            <i class="feather-plus me-1"></i>{{ __('projects.add_milestone') }}
        </button>
    @endif
</div>

<x-ui.table>
    <thead>
        <tr>
            <th scope="col">{{ __('projects.milestone_name') }}</th>
            <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.milestone_owner') }}</th>
            <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.start_date') }}</th>
            <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.due_date') }}</th>
            <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.status') }}</th>
            <th scope="col" style="width: 1%; white-space: nowrap;">{{ __('projects.completion_percentage') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($paginatedMilestones as $milestone)
            <tr @if ($canManageMilestones) role="button" style="cursor: pointer;"
                    onclick="openMilestoneDetailsDrawer({
                        id: {{ $milestone->id }},
                        updateUrl: @js(route('projects.milestones.update', [$project, $milestone->id])),
                        deleteUrl: @js(route('projects.milestones.destroy', [$project, $milestone->id])),
                        name: @js($milestone->name),
                        description: @js($milestone->description),
                        ownerId: @js($milestone->owner_id),
                        ownerName: @js($milestone->owner?->name),
                        startDate: @js($milestone->start_date?->format('Y-m-d')),
                        dueDate: @js($milestone->due_date?->format('Y-m-d')),
                        startDateDisplay: @js($milestone->start_date?->format('d/m/Y')),
                        dueDateDisplay: @js($milestone->due_date?->format('d/m/Y')),
                        status: @js($milestone->status),
                        completionPercentage: {{ $milestone->completion_percentage }}
                    })"
                @endif>
                <td>
                    <div class="fw-semibold text-dark">{{ $milestone->name }}</div>
                    @if ($milestone->description)
                        <div class="fs-11 text-muted">{{ \Illuminate\Support\Str::limit($milestone->description, 60) }}</div>
                    @endif
                </td>
                <td>{{ $milestone->owner?->name ?: '—' }}</td>
                <td>{{ $milestone->start_date?->format('d/m/Y') ?: '—' }}</td>
                <td>{{ $milestone->due_date?->format('d/m/Y') ?: '—' }}</td>
                <td>
                    @if ($milestone->status)
                        @php
                            $milestoneStatusVariant = match ($milestone->status) {
                                'Active' => 'success',
                                'On Hold' => 'warning',
                                'Completed' => 'primary',
                                'Closed' => 'dark',
                                default => 'secondary',
                            };
                        @endphp
                        <x-ui.badge variant="{{ $milestoneStatusVariant }}" soft>
                            {{ __('projects.statuses.' . $milestone->status) }}
                        </x-ui.badge>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $milestone->completion_percentage }}%</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-muted py-4">
                    {{ __('projects.no_milestones') }}
                </td>
            </tr>
        @endforelse
    </tbody>
</x-ui.table>

@if ($totalMilestonePages > 1)
    <div class="pt-3 border-top">
        <div class="erp-pagination-container">
            <ul class="erp-pagination">
                <li class="page-item {{ $milestonePage <= 1 ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $milestonePage <= 1 ? 'javascript:void(0);' : request()->fullUrlWithQuery(['milestone_page' => $milestonePage - 1]) }}" aria-label="Previous">
                        <i class="feather-chevron-left"></i>
                    </a>
                </li>
                @for ($i = 1; $i <= $totalMilestonePages; $i++)
                    <li class="page-item {{ $milestonePage == $i ? 'active' : '' }}">
                        <a class="page-link" href="{{ request()->fullUrlWithQuery(['milestone_page' => $i]) }}">{{ $i }}</a>
                    </li>
                @endfor
                <li class="page-item {{ $milestonePage >= $totalMilestonePages ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $milestonePage >= $totalMilestonePages ? 'javascript:void(0);' : request()->fullUrlWithQuery(['milestone_page' => $milestonePage + 1]) }}" aria-label="Next">
                        <i class="feather-chevron-right"></i>
                    </a>
                </li>
            </ul>
            <div class="erp-pagination-info">
                Showing {{ min(($milestonePage - 1) * $milestonesPerPage + 1, $totalMilestones) }} to {{ min($milestonePage * $milestonesPerPage, $totalMilestones) }} of {{ $totalMilestones }} entries
            </div>
        </div>
    </div>
@endif

@push('styles')
    <style>
        .erp-pagination-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: auto !important;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }
        .erp-pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 0;
            padding-left: 0;
            list-style: none;
        }
        .erp-pagination .page-item {
            display: inline-block;
        }
        .erp-pagination .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50% !important;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            cursor: pointer;
        }
        .erp-pagination .page-link:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.08);
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }
        .erp-pagination .page-item.active .page-link {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 10px rgba(var(--bs-primary-rgb), 0.2);
        }
        .erp-pagination .page-item.disabled .page-link {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }
        .erp-pagination-info {
            font-size: 12px;
            color: #64748b;
        }
    </style>
@endpush

@if ($canManageMilestones)
    @include('modules.projects.milestones._modal')
    @include('modules.projects.milestones._drawer')

    @if ($errors->any() && in_array(old('_milestone_form'), ['add', 'edit'], true))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    @if (old('_milestone_form') === 'edit')
                        openMilestoneModal('edit', {
                            id: {{ (int) old('_milestone_id') }},
                            updateUrl: @js(route('projects.milestones.update', [$project, (int) old('_milestone_id')])),
                            deleteUrl: @js(route('projects.milestones.destroy', [$project, (int) old('_milestone_id')])),
                            name: @js(old('name')),
                            description: @js(old('description')),
                            ownerId: @js(old('owner_id')),
                            startDate: @js(old('start_date')),
                            dueDate: @js(old('due_date')),
                            status: @js(old('status')),
                            completionPercentage: @js(old('completion_percentage')),
                        });
                    @else
                        openMilestoneModal('add', {
                            name: @js(old('name')),
                            description: @js(old('description')),
                            ownerId: @js(old('owner_id')),
                            startDate: @js(old('start_date')),
                            dueDate: @js(old('due_date')),
                            status: @js(old('status')),
                            completionPercentage: @js(old('completion_percentage')),
                        });
                    @endif
                });
            </script>
        @endpush
    @endif
@endif
