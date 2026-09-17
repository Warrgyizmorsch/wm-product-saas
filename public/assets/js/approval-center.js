/**
 * Production Approval Center Header Integration
 * 
 * Safely fetches and renders actionable pending Production approvals.
 * Follows XSS-safe DOM manipulation and non-blocking asynchronous fetching.
 */
(function () {
    'use strict';

    if (window.__approvalCenterInitialized) {
        return;
    }
    window.__approvalCenterInitialized = true;

    document.addEventListener('DOMContentLoaded', function () {
        const badgeEl = document.getElementById('header-approvals-badge');
        const listEl = document.getElementById('header-approvals-list');
        const statusTextEl = document.getElementById('header-approvals-status-text');
        const footerTextEl = document.getElementById('header-approvals-footer-text');
        const triggerEl = document.getElementById('header-approvals-trigger');

        if (!badgeEl || !listEl) {
            return;
        }

        let isFetching = false;
        let lastFetchedAt = 0;
        const CACHE_TTL_MS = 30000; // 30 seconds freshness guard

        function fetchApprovals(force) {
            const now = Date.now();
            if (!force && lastFetchedAt > 0 && (now - lastFetchedAt < CACHE_TTL_MS)) {
                return;
            }
            if (isFetching) return;
            isFetching = true;

            fetch('/global-approvals', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                lastFetchedAt = Date.now();
                renderApprovals(data);
            })
            .catch(function (error) {
                console.warn('[Approval Center] Failed to load approvals:', error);
                renderError();
            })
            .finally(function () {
                isFetching = false;
            });
        }

        function renderApprovals(data) {
            const count = (data && typeof data.count === 'number') ? data.count : 0;
            const items = (data && Array.isArray(data.items)) ? data.items : [];

            // Update badge visibility & count
            if (count > 0) {
                badgeEl.textContent = count > 99 ? '99+' : String(count);
                badgeEl.classList.remove('d-none');
            } else {
                badgeEl.textContent = '0';
                badgeEl.classList.add('d-none');
            }

            // Update header status text
            if (statusTextEl) {
                statusTextEl.textContent = count > 0 ? (count + ' pending') : '';
            }

            // Update footer text
            if (footerTextEl) {
                if (count > items.length) {
                    footerTextEl.textContent = 'Showing ' + items.length + ' of ' + count + ' actionable approvals';
                } else {
                    footerTextEl.textContent = count > 0 ? (count + ' actionable approvals') : 'Approvals';
                }
            }

            // Clear container
            listEl.innerHTML = '';

            if (items.length === 0) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'text-center py-4 px-3 text-muted';

                const icon = document.createElement('i');
                icon.className = 'feather-check-circle fs-3 text-success d-block mb-2';
                emptyDiv.appendChild(icon);

                const p1 = document.createElement('p');
                p1.className = 'fs-12 fw-medium text-dark mb-0';
                p1.textContent = 'No pending approvals';
                emptyDiv.appendChild(p1);

                const p2 = document.createElement('p');
                p2.className = 'fs-11 text-muted mb-0';
                p2.textContent = 'You are all caught up!';
                emptyDiv.appendChild(p2);

                listEl.appendChild(emptyDiv);
                return;
            }

            // Render items safely with textContent
            items.forEach(function (item) {
                const link = document.createElement('a');
                link.href = item.url || 'javascript:void(0);';
                link.className = 'd-flex align-items-center justify-content-between py-2 px-3 border-bottom text-decoration-none';

                // Left container: avatar icon + text
                const leftContainer = document.createElement('div');
                leftContainer.className = 'd-flex align-items-center overflow-hidden me-2';

                const avatarDiv = document.createElement('div');
                avatarDiv.className = 'avatar-text avatar-sm bg-primary-subtle text-primary rounded me-2 flex-shrink-0';
                const itemIcon = document.createElement('i');
                const safeIcon = (item.icon && /^feather-[a-z0-9-]+$/.test(item.icon)) ? item.icon : 'feather-check-square';
                itemIcon.className = safeIcon;
                avatarDiv.appendChild(itemIcon);
                leftContainer.appendChild(avatarDiv);

                const textDiv = document.createElement('div');
                textDiv.className = 'overflow-hidden';

                const titleSpan = document.createElement('span');
                titleSpan.className = 'fs-12 fw-semibold text-dark text-truncate d-block';
                titleSpan.textContent = item.title || 'Approval Item';
                textDiv.appendChild(titleSpan);

                const subtitleSpan = document.createElement('span');
                subtitleSpan.className = 'fs-11 text-muted text-truncate d-block';
                const typeText = item.type ? (item.type + ' • ') : '';
                subtitleSpan.textContent = typeText + (item.subtitle || 'Approval required');
                textDiv.appendChild(subtitleSpan);

                leftContainer.appendChild(textDiv);
                link.appendChild(leftContainer);

                // Right container: relative time & chevron
                const rightContainer = document.createElement('div');
                rightContainer.className = 'd-flex align-items-center flex-shrink-0 ms-2 text-end';

                if (item.time) {
                    const timeSpan = document.createElement('span');
                    timeSpan.className = 'fs-10 text-muted me-1 text-nowrap';
                    timeSpan.textContent = item.time;
                    rightContainer.appendChild(timeSpan);
                }

                const chevron = document.createElement('i');
                chevron.className = 'feather-chevron-right text-muted fs-12';
                rightContainer.appendChild(chevron);

                link.appendChild(rightContainer);
                listEl.appendChild(link);
            });
        }

        function renderError() {
            listEl.innerHTML = '';
            const errorDiv = document.createElement('div');
            errorDiv.className = 'text-center py-3 px-3 text-muted';

            const p = document.createElement('p');
            p.className = 'fs-11 text-muted mb-0';
            p.textContent = 'Unable to load approvals.';
            errorDiv.appendChild(p);

            listEl.appendChild(errorDiv);
        }

        // Fetch on initial load
        fetchApprovals();

        // Refresh when dropdown is opened
        if (triggerEl) {
            triggerEl.addEventListener('click', function () {
                fetchApprovals();
            });
        }
    });
})();
