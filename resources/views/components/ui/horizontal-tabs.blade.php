@props([
    'id',
    'tabs' => [], // array of ['id' => '', 'label' => '', 'active' => true/false, 'icon' => '']
    'syncUrl' => false,
    'syncParam' => 'tab'
])

@once
    @push('styles')
        <style>
            .erp-horizontal-tabs,
            ul.nav.nav-tabs.erp-horizontal-tabs {
                border-bottom: 1px solid #e2e8f0 !important;
                gap: 8px !important;
                overflow-x: auto;
                overflow-y: visible;
                flex-wrap: nowrap;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none; /* Firefox */
                -ms-overflow-style: none; /* IE and Edge */
                padding-bottom: 8px !important;
                padding-top: 4px;
                margin-bottom: 1.5rem !important;
            }
            .erp-horizontal-tabs::-webkit-scrollbar {
                display: none; /* Chrome, Safari, Opera */
                width: 0;
                height: 0;
            }
            .erp-horizontal-tabs .nav-item {
                margin-bottom: 0 !important;
                flex-shrink: 0;
                position: relative;
            }
            .erp-horizontal-tabs .nav-link,
            ul.nav.nav-tabs.erp-horizontal-tabs .nav-link {
                border: 1px solid #e2e8f0 !important;
                border-bottom: 1px solid #e2e8f0 !important;
                background-color: #ffffff !important;
                color: #64748b !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                padding: 8px 16px !important;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                display: inline-flex !important;
                align-items: center !important;
                border-radius: 6px !important;
                white-space: nowrap !important;
                flex-shrink: 0 !important;
                position: relative !important;
                overflow: visible !important;
                margin-bottom: 0 !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important;
            }
            .erp-horizontal-tabs .nav-link .erp-tab-badge {
                font-size: 11px !important;
                font-weight: 700 !important;
                padding: 2px 7px !important;
                border-radius: 9999px !important;
                margin-left: 8px !important;
                background-color: #f1f5f9 !important;
                color: #475569 !important;
                border: 1px solid #e2e8f0 !important;
                transition: all 0.2s ease !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                line-height: 1 !important;
            }
            .erp-horizontal-tabs .nav-link:hover .erp-tab-badge {
                background-color: color-mix(in srgb, var(--bs-primary) 15%, transparent) !important;
                color: var(--bs-primary) !important;
                border-color: color-mix(in srgb, var(--bs-primary) 30%, transparent) !important;
            }
            .erp-horizontal-tabs .nav-link.active .erp-tab-badge {
                background-color: #ffffff !important;
                color: var(--bs-primary) !important;
                border-color: #ffffff !important;
                font-weight: 800 !important;
            }
            .erp-horizontal-tabs .nav-link i,
            ul.nav.nav-tabs.erp-horizontal-tabs .nav-link i {
                font-size: 14px !important;
                margin-right: 8px !important;
                color: inherit !important;
                transition: transform 0.2s ease, color 0.2s ease;
            }
            .erp-horizontal-tabs .nav-link:hover,
            ul.nav.nav-tabs.erp-horizontal-tabs .nav-link:hover {
                color: var(--bs-primary) !important;
                background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent) !important;
                border-color: color-mix(in srgb, var(--bs-primary) 20%, transparent) !important;
            }
            .erp-horizontal-tabs .nav-link:hover i {
                transform: translateY(-1px);
            }
            .erp-horizontal-tabs .nav-link.active,
            ul.nav.nav-tabs.erp-horizontal-tabs .nav-link.active {
                background-color: var(--bs-primary) !important;
                color: #ffffff !important;
                font-weight: 700 !important;
                border-color: var(--bs-primary) !important;
                border-bottom: 1px solid var(--bs-primary) !important;
                box-shadow: 0 2px 6px color-mix(in srgb, var(--bs-primary) 35%, transparent) !important;
            }
            .erp-horizontal-tabs .nav-link.active i,
            ul.nav.nav-tabs.erp-horizontal-tabs .nav-link.active i {
                color: #ffffff !important;
            }
            .erp-horizontal-tabs .erp-tab-header-badge {
                letter-spacing: 0.08em;
                font-size: 9px;
                font-weight: 800;
                color: #475569;
                background-color: #f1f5f9;
                border-radius: 4px;
                flex-shrink: 0;
                border: 1px solid #cbd5e1;
            }

            /* Dark Mode Support for Horizontal Tabs */
            html.app-skin-dark .erp-horizontal-tabs,
            html.app-skin-dark ul.nav.nav-tabs.erp-horizontal-tabs {
                border-bottom: 1px solid #1e293b !important;
            }
            html.app-skin-dark .erp-horizontal-tabs .nav-link,
            html.app-skin-dark ul.nav.nav-tabs.erp-horizontal-tabs .nav-link {
                background-color: #162038 !important;
                border-color: #283c50 !important;
                color: #94a3b8 !important;
            }
            html.app-skin-dark .erp-horizontal-tabs .nav-link:hover,
            html.app-skin-dark ul.nav.nav-tabs.erp-horizontal-tabs .nav-link:hover {
                color: #ffffff !important;
                background-color: rgba(255, 255, 255, 0.08) !important;
                border-color: rgba(255, 255, 255, 0.15) !important;
            }
            html.app-skin-dark .erp-horizontal-tabs .nav-link.active,
            html.app-skin-dark ul.nav.nav-tabs.erp-horizontal-tabs .nav-link.active {
                background-color: var(--bs-primary) !important;
                color: #ffffff !important;
                border-color: var(--bs-primary) !important;
                border-bottom-color: var(--bs-primary) !important;
                box-shadow: 0 2px 10px color-mix(in srgb, var(--bs-primary) 40%, transparent) !important;
            }
            html.app-skin-dark .erp-horizontal-tabs .erp-tab-header-badge {
                background-color: #162038 !important;
                color: #cbd5e1 !important;
                border-color: #283c50 !important;
            }
            html.app-skin-dark .erp-horizontal-tabs .nav-link .erp-tab-badge {
                background-color: #1e293b !important;
                color: #94a3b8 !important;
                border-color: #334155 !important;
            }
            html.app-skin-dark .erp-horizontal-tabs .nav-link.active .erp-tab-badge {
                background-color: #ffffff !important;
                color: var(--bs-primary) !important;
                border-color: #ffffff !important;
            }
        </style>
    @endpush
@endonce

<ul class="nav nav-tabs erp-horizontal-tabs" id="{{ $id }}" role="tablist" 
    @if($syncUrl) data-sync-url="true" data-sync-param="{{ $syncParam }}" @endif 
    {{ $attributes }}>
    @foreach($tabs as $tab)
        @if(!empty($tab['is_header']) || !empty($tab['header']))
            <li class="nav-item d-flex align-items-center px-2 py-1 text-uppercase fw-extrabold me-1 ms-1 erp-tab-header-badge">
                <i class="feather-grid me-1 text-primary" style="font-size: 9px;"></i>
                {{ $tab['header'] ?? $tab['label'] }}
            </li>
        @elseif(!empty($tab['url']) || !empty($tab['href']))
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ ($tab['active'] ?? false) ? 'active' : '' }}" 
                   id="{{ ($tab['id'] ?? 'tab-'.\Illuminate\Support\Str::slug($tab['label'] ?? 'item')) }}-tab"
                   href="{{ $tab['url'] ?? $tab['href'] }}">
                    @if(!empty($tab['icon']))
                        <i class="{{ $tab['icon'] }} me-2"></i>
                    @endif
                    {{ $tab['label'] }}
                    @if(isset($tab['badge']) && $tab['badge'] !== null && $tab['badge'] !== '')
                        <span class="erp-tab-badge {{ $tab['badgeClass'] ?? '' }}">{{ $tab['badge'] }}</span>
                    @endif
                </a>
            </li>
        @else
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ ($tab['active'] ?? false) ? 'active' : '' }}" 
                        id="{{ $tab['id'] }}-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#{{ $tab['id'] }}" 
                        type="button" 
                        role="tab" 
                        aria-controls="{{ $tab['id'] }}" 
                        aria-selected="{{ ($tab['active'] ?? false) ? 'true' : 'false' }}">
                    @if(!empty($tab['icon']))
                        <i class="{{ $tab['icon'] }} me-2"></i>
                    @endif
                    {{ $tab['label'] }}
                    @if(isset($tab['badge']) && $tab['badge'] !== null && $tab['badge'] !== '')
                        <span class="erp-tab-badge {{ $tab['badgeClass'] ?? '' }}">{{ $tab['badge'] }}</span>
                    @endif
                </button>
            </li>
        @endif
    @endforeach
</ul>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Synchronize tab clicks with URL query param if data-sync-url="true"
                document.addEventListener('shown.bs.tab', function (event) {
                    var tabButton = event.target;
                    var container = tabButton.closest('.erp-horizontal-tabs[data-sync-url="true"]');
                    if (!container) return;

                    var target = tabButton.getAttribute('data-bs-target') || '';
                    var paramName = container.getAttribute('data-sync-param') || 'tab';
                    var tabKey = target.replace('#tab-', '').replace('#', '');

                    if (tabKey) {
                        var url = new URL(window.location.href);
                        if (url.searchParams.get(paramName) !== tabKey) {
                            url.searchParams.set(paramName, tabKey);
                            window.history.replaceState({ path: url.toString() }, '', url.toString());
                        }
                    }
                });

                // Handle browser back/forward buttons (popstate)
                window.addEventListener('popstate', function () {
                    document.querySelectorAll('.erp-horizontal-tabs[data-sync-url="true"]').forEach(function (container) {
                        var paramName = container.getAttribute('data-sync-param') || 'tab';
                        var url = new URL(window.location.href);
                        var tabKey = url.searchParams.get(paramName);
                        if (tabKey && window.bootstrap) {
                            var tabBtn = container.querySelector('button[data-bs-target="#tab-' + tabKey + '"], button[data-bs-target="#' + tabKey + '"]');
                            if (tabBtn && !tabBtn.classList.contains('active')) {
                                var tab = bootstrap.Tab.getOrCreateInstance(tabBtn);
                                tab.show();
                            }
                        }
                    });
                });

                // Activate tab matching URL query or hash on initial load if not already active
                document.querySelectorAll('.erp-horizontal-tabs[data-sync-url="true"]').forEach(function (container) {
                    var paramName = container.getAttribute('data-sync-param') || 'tab';
                    var url = new URL(window.location.href);
                    var tabKey = url.searchParams.get(paramName);

                    if (!tabKey && window.location.hash) {
                        tabKey = window.location.hash.replace('#tab-', '').replace('#', '');
                    }

                    if (tabKey && window.bootstrap) {
                        var tabBtn = container.querySelector('button[data-bs-target="#tab-' + tabKey + '"], button[data-bs-target="#' + tabKey + '"]');
                        if (tabBtn && !tabBtn.classList.contains('active')) {
                            var tab = bootstrap.Tab.getOrCreateInstance(tabBtn);
                            tab.show();
                        }
                    }
                });
            });
        </script>
    @endpush
@endonce
