@php
    $pastCount = $pastDoneActivitiesCount ?? ($pastDoneActivities ? $pastDoneActivities->count() : 0);
    $hasAnyPending = $plannedActivities->isNotEmpty() || $overdueActivities->isNotEmpty();
    $activeActionableCount = $plannedActivities->count() + $overdueActivities->count() + $todayDoneActivities->count();
    $allActivitiesTotal = $allActivitiesTotalCount ?? ($activeActionableCount + $pastCount);
    $historyCount = $lead->histories->count();
@endphp

<div class="odoo-chatter-col h-100 border-start d-flex flex-column d-print-none bg-white" style="width: 440px; min-width: 380px; max-width: 480px; flex-shrink: 0; overflow-x: hidden !important; overflow-y: auto !important;" id="odooRightChatter">
    
    <!-- Chatter Sticky Header with Tabs & Filter Dropdown -->
    <div class="p-2.5 px-3 border-bottom bg-white d-flex align-items-center justify-content-between sticky-top shadow-2xs gap-1.5 flex-nowrap" style="z-index: 10;">
        <div class="nav nav-pills odoo-chatter-nav-pills p-0.5 bg-light rounded-2 border d-inline-flex flex-row align-items-center flex-nowrap" id="odooChatterTabs" role="tablist">
            <button class="nav-link px-2.5 py-1 fs-12 fw-bold active d-inline-flex align-items-center gap-1.5 rounded-2 text-nowrap" id="chatter-activity-tab" data-bs-toggle="pill" data-bs-target="#chatter-activity-pane" type="button" role="tab" aria-controls="chatter-activity-pane" aria-selected="true">
                <i class="feather-calendar fs-12"></i>
                <span>Activities</span>
                @if($activeActionableCount > 0)
                    <span class="badge bg-primary text-white rounded-pill px-1.5 py-0.2 fs-10 font-monospace">{{ $activeActionableCount }}</span>
                @endif
            </button>
            <button class="nav-link px-2.5 py-1 fs-12 fw-bold d-inline-flex align-items-center gap-1.5 rounded-2 text-muted text-nowrap" id="chatter-history-tab" data-bs-toggle="pill" data-bs-target="#chatter-history-pane" type="button" role="tab" aria-controls="chatter-history-pane" aria-selected="false">
                <i class="feather-clock fs-12"></i>
                <span>History</span>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-1.5 py-0.2 fs-10 font-monospace">{{ $historyCount }}</span>
            </button>
        </div>

        <!-- Filter Dropdown (Swapped to Top Right) -->
        <div class="dropdown">
            <button class="btn btn-xs btn-white border dropdown-toggle d-inline-flex align-items-center gap-1.5 px-2.5 py-1 rounded-2 shadow-2xs fw-semibold fs-12 text-dark" type="button" id="activityFilterDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="feather-filter text-primary fs-12"></i>
                <span id="activeFilterLabel">All ({{ $allActivitiesTotal }})</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm fs-12 py-1 border" aria-labelledby="activityFilterDropdownBtn" id="activityFilterMenu" style="min-width: 220px;">
                <li>
                    <a class="dropdown-item activity-filter-item d-flex align-items-center justify-content-between py-1.5 px-3 active" href="javascript:void(0)" data-filter="all" onclick="applyChatterActivityFilter('all', 'All', {{ $allActivitiesTotal }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-list text-muted fs-12"></i>
                            <span>All Activities</span>
                        </span>
                        <span class="badge bg-dark text-white rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $allActivitiesTotal }}</span>
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item activity-filter-item d-flex align-items-center justify-content-between py-1.5 px-3" href="javascript:void(0)" data-filter="planned" onclick="applyChatterActivityFilter('planned', 'Planned', {{ $plannedActivities->count() }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-calendar text-success fs-12"></i>
                            <span>Planned Activities</span>
                        </span>
                        <span class="badge bg-success text-white rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $plannedActivities->count() }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item activity-filter-item d-flex align-items-center justify-content-between py-1.5 px-3" href="javascript:void(0)" data-filter="overdue" onclick="applyChatterActivityFilter('overdue', 'Overdue', {{ $overdueActivities->count() }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-alert-circle text-danger fs-12"></i>
                            <span>Overdue Activities</span>
                        </span>
                        <span class="badge bg-danger text-white rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $overdueActivities->count() }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item activity-filter-item d-flex align-items-center justify-content-between py-1.5 px-3" href="javascript:void(0)" data-filter="today" onclick="applyChatterActivityFilter('today', 'Today', {{ $todayDoneActivities->count() }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-check-circle text-warning fs-12"></i>
                            <span>Today Activity</span>
                        </span>
                        <span class="badge bg-warning text-dark rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $todayDoneActivities->count() }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item activity-filter-item d-flex align-items-center justify-content-between py-1.5 px-3" href="javascript:void(0)" data-filter="past" onclick="applyChatterActivityFilter('past', 'Past', {{ $pastCount }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-clock text-secondary fs-12"></i>
                            <span>Past Activities</span>
                        </span>
                        <span class="badge bg-secondary text-white rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $pastCount }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Chatter Tab Panes -->
    <div class="tab-content flex-grow-1 p-3 bg-white" id="odooChatterTabContent">
        
        <!-- ==================== CHATTER PANE 1: ACTIVITIES ==================== -->
        <div class="tab-pane fade show active" id="chatter-activity-pane" role="tabpanel" aria-labelledby="chatter-activity-tab">

            <div class="activity-feed-container bg-white">
                <!-- Sub-Header Actions: Soft White Button Style (+ Activity, ✎ Note) -->
                <div class="d-flex align-items-center gap-2 mb-2.5 pb-2 border-bottom bg-white">
                    <button type="button" 
                            class="btn btn-xs btn-white border rounded-2 px-2.5 py-1 fw-semibold fs-11 text-primary d-inline-flex align-items-center gap-1.5 shadow-2xs hover-primary btn-open-followup-offcanvas"
                            data-bs-toggle="offcanvas" 
                            data-bs-target="#leadFollowupOffcanvas" 
                            data-lead-id="{{ $lead->id }}" 
                            data-lead-name="{{ $lead->company_name }}" 
                            data-lead-status="{{ $lead->status }}" 
                            data-lead-priority="{{ $lead->priority }}" 
                            data-next-followup="{{ $lead->next_followup_date ? $lead->next_followup_date->format('Y-m-d\TH:i') : '' }}"
                            title="{{ __('crm.schedule_next_activity_btn') }}">
                        <i class="feather-plus fs-11 text-primary"></i>
                        <span>Activity</span>
                    </button>

                    <button type="button" 
                            class="btn btn-xs btn-white border rounded-2 px-2.5 py-1 fw-semibold fs-11 text-dark d-inline-flex align-items-center gap-1.5 shadow-2xs hover-light"
                            data-bs-toggle="modal" 
                            data-bs-target="#modalLogNote" 
                            title="{{ __('crm.add_note') }}">
                        <i class="feather-edit-2 fs-10 text-muted"></i>
                        <span>Note</span>
                    </button>
                </div>

                <!-- ODOO ACTIVITY FEED -->
                <div class="odoo-chatter-feed">
                    
                    <!-- 1. OVERDUE ACTIVITIES (Pending past today) -->
                    @if($overdueActivities->isNotEmpty())
                        <div class="activity-section-block" data-activity-section="overdue">
                            <div class="odoo-feed-divider odoo-feed-divider--overdue" data-bs-toggle="collapse" data-bs-target="#collapseOverdue_chatter" aria-expanded="true" aria-controls="collapseOverdue_chatter">
                                <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.overdue_activities') }} ({{ $overdueActivities->count() }})</span>
                            </div>
                            <div class="collapse show odoo-activity-list mb-3" id="collapseOverdue_chatter">
                                @foreach($overdueActivities->take(10) as $item)
                                    @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'overdue', 'lead' => $lead, 'users' => $users])
                                @endforeach

                                @if($overdueActivities->count() > 10)
                                    <div class="extra-overdue-items d-none">
                                        @foreach($overdueActivities->slice(10) as $item)
                                            @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'overdue', 'lead' => $lead, 'users' => $users])
                                        @endforeach
                                    </div>
                                    <div class="text-center pt-2 pb-1">
                                        <button type="button" class="btn btn-xs btn-white border rounded-pill px-3 py-1.5 fs-11 text-secondary fw-semibold shadow-2xs hover-primary btn-load-more-section" data-target=".extra-overdue-items" data-count="{{ $overdueActivities->count() - 10 }}">
                                            <i class="feather-chevron-down fs-11 me-1"></i>
                                            <span>Load More (+{{ $overdueActivities->count() - 10 }} more)</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- 2. PLANNED ACTIVITIES (Upcoming from Today Onwards) -->
                    @if($plannedActivities->isNotEmpty())
                        <div class="activity-section-block" data-activity-section="planned">
                            <div class="odoo-feed-divider odoo-feed-divider--planned" data-bs-toggle="collapse" data-bs-target="#collapsePlanned_chatter" aria-expanded="true" aria-controls="collapsePlanned_chatter">
                                <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.planned_activities') }} ({{ $plannedActivities->count() }})</span>
                            </div>
                            <div class="collapse show odoo-activity-list mb-3" id="collapsePlanned_chatter">
                                @foreach($plannedActivities->take(10) as $item)
                                    @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'planned', 'lead' => $lead, 'users' => $users])
                                @endforeach

                                @if($plannedActivities->count() > 10)
                                    <div class="extra-planned-items d-none">
                                        @foreach($plannedActivities->slice(10) as $item)
                                            @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'planned', 'lead' => $lead, 'users' => $users])
                                        @endforeach
                                    </div>
                                    <div class="text-center pt-2 pb-1">
                                        <button type="button" class="btn btn-xs btn-white border rounded-pill px-3 py-1.5 fs-11 text-secondary fw-semibold shadow-2xs hover-primary btn-load-more-section" data-target=".extra-planned-items" data-count="{{ $plannedActivities->count() - 10 }}">
                                            <i class="feather-chevron-down fs-11 me-1"></i>
                                            <span>Load More (+{{ $plannedActivities->count() - 10 }} more)</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- 3. TODAY'S ACTIVITIES (Done/Logged Today) -->
                    @if($todayDoneActivities->isNotEmpty())
                        <div class="activity-section-block" data-activity-section="today">
                            <div class="odoo-feed-divider odoo-feed-divider--today-done" data-bs-toggle="collapse" data-bs-target="#collapseToday_chatter" aria-expanded="true" aria-controls="collapseToday_chatter">
                                <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.today_activity') }} ({{ $todayDoneActivities->count() }})</span>
                            </div>
                            <div class="collapse show odoo-activity-list mb-3" id="collapseToday_chatter">
                                @foreach($todayDoneActivities as $item)
                                    @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'today_done', 'lead' => $lead, 'users' => $users])
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- 4. PAST ACTIVITIES (On-demand DB fetch with infinite scroll) -->
                    @if($pastCount > 0)
                        <div class="activity-section-block" data-activity-section="past">
                            <div class="odoo-feed-divider odoo-feed-divider--past-done" data-bs-toggle="collapse" data-bs-target="#collapsePast_chatter" aria-expanded="true" aria-controls="collapsePast_chatter">
                                <span><i class="feather-chevron-down toggle-arrow me-1"></i> Past Activities (<span id="pastActivitiesTotalBadge">{{ $pastCount }}</span>)</span>
                            </div>
                            <div class="collapse show odoo-activity-list mb-3" id="collapsePast_chatter">
                                <div id="pastActivitiesContainer">
                                    @foreach($pastDoneActivities as $item)
                                        @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'history', 'lead' => $lead, 'users' => $users])
                                    @endforeach
                                </div>

                                @if($pastCount > 5)
                                    <div id="pastActivitiesInfiniteTrigger" class="text-center py-2.5 my-2" data-lead-id="{{ $lead->id }}" data-current-page="1" data-has-more="true" data-total="{{ $pastCount }}">
                                        <div class="past-activities-spinner d-none text-primary fs-11 py-1 fw-semibold">
                                            <span class="spinner-border spinner-border-sm text-primary me-1.5" style="width: 14px; height: 14px;" role="status"></span>
                                            <span>Loading next 5 past activities...</span>
                                        </div>
                                        <button type="button" class="btn btn-xs btn-white border rounded-pill px-3 py-1.5 fs-11 text-secondary fw-semibold shadow-2xs hover-primary btn-load-more-past-activities" onclick="loadNextPastActivities()">
                                            <i class="feather-chevron-down fs-11 me-1 text-primary"></i>
                                            <span>Load More Activities (+{{ $pastCount - 5 }} remaining)</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Fallback if no activities at all -->
                    @if(!$hasAnyPending && $todayDoneActivities->isEmpty() && $pastCount == 0)
                        <div class="text-center py-5 px-3 bg-white border border-dashed rounded-3 my-3">
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-2">
                                <i class="feather-calendar fs-5"></i>
                            </div>
                            <h6 class="fw-bold text-dark fs-13 mb-1">{{ __('crm.no_pending_activities') }}</h6>
                            <p class="text-muted fs-11 mb-3">Schedule a call, meeting, or email reminder for this lead.</p>
                            <x-ui.button type="button" variant="primary" size="xs" icon="feather-plus" class="fw-semibold px-3 py-1.5 rounded-pill btn-open-followup-offcanvas" 
                                    data-bs-toggle="offcanvas" 
                                    data-bs-target="#leadFollowupOffcanvas" 
                                    data-lead-id="{{ $lead->id }}" 
                                    data-lead-name="{{ $lead->company_name }}" 
                                    data-lead-status="{{ $lead->status }}" 
                                    data-lead-priority="{{ $lead->priority }}" 
                                    data-next-followup="{{ $lead->next_followup_date ? $lead->next_followup_date->format('Y-m-d\TH:i') : '' }}">
                                {{ __('crm.schedule_next_activity_btn') }}
                            </x-ui.button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- ==================== CHATTER PANE 2: HISTORY & AUDIT ==================== -->
        <div class="tab-pane fade" id="chatter-history-pane" role="tabpanel" aria-labelledby="chatter-history-tab">
            
            <!-- Sticky Sub-Header with Search & Counter -->
            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom flex-wrap gap-2">
                <div class="d-flex align-items-center gap-1.5">
                    <span class="fs-12 fw-bold text-dark d-flex align-items-center gap-1.5">
                        <i class="feather-clock text-primary fs-12"></i>
                        <span>{{ __('crm.timeline_history') }}</span>
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5 fs-10 font-monospace fw-bold" id="historyTotalBadge">{{ $historyCount }}</span>
                </div>
                <div class="input-group input-group-sm" style="width: 150px;">
                    <span class="input-group-text bg-white border-end-0 py-0.5 px-2 text-muted" style="border-color: #e2e8f0;"><i class="feather-search fs-10"></i></span>
                    <input type="search" class="form-control form-control-sm border-start-0 py-0.5 px-1 fs-11" id="historyQuickSearch" placeholder="Search history..." oninput="filterHistoryEvents(this.value)" onsearch="filterHistoryEvents(this.value)" style="border-color: #e2e8f0;">
                </div>
            </div>

            <!-- Timeline Main Container -->
            <div class="history-timeline-main-wrapper position-relative">
                @if($groupedHistory->isEmpty())
                    <div class="text-center py-5 text-muted border border-dashed rounded-3 bg-white fs-12 my-2">
                        <div class="avatar-text avatar-md bg-light text-muted rounded-circle mx-auto mb-2">
                            <i class="feather-clock fs-20 opacity-50"></i>
                        </div>
                        <h6 class="fw-bold text-dark fs-12 mb-1">{{ __('crm.no_history_events') }}</h6>
                        <p class="text-muted fs-11 mb-0">No past audit entries or activity records found for this lead.</p>
                    </div>
                @else
                    <div id="historyEventsList">
                        @foreach($groupedHistory as $date => $items)
                            @php
                                try {
                                    $parsedDate = \Carbon\Carbon::createFromFormat('d/m/Y', $date)->startOfDay();
                                    if ($parsedDate->isToday()) {
                                        $displayDate = 'Today • ' . $parsedDate->format('d M Y');
                                    } elseif ($parsedDate->isYesterday()) {
                                        $displayDate = 'Yesterday • ' . $parsedDate->format('d M Y');
                                    } else {
                                        $displayDate = $parsedDate->format('d M Y') . ' (' . $parsedDate->format('l') . ')';
                                    }
                                } catch (\Exception $e) {
                                    $displayDate = $date;
                                }
                            @endphp

                            <!-- Date Group -->
                            <div class="history-date-group mb-3">
                                <!-- Date Separator Badge -->
                                <div class="d-flex align-items-center gap-2 mb-2.5">
                                    <div class="flex-grow-1 border-top" style="border-color: #e2e8f0 !important;"></div>
                                    <span class="badge bg-white text-secondary border px-2.5 py-1 rounded-pill fs-11 fw-semibold shadow-2xs d-inline-flex align-items-center gap-1.5" style="border-color: #cbd5e1 !important;">
                                        <i class="feather-calendar text-primary fs-11"></i>
                                        <span>{{ $displayDate }}</span>
                                    </span>
                                    <div class="flex-grow-1 border-top" style="border-color: #e2e8f0 !important;"></div>
                                </div>

                                <!-- Items in this date -->
                                @foreach($items as $item)
                                    @include('modules.crm.leads.partials.history-timeline-item', ['item' => $item])
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <!-- No Results Search Fallback -->
                    <div id="historyEmptySearchResults" class="text-center py-4 text-muted border border-dashed rounded-3 bg-white fs-11 my-2" style="display: none;">
                        <i class="feather-search fs-18 mb-1 d-block text-muted opacity-50"></i>
                        <span>No history events match your filter.</span>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

<script>
    // History Search Helper
    function filterHistoryEvents(query) {
        var q = (query || '').toLowerCase().trim();
        var totalVisible = 0;

        if (!q) {
            // When query is cleared or empty, instantly restore ALL history items and groups
            $('.history-date-group').show();
            $('.history-timeline-item').show();
            $('#historyEmptySearchResults').hide();
            $('#historyTotalBadge').text($('.history-timeline-item').length);
            return;
        }

        // When query is present, check each date group and its items
        $('.history-date-group').each(function() {
            var $group = $(this);
            var groupVisibleCount = 0;

            $group.find('.history-timeline-item').each(function() {
                var $item = $(this);
                var text = ($item.attr('data-search-term') || '').toLowerCase();
                if (text.indexOf(q) !== -1) {
                    $item.show();
                    groupVisibleCount++;
                    totalVisible++;
                } else {
                    $item.hide();
                }
            });

            // Show date group only if it has matching items
            $group.toggle(groupVisibleCount > 0);
        });

        $('#historyEmptySearchResults').toggle(totalVisible === 0);
        $('#historyTotalBadge').text(totalVisible);
    }

    function applyChatterActivityFilter(filterType, label, count) {
        var container = $('#chatter-activity-pane');
        var countText = (count !== undefined && count !== null) ? ' (' + count + ')' : '';
        $('#activeFilterLabel').text(label + countText);
        $('#activityFilterMenu .activity-filter-item').removeClass('active');
        $('#activityFilterMenu .activity-filter-item[data-filter="' + filterType + '"]').addClass('active');

        if (filterType === 'planned') {
            container.find('.activity-section-block').hide();
            container.find('.activity-section-block[data-activity-section="planned"]').stop(true, true).fadeIn(150).find('.collapse').collapse('show');
        } else if (filterType === 'overdue') {
            container.find('.activity-section-block').hide();
            container.find('.activity-section-block[data-activity-section="overdue"]').stop(true, true).fadeIn(150).find('.collapse').collapse('show');
        } else if (filterType === 'today') {
            container.find('.activity-section-block').hide();
            container.find('.activity-section-block[data-activity-section="today"]').stop(true, true).fadeIn(150).find('.collapse').collapse('show');
        } else if (filterType === 'past') {
            container.find('.activity-section-block').hide();
            container.find('.activity-section-block[data-activity-section="past"]').stop(true, true).fadeIn(150).find('.collapse').collapse('show');
        } else {
            // All
            container.find('.activity-section-block').stop(true, true).fadeIn(150);
        }
    }

    // Toggle Load More in Overdue and Planned sections
    $(document).on('click', '.btn-load-more-section', function() {
        var btn = $(this);
        var target = $(btn.attr('data-target'));
        var count = btn.attr('data-count');
        if (target.hasClass('d-none')) {
            target.removeClass('d-none').hide().slideDown(200);
            btn.find('span').text('Show Less');
            btn.find('i').removeClass('feather-chevron-down').addClass('feather-chevron-up');
        } else {
            target.slideUp(200, function() {
                target.addClass('d-none');
            });
            btn.find('span').text('Load More (+' + count + ' more)');
            btn.find('i').removeClass('feather-chevron-up').addClass('feather-chevron-down');
        }
    });

    // Past Activities Infinite Scroll Lazy Loader
    var pastActivitiesLoading = false;

    function loadNextPastActivities() {
        var trigger = $('#pastActivitiesInfiniteTrigger');
        if (!trigger.length || pastActivitiesLoading) return;

        var hasMore = trigger.attr('data-has-more') === 'true';
        if (!hasMore) return;

        var leadId = trigger.attr('data-lead-id') || '{{ $lead->id }}';
        var currentPage = parseInt(trigger.attr('data-current-page') || 1);
        var nextPage = currentPage + 1;

        pastActivitiesLoading = true;
        trigger.find('.past-activities-spinner').removeClass('d-none');
        trigger.find('.btn-load-more-past-activities').addClass('d-none');

        // Always use window.location.origin to avoid any localhost vs 127.0.0.1 CORS mismatches
        var endpointUrl = window.location.origin + '/crm/leads/' + leadId + '/past-activities';

        $.ajax({
            url: endpointUrl,
            type: 'GET',
            data: { page: nextPage, per_page: 5 },
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(res) {
                pastActivitiesLoading = false;
                trigger.find('.past-activities-spinner').addClass('d-none');

                if (res && res.html && res.html.trim().length > 0) {
                    $('#pastActivitiesContainer').append(res.html);
                    trigger.attr('data-current-page', nextPage);
                    trigger.attr('data-has-more', res.has_more ? 'true' : 'false');

                    if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
                        $('#pastActivitiesContainer [data-bs-toggle="popover"]:not([data-bs-original-title])').each(function() {
                            new bootstrap.Popover(this);
                        });
                    }

                    if (res.has_more && res.remaining > 0) {
                        trigger.find('.btn-load-more-past-activities').removeClass('d-none').find('span').text('Load More Activities (+' + res.remaining + ' remaining)');
                    } else {
                        trigger.remove();
                    }
                } else {
                    trigger.attr('data-has-more', 'false');
                    trigger.remove();
                }
            },
            error: function(xhr, status, error) {
                console.error('Past activities load error:', error);
                pastActivitiesLoading = false;
                trigger.find('.past-activities-spinner').addClass('d-none');
                trigger.find('.btn-load-more-past-activities').removeClass('d-none').html('<i class="feather-refresh-cw me-1 text-danger"></i> Failed to load. Click to retry');
            }
        });
    }

    // Scroll listener on right chatter column and window
    $(document).ready(function() {
        var chatterColumn = document.getElementById('odooRightChatter');

        function checkScrollTrigger() {
            var trigger = $('#pastActivitiesInfiniteTrigger');
            if (!trigger.length || pastActivitiesLoading) return;

            // 1. Check #odooRightChatter inner scroll position
            var col = $('#odooRightChatter');
            if (col.length) {
                var scrollTop = col.scrollTop();
                var scrollHeight = col[0].scrollHeight;
                var clientHeight = col.innerHeight();

                if (scrollTop + clientHeight >= scrollHeight - 350) {
                    loadNextPastActivities();
                    return;
                }
            }

            // 2. Secondary check via getBoundingClientRect
            var triggerElem = document.getElementById('pastActivitiesInfiniteTrigger');
            if (triggerElem) {
                var rect = triggerElem.getBoundingClientRect();
                var limit = chatterColumn ? chatterColumn.getBoundingClientRect().bottom : window.innerHeight;
                if (rect.top <= limit + 250) {
                    loadNextPastActivities();
                }
            }
        }

        if (chatterColumn) {
            chatterColumn.addEventListener('scroll', checkScrollTrigger, { passive: true });
        }
        // Capture-phase listener catches scroll on ANY ancestor container
        document.addEventListener('scroll', checkScrollTrigger, true);
        window.addEventListener('scroll', checkScrollTrigger, { passive: true });

        // IntersectionObserver backup
        if ('IntersectionObserver' in window) {
            var triggerElem = document.getElementById('pastActivitiesInfiniteTrigger');
            if (triggerElem) {
                var observer = new IntersectionObserver(function(entries) {
                    if (entries[0].isIntersecting) {
                        loadNextPastActivities();
                    }
                }, {
                    root: chatterColumn || null,
                    rootMargin: '200px'
                });
                observer.observe(triggerElem);
            }
        }
    });
</script>
