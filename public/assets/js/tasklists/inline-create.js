(function () {
    var FIELD_SELECTORS = {
        name: '.tasklist-create-name',
    };

    var activeCard = null;

    window.startTaskListInlineCreate = function () {
        if (activeCard) {
            var existingName = activeCard.querySelector(FIELD_SELECTORS.name);
            if (existingName) existingName.focus();
            return;
        }

        var template = document.getElementById('taskListCreateRowTemplate');
        if (!template) return;

        var storeUrl = template.dataset.storeUrl;
        var milestoneId = template.dataset.milestoneId || null;
        var fragment = template.content.cloneNode(true);
        var card = fragment.querySelector('.tasklist-row-creating');
        if (!card) return;

        var container = ensureListContainer();
        container.insertBefore(card, container.firstChild);
        activeCard = card;

        var nameInput = card.querySelector(FIELD_SELECTORS.name);
        var submitBtn = card.querySelector('.tasklist-create-submit');
        var cancelBtn = card.querySelector('.tasklist-create-cancel');

        nameInput.focus();

        var submit = function () {
            submitCard(card, storeUrl, milestoneId);
        };
        var cancel = function () {
            cancelCard(card);
        };

        submitBtn.addEventListener('click', submit);
        cancelBtn.addEventListener('click', cancel);

        card.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                cancel();
                return;
            }

            if (event.key === 'Enter' && event.target === nameInput) {
                event.preventDefault();
                submit();
            }
        });
    };

    function ensureListContainer() {
        var container = document.getElementById('taskListContainer');
        if (container) return container;

        var emptyState = document.getElementById('taskListEmptyState');
        container = document.createElement('div');
        container.id = 'taskListContainer';

        if (emptyState && emptyState.parentNode) {
            emptyState.classList.add('d-none');
            emptyState.parentNode.insertBefore(container, emptyState);
        }

        return container;
    }

    function cancelCard(card) {
        if (card.dataset.submitting === '1') return;

        var container = card.parentNode;
        card.remove();
        activeCard = null;

        if (container && container.id === 'taskListContainer' && container.children.length === 0) {
            var emptyState = document.getElementById('taskListEmptyState');
            container.remove();
            if (emptyState) emptyState.classList.remove('d-none');
        }
    }

    function submitCard(card, storeUrl, milestoneId) {
        if (card.dataset.submitting === '1') return;

        var nameInput = card.querySelector(FIELD_SELECTORS.name);
        var submitBtn = card.querySelector('.tasklist-create-submit');
        var cancelBtn = card.querySelector('.tasklist-create-cancel');

        clearErrors(card);

        var name = nameInput.value.trim();
        if (!name) {
            showFieldError(card, 'name', 'Task list name is required.');
            nameInput.focus();
            return;
        }

        card.dataset.submitting = '1';
        submitBtn.disabled = true;
        cancelBtn.disabled = true;
        var originalSubmitHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

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
            body: JSON.stringify({
                name: name,
                milestone_id: milestoneId || null,
            }),
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                return { status: response.status, ok: response.ok, data: data };
            });
        }).then(function (result) {
            if (result.status === 422) {
                showValidationErrors(card, result.data.errors || {});
                resetSubmitState(card, submitBtn, cancelBtn, originalSubmitHtml);
                return;
            }

            if (!result.ok) {
                showGeneralError(card, result.data.message || 'Could not save the task list. Please try again.');
                resetSubmitState(card, submitBtn, cancelBtn, originalSubmitHtml);
                return;
            }

            replaceWithRealRow(card, result.data.html);
        }).catch(function () {
            showGeneralError(card, 'Network error — please try again.');
            resetSubmitState(card, submitBtn, cancelBtn, originalSubmitHtml);
        });
    }

    function resetSubmitState(card, submitBtn, cancelBtn, originalSubmitHtml) {
        card.dataset.submitting = '0';
        submitBtn.disabled = false;
        cancelBtn.disabled = false;
        submitBtn.innerHTML = originalSubmitHtml;
    }

    function replaceWithRealRow(card, html) {
        var wrapper = document.createElement('div');
        wrapper.innerHTML = (html || '').trim();
        var newRow = wrapper.firstElementChild;

        if (newRow) {
            newRow.classList.add('tasklist-row-created-flash');
            card.replaceWith(newRow);
            window.setTimeout(function () {
                newRow.classList.remove('tasklist-row-created-flash');
            }, 900);

            var opener = newRow.querySelector('[data-task-list-drawer-payload]');
            if (opener) window.openTaskListCardDrawer(opener);
        } else {
            card.remove();
        }

        activeCard = null;
    }

    function clearErrors(card) {
        card.querySelectorAll('.tasklist-create-error').forEach(function (el) {
            el.textContent = '';
        });
        Object.keys(FIELD_SELECTORS).forEach(function (field) {
            var input = card.querySelector(FIELD_SELECTORS[field]);
            if (input) input.classList.remove('is-invalid');
        });
        var generalError = card.querySelector('.tasklist-create-general-error');
        if (generalError) {
            generalError.textContent = '';
            generalError.classList.add('d-none');
        }
    }

    function showFieldError(card, field, message) {
        var el = card.querySelector('.tasklist-create-error[data-field="' + field + '"]');
        if (el) el.textContent = message;

        var input = card.querySelector(FIELD_SELECTORS[field]);
        if (input) input.classList.add('is-invalid');
    }

    function showValidationErrors(card, errors) {
        clearErrors(card);

        var firstField = null;
        Object.keys(errors).forEach(function (field) {
            if (!FIELD_SELECTORS[field]) return;

            var messages = errors[field];
            showFieldError(card, field, Array.isArray(messages) ? messages[0] : messages);
            if (!firstField) firstField = field;
        });

        if (firstField) {
            var input = card.querySelector(FIELD_SELECTORS[firstField]);
            if (input) input.focus();
        }
    }

    function showGeneralError(card, message) {
        var el = card.querySelector('.tasklist-create-general-error');
        if (el) {
            el.textContent = message;
            el.classList.remove('d-none');
        }
    }

    // Shared by manual card clicks (onclick="openTaskListCardDrawer(this)") and the
    // auto-open step above, so both paths read the exact same rendered payload —
    // the card itself stays the single source of truth for drawer data.
    window.openTaskListCardDrawer = function (el) {
        if (!el || typeof window.openTaskListDetailsDrawer !== 'function') return;

        try {
            window.openTaskListDetailsDrawer(JSON.parse(el.dataset.taskListDrawerPayload));
        } catch (e) {
            // Card is already rendered/visible; a malformed payload just skips the drawer.
        }
    };
})();
