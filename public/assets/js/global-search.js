/**
 * Global Intelligence Search — Client Handler
 * Handles live debounced search, AbortController, safe DOM rendering,
 * module scoping, keyboard navigation, and recent searches.
 */
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('global-search-container');
    if (!container) return;

    const searchUrl = container.getAttribute('data-search-url') || '/global-search';
    const input = document.getElementById('global-search-input');
    const clearBtn = document.getElementById('global-search-clear');
    const spinnerIcon = document.getElementById('search-spinner-icon');
    const activeScopeLabel = document.getElementById('global-search-active-scope');
    const scopePills = document.querySelectorAll('.search-scope-pill');

    const defaultView = document.getElementById('global-search-default-view');
    const resultsView = document.getElementById('global-search-results-view');
    const resultsList = document.getElementById('global-search-results-list');
    const emptyView = document.getElementById('global-search-empty-view');
    const emptyQueryText = document.getElementById('empty-query-text');
    const resetScopeBtn = document.getElementById('reset-scope-btn');

    const recentSection = document.getElementById('global-search-recent-section');
    const recentList = document.getElementById('recent-searches-list');
    const clearRecentBtn = document.getElementById('clear-recent-searches');

    const STORAGE_KEY = 'wm_erp_recent_searches';
    let currentModule = 'all';
    let debounceTimer = null;
    let abortController = null;
    let currentResults = [];
    let selectedIndex = -1;

    // Initialize Recent Searches on Load
    renderRecentSearches();

    // ── Module Scope Selection ──
    scopePills.forEach(function (pill) {
        pill.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            scopePills.forEach(function (p) {
                p.classList.remove('btn-primary');
                p.classList.add('btn-outline-secondary');
            });
            pill.classList.remove('btn-outline-secondary');
            pill.classList.add('btn-primary');

            currentModule = pill.getAttribute('data-scope') || 'all';
            if (activeScopeLabel) {
                activeScopeLabel.textContent = pill.textContent.trim();
            }

            if (input.value.trim().length >= 2) {
                triggerSearch(input.value.trim());
            }
        });
    });

    if (resetScopeBtn) {
        resetScopeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const allPill = document.querySelector('.search-scope-pill[data-scope="all"]');
            if (allPill) {
                allPill.click();
            }
        });
    }

    // ── Input & Debounce Handling ──
    input.addEventListener('input', function () {
        const val = input.value.trim();

        if (clearBtn) {
            clearBtn.style.display = val.length > 0 ? 'inline-block' : 'none';
        }

        if (val.length < 2) {
            if (abortController) {
                abortController.abort();
                abortController = null;
            }
            clearTimeout(debounceTimer);
            setLoading(false);
            showDefaultView();
            return;
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            triggerSearch(val);
        }, 250);
    });

    // ── Clear Button ──
    if (clearBtn) {
        clearBtn.addEventListener('click', function (e) {
            e.preventDefault();
            input.value = '';
            clearBtn.style.display = 'none';
            if (abortController) {
                abortController.abort();
                abortController = null;
            }
            setLoading(false);
            showDefaultView();
            input.focus();
        });
    }

    // ── Keyboard Navigation (ArrowUp, ArrowDown, Enter, Escape) ──
    input.addEventListener('keydown', function (e) {
        const items = resultsList.querySelectorAll('.global-search-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (items.length === 0) return;
            selectedIndex = (selectedIndex + 1) % items.length;
            highlightSelectedItem(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (items.length === 0) return;
            selectedIndex = (selectedIndex - 1 + items.length) % items.length;
            highlightSelectedItem(items);
        } else if (e.key === 'Enter') {
            if (selectedIndex >= 0 && items[selectedIndex]) {
                e.preventDefault();
                items[selectedIndex].click();
            }
        } else if (e.key === 'Escape') {
            if (input.value.length > 0) {
                input.value = '';
                if (clearBtn) clearBtn.style.display = 'none';
                showDefaultView();
            }
        }
    });

    function highlightSelectedItem(items) {
        items.forEach(function (el, idx) {
            if (idx === selectedIndex) {
                el.classList.add('global-search-selected');
                el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } else {
                el.classList.remove('global-search-selected');
            }
        });
    }

    // ── Perform Search Request ──
    function triggerSearch(query) {
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        setLoading(true);

        const url = `${searchUrl}?q=${encodeURIComponent(query)}&module=${encodeURIComponent(currentModule)}`;

        fetch(url, {
            signal: abortController.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Network error');
            return res.json();
        })
        .then(function (data) {
            setLoading(false);
            currentResults = data.results || [];
            selectedIndex = -1;

            if (currentResults.length === 0) {
                showEmptyView(query);
            } else {
                renderResults(currentResults);
            }
        })
        .catch(function (err) {
            if (err.name === 'AbortError') {
                return; // Suppress cancelled requests
            }
            setLoading(false);
            showEmptyView(query);
        });
    }

    function setLoading(isLoading) {
        if (!spinnerIcon) return;
        if (isLoading) {
            spinnerIcon.className = 'feather-loader text-primary search-spin-animate fs-6';
        } else {
            spinnerIcon.className = 'feather-search fs-6 text-muted';
        }
    }

    // ── Safe DOM Rendering ──
    function renderResults(results) {
        defaultView.style.display = 'none';
        emptyView.style.display = 'none';
        resultsView.style.display = 'block';

        // Clear existing items safely
        while (resultsList.firstChild) {
            resultsList.removeChild(resultsList.firstChild);
        }

        // Group by category
        const groups = {};
        results.forEach(function (item) {
            const cat = item.category || 'Results';
            if (!groups[cat]) groups[cat] = [];
            groups[cat].push(item);
        });

        let overallIndex = 0;

        Object.keys(groups).forEach(function (categoryName) {
            // Category header
            const header = document.createElement('div');
            header.className = 'px-3 py-1 bg-light-subtle fs-11 fw-bold text-muted text-uppercase tracking-wider border-top border-bottom';
            header.textContent = categoryName;
            resultsList.appendChild(header);

            groups[categoryName].forEach(function (item) {
                const row = document.createElement('a');
                row.href = item.url;
                row.className = 'global-search-item d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none border-bottom text-dark';
                row.setAttribute('data-index', overallIndex);

                const left = document.createElement('div');
                left.className = 'd-flex align-items-center gap-2 overflow-hidden';

                const iconWrap = document.createElement('div');
                iconWrap.className = 'avatar-text avatar-sm bg-light rounded text-primary flex-shrink-0';
                const iconEl = document.createElement('i');
                iconEl.className = item.icon || 'feather-file';
                iconWrap.appendChild(iconEl);

                const textWrap = document.createElement('div');
                textWrap.className = 'overflow-hidden';

                const titleEl = document.createElement('div');
                titleEl.className = 'fw-bold fs-12 text-truncate';
                titleEl.textContent = item.title;

                const subtitleEl = document.createElement('div');
                subtitleEl.className = 'fs-11 text-muted text-truncate';
                subtitleEl.textContent = item.subtitle;

                textWrap.appendChild(titleEl);
                textWrap.appendChild(subtitleEl);

                left.appendChild(iconWrap);
                left.appendChild(textWrap);

                const right = document.createElement('div');
                right.className = 'flex-shrink-0 ms-2';
                const chevron = document.createElement('i');
                chevron.className = 'feather-chevron-right text-muted fs-12';
                right.appendChild(chevron);

                row.appendChild(left);
                row.appendChild(right);

                // Save to recent searches on click
                row.addEventListener('click', function () {
                    addRecentSearch(item);
                });

                resultsList.appendChild(row);
                overallIndex++;
            });
        });
    }

    function showEmptyView(query) {
        defaultView.style.display = 'none';
        resultsView.style.display = 'none';
        emptyView.style.display = 'block';
        if (emptyQueryText) {
            emptyQueryText.textContent = query;
        }
    }

    function showDefaultView() {
        emptyView.style.display = 'none';
        resultsView.style.display = 'none';
        defaultView.style.display = 'block';
        renderRecentSearches();
    }

    // ── Recent Searches (localStorage) ──
    function getRecentSearches() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function addRecentSearch(item) {
        try {
            let list = getRecentSearches();
            list = list.filter(function (i) { return i.url !== item.url; });
            list.unshift({
                category: item.category,
                title: item.title,
                subtitle: item.subtitle,
                url: item.url,
                icon: item.icon,
            });
            if (list.length > 5) list = list.slice(0, 5);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
        } catch (e) {
            // Storage quota or disabled
        }
    }

    function renderRecentSearches() {
        if (!recentSection || !recentList) return;
        const list = getRecentSearches();

        if (list.length === 0) {
            recentSection.style.display = 'none';
            return;
        }

        recentSection.style.display = 'block';
        while (recentList.firstChild) {
            recentList.removeChild(recentList.firstChild);
        }

        list.forEach(function (item) {
            const row = document.createElement('a');
            row.href = item.url;
            row.className = 'd-flex align-items-center justify-content-between py-2 text-decoration-none text-dark border-bottom border-light';

            const left = document.createElement('div');
            left.className = 'd-flex align-items-center gap-2 overflow-hidden';

            const iconWrap = document.createElement('div');
            iconWrap.className = 'avatar-text avatar-xs bg-light rounded text-secondary flex-shrink-0';
            const iconEl = document.createElement('i');
            iconEl.className = item.icon || 'feather-clock';
            iconWrap.appendChild(iconEl);

            const textWrap = document.createElement('div');
            textWrap.className = 'overflow-hidden';

            const titleEl = document.createElement('div');
            titleEl.className = 'fw-medium fs-12 text-truncate';
            titleEl.textContent = item.title;

            const subtitleEl = document.createElement('div');
            subtitleEl.className = 'fs-11 text-muted text-truncate';
            subtitleEl.textContent = (item.category ? item.category + ' • ' : '') + item.subtitle;

            textWrap.appendChild(titleEl);
            textWrap.appendChild(subtitleEl);

            left.appendChild(iconWrap);
            left.appendChild(textWrap);

            const chevron = document.createElement('i');
            chevron.className = 'feather-arrow-up-right text-muted fs-11 flex-shrink-0';

            row.appendChild(left);
            row.appendChild(chevron);

            recentList.appendChild(row);
        });
    }

    if (clearRecentBtn) {
        clearRecentBtn.addEventListener('click', function (e) {
            e.preventDefault();
            try {
                localStorage.removeItem(STORAGE_KEY);
            } catch (e) {}
            renderRecentSearches();
        });
    }
});
