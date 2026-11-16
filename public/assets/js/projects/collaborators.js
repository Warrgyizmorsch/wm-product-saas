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
            var tasksUrlTemplate = container.dataset.tasksUrlTemplate;
            var canManage = container.dataset.canManage === '1';
            var removeLabel = container.dataset.removeLabel || 'Remove Collaborator';
            var confirmRemoveTemplate = container.dataset.confirmRemoveTemplate || 'Remove this collaborator?';
            var removeSuccessText = container.dataset.removeSuccessText || 'Collaborator removed.';
            var removeErrorText = container.dataset.removeErrorText || 'Could not remove collaborator.';
            var activePopover = null;
            var isRemoving = false;
            var isCheckingRemoval = false;
            var pendingRemovalMemberId = null;

            var reassignModalEl = document.getElementById('modalReassignTasksBeforeRemoval');
            var reassignModal = (reassignModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(reassignModalEl) : null;
            var reassignTableBody = document.getElementById('reassignTasksTableBody');
            var quickReassignSelect = document.getElementById('quickReassignAllSelect');
            var btnConfirmReassignAndRemove = document.getElementById('btnConfirmReassignAndRemove');
            var modalReassignTasksSubtitle = document.getElementById('modalReassignTasksSubtitle');
            var modalReassignTasksError = document.getElementById('modalReassignTasksError');

            if (quickReassignSelect) {
                quickReassignSelect.addEventListener('change', function () {
                    var chosenVal = quickReassignSelect.value;
                    if (!chosenVal) return;
                    if (!reassignTableBody) return;
                    var taskSelects = reassignTableBody.querySelectorAll('.task-reassign-select');
                    taskSelects.forEach(function (sel) {
                        sel.value = chosenVal;
                    });
                });
            }

            if (btnConfirmReassignAndRemove) {
                btnConfirmReassignAndRemove.addEventListener('click', function () {
                    if (!pendingRemovalMemberId || isRemoving) return;

                    var reassignments = {};
                    var taskSelects = reassignTableBody ? reassignTableBody.querySelectorAll('.task-reassign-select') : [];
                    taskSelects.forEach(function (sel) {
                        var taskId = sel.dataset.taskId;
                        var val = sel.value;
                        reassignments[taskId] = val;
                    });

                    var origHtml = btnConfirmReassignAndRemove.innerHTML;
                    btnConfirmReassignAndRemove.disabled = true;
                    btnConfirmReassignAndRemove.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';

                    performRemove(pendingRemovalMemberId, reassignments, function () {
                        btnConfirmReassignAndRemove.disabled = false;
                        btnConfirmReassignAndRemove.innerHTML = origHtml;
                    }, function (success) {
                        if (success && reassignModal) {
                            reassignModal.hide();
                        }
                    });
                });
            }

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
                if (removeTrigger) {
                    var memberId = removeTrigger.dataset.memberId;
                    var name = removeTrigger.dataset.collaboratorName || '';
                    hideActivePopover();
                    checkAndRemove(memberId, name);
                    return;
                }

                var editTrigger = event.target.closest('.collaborator-edit-rate-btn');
                if (editTrigger) {
                    var editMemberId = editTrigger.dataset.memberId;
                    var editName = editTrigger.dataset.collaboratorName || '';
                    var editRate = editTrigger.dataset.collaboratorRate || '';
                    hideActivePopover();
                    promptEditRate(editMemberId, editName, editRate);
                    return;
                }
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
                var rate = avatar.dataset.collaboratorRate;
                var memberId = avatar.dataset.memberId;
                var currencySymbol = container.dataset.currencySymbol || '₹';
                var editRateLabel = container.dataset.editRateLabel || 'Edit Rate';
                var rateLabel = container.dataset.rateLabel || 'Rate / Hour';

                var content = '<div class="fw-semibold fs-13 text-dark">' + escapeHtml(name) + '</div>'
                    + '<div class="text-muted fs-11 mb-2">' + escapeHtml(role) + '</div>';

                content += '<div class="p-2 rounded bg-light border mb-2 d-flex align-items-center justify-content-between gap-2">'
                    + '<span class="text-muted fs-11 text-uppercase fw-semibold">' + escapeHtml(rateLabel) + '</span>';

                if (rate && !isNaN(parseFloat(rate)) && parseFloat(rate) > 0) {
                    content += '<span class="text-primary fw-bold fs-12">'
                        + escapeHtml(currencySymbol) + ' ' + parseFloat(rate).toFixed(2) + ' / hr</span>';
                } else {
                    content += '<span class="text-muted fs-11 fst-italic">None</span>';
                }
                content += '</div>';

                if (canManage) {
                    content += '<div class="d-flex gap-1.5 mt-2">'
                        + '<button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 collaborator-edit-rate-btn d-inline-flex align-items-center justify-content-center" '
                        + 'data-member-id="' + escapeHtml(memberId) + '" data-collaborator-name="' + escapeHtml(name) + '" data-collaborator-rate="' + escapeHtml(rate || '') + '">'
                        + '<i class="feather-edit-2 me-1"></i>' + escapeHtml(editRateLabel) + '</button>'
                        + '<button type="button" class="btn btn-sm btn-outline-danger collaborator-remove-btn" '
                        + 'data-member-id="' + escapeHtml(memberId) + '" data-collaborator-name="' + escapeHtml(name) + '" title="' + escapeHtml(removeLabel) + '">'
                        + '<i class="feather-trash-2"></i></button>'
                        + '</div>';
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

            function checkAndRemove(memberId, name) {
                if (isCheckingRemoval || isRemoving) return;

                if (!tasksUrlTemplate) {
                    confirmRemove(memberId, name);
                    return;
                }

                isCheckingRemoval = true;
                var url = tasksUrlTemplate.replace('__MEMBER__', memberId);

                fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, status: response.status, data: data };
                        });
                    })
                    .then(function (res) {
                        isCheckingRemoval = false;

                        if (!res.ok || res.data.can_remove === false) {
                            var reason = (res.data && res.data.reason)
                                || (res.data && res.data.message)
                                || removeErrorText;
                            if (window.showAppToast) {
                                window.showAppToast('error', reason);
                            } else {
                                alert(reason);
                            }
                            return;
                        }

                        var activeTasks = res.data.active_tasks || [];
                        var activeCount = (typeof res.data.active_tasks_count !== 'undefined')
                            ? res.data.active_tasks_count
                            : activeTasks.length;
                        var candidates = res.data.candidates || [];

                        if (activeCount === 0) {
                            confirmRemove(memberId, name);
                        } else {
                            openReassignModal(memberId, name, activeTasks, candidates);
                        }
                    })
                    .catch(function () {
                        isCheckingRemoval = false;
                        window.showAppToast && window.showAppToast('error', removeErrorText);
                    });
            }

            function openReassignModal(memberId, name, activeTasks, candidates) {
                if (!reassignModal || !reassignTableBody) {
                    confirmRemove(memberId, name);
                    return;
                }

                pendingRemovalMemberId = memberId;

                if (modalReassignTasksSubtitle) {
                    modalReassignTasksSubtitle.textContent = 'Reassign ' + activeTasks.length + ' active task(s) before removing ' + name + '.';
                }
                if (modalReassignTasksError) {
                    modalReassignTasksError.classList.add('d-none');
                    modalReassignTasksError.textContent = '';
                }

                var noneOptionLabel = container.dataset.noneOption || '— None —';

                // Populate Quick Reassign dropdown
                if (quickReassignSelect) {
                    var quickPlaceholder = container.dataset.quickReassignPlaceholder || 'Select member or unassigned';
                    quickReassignSelect.innerHTML = '<option value="">' + escapeHtml(quickPlaceholder) + '</option>'
                        + '<option value="__unassigned__">' + escapeHtml(noneOptionLabel) + '</option>';
                    candidates.forEach(function (c) {
                        var opt = document.createElement('option');
                        opt.value = c.user_id;
                        opt.textContent = c.name + (c.is_manager ? ' (Manager)' : '');
                        quickReassignSelect.appendChild(opt);
                    });
                    quickReassignSelect.value = '';
                }

                // Build candidate options HTML for task rows
                var candidateOptionsHtml = '<option value="__unassigned__">' + escapeHtml(noneOptionLabel) + '</option>';
                candidates.forEach(function (c) {
                    candidateOptionsHtml += '<option value="' + escapeHtml(c.user_id) + '">'
                        + escapeHtml(c.name) + (c.is_manager ? ' (Manager)' : '')
                        + '</option>';
                });

                // Populate task rows
                reassignTableBody.innerHTML = '';
                activeTasks.forEach(function (task) {
                    var tr = document.createElement('tr');

                    var statusBadgeClass = 'bg-secondary';
                    if (task.status === 'In Progress') statusBadgeClass = 'bg-primary';
                    else if (task.status === 'Review') statusBadgeClass = 'bg-warning text-dark';
                    else if (task.status === 'On Hold') statusBadgeClass = 'bg-info text-dark';
                    else if (task.status === 'Open') statusBadgeClass = 'bg-light text-dark border';

                    tr.innerHTML = '<td class="ps-3 fw-semibold text-primary"><span class="badge bg-light text-primary border font-monospace">'
                        + escapeHtml(task.task_code || ('#' + task.id)) + '</span></td>'
                        + '<td><div class="fw-semibold text-dark text-truncate" style="max-width: 250px;" title="' + escapeHtml(task.title) + '">'
                        + escapeHtml(task.title) + '</div></td>'
                        + '<td><span class="badge ' + statusBadgeClass + ' fs-11">' + escapeHtml(task.status) + '</span></td>'
                        + '<td class="pe-3">'
                        + '<select class="form-select form-select-sm task-reassign-select shadow-none" data-task-id="' + escapeHtml(task.id) + '">'
                        + candidateOptionsHtml
                        + '</select>'
                        + '</td>';

                    reassignTableBody.appendChild(tr);
                });

                reassignModal.show();
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

            function performRemove(memberId, reassignments, onComplete, onSuccess) {
                if (isRemoving) return;
                isRemoving = true;

                var csrfToken = document.querySelector('meta[name="csrf-token"]');
                csrfToken = csrfToken ? csrfToken.content : '';

                var headers = {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                };

                fetch(removeUrlTemplate.replace('__MEMBER__', memberId), {
                    method: 'DELETE',
                    headers: headers,
                    body: JSON.stringify({ reassignments: reassignments || {} }),
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
                            if (modalReassignTasksError && reassignModalEl && reassignModalEl.classList.contains('show')) {
                                modalReassignTasksError.textContent = message;
                                modalReassignTasksError.classList.remove('d-none');
                            }
                            window.showAppToast && window.showAppToast('error', message);
                            if (typeof onSuccess === 'function') onSuccess(false);
                            return;
                        }

                        if (typeof onSuccess === 'function') onSuccess(true);

                        var avatar = avatarsContainer.querySelector('.collaborator-avatar[data-member-id="' + memberId + '"]');
                        if (avatar) avatar.remove();

                        if (countEl && typeof result.data.active_count !== 'undefined') {
                            countEl.textContent = countEl.textContent.replace(/^\d+/, result.data.active_count);
                        }

                        var successMsg = (result.data && result.data.message) || removeSuccessText;
                        window.showAppToast && window.showAppToast('success', successMsg);

                        if (result.data && result.data.should_reload) {
                            setTimeout(function () {
                                window.location.reload();
                            }, 400);
                            return;
                        }
                    })
                    .catch(function () {
                        window.showAppToast && window.showAppToast('error', removeErrorText);
                        if (typeof onSuccess === 'function') onSuccess(false);
                    })
                    .then(function () {
                        isRemoving = false;
                        if (typeof onComplete === 'function') onComplete();
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

        var rateModalEl = document.getElementById('modalAddCollaboratorRate');
        var rateModal = (rateModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(rateModalEl) : null;
        var modalUserNameEl = document.getElementById('modalCollaboratorUserName');
        var modalRateInput = document.getElementById('modalCollaboratorRateInput');
        var modalRateError = document.getElementById('modalCollaboratorRateError');
        var modalSubmitBtn = document.getElementById('modalCollaboratorSubmitBtn');
        var pendingCollaborator = null;
        var editingMember = null;

        function getBillingMethod() {
            var billingField = document.querySelector('[data-field="billing_method"]');
            if (billingField) {
                var select = billingField.querySelector('select');
                if (select && select.value) return select.value;
                var textEl = billingField.querySelector('.inline-edit__text');
                if (textEl) {
                    var txt = textEl.textContent.trim();
                    if (txt.toLowerCase().includes('user')) return 'User Based';
                    if (txt && txt !== '—' && !txt.includes('None')) return txt;
                }
            }
            return container.dataset.billingMethod || '';
        }

        function isTimeAndMaterial() {
            var budgetField = document.querySelector('[data-field="budget_type"]');
            if (budgetField) {
                var select = budgetField.querySelector('select');
                if (select && select.value) return select.value === 'Time & Material';
                var textEl = budgetField.querySelector('.inline-edit__text');
                if (textEl) {
                    var txt = textEl.textContent.trim();
                    if (txt.toLowerCase().includes('time')) return true;
                }
            }
            return container.dataset.budgetType === 'Time & Material';
        }

        function hideCostWarning() {
            var warningEl = document.getElementById('modalCollaboratorCostWarning');
            if (warningEl) warningEl.classList.add('d-none');
        }

        function checkRateMarginWarning() {
            var warningEl = document.getElementById('modalCollaboratorCostWarning');
            var warningText = document.getElementById('modalCollaboratorCostWarningText');
            if (!warningEl || !warningText || (!pendingCollaborator && !editingMember)) return;

            var cost = pendingCollaborator ? pendingCollaborator.costPerHour : (editingMember ? editingMember.costPerHour : null);
            var val = modalRateInput ? parseFloat(modalRateInput.value.trim()) : NaN;

            if (cost !== null && typeof cost !== 'undefined' && !isNaN(cost) && cost > 0 && !isNaN(val) && val > 0 && val < cost) {
                var currency = container.dataset.currencySymbol || '₹';
                warningText.innerHTML = '<div class="fw-bold mb-1">Margin Warning: Negative Margin Risk</div><div>Entered rate (<strong>' + currency + val.toFixed(2) + '/hr</strong>) is lower than this employee\'s baseline cost (<strong>' + currency + parseFloat(cost).toFixed(2) + '/hr</strong> based on shift & salary). This allocation may generate a financial loss.</div>';
                warningEl.classList.remove('d-none');
            } else {
                warningEl.classList.add('d-none');
            }
        }

        if (modalSubmitBtn) {
            modalSubmitBtn.addEventListener('click', function () {
                submitRateModal();
            });
        }

        if (modalRateInput) {
            modalRateInput.addEventListener('input', checkRateMarginWarning);
            modalRateInput.addEventListener('keyup', checkRateMarginWarning);
            modalRateInput.addEventListener('change', checkRateMarginWarning);
            modalRateInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitRateModal();
                }
            });
        }

        function submitRateModal() {
            if (!pendingCollaborator && !editingMember) return;
            var val = modalRateInput ? modalRateInput.value.trim() : '';
            var isStrictlyRequired = (getBillingMethod() === 'User Based' || isTimeAndMaterial());
            var num = null;

            if (val !== '') {
                num = parseFloat(val);
                if (isNaN(num) || num < 0) {
                    if (modalRateInput) modalRateInput.classList.add('is-invalid');
                    if (modalRateError) modalRateError.classList.remove('d-none');
                    return;
                }
            } else if (isStrictlyRequired) {
                if (modalRateInput) modalRateInput.classList.add('is-invalid');
                if (modalRateError) modalRateError.classList.remove('d-none');
                return;
            }

            if (modalRateInput) modalRateInput.classList.remove('is-invalid');
            if (modalRateError) modalRateError.classList.add('d-none');
            hideCostWarning();

            if (editingMember) {
                var editTarget = editingMember;
                editingMember = null;
                if (rateModal) {
                    rateModal.hide();
                }
                updateCollaboratorRate(editTarget.id, num);
                return;
            }

            var target = pendingCollaborator;
            pendingCollaborator = null;
            if (rateModal) {
                rateModal.hide();
            }

            addCollaborator(target.id, num);
        }

        function promptEditRate(memberId, name, currentRate) {
            hideResults();
            pendingCollaborator = null;
            editingMember = { id: memberId, name: name, rate: currentRate };

            var isStrictlyRequired = (getBillingMethod() === 'User Based' || isTimeAndMaterial());
            var asterisk = document.getElementById('modalCollaboratorRateRequiredAsterisk');
            if (asterisk) {
                asterisk.classList.toggle('d-none', !isStrictlyRequired);
            }

            var modalTitle = document.getElementById('modalAddCollaboratorRateLabel');
            if (modalTitle) {
                modalTitle.textContent = container.dataset.updateRateTitle || 'Update Billing Rate';
            }

            if (modalSubmitBtn) {
                modalSubmitBtn.innerHTML = '<i class="feather-check me-1.5"></i>' + (container.dataset.updateRateLabel || 'Save Rate');
            }

            if (rateModal && rateModalEl) {
                if (modalUserNameEl) modalUserNameEl.textContent = name;
                var emailEl = document.getElementById('modalCollaboratorUserEmail');
                if (emailEl) {
                    emailEl.classList.add('d-none');
                }
                var avatarEl = document.getElementById('modalCollaboratorAvatar');
                if (avatarEl) {
                    var parts = (name || '').trim().split(/\s+/);
                    var initials = parts.map(function (p) { return p.charAt(0); }).join('').toUpperCase().substring(0, 2) || '?';
                    avatarEl.textContent = initials;
                }
                var costWrapEl = document.getElementById('modalCollaboratorCostWrap');
                if (costWrapEl) {
                    costWrapEl.classList.add('d-none');
                }
                if (modalRateInput) {
                    modalRateInput.value = (currentRate && !isNaN(parseFloat(currentRate))) ? parseFloat(currentRate).toFixed(2) : '';
                    modalRateInput.classList.remove('is-invalid');
                }
                if (modalRateError) modalRateError.classList.add('d-none');
                hideCostWarning();
                rateModal.show();
                setTimeout(function () {
                    if (modalRateInput) {
                        modalRateInput.focus();
                        modalRateInput.select();
                    }
                }, 150);
            }
        }

        function updateCollaboratorRate(memberId, ratePerHour) {
            var updateUrlTemplate = container.dataset.updateUrlTemplate;
            if (!updateUrlTemplate) return;

            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            csrfToken = csrfToken ? csrfToken.content : '';

            var payload = {
                rate_per_hour: ratePerHour,
            };

            fetch(updateUrlTemplate.replace('__MEMBER__', memberId), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
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
                            || 'Could not update billing rate.';
                        window.showAppToast && window.showAppToast('error', message);
                        return;
                    }

                    var avatar = avatarsContainer.querySelector('.collaborator-avatar[data-member-id="' + memberId + '"]');
                    if (avatar) {
                        avatar.dataset.collaboratorRate = (ratePerHour !== null && typeof ratePerHour !== 'undefined') ? ratePerHour : '';
                    }

                    window.showAppToast && window.showAppToast('success', result.data.message || 'Billing rate updated successfully.');
                })
                .catch(function () {
                    window.showAppToast && window.showAppToast('error', 'Could not update billing rate.');
                });
        }

        function promptRateAndAdd(userId, userName, userEmail, costPerHour) {
            hideResults();
            editingMember = null;
            pendingCollaborator = { id: userId, name: userName, email: userEmail, costPerHour: costPerHour };

            var modalTitle = document.getElementById('modalAddCollaboratorRateLabel');
            if (modalTitle) {
                modalTitle.textContent = container.dataset.addCollaboratorTitle || 'Add Collaborator';
            }

            if (modalSubmitBtn) {
                modalSubmitBtn.innerHTML = '<i class="feather-check me-1.5"></i>' + (container.dataset.addCollaboratorTitle || 'Add Collaborator');
            }

            var isStrictlyRequired = (getBillingMethod() === 'User Based' || isTimeAndMaterial());
            var asterisk = document.getElementById('modalCollaboratorRateRequiredAsterisk');
            if (asterisk) {
                asterisk.classList.toggle('d-none', !isStrictlyRequired);
            }

            if (rateModal && rateModalEl) {
                if (modalUserNameEl) modalUserNameEl.textContent = userName;
                var emailEl = document.getElementById('modalCollaboratorUserEmail');
                if (emailEl) {
                    emailEl.textContent = userEmail || '';
                    emailEl.classList.toggle('d-none', !userEmail);
                }
                var avatarEl = document.getElementById('modalCollaboratorAvatar');
                if (avatarEl) {
                    var parts = (userName || '').trim().split(/\s+/);
                    var initials = parts.map(function (p) { return p.charAt(0); }).join('').toUpperCase().substring(0, 2) || '?';
                    avatarEl.textContent = initials;
                }
                var badgeEl = document.getElementById('modalCollaboratorBaselineCostBadge');
                var costWrapEl = document.getElementById('modalCollaboratorCostWrap');
                if (costWrapEl && badgeEl) {
                    if (costPerHour !== null && typeof costPerHour !== 'undefined' && !isNaN(costPerHour) && costPerHour > 0) {
                        var currency = container.dataset.currencySymbol || '₹';
                        badgeEl.textContent = currency + parseFloat(costPerHour).toFixed(2) + ' / hr';
                        costWrapEl.classList.remove('d-none');
                    } else {
                        costWrapEl.classList.add('d-none');
                    }
                }
                if (modalRateInput) {
                    modalRateInput.value = '';
                    modalRateInput.classList.remove('is-invalid');
                }
                if (modalRateError) modalRateError.classList.add('d-none');
                hideCostWarning();
                rateModal.show();
                setTimeout(function () {
                    if (modalRateInput) modalRateInput.focus();
                }, 300);
            } else {
                var entered = window.prompt('Enter Rate / Hour (' + (container.dataset.currencySymbol || '₹') + ') for ' + userName + ':');
                if (entered !== null && entered.trim() !== '' && !isNaN(entered)) {
                    addCollaborator(userId, parseFloat(entered));
                }
            }
        }

        function renderResults(results) {
            resultsList.innerHTML = '';

            if (results.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'list-group-item fs-12 text-muted py-2 px-3';
                empty.textContent = noResultsText;
                resultsList.appendChild(empty);
                resultsList.classList.remove('d-none');
                return;
            }

            results.forEach(function (result) {
                var item = document.createElement('li');
                item.className = 'list-group-item list-group-item-action fs-12 py-2 px-3 text-start';
                item.style.cursor = 'pointer';

                var nameEl = document.createElement('div');
                nameEl.className = 'fw-semibold text-dark text-truncate';
                nameEl.textContent = result.text;
                item.appendChild(nameEl);

                if (result.email) {
                    var emailEl = document.createElement('div');
                    emailEl.className = 'text-muted fs-11 text-truncate';
                    emailEl.textContent = result.email;
                    item.appendChild(emailEl);
                }

                item.addEventListener('click', function () {
                    promptRateAndAdd(result.id, result.text, result.email, result.cost_per_hour);
                });
                resultsList.appendChild(item);
            });

            resultsList.classList.remove('d-none');
        }

        function addCollaborator(userId, ratePerHour) {
            if (isAdding) return;
            isAdding = true;

            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            csrfToken = csrfToken ? csrfToken.content : '';

            var payload = { user_id: userId };
            if (ratePerHour !== null && typeof ratePerHour !== 'undefined') {
                payload.rate_per_hour = ratePerHour;
            }

            fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
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

                    if (result.data && result.data.should_reload) {
                        window.showAppToast && window.showAppToast('success', 'Collaborator added. Contractual fields are now locked.');
                        setTimeout(function () {
                            window.location.reload();
                        }, 400);
                        return;
                    }

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
