@php
    $milestoneSearch = trim((string) request('search', ''));
    $milestoneStatusFilter = (string) request('status', '');
    $milestoneOwnerFilter = (string) request('owner_id', '');
    $milestoneHealthFilter = (string) request('health', '');
    $milestoneOverdueFilter = (string) request('overdue', '');

    $hasActiveMilestoneFilters = $milestoneSearch !== ''
        || $milestoneStatusFilter !== ''
        || $milestoneOwnerFilter !== ''
        || $milestoneHealthFilter !== ''
        || $milestoneOverdueFilter !== '';

    $milestoneToday = \Illuminate\Support\Carbon::today();

    $filteredMilestones = $milestones->filter(function ($milestone) use (
        $milestoneSearch,
        $milestoneStatusFilter,
        $milestoneOwnerFilter,
        $milestoneHealthFilter,
        $milestoneOverdueFilter,
        $milestoneToday
    ) {
        if ($milestoneSearch !== '') {
            $haystack = strtolower($milestone->name . ' ' . $milestone->description);
            if (! str_contains($haystack, strtolower($milestoneSearch))) {
                return false;
            }
        }

        if ($milestoneStatusFilter !== '' && $milestone->status !== $milestoneStatusFilter) {
            return false;
        }

        if ($milestoneOwnerFilter !== '' && (string) $milestone->owner_id !== $milestoneOwnerFilter) {
            return false;
        }

        if ($milestoneHealthFilter !== '' && $milestone->health_state !== $milestoneHealthFilter) {
            return false;
        }

        if ($milestoneOverdueFilter === '1') {
            $isOverdue = $milestone->due_date
                && $milestoneToday->gt($milestone->due_date)
                && ! in_array($milestone->status, ['Completed', 'Closed'], true);

            if (! $isOverdue) {
                return false;
            }
        }

        return true;
    })->values();

    $milestoneOwnerOptions = $milestones->pluck('owner')->filter()->unique('id')->sortBy(fn ($owner) => $owner->name)->values();

    $milestonePage = (int) request('milestone_page', 1);
    $milestonesPerPage = 8;
    $totalFilteredMilestones = $filteredMilestones->count();
    $totalMilestonePages = (int) ceil($totalFilteredMilestones / $milestonesPerPage);
    $paginatedMilestones = $filteredMilestones->slice(($milestonePage - 1) * $milestonesPerPage, $milestonesPerPage);
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h5 class="fw-bold text-dark mb-0">
        <i class="feather-flag me-2 text-primary"></i>{{ __('projects.milestones') }}
    </h5>
</div>

{{-- Toolbar --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <form method="GET" action="{{ route('projects.show', $project) }}" class="d-flex flex-wrap align-items-center gap-2">
        <input type="hidden" name="tab" value="milestones">
        <div style="min-width: 220px;">
            <input type="text" name="search" class="form-control form-control-sm" value="{{ $milestoneSearch }}"
                   placeholder="{{ __('projects.milestone_search_and_desc_placeholder') }}">
        </div>

        <button type="submit" class="btn btn-primary btn-sm">
            <i class="feather-search me-1"></i>{{ __('ui.search') }}
        </button>

        <x-ui.filter :label="__('ui.filter')" offset="0, 5">
            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i>{{ __('projects.filter_options') }}</h6>

            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.status') }}</label>
                <x-ui.odoo-form-ui type="select" name="status">
                    <option value="">{{ __('projects.all_statuses') }}</option>
                    @foreach (\App\Domains\Projects\Models\Milestone::STATUSES as $statusOption)
                        <option value="{{ $statusOption }}" @selected($milestoneStatusFilter === $statusOption)>
                            {{ __('projects.statuses.' . $statusOption) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.milestone_owner') }}</label>
                <x-ui.odoo-form-ui type="select" name="owner_id" select2Selector="default">
                    <option value="">{{ __('projects.all_owners') }}</option>
                    @foreach ($milestoneOwnerOptions as $owner)
                        <option value="{{ $owner->id }}" @selected($milestoneOwnerFilter === (string) $owner->id)>
                            {{ $owner->name }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.health') }}</label>
                <x-ui.odoo-form-ui type="select" name="health">
                    <option value="">{{ __('projects.all_health') }}</option>
                    @foreach (['on_track', 'at_risk', 'off_track', 'blocked', 'not_applicable'] as $healthOption)
                        <option value="{{ $healthOption }}" @selected($milestoneHealthFilter === $healthOption)>
                            {{ __('projects.health_states.' . $healthOption) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'milestones']) }}" class="btn btn-sm btn-light border">{{ __('projects.reset') }}</a>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('projects.apply_filters') }}</button>
            </div>
        </x-ui.filter>
    </form>

    @if ($canManageMilestones)
        <button type="button" class="btn btn-primary btn-sm" onclick="startMilestoneInlineCreate()">
            <i class="feather-plus me-1"></i>{{ __('projects.add_milestone') }}
        </button>
    @endif
</div>

@if ($hasActiveMilestoneFilters)
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        @if ($milestoneSearch !== '')
            <x-ui.badge variant="secondary" soft>{{ __('projects.search_keywords') }}: "{{ $milestoneSearch }}"</x-ui.badge>
        @endif
        @if ($milestoneStatusFilter !== '')
            <x-ui.badge variant="secondary" soft>{{ __('projects.status') }}: {{ __('projects.statuses.' . $milestoneStatusFilter) }}</x-ui.badge>
        @endif
        @if ($milestoneOwnerFilter !== '')
            @php $milestoneOwnerChip = $milestoneOwnerOptions->firstWhere('id', (int) $milestoneOwnerFilter); @endphp
            <x-ui.badge variant="secondary" soft>{{ __('projects.milestone_owner') }}: {{ $milestoneOwnerChip->name ?? '—' }}</x-ui.badge>
        @endif
        @if ($milestoneHealthFilter !== '')
            <x-ui.badge variant="secondary" soft>{{ __('projects.health') }}: {{ __('projects.health_states.' . $milestoneHealthFilter) }}</x-ui.badge>
        @endif
        @if ($milestoneOverdueFilter === '1')
            <x-ui.badge variant="secondary" soft>{{ __('projects.kpi_overdue') }}</x-ui.badge>
        @endif
        <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'milestones']) }}" class="fs-11 text-danger fw-semibold">
            <i class="feather-x me-1"></i>{{ __('projects.clear_filters') }}
        </a>
    </div>
@endif

{{-- Milestone hybrid rows --}}
@if ($paginatedMilestones->isNotEmpty())
    <div class="border rounded-3 overflow-hidden" id="milestoneListContainer">
        @foreach ($paginatedMilestones as $milestone)
            @include('modules.projects.milestones._row')
        @endforeach
    </div>
@elseif ($milestones->isEmpty())
    <div id="milestoneEmptyState" class="text-center py-5">
        <div class="avatar-text avatar-xl bg-soft-primary text-primary mx-auto mb-3">
            <i class="feather-flag fs-24"></i>
        </div>
        <div class="fs-15 fw-semibold text-dark mb-1">{{ __('projects.no_milestones_yet') }}</div>
        <p class="fs-12 text-muted mb-3">{{ __('projects.no_milestones_yet_hint') }}</p>
        @if ($canManageMilestones)
            <button type="button" class="btn btn-primary btn-sm" onclick="startMilestoneInlineCreate()">
                <i class="feather-plus me-1"></i>{{ __('projects.add_milestone') }}
            </button>
        @endif
    </div>
@else
    <div class="text-center py-5">
        <div class="avatar-text avatar-xl bg-soft-secondary text-secondary mx-auto mb-3">
            <i class="feather-search fs-24"></i>
        </div>
        <div class="fs-15 fw-semibold text-dark mb-1">{{ __('projects.no_matching_milestones') }}</div>
        <p class="fs-12 text-muted mb-3">{{ __('projects.no_matching_milestones_hint') }}</p>
        <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'milestones']) }}" class="btn btn-light-brand btn-sm">
            <i class="feather-x me-1"></i>{{ __('projects.clear_filters') }}
        </a>
    </div>
@endif

@if ($totalMilestonePages > 1)
    <div class="pt-3">
        <x-ui.pagination
            :currentPage="$milestonePage"
            :totalPages="$totalMilestonePages"
            :totalResults="$totalFilteredMilestones"
            :perPage="$milestonesPerPage"
            pageParam="milestone_page"
            tab="milestones"
        />
    </div>
@endif

@if ($canManageMilestones)
    @include('modules.projects.milestones._modal')
    @include('modules.projects.milestones._drawer')
    @include('modules.projects.milestones._create-row')

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
