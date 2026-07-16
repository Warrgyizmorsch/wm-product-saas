@php
    $activityTotalPages = (int) ceil($activityTotal / $activityPerPage);
@endphp

<h6 class="fw-bold text-dark mb-3"><i class="feather-activity me-2 text-primary"></i>{{ __('projects.activity') }}</h6>

<div class="border rounded-3">
    @include('modules.projects._activity-list', ['activities' => $activities])
</div>

<x-ui.pagination
    :currentPage="$activityPage"
    :totalPages="$activityTotalPages"
    :totalResults="$activityTotal"
    :perPage="$activityPerPage"
    pageParam="activity_page"
/>
