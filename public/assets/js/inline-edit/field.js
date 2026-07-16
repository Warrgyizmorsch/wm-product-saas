import { patchField } from './api.js';
import { adapterRegistry } from './adapters/index.js';

export class InlineEditField {
    constructor(el, manager) {
        this.el = el;
        this.manager = manager;
        this.viewEl = el.querySelector('.inline-edit__view');
        this.textEl = el.querySelector('.inline-edit__text');
        this.editEl = el.querySelector('.inline-edit__edit');
        this.controlEl = el.querySelector('.inline-edit__control');
        this.errorEl = el.querySelector('.inline-edit__error');
        this.saveBtn = el.querySelector('.inline-edit__action--save');
        this.cancelBtn = el.querySelector('.inline-edit__action--cancel');
        this.field = el.dataset.field;
        this.url = el.dataset.url;
        this.emptyPlaceholder = el.dataset.emptyPlaceholder ?? '';
        this.originalValue = this.controlEl.value;

        const Adapter = adapterRegistry[el.dataset.type] ?? adapterRegistry.text;
        this.adapter = new Adapter(this.controlEl, {
            onCommit: () => this.commit(),
            onCancel: () => this.cancelEdit(),
        });

        this.viewEl.addEventListener('click', () => this.enterEdit());
        this.viewEl.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') this.enterEdit();
        });

        // A plain click on these buttons would first move focus away from
        // controlEl, firing the adapter's blur handler (which commits) before
        // the click handler below ever runs. Suppressing the mousedown's
        // default action keeps focus on controlEl — no blur, no race — while
        // the click event (and native keyboard activation) still fires.
        [this.saveBtn, this.cancelBtn].forEach((btn) => {
            btn.addEventListener('mousedown', (event) => event.preventDefault());
        });
        this.saveBtn.addEventListener('click', () => this.commit());
        this.cancelBtn.addEventListener('click', () => this.cancelEdit());
    }

    isSaving() {
        return this.el.classList.contains('is-saving');
    }

    enterEdit() {
        if (!this.manager.activate(this)) {
            return;
        }
        // Bootstrap tooltips don't auto-hide when their trigger is display:none'd
        // programmatically (only on mouseleave/blur) — hide it explicitly or the
        // "Edit" tooltip is left floating on screen after the pencil disappears.
        this.hideTooltip(this.viewEl);
        this.el.classList.add('is-editing');
        this.viewEl.classList.add('d-none');
        this.editEl.classList.remove('d-none');
        this.clearError();
        this.adapter.activate();
    }

    cancelEdit() {
        this.adapter.deactivate();
        this.adapter.setValue(this.originalValue);
        this.hideTooltip(this.saveBtn);
        this.hideTooltip(this.cancelBtn);
        this.el.classList.remove('is-editing');
        this.editEl.classList.add('d-none');
        this.viewEl.classList.remove('d-none');
        this.clearError();
        this.manager.clear(this);
    }

    async commit() {
        if (this.el.classList.contains('is-saving')) {
            return;
        }

        const value = this.adapter.getValue();

        if (value === this.originalValue) {
            this.cancelEdit();
            return;
        }

        this.el.classList.add('is-saving');
        this.controlEl.disabled = true;

        try {
            const data = await patchField(this.url, this.field, value);
            const isEmpty = data.value === null || data.value === '';
            this.originalValue = isEmpty ? '' : String(data.value);
            this.adapter.setValue(this.originalValue);
            this.textEl.textContent = isEmpty ? this.emptyPlaceholder : this.adapter.format(this.originalValue);
            this.viewEl.classList.toggle('inline-edit__view--empty', isEmpty);
            this.cancelEdit();
            window.showAppToast?.('success', 'Saved');
        } catch (error) {
            if (error.status === 422) {
                const errors = error.data?.errors ?? {};
                const message = Object.values(errors)[0]?.[0] ?? 'Invalid value';
                this.showError(message);
            } else {
                this.cancelEdit();
                window.showAppToast?.('error', error.data?.message ?? 'Could not save');
            }
        } finally {
            this.el.classList.remove('is-saving');
            this.controlEl.disabled = false;
        }
    }

    showError(message) {
        this.errorEl.textContent = message;
        this.errorEl.classList.remove('d-none');
    }

    clearError() {
        this.errorEl.textContent = '';
        this.errorEl.classList.add('d-none');
    }

    hideTooltip(el) {
        window.bootstrap?.Tooltip.getInstance(el)?.hide();
    }
}
