@php
    $activityTotalPages = (int) ceil($activityTotal / $activityPerPage);
@endphp

<div class="border rounded-3">
    @include('modules.projects._activity-list', ['activities' => $activities])
</div>

<x-ui.pagination
    :currentPage="$activityPage"
    :totalPages="$activityTotalPages"
    :totalResults="$activityTotal"
    :perPage="$activityPerPage"
    pageParam="activity_page"
    tab="activity"
/>
