(function () {
    var FIELD_SELECTORS = {
        name: '.milestone-create-name',
        owner_id: '.milestone-create-owner',
        due_date: '.milestone-create-due-date',
    };

    var activeCard = null;

    window.startMilestoneInlineCreate = function () {
        if (activeCard) {
            var existingName = activeCard.querySelector(FIELD_SELECTORS.name);
            if (existingName) existingName.focus();
            return;
        }

        var template = document.getElementById('milestoneCreateRowTemplate');
        if (!template) return;

        var storeUrl = template.dataset.storeUrl;
        var fragment = template.content.cloneNode(true);
        var card = fragment.querySelector('.milestone-row-creating');
        if (!card) return;

        var container = ensureListContainer();
        container.insertBefore(card, container.firstChild);
        activeCard = card;

        var nameInput = card.querySelector(FIELD_SELECTORS.name);
        var ownerSelect = card.querySelector(FIELD_SELECTORS.owner_id);
        var dueDateInput = card.querySelector(FIELD_SELECTORS.due_date);
        var submitBtn = card.querySelector('.milestone-create-submit');
        var cancelBtn = card.querySelector('.milestone-create-cancel');

        if (window.jQuery && window.initSelect2) {
            window.initSelect2(ownerSelect);
        }

        nameInput.focus();

        var submit = function () {
            submitCard(card, storeUrl);
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

            if (event.key === 'Enter' && (event.target === nameInput || event.target === dueDateInput)) {
                event.preventDefault();
                submit();
            }
        });
    };

    function ensureListContainer() {
        var container = document.getElementById('milestoneListContainer');
        if (container) return container;

        var emptyState = document.getElementById('milestoneEmptyState');
        container = document.createElement('div');
        container.className = 'border rounded-3 overflow-hidden';
        container.id = 'milestoneListContainer';

        if (emptyState && emptyState.parentNode) {
            emptyState.classList.add('d-none');
            emptyState.parentNode.insertBefore(container, emptyState);
        }

        return container;
    }

    function cancelCard(card) {
        if (card.dataset.submitting === '1') return;

        var container = card.parentNode;
        destroySelect2(card);
        card.remove();
        activeCard = null;

        if (container && container.id === 'milestoneListContainer' && container.children.length === 0) {
            var emptyState = document.getElementById('milestoneEmptyState');
            container.remove();
            if (emptyState) emptyState.classList.remove('d-none');
        }
    }

    function submitCard(card, storeUrl) {
        if (card.dataset.submitting === '1') return;

        var nameInput = card.querySelector(FIELD_SELECTORS.name);
        var ownerSelect = card.querySelector(FIELD_SELECTORS.owner_id);
        var dueDateInput = card.querySelector(FIELD_SELECTORS.due_date);
        var submitBtn = card.querySelector('.milestone-create-submit');
        var cancelBtn = card.querySelector('.milestone-create-cancel');

        clearErrors(card);

        var name = nameInput.value.trim();
        if (!name) {
            showFieldError(card, 'name', 'Milestone name is required.');
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
                owner_id: ownerSelect.value || null,
                due_date: dueDateInput.value || null,
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
                showGeneralError(card, result.data.message || 'Could not save the milestone. Please try again.');
                resetSubmitState(card, submitBtn, cancelBtn, originalSubmitHtml);
                return;
            }

            replaceWithRealRow(card, result.data.html);
        }).catch(function () {
            showGeneralError(card, 'Network error — please try again.');
            resetSubmitState(card, submitBtn, cancelBtn, originalSubmitHtml);
        });
    }

    function destroySelect2(card) {
        if (!window.jQuery) return;

        var ownerSelect = card.querySelector(FIELD_SELECTORS.owner_id);
        if (ownerSelect && jQuery(ownerSelect).hasClass('select2-hidden-accessible')) {
            jQuery(ownerSelect).select2('destroy');
        }
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

        destroySelect2(card);

        if (newRow) {
            newRow.classList.add('milestone-row-created-flash');
            card.replaceWith(newRow);
            window.setTimeout(function () {
                newRow.classList.remove('milestone-row-created-flash');
            }, 900);
        } else {
            card.remove();
        }

        activeCard = null;
    }

    function clearErrors(card) {
        card.querySelectorAll('.milestone-create-error').forEach(function (el) {
            el.textContent = '';
        });
        Object.keys(FIELD_SELECTORS).forEach(function (field) {
            var input = card.querySelector(FIELD_SELECTORS[field]);
            if (input) input.classList.remove('is-invalid');
        });
        var generalError = card.querySelector('.milestone-create-general-error');
        if (generalError) {
            generalError.textContent = '';
            generalError.classList.add('d-none');
        }
    }

    function showFieldError(card, field, message) {
        var el = card.querySelector('.milestone-create-error[data-field="' + field + '"]');
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
        var el = card.querySelector('.milestone-create-general-error');
        if (el) {
            el.textContent = message;
            el.classList.remove('d-none');
        }
    }
})();
