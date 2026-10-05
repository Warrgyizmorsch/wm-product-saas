{{-- Global search (assets/js/global-search.js drives it by these ids). Opened by the header search box or Ctrl/⌘+K. --}}
                <div class="dropdown nxl-h-item nxl-header-search" id="global-search-container"
                    data-search-url="{{ route('global-search') }}">
                    <a href="javascript:void(0);" class="nxl-head-link ax-search-trigger me-0" data-bs-toggle="dropdown"
                        data-bs-auto-close="outside" id="global-search-toggle" aria-label="{{ __('ui.search_placeholder') }}">
                        <i class="feather-search"></i>
                        <span class="ax-search-placeholder d-none d-lg-inline">{{ __('ui.search_placeholder') }}</span>
                        <kbd class="d-none d-lg-inline">Ctrl K</kbd>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown shadow-lg border-0"
                        id="global-search-dropdown" style="min-width: 420px; max-width: 520px;">
                        <div class="input-group search-form border-bottom">
                            <span class="input-group-text bg-transparent border-0 pe-1">
                                <i class="feather-search fs-6 text-muted" id="search-spinner-icon"></i>
                            </span>
                            <input type="text" class="form-control search-input-field border-0 ps-1"
                                id="global-search-input" placeholder="{{ __('ui.search_placeholder') }}"
                                autocomplete="off">
                            <span class="input-group-text bg-transparent border-0">
                                <button type="button" class="btn-close fs-11" id="global-search-clear"
                                    style="display: none;"></button>
                            </span>
                        </div>

                        {{-- Scope Selector Pills --}}
                        <div class="search-scope-bar px-3 py-2 bg-light-subtle border-bottom">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fs-11 fw-semibold text-muted text-uppercase tracking-wider">Search
                                    in</span>
                                <span class="badge bg-secondary-subtle text-secondary fs-11"
                                    id="global-search-active-scope">All</span>
                            </div>
                            <div class="d-flex flex-wrap gap-1" id="search-scope-pills">
                                @php
                                    $searchScopes = [
                                        ['id' => 'all', 'label' => 'All'],
                                        ['id' => 'navigation', 'label' => 'Menu'],
                                        ['id' => 'sales', 'label' => 'Sales'],
                                        ['id' => 'purchase', 'label' => 'Purchase'],
                                        ['id' => 'inventory', 'label' => 'Inventory'],
                                        ['id' => 'production', 'label' => 'Production'],
                                        ['id' => 'accounting', 'label' => 'Accounting'],
                                        ['id' => 'hrms', 'label' => 'HRMS'],
                                        ['id' => 'projects', 'label' => 'Projects'],
                                        ['id' => 'crm', 'label' => 'CRM'],
                                    ];
                                @endphp
                                @foreach ($searchScopes as $scope)
                                    <button type="button"
                                        class="btn btn-xs py-1 px-2 fs-11 fw-medium search-scope-pill {{ $loop->first ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        data-scope="{{ $scope['id'] }}">
                                        {{ $scope['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Dynamic Search & Recent Results Container --}}
                        <div class="search-items-wrapper" id="global-search-results-wrapper"
                            style="max-height: 380px; overflow-y: auto;">
                            {{-- Default view: Recent Searches & Tips --}}
                            <div id="global-search-default-view">
                                <div class="recent-result px-3 py-2" id="global-search-recent-section"
                                    style="display: none;">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="fs-12 fw-semibold text-muted text-uppercase">Recent Searches</span>
                                        <button type="button"
                                            class="btn btn-link btn-xs text-muted p-0 text-decoration-none fs-11"
                                            id="clear-recent-searches">Clear History</button>
                                    </div>
                                    <div id="recent-searches-list"></div>
                                    <div class="dropdown-divider my-2"></div>
                                </div>
                                <div class="px-3 py-2">
                                    <p class="fs-11 fw-medium text-muted mb-0 d-flex align-items-center gap-1">
                                        <i class="feather-info text-primary fs-13 me-0.5"></i>
                                        <span>Type at least 2 characters to search. Use <span
                                                class="search-kbd-key">↑</span> <span class="search-kbd-key">↓</span> to
                                            navigate, <span class="search-kbd-key">Enter</span> to open.</span>
                                    </p>
                                </div>
                            </div>

                            {{-- Live Results View --}}
                            <div id="global-search-results-view" style="display: none;">
                                <div id="global-search-results-list" class="py-1"></div>
                            </div>

                            {{-- Empty State View --}}
                            <div id="global-search-empty-view" class="text-center py-4 px-3" style="display: none;">
                                <div
                                    class="avatar-text avatar-md bg-light-subtle rounded-circle mx-auto mb-2 text-muted">
                                    <i class="feather-search fs-4"></i>
                                </div>
                                <p class="fs-13 fw-medium text-dark mb-1">No results found for "<span
                                        id="empty-query-text"></span>"</p>
                                <p class="fs-11 text-muted mb-2">Try searching with another keyword or reset filter to
                                    <strong>All</strong>.</p>
                                <button type="button" class="btn btn-sm btn-outline-primary fs-11 py-1 px-2"
                                    id="reset-scope-btn">Switch to All Modules</button>
                            </div>
                        </div>
                    </div>
                </div>

