@php
    $activityTotalPages = (int) ceil($activityTotal / $activityPerPage);
@endphp

<div class="project-content-card p-3 p-md-4">
    <div class="milestone-activity-scroll">
        @include('modules.projects._activity-list', ['activities' => $activities])
    </div>
</div>

<x-ui.pagination
    :currentPage="$activityPage"
    :totalPages="$activityTotalPages"
    :totalResults="$activityTotal"
    :perPage="$activityPerPage"
    pageParam="activity_page"
    tab="activity"
/>

@once
    @push('styles')
        <style>
            /* Caps the card height so it doesn't grow tall with a full page of activity items. */
            .milestone-activity-scroll {
                max-height: 480px;
                overflow-y: auto;
            }
        </style>
    @endpush
@endonce
