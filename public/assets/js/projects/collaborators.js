(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('projectCollaborators');
        if (!container) return;

        var addBtn = document.getElementById('projectCollaboratorAddBtn');
        var searchWrapper = document.getElementById('projectCollaboratorSearchWrapper');
        var searchInput = document.getElementById('projectCollaboratorSearchInput');
        var resultsList = document.getElementById('projectCollaboratorResults');
        var avatarsContainer = document.getElementById('projectCollaboratorAvatars');
        var countEl = document.getElementById('projectCollaboratorCount');

        setupRemovePopovers();

        if (!addBtn || !searchWrapper || !searchInput || !resultsList) return;

        var searchUrl = container.dataset.searchUrl;
        var storeUrl = container.dataset.storeUrl;
        var noResultsText = container.dataset.noResultsText;
        var debounceTimer = null;
        var searchAbortController = null;
        var isAdding = false;

        addBtn.addEventListener('click', openSearch);

        // Empty-state "Add Collaborator" buttons elsewhere on the page (Owner,
        // Manager, Milestone/Task List owner fields) reuse this same search
        // panel instead of opening a second picker.
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-action="open-project-collaborators"]');
            if (!trigger) return;

            event.preventDefault();
            event.stopImmediatePropagation();
            openSearch();
            container.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                runSearch(searchInput.value.trim());
            }, 250);
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeSearch();
            }
        });

        document.addEventListener('click', function (event) {
            if (!searchWrapper.classList.contains('d-none') && !container.contains(event.target)) {
                closeSearch();
            }
        });

        function setupRemovePopovers() {
            if (!avatarsContainer || typeof bootstrap === 'undefined') return;

            var removeUrlTemplate = container.dataset.removeUrlTemplate;
            var canManage = container.dataset.canManage === '1';
            var removeLabel = container.dataset.removeLabel || 'Remove Collaborator';
            var confirmRemoveTemplate = container.dataset.confirmRemoveTemplate || 'Remove this collaborator?';
            var removeSuccessText = container.dataset.removeSuccessText || 'Collaborator removed.';
            var removeErrorText = container.dataset.removeErrorText || 'Could not remove collaborator.';
            var activePopover = null;
            var isRemoving = false;

            avatarsContainer.addEventListener('click', function (event) {
                var avatar = event.target.closest('.collaborator-avatar');
                if (!avatar) return;
                event.stopPropagation();
                toggleAvatarPopover(avatar);
            });

            document.addEventListener('click', function (event) {
                if (!activePopover) return;
                if (event.target.closest('.popover') || event.target.closest('.collaborator-avatar')) return;
                hideActivePopover();
            });

            document.addEventListener('click', function (event) {
                var removeTrigger = event.target.closest('.collaborator-remove-btn');
                if (!removeTrigger) return;

                var memberId = removeTrigger.dataset.memberId;
                var name = removeTrigger.dataset.collaboratorName || '';
                hideActivePopover();
                confirmRemove(memberId, name);
            });

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function hideActivePopover() {
                if (!activePopover) return;
                activePopover.instance.dispose();
                activePopover = null;
            }

            function toggleAvatarPopover(avatar) {
                if (activePopover && activePopover.el === avatar) {
                    hideActivePopover();
                    return;
                }

                hideActivePopover();

                var name = avatar.dataset.collaboratorName || '';
                var role = avatar.dataset.collaboratorRole || '';
                var memberId = avatar.dataset.memberId;

                var content = '<div class="fw-semibold fs-13">' + escapeHtml(name) + '</div>'
                    + '<div class="text-muted fs-12 mb-2">' + escapeHtml(role) + '</div>';

                if (canManage) {
                    content += '<button type="button" class="btn btn-sm btn-outline-danger w-100 collaborator-remove-btn" '
                        + 'data-member-id="' + escapeHtml(memberId) + '" data-collaborator-name="' + escapeHtml(name) + '">'
                        + escapeHtml(removeLabel) + '</button>';
                }

                var instance = new bootstrap.Popover(avatar, {
                    html: true,
                    sanitize: false,
                    trigger: 'manual',
                    placement: 'bottom',
                    content: content,
                });

                activePopover = { el: avatar, instance: instance };
                instance.show();
            }

            function confirmRemove(memberId, name) {
                if (typeof window.confirmAction !== 'function' || !removeUrlTemplate) return;

                window.confirmAction({
                    title: removeLabel,
                    message: confirmRemoveTemplate.replace('__NAME__', name),
                    variant: 'danger',
                    confirmButtonText: removeLabel,
                    onConfirm: function () {
                        performRemove(memberId);
                    },
                });
            }

            function performRemove(memberId) {
                if (isRemoving) return;
                isRemoving = true;

                var csrfToken = document.querySelector('meta[name="csrf-token"]');
                csrfToken = csrfToken ? csrfToken.content : '';

                fetch(removeUrlTemplate.replace('__MEMBER__', memberId), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                })
                    .then(function (response) {
                        return response.json().catch(function () { return {}; }).then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            var errors = result.data && result.data.errors;
                            var message = (errors && Object.values(errors)[0] && Object.values(errors)[0][0])
                                || (result.data && result.data.message)
                                || removeErrorText;
                            window.showAppToast && window.showAppToast('error', message);
                            return;
                        }

                        var avatar = avatarsContainer.querySelector('.collaborator-avatar[data-member-id="' + memberId + '"]');
                        if (avatar) avatar.remove();

                        if (countEl && typeof result.data.active_count !== 'undefined') {
                            countEl.textContent = countEl.textContent.replace(/^\d+/, result.data.active_count);
                        }

                        window.showAppToast && window.showAppToast('success', removeSuccessText);
                    })
                    .catch(function () {
                        window.showAppToast && window.showAppToast('error', removeErrorText);
                    })
                    .then(function () {
                        isRemoving = false;
                    });
            }
        }

        function openSearch() {
            addBtn.classList.add('d-none');
            searchWrapper.classList.remove('d-none');
            searchInput.value = '';
            hideResults();
            searchInput.focus();
        }

        function closeSearch() {
            searchWrapper.classList.add('d-none');
            addBtn.classList.remove('d-none');
            searchInput.value = '';
            hideResults();

            if (searchAbortController) {
                searchAbortController.abort();
                searchAbortController = null;
            }
        }

        function hideResults() {
            resultsList.innerHTML = '';
            resultsList.classList.add('d-none');
        }

        function runSearch(term) {
            if (searchAbortController) {
                searchAbortController.abort();
                searchAbortController = null;
            }

            if (term.length < 2) {
                hideResults();
                return;
            }

            searchAbortController = new AbortController();

            fetch(searchUrl + '?q=' + encodeURIComponent(term), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: searchAbortController.signal,
            })
                .then(function (response) { return response.json(); })
                .then(function (data) { renderResults(data.results || []); })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') return;
                    hideResults();
                });
        }

        function renderResults(results) {
            resultsList.innerHTML = '';

            if (results.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'list-group-item fs-12 text-muted';
                empty.textContent = noResultsText;
                resultsList.appendChild(empty);
                resultsList.classList.remove('d-none');
                return;
            }

            results.forEach(function (result) {
                var item = document.createElement('li');
                item.className = 'list-group-item list-group-item-action fs-12';
                item.style.cursor = 'pointer';
                item.textContent = result.text;
                item.addEventListener('click', function () {
                    addCollaborator(result.id);
                });
                resultsList.appendChild(item);
            });

            resultsList.classList.remove('d-none');
        }

        function addCollaborator(userId) {
            if (isAdding) return;
            isAdding = true;

            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            csrfToken = csrfToken ? csrfToken.content : '';

            fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ user_id: userId }),
            })
                .then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        var errors = result.data && result.data.errors;
                        var message = (errors && Object.values(errors)[0] && Object.values(errors)[0][0])
                            || (result.data && result.data.message)
                            || 'Could not add collaborator.';
                        window.showAppToast && window.showAppToast('error', message);
                        return;
                    }

                    var wrapper = document.createElement('div');
                    wrapper.innerHTML = (result.data.avatar_html || '').trim();
                    var avatar = wrapper.firstElementChild;
                    if (avatar) {
                        avatarsContainer.insertBefore(avatar, addBtn);
                    }

                    if (countEl && typeof result.data.active_count !== 'undefined') {
                        countEl.textContent = countEl.textContent.replace(/^\d+/, result.data.active_count);
                    }

                    searchInput.value = '';
                    hideResults();
                    searchInput.focus();
                })
                .catch(function () {
                    window.showAppToast && window.showAppToast('error', 'Could not add collaborator.');
                })
                .then(function () {
                    isAdding = false;
                });
        }
    });
})();
