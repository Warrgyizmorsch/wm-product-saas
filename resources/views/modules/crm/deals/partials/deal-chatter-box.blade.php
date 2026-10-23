@php
    $pastCount = $pastDoneActivities ? $pastDoneActivities->count() : 0;
    $hasAnyPending = $plannedActivities->isNotEmpty() || $overdueActivities->isNotEmpty();
    $activeActionableCount = $plannedActivities->count() + $overdueActivities->count() + $todayDoneActivities->count();
    $allActivitiesTotal = $activeActionableCount + $pastCount;
    $historyCount = $groupedHistory->flatten()->count();

    $dealOriginCount = $allDealFollowups->filter(fn($i) => $i->crm_deal_id && $i->crm_deal_id == $deal->id)->count();
    $leadOriginCount = $allDealFollowups->filter(fn($i) => $i->lead_id && $i->crm_deal_id != $deal->id)->count();
@endphp

<div class="odoo-chatter-col h-100 border-start d-flex flex-column d-print-none bg-white" style="width: 440px; min-width: 380px; max-width: 480px; flex-shrink: 0; overflow-x: hidden !important; overflow-y: auto !important;" id="odooRightChatter">
    
    <!-- Chatter Sticky Header with Tabs & Filter Dropdown -->
    <div class="p-2.5 px-3 border-bottom bg-white d-flex align-items-center justify-content-between sticky-top shadow-2xs gap-1.5 flex-nowrap" style="z-index: 10;">
        <div class="nav nav-pills odoo-chatter-nav-pills p-0.5 bg-light rounded-2 border d-inline-flex flex-row align-items-center flex-nowrap" id="dealChatterTabs" role="tablist">
            <button class="nav-link px-2.5 py-1 fs-12 fw-bold active d-inline-flex align-items-center gap-1.5 rounded-2 text-nowrap" id="deal-chatter-activity-tab" data-bs-toggle="pill" data-bs-target="#deal-chatter-activity-pane" type="button" role="tab" aria-controls="deal-chatter-activity-pane" aria-selected="true">
                <i class="feather-calendar fs-12"></i>
                <span>{{ __('crm.activities') }}</span>
                @if($activeActionableCount > 0)
                    <span class="badge bg-primary text-white rounded-pill px-1.5 py-0.2 fs-10 font-monospace">{{ $activeActionableCount }}</span>
                @endif
            </button>
            <button class="nav-link px-2.5 py-1 fs-12 fw-bold d-inline-flex align-items-center gap-1.5 rounded-2 text-muted text-nowrap" id="deal-chatter-history-tab" data-bs-toggle="pill" data-bs-target="#deal-chatter-history-pane" type="button" role="tab" aria-controls="deal-chatter-history-pane" aria-selected="false">
                <i class="feather-clock fs-12"></i>
                <span>{{ __('crm.history') }}</span>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-1.5 py-0.2 fs-10 font-monospace">{{ $historyCount }}</span>
            </button>
        </div>

        <!-- Filter Dropdown (Swapped to Top Right) -->
        <div class="dropdown">
            <button class="btn btn-xs btn-white border dropdown-toggle d-inline-flex align-items-center gap-1.5 px-2.5 py-1 rounded-2 shadow-2xs fw-semibold fs-12 text-dark" type="button" id="dealActivityFilterDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="feather-filter text-primary fs-12"></i>
                <span id="dealActiveFilterLabel">{{ __('crm.all_activities') }} ({{ $allActivitiesTotal }})</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm fs-12 py-1 border" aria-labelledby="dealActivityFilterDropdownBtn" id="dealActivityFilterMenu" style="min-width: 230px;">
                <li>
                    <a class="dropdown-item deal-filter-item d-flex align-items-center justify-content-between py-1.5 px-3 active" href="javascript:void(0)" data-filter="all" onclick="applyDealChatterActivityFilter('all', '{{ addslashes(__('crm.all_activities')) }}', {{ $allActivitiesTotal }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-list text-muted fs-12"></i>
                            <span>{{ __('crm.all_activities') }}</span>
                        </span>
                        <span class="badge bg-dark text-white rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $allActivitiesTotal }}</span>
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item deal-filter-item d-flex align-items-center justify-content-between py-1.5 px-3" href="javascript:void(0)" data-filter="deal" onclick="applyDealChatterActivityFilter('deal', '{{ addslashes(__('crm.deal_activities')) }}', {{ $dealOriginCount }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-briefcase text-primary fs-12"></i>
                            <span>{{ __('crm.deal_activities') }}</span>
                        </span>
                        <span class="badge bg-primary text-white rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $dealOriginCount }}</span>
                    </a>
                </li>
                @if($linkedLead)
                    <li>
                        <a class="dropdown-item deal-filter-item d-flex align-items-center justify-content-between py-1.5 px-3" href="javascript:void(0)" data-filter="lead" onclick="applyDealChatterActivityFilter('lead', '{{ addslashes(__('crm.lead_conversations')) }}', {{ $leadOriginCount }})">
                            <span class="d-flex align-items-center gap-2">
                                <i class="feather-user-check text-info fs-12"></i>
                                <span>{{ __('crm.lead_conversations') }}</span>
                            </span>
                            <span class="badge bg-info text-white rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $leadOriginCount }}</span>
                        </a>
                    </li>
                @endif
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item deal-filter-item d-flex align-items-center justify-content-between py-1.5 px-3" href="javascript:void(0)" data-filter="planned" onclick="applyDealChatterActivityFilter('planned', '{{ addslashes(__('crm.planned_activities')) }}', {{ $plannedActivities->count() }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-calendar text-success fs-12"></i>
                            <span>{{ __('crm.planned_activities') }}</span>
                        </span>
                        <span class="badge bg-success text-white rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $plannedActivities->count() }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item deal-filter-item d-flex align-items-center justify-content-between py-1.5 px-3" href="javascript:void(0)" data-filter="overdue" onclick="applyDealChatterActivityFilter('overdue', '{{ addslashes(__('crm.overdue_activities')) }}', {{ $overdueActivities->count() }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-alert-circle text-danger fs-12"></i>
                            <span>{{ __('crm.overdue_activities') }}</span>
                        </span>
                        <span class="badge bg-danger text-white rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $overdueActivities->count() }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item deal-filter-item d-flex align-items-center justify-content-between py-1.5 px-3" href="javascript:void(0)" data-filter="today" onclick="applyDealChatterActivityFilter('today', '{{ addslashes(__('crm.today_activity')) }}', {{ $todayDoneActivities->count() }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-check-circle text-warning fs-12"></i>
                            <span>{{ __('crm.today_activity') }}</span>
                        </span>
                        <span class="badge bg-warning text-dark rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $todayDoneActivities->count() }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item deal-filter-item d-flex align-items-center justify-content-between py-1.5 px-3" href="javascript:void(0)" data-filter="past" onclick="applyDealChatterActivityFilter('past', '{{ addslashes(__('crm.past_activities')) }}', {{ $pastCount }})">
                        <span class="d-flex align-items-center gap-2">
                            <i class="feather-clock text-secondary fs-12"></i>
                            <span>{{ __('crm.past_activities') }}</span>
                        </span>
                        <span class="badge bg-secondary text-white rounded-pill px-1.5 py-0.5 fs-10 font-monospace">{{ $pastCount }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Chatter Tab Panes -->
    <div class="tab-content flex-grow-1 p-3 bg-white" id="dealChatterTabContent">
        
        <!-- ==================== CHATTER PANE 1: ACTIVITIES ==================== -->
        <div class="tab-pane fade show active" id="deal-chatter-activity-pane" role="tabpanel" aria-labelledby="deal-chatter-activity-tab">

            <div class="activity-feed-container bg-white">
                <!-- Sub-Header Actions: Soft White Button Style (+ Activity, ✎ Note) -->
                <div class="d-flex align-items-center gap-2 mb-2.5 pb-2 border-bottom bg-white">
                    <button type="button" 
                            class="btn btn-xs btn-white border rounded-2 px-2.5 py-1 fw-semibold fs-11 text-primary d-inline-flex align-items-center gap-1.5 shadow-2xs hover-primary btn-open-deal-followup-offcanvas"
                            data-bs-toggle="offcanvas" 
                            data-bs-target="#dealFollowupOffcanvas" 
                            data-deal-id="{{ $deal->id }}" 
                            data-deal-title="{{ $deal->title }}" 
                            title="{{ __('crm.schedule_next_activity_btn') }}">
                        <i class="feather-plus fs-11 text-primary"></i>
                        <span>{{ __('crm.activity') }}</span>
                    </button>

                    <button type="button" 
                            class="btn btn-xs btn-white border rounded-2 px-2.5 py-1 fw-semibold fs-11 text-dark d-inline-flex align-items-center gap-1.5 shadow-2xs hover-light"
                            data-bs-toggle="modal" 
                            data-bs-target="#modalDealLogNote" 
                            title="{{ __('crm.add_note') }}">
                        <i class="feather-edit-2 fs-10 text-muted"></i>
                        <span>{{ __('crm.note') }}</span>
                    </button>

                    @php
                        $primaryDealPhone = $deal->contact?->phone ?: ($deal->account?->phone ?: ($linkedLead?->company_phone ?: $linkedLead?->phone));
                    @endphp
                    @if($primaryDealPhone)
                        <button type="button" 
                                class="btn btn-xs btn-white border rounded-2 px-2.5 py-1 fw-semibold fs-11 text-success d-inline-flex align-items-center gap-1.5 shadow-2xs hover-light ms-auto btn-crm-deal-click-to-call"
                                data-deal-id="{{ $deal->id }}"
                                data-deal-title="{{ addslashes($deal->title) }}"
                                data-deal-phone="{{ $primaryDealPhone }}"
                                title="Call {{ $primaryDealPhone }}">
                            <i class="feather-phone-call fs-10 text-success"></i>
                            <span>{{ __('crm.call_phone') }}</span>
                        </button>
                    @endif
                </div>

                <!-- ACTIVITY FEED SECTIONS -->
                <div class="odoo-chatter-feed" id="dealChatterFeedContainer">
                    
                    <!-- 1. OVERDUE ACTIVITIES -->
                    @if($overdueActivities->isNotEmpty())
                        <div class="deal-activity-section-block" data-activity-section="overdue">
                            <div class="odoo-feed-divider odoo-feed-divider--overdue" data-bs-toggle="collapse" data-bs-target="#collapseOverdue_dealChatter" aria-expanded="true" aria-controls="collapseOverdue_dealChatter">
                                <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.overdue_activities') }} ({{ $overdueActivities->count() }})</span>
                            </div>
                            <div class="collapse show odoo-activity-list mb-3" id="collapseOverdue_dealChatter">
                                @foreach($overdueActivities->take(10) as $item)
                                    @include('modules.crm.deals.partials.deal-activity-card', ['item' => $item, 'category' => 'overdue', 'deal' => $deal, 'linkedLead' => $linkedLead, 'users' => $users])
                                @endforeach

                                @if($overdueActivities->count() > 10)
                                    <div class="extra-overdue-deal-items d-none">
                                        @foreach($overdueActivities->slice(10) as $item)
                                            @include('modules.crm.deals.partials.deal-activity-card', ['item' => $item, 'category' => 'overdue', 'deal' => $deal, 'linkedLead' => $linkedLead, 'users' => $users])
                                        @endforeach
                                    </div>
                                    <div class="text-center pt-1 pb-1">
                                        <button type="button" class="btn btn-2xs btn-white border rounded-pill px-3 py-1 fs-11 text-secondary fw-semibold shadow-2xs hover-primary" onclick="$(this).parent().prev('.extra-overdue-deal-items').removeClass('d-none'); $(this).parent().remove();">
                                            <i class="feather-chevron-down fs-11 me-1"></i>
                                            <span>Load More (+{{ $overdueActivities->count() - 10 }} more)</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- 2. PLANNED ACTIVITIES -->
                    @if($plannedActivities->isNotEmpty())
                        <div class="deal-activity-section-block" data-activity-section="planned">
                            <div class="odoo-feed-divider odoo-feed-divider--planned" data-bs-toggle="collapse" data-bs-target="#collapsePlanned_dealChatter" aria-expanded="true" aria-controls="collapsePlanned_dealChatter">
                                <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.planned_activities') }} ({{ $plannedActivities->count() }})</span>
                            </div>
                            <div class="collapse show odoo-activity-list mb-3" id="collapsePlanned_dealChatter">
                                @foreach($plannedActivities->take(10) as $item)
                                    @include('modules.crm.deals.partials.deal-activity-card', ['item' => $item, 'category' => 'planned', 'deal' => $deal, 'linkedLead' => $linkedLead, 'users' => $users])
                                @endforeach

                                @if($plannedActivities->count() > 10)
                                    <div class="extra-planned-deal-items d-none">
                                        @foreach($plannedActivities->slice(10) as $item)
                                            @include('modules.crm.deals.partials.deal-activity-card', ['item' => $item, 'category' => 'planned', 'deal' => $deal, 'linkedLead' => $linkedLead, 'users' => $users])
                                        @endforeach
                                    </div>
                                    <div class="text-center pt-1 pb-1">
                                        <button type="button" class="btn btn-2xs btn-white border rounded-pill px-3 py-1 fs-11 text-secondary fw-semibold shadow-2xs hover-primary" onclick="$(this).parent().prev('.extra-planned-deal-items').removeClass('d-none'); $(this).parent().remove();">
                                            <i class="feather-chevron-down fs-11 me-1"></i>
                                            <span>{{ __('crm.load_more') }} (+{{ $plannedActivities->count() - 10 }})</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- 3. TODAY'S ACTIVITIES -->
                    @if($todayDoneActivities->isNotEmpty())
                        <div class="deal-activity-section-block" data-activity-section="today">
                            <div class="odoo-feed-divider odoo-feed-divider--today-done" data-bs-toggle="collapse" data-bs-target="#collapseToday_dealChatter" aria-expanded="true" aria-controls="collapseToday_dealChatter">
                                <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.today_activity') }} ({{ $todayDoneActivities->count() }})</span>
                            </div>
                            <div class="collapse show odoo-activity-list mb-3" id="collapseToday_dealChatter">
                                @foreach($todayDoneActivities as $item)
                                    @include('modules.crm.deals.partials.deal-activity-card', ['item' => $item, 'category' => 'today_done', 'deal' => $deal, 'linkedLead' => $linkedLead, 'users' => $users])
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- 4. PAST ACTIVITIES (Includes Past Lead Conversations) -->
                    @if($pastCount > 0)
                        <div class="deal-activity-section-block" data-activity-section="past">
                            <div class="odoo-feed-divider odoo-feed-divider--past-done" data-bs-toggle="collapse" data-bs-target="#collapsePast_dealChatter" aria-expanded="true" aria-controls="collapsePast_dealChatter">
                                <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.past_activities_conversations') }} ({{ $pastCount }})</span>
                            </div>
                            <div class="collapse show odoo-activity-list mb-3" id="collapsePast_dealChatter">
                                @foreach($pastDoneActivities->take(15) as $item)
                                    @include('modules.crm.deals.partials.deal-activity-card', ['item' => $item, 'category' => 'past', 'deal' => $deal, 'linkedLead' => $linkedLead, 'users' => $users])
                                @endforeach

                                @if($pastCount > 15)
                                    <div class="extra-past-deal-items d-none">
                                        @foreach($pastDoneActivities->slice(15) as $item)
                                            @include('modules.crm.deals.partials.deal-activity-card', ['item' => $item, 'category' => 'past', 'deal' => $deal, 'linkedLead' => $linkedLead, 'users' => $users])
                                        @endforeach
                                    </div>
                                    <div class="text-center pt-2 pb-1">
                                        <button type="button" class="btn btn-2xs btn-white border rounded-pill px-3 py-1.5 fs-11 text-secondary fw-semibold shadow-2xs hover-primary" onclick="$(this).parent().prev('.extra-past-deal-items').removeClass('d-none'); $(this).parent().remove();">
                                            <i class="feather-chevron-down fs-11 me-1 text-primary"></i>
                                            <span>{{ __('crm.load_more_activities') }} (+{{ $pastCount - 15 }})</span>
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
                            <p class="text-muted fs-11 mb-3">{{ __('crm.schedule_call_reminder_deal_desc') }}</p>
                            <button type="button" class="btn btn-xs btn-primary fw-semibold px-3 py-1.5 rounded-pill btn-open-deal-followup-offcanvas" data-bs-toggle="offcanvas" data-bs-target="#dealFollowupOffcanvas">
                                <i class="feather-plus me-1"></i> {{ __('crm.schedule_next_activity_btn') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- ==================== CHATTER PANE 2: HISTORY & AUDIT ==================== -->
        <div class="tab-pane fade" id="deal-chatter-history-pane" role="tabpanel" aria-labelledby="deal-chatter-history-tab">
            
            <!-- Sticky Sub-Header with Search & Counter -->
            <div class="d-flex align-items-center justify-content-between mb-2.5 pb-2 border-bottom flex-wrap gap-2">
                <div class="d-flex align-items-center gap-1.5">
                    <span class="fs-12 fw-bold text-dark d-flex align-items-center gap-1.5">
                        <i class="feather-clock text-primary fs-12"></i>
                        <span>{{ __('crm.timeline_history') }}</span>
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5 fs-10 font-monospace fw-bold" id="dealHistoryTotalBadge">{{ $historyCount }}</span>
                </div>
                <div class="input-group input-group-sm" style="width: 150px;">
                    <span class="input-group-text bg-white border-end-0 py-0.5 px-2 text-muted" style="border-color: #cbd5e1;"><i class="feather-search fs-10"></i></span>
                    <input type="search" class="form-control form-control-sm border-start-0 py-0.5 px-1 fs-11" id="dealHistoryQuickSearch" placeholder="{{ __('crm.search_history') }}" oninput="filterDealChatterHistory(this.value)" onsearch="filterDealChatterHistory(this.value)" style="border-color: #cbd5e1;">
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
                        <p class="text-muted fs-11 mb-0">{{ __('crm.no_past_history_deal_desc') }}</p>
                    </div>
                @else
                    <div id="dealHistoryEventsList">
                        @foreach($groupedHistory as $date => $items)
                            @php
                                try {
                                    $parsedDate = \Carbon\Carbon::createFromFormat('d/m/Y', $date)->startOfDay();
                                    if ($parsedDate->isToday()) {
                                        $displayDate = __('crm.today') . ' • ' . $parsedDate->format('d M Y');
                                    } elseif ($parsedDate->isYesterday()) {
                                        $displayDate = __('crm.yesterday') . ' • ' . $parsedDate->format('d M Y');
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
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="flex-grow-1 border-top" style="border-color: #e2e8f0 !important;"></div>
                                    <span class="badge bg-white text-secondary border px-2.5 py-1 rounded-pill fs-11 fw-semibold shadow-2xs d-inline-flex align-items-center gap-1.5" style="border-color: #cbd5e1 !important;">
                                        <i class="feather-calendar text-primary fs-11"></i>
                                        <span>{{ $displayDate }}</span>
                                    </span>
                                    <div class="flex-grow-1 border-top" style="border-color: #e2e8f0 !important;"></div>
                                </div>

                                <!-- Items in this date -->
                                @foreach($items as $item)
                                    @php
                                        $isLeadHist = !empty($item->is_lead_history);
                                        $evType = $item->event_type ?? 'history';
                                        $evNote = $item->notes ?? '';
                                        $evUser = $item->user?->name ?: 'System';
                                        $evTime = $item->created_at ? $item->created_at->format('h:i A') : '';
                                    @endphp
                                    <div class="deal-history-entry p-2 mb-1.5 rounded-2 border bg-white shadow-2xs fs-11">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <div class="d-flex align-items-center gap-1.5">
                                                @if($isLeadHist)
                                                    <span class="badge bg-soft-info text-info border border-info-subtle px-1.5 py-0.2 fs-9 fw-bold">
                                                        <i class="feather-user-check me-0.5"></i> {{ __('crm.lead_history') }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-soft-primary text-primary border border-primary-subtle px-1.5 py-0.2 fs-9 fw-bold">
                                                        <i class="feather-briefcase me-0.5"></i> {{ __('crm.deal_audit') }}
                                                    </span>
                                                @endif
                                                <strong class="text-dark">{{ ucwords(str_replace('_', ' ', $evType)) }}</strong>
                                            </div>
                                            <span class="text-muted font-monospace fs-10">{{ $evTime }}</span>
                                        </div>
                                        @if($evNote)
                                            <div class="text-secondary ps-1" style="line-height: 1.4;">
                                                {{ $evNote }}
                                            </div>
                                        @endif
                                        <div class="text-muted fs-10 mt-1">
                                            <i class="feather-user fs-9 me-1"></i>{{ __('crm.by') }} <strong class="text-dark">{{ $evUser }}</strong>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <!-- No Results Search Fallback -->
                    <div id="dealHistoryEmptySearchResults" class="text-center py-4 text-muted border border-dashed rounded-3 bg-white fs-11 my-2" style="display: none;">
                        <i class="feather-search fs-18 mb-1 d-block text-muted opacity-50"></i>
                        <span>{{ __('crm.no_history_match_filter') }}</span>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

<!-- Log Note Modal for Deals -->
<div class="modal fade" id="modalDealLogNote" tabindex="-1" aria-labelledby="modalDealLogNoteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2.5 px-3 border-bottom">
                <h6 class="modal-title fw-bold text-dark fs-13 d-flex align-items-center gap-1.5" id="modalDealLogNoteLabel">
                    <i class="feather-edit-2 text-primary fs-13"></i>
                    <span>{{ __('crm.add_note') }} / {{ __('crm.discussion_note') }}</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('crm.deals.followups.store', $deal->id) }}" method="POST">
                @csrf
                <input type="hidden" name="action_mode" value="log_note">
                <input type="hidden" name="type" value="Call">
                <input type="hidden" name="status" value="Connected">
                <div class="modal-body p-3">
                    <div class="mb-2">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.discussion_note_interaction_summary') }}</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="4" placeholder="{{ __('crm.log_note_placeholder') }}" required></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.tag_team_members') }}</label>
                        <select name="tagged_user_ids[]" class="form-select form-select-sm" multiple>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">{{ __('crm.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold">{{ __('crm.save_note') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function applyDealChatterActivityFilter(filterType, filterLabel, count) {
        $('#dealActiveFilterLabel').text(filterLabel + ' (' + count + ')');
        $('#dealActivityFilterMenu .deal-filter-item').removeClass('active');
        $('#dealActivityFilterMenu .deal-filter-item[data-filter="' + filterType + '"]').addClass('active');

        if (filterType === 'all') {
            $('.deal-activity-section-block').show();
            $('.deal-chatter-item').show();
        } else if (filterType === 'deal') {
            $('.deal-activity-section-block').show();
            $('.deal-chatter-item').each(function() {
                $(this).toggle($(this).data('origin') === 'deal');
            });
            // Hide sections that have no visible children
            $('.deal-activity-section-block').each(function() {
                var vis = $(this).find('.deal-chatter-item:visible').length;
                $(this).toggle(vis > 0);
            });
        } else if (filterType === 'lead') {
            $('.deal-activity-section-block').show();
            $('.deal-chatter-item').each(function() {
                $(this).toggle($(this).data('origin') === 'lead');
            });
            $('.deal-activity-section-block').each(function() {
                var vis = $(this).find('.deal-chatter-item:visible').length;
                $(this).toggle(vis > 0);
            });
        } else {
            // Section based filter (planned, overdue, today, past)
            $('.deal-activity-section-block').each(function() {
                var sec = $(this).data('activity-section');
                $(this).toggle(sec === filterType);
            });
            $('.deal-chatter-item').show();
        }
    }

    function filterDealChatterHistory(searchVal) {
        var query = (searchVal || '').trim().toLowerCase();
        var items = $('.deal-history-entry');
        if (!query) {
            items.show();
            $('.history-date-group').show();
            $('#dealHistoryEmptySearchResults').hide();
            return;
        }

        var visibleCount = 0;
        items.each(function() {
            var text = $(this).text().toLowerCase();
            var matches = text.indexOf(query) > -1;
            $(this).toggle(matches);
            if (matches) visibleCount++;
        });

        $('.history-date-group').each(function() {
            var visInGroup = $(this).find('.deal-history-entry:visible').length;
            $(this).toggle(visInGroup > 0);
        });

        $('#dealHistoryEmptySearchResults').toggle(visibleCount === 0);
    }
</script>
